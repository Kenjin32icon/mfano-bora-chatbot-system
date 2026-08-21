<?php
/**
 * chat.php
 * POST /api/chat.php   { "message": "...", "session_id": "..." }
 * Returns: { "answer": "...", "fallback": bool, "sources": [...] }
 *
 * SQLite has no pg_trgm and no pgvector, so both fuzzy FAQ matching and
 * embedding cosine similarity are computed here in PHP instead of in SQL.
 * KB size for a single organisation's chatbot is small enough (hundreds,
 * not millions, of rows) that scanning all active rows per request is fine.
 *
 * Pipeline:
 *  1. Curated FAQ fuzzy match first (fastest, zero LLM cost).
 *  2. Otherwise: embed the query -> cosine-similarity rank against all active
 *     knowledge_base rows that have an embedding, PLUS a keyword-overlap
 *     score as a fallback signal -> merge and take the top 5.
 *  3. If nothing relevant is retrieved, return a fallback message instead of
 *     calling the LLM (prevents hallucination) and log it for gap analysis.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/embeddings.php';
require_once __DIR__ . '/groq.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . ALLOWED_ORIGIN);
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$startTime = microtime(true);
$input = json_decode(file_get_contents('php://input'), true);

$userMessage = trim((string)($input['message'] ?? ''));
$sessionId   = trim((string)($input['session_id'] ?? bin2hex(random_bytes(8))));

if ($userMessage === '' || mb_strlen($userMessage) > 800) {
    http_response_code(400);
    echo json_encode(['error' => 'Message is required and must be under 800 characters.']);
    exit;
}

$pdo = mfano_db();

// ---- 1. Curated FAQ fast path (fuzzy match done in PHP) --------------------
$faqRows = $pdo->query("SELECT question, answer FROM faq_entries WHERE is_active = 1")->fetchAll();

$bestFaq = null;
$bestFaqScore = 0.0;
foreach ($faqRows as $faq) {
    similar_text(mb_strtolower($userMessage), mb_strtolower($faq['question']), $pct);
    $score = $pct / 100;
    if ($score > $bestFaqScore) {
        $bestFaqScore = $score;
        $bestFaq = $faq;
    }
}

if ($bestFaq && $bestFaqScore >= 0.55) {
    mfano_log_chat($pdo, $sessionId, $userMessage, [], $bestFaq['answer'], false, 1.0, $startTime);
    echo json_encode([
        'answer'   => $bestFaq['answer'],
        'fallback' => false,
        'source'   => 'faq',
        'sources'  => [],
    ]);
    exit;
}

// ---- 2. Hybrid retrieval from knowledge_base (computed in PHP) -------------
$kbRows = $pdo->query(
    "SELECT id, dc_title, content_chunk, target_audience, embedding
     FROM knowledge_base WHERE is_active = 1"
)->fetchAll();

$queryEmbedding = mfano_get_embedding($userMessage);
$queryWords = mfano_keyword_set($userMessage);

$scored = [];
foreach ($kbRows as $row) {
    $vectorScore = 0.0;
    if ($queryEmbedding !== null && !empty($row['embedding'])) {
        $rowVector = json_decode($row['embedding'], true);
        if (is_array($rowVector)) {
            $vectorScore = mfano_cosine_similarity($queryEmbedding, $rowVector);
        }
    }

    $keywordScore = mfano_keyword_overlap_score($queryWords, $row['dc_title'] . ' ' . $row['content_chunk']);

    // Weighted blend: trust the embedding more when it's available.
    $combined = ($queryEmbedding !== null)
        ? (0.7 * $vectorScore + 0.3 * $keywordScore)
        : $keywordScore;

    if ($combined > 0) {
        $scored[] = [
            'id' => $row['id'],
            'dc_title' => $row['dc_title'],
            'content_chunk' => $row['content_chunk'],
            'target_audience' => $row['target_audience'],
            'similarity' => $combined,
        ];
    }
}

usort($scored, fn($a, $b) => $b['similarity'] <=> $a['similarity']);
$relevant = array_slice(array_filter($scored, fn($m) => $m['similarity'] >= 0.20), 0, 5);

if (empty($relevant)) {
    $fallbackMsg = "I don't have specific information on that yet. "
                 . "Could you rephrase, or reach us directly at info@mfanoboraafrica.com?";
    mfano_log_chat($pdo, $sessionId, $userMessage, [], $fallbackMsg, true, 0.0, $startTime);
    echo json_encode(['answer' => $fallbackMsg, 'fallback' => true, 'sources' => []]);
    exit;
}

// ---- 3. Grounded generation via Groq ---------------------------------------
$result = mfano_generate_answer($userMessage, $relevant);

$matchedIds = array_map(fn($m) => $m['id'], $relevant);
$avgSim     = array_sum(array_column($relevant, 'similarity')) / count($relevant);

mfano_log_chat($pdo, $sessionId, $userMessage, $matchedIds, $result['answer'], !$result['success'], $avgSim, $startTime);

echo json_encode([
    'answer'   => $result['answer'],
    'fallback' => !$result['success'],
    'sources'  => array_map(fn($m) => $m['dc_title'], $relevant),
]);
exit;

// -----------------------------------------------------------------------
function mfano_log_chat(
    PDO $pdo,
    string $sessionId,
    string $userMessage,
    array $matchedIds,
    ?string $botResponse,
    bool $wasFallback,
    float $confidence,
    float $startTime
): void {
    $stmt = $pdo->prepare(
        "INSERT INTO chat_logs
            (session_id, user_message, matched_kb_ids, bot_response, was_fallback,
             confidence_score, response_time_ms, user_agent)
         VALUES (:sid, :msg, :ids, :resp, :fb, :conf, :rt, :ua)"
    );
    $stmt->execute([
        'sid'  => $sessionId,
        'msg'  => $userMessage,
        'ids'  => json_encode($matchedIds),   // JSON array, matches schema comment
        'resp' => $botResponse,
        'fb'   => $wasFallback ? 1 : 0,        // integer, matches INTEGER column affinity
        'conf' => round($confidence, 3),
        'rt'   => (int)((microtime(true) - $startTime) * 1000),
        'ua'   => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
    ]);
}

function mfano_cosine_similarity(array $a, array $b): float
{
    $len = min(count($a), count($b));
    if ($len === 0) {
        return 0.0;
    }
    $dot = 0.0; $normA = 0.0; $normB = 0.0;
    for ($i = 0; $i < $len; $i++) {
        $dot   += $a[$i] * $b[$i];
        $normA += $a[$i] * $a[$i];
        $normB += $b[$i] * $b[$i];
    }
    if ($normA <= 0 || $normB <= 0) {
        return 0.0;
    }
    return $dot / (sqrt($normA) * sqrt($normB));
}

function mfano_keyword_set(string $text): array
{
    $words = preg_split('/[^a-z0-9]+/', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY);
    return array_unique($words ?: []);
}

function mfano_keyword_overlap_score(array $queryWords, string $target): float
{
    if (empty($queryWords)) {
        return 0.0;
    }
    $targetWords = mfano_keyword_set($target);
    $overlap = count(array_intersect($queryWords, $targetWords));
    return $overlap / count($queryWords);
}