<?php
/**
 * embeddings.php
 * Converts a text query into a vector using HuggingFace's hosted Inference API,
 * so no local Python/ML runtime is required inside the PHP stack.
 * The SAME model (all-MiniLM-L6-v2, 384-dim) must be used at ingestion time
 * (see scripts/ingest_kb.php) and at query time, or similarity search breaks.
 */

declare(strict_types=1);
require_once __DIR__ . '/config.php';

/**
 * Returns a 384-length float array, or null on failure (caller should fall
 * back to keyword-only search rather than fail the whole request).
 */
function mfano_get_embedding(string $text): ?array
{
    if (HF_API_KEY === '') {
        return null; // Gracefully degrade to keyword search only.
    }

    $ch = curl_init(HF_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . HF_API_KEY,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'inputs' => $text,
            'options' => ['wait_for_model' => true],
        ]),
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $httpCode !== 200) {
        return null;
    }

    $decoded = json_decode($response, true);

    // The feature-extraction endpoint can return [floats] or [[floats]] or
    // token-level [[[floats]]] depending on model/pooling; normalise to one vector.
    if (is_array($decoded) && isset($decoded[0]) && is_array($decoded[0])) {
        if (isset($decoded[0][0]) && is_array($decoded[0][0])) {
            // token-level -> mean pool
            return mfano_mean_pool($decoded[0]);
        }
        return $decoded[0]; // already mean-pooled per token dim? treat as vector
    }

    return is_array($decoded) ? $decoded : null;
}

function mfano_mean_pool(array $tokenVectors): array
{
    $dim = count($tokenVectors[0]);
    $sums = array_fill(0, $dim, 0.0);
    foreach ($tokenVectors as $vec) {
        foreach ($vec as $i => $v) {
            $sums[$i] += $v;
        }
    }
    $count = count($tokenVectors);
    return array_map(fn($s) => $s / $count, $sums);
}

function mfano_vector_to_pg_literal(array $vector): string
{
    return '[' . implode(',', array_map('floatval', $vector)) . ']';
}
