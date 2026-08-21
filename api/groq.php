<?php
/**
 * groq.php
 * Sends the retrieved knowledge-base context + user question to Groq's
 * Llama-3 model and returns a grounded answer. If no relevant context was
 * retrieved, we do NOT call the LLM at all (see chat.php) to avoid hallucination.
 */

declare(strict_types=1);
require_once __DIR__ . '/config.php';

function mfano_generate_answer(string $userQuestion, array $contextChunks): array
{
    $contextText = '';
    foreach ($contextChunks as $i => $chunk) {
        $contextText .= "[Source " . ($i + 1) . ": {$chunk['dc_title']}]\n{$chunk['content_chunk']}\n\n";
    }

    $systemPrompt = <<<PROMPT
You are the official Mfano Bora Africa website assistant. Answer ONLY using
the CONTEXT provided below. If the context does not contain the answer, say
you don't have that information and suggest the user contact
info@mfanoboraafrica.com or visit the relevant section of the website.
Never invent facts, dates, prices, or contact details not present in the context.
Keep answers concise and friendly.

CONTEXT:
{$contextText}
PROMPT;

    $payload = [
        'model' => GROQ_MODEL,
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userQuestion],
        ],
        'temperature' => 0.2,
        'max_tokens'  => 500,
    ];

    $ch = curl_init(GROQ_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . GROQ_API_KEY,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $httpCode !== 200) {
        return [
            'answer' => "I'm having trouble reaching my knowledge engine right now. "
                      . "Please try again shortly or email info@mfanoboraafrica.com.",
            'success' => false,
        ];
    }

    $decoded = json_decode($response, true);
    $answer = $decoded['choices'][0]['message']['content'] ?? null;

    if (!$answer) {
        return ['answer' => 'Sorry, I could not generate a response.', 'success' => false];
    }

    return ['answer' => trim($answer), 'success' => true];
}
