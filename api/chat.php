<?php
/**
 * chat.php
 * POST /api/chat.php   { "message": "...", "session_id": "..." }
 * Returns: { "answer": "...", "fallback": bool, "sources": [...] }
 *
 * Pipeline:
 *  1. Try curated FAQ exact/fuzzy match first (fastest, zero LLM cost).
 *  2. Otherwise: embed the query -> hybrid vector+keyword search on
 *     knowledge_base (match_knowledge_base RPC/SQL) -> if hits found,
 *     pass ONLY those chunks to Groq for a grounded answer.
 *  3. If nothing relevant is retrieved, return a fallback message instead
 *     of calling the LLM at all (prevents hallucination) and flag the row
 *     in chat_logs for the weekly gap-analysis job.
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

// ---- 1. Curated FAQ fast path ---------------------------------------------
$faqStmt = $pdo->prepare(
    "SELECT question, answer, similarity(question, :q) AS sim
     FROM faq_entries
     WHERE is_active = true
     ORDER BY sim DESC
     LIMIT 1"
);
$faqStmt->execute(['q' => $userMessage]);
$faqRow = $faqStmt->fetch();

if ($faqRow && (float)$faqRow['sim'] >= 0.45) {
    mfano_log_chat($pdo, $sessionId, $userMessage, [], $faqRow['answer'], false, 1.0, $startTime);
    echo json_encode([
        'answer'   => $faqRow['answer'],
        'fallback' => false,
        'source'   => 'faq',
        'sources'  => [],
    ]);
    exit;
}

// ---- 2. Hybrid retrieval from knowledge_base -------------------------------
$embedding = mfano_get_embedding($userMessage);

if ($embedding !== null) {
    $vecLiteral = mfano_vector_to_pg_literal($embedding);
    $sql = "SELECT id, dc_title, content_chunk, target_audience, similarity
            FROM match_knowledge_base(:emb::vector, :qtext, 5)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['emb' => $vecLiteral, 'qtext' => $userMessage]);
} else {
    // Degrade gracefully to keyword-only search if the embedding API is unreachable.
    $sql = "SELECT id, dc_title, content_chunk, target_audience,
                   ts_rank(search_vector, plainto_tsquery('english', :qtext)) AS similarity
            FROM knowledge_base
            WHERE is_active = true
              AND search_vector @@ plainto_tsquery('english', :qtext)
            ORDER BY similarity DESC
            LIMIT 5";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['qtext' => $userMessage]);
}

$matches = $stmt->fetchAll();

// Filter out weak/irrelevant matches so we never feed noise to the LLM.
$relevant = array_values(array_filter($matches, fn($m) => (float)$m['similarity'] >= 0.20));

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
        'ids'  => '{' . implode(',', $matchedIds) . '}',
        'resp' => $botResponse,
        'fb'   => $wasFallback ? 'true' : 'false',
        'conf' => round($confidence, 3),
        'rt'   => (int)((microtime(true) - $startTime) * 1000),
        'ua'   => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
    ]);
}
