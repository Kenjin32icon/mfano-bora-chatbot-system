<?php
/**
 * ingest_kb.php
 * Run from the command line (not the web) to bulk-load or update
 * knowledge_base rows with a freshly computed embedding for each chunk.
 *
 * Usage:
 *   php ingest_kb.php path/to/content.csv
 *
 * CSV columns (header row required):
 *   category_slug, dc_title, dc_source, target_audience, content_chunk
 *
 * This keeps ingestion-time and query-time embeddings on the SAME model
 * (see api/embeddings.php), which is required for cosine similarity to work.
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    die("Run this script from the command line: php ingest_kb.php file.csv\n");
}

require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../api/embeddings.php';

$csvPath = $argv[1] ?? null;
if (!$csvPath || !file_exists($csvPath)) {
    die("CSV file not found. Usage: php ingest_kb.php path/to/content.csv\n");
}

$pdo = mfano_db();
$handle = fopen($csvPath, 'r');
$header = fgetcsv($handle);

$catStmt = $pdo->prepare("SELECT id FROM kb_categories WHERE slug = :slug");
$insertStmt = $pdo->prepare(
    "INSERT INTO knowledge_base
        (category_id, dc_title, dc_source, target_audience, content_chunk, embedding)
     VALUES (:cat, :title, :source, :audience, :chunk, :emb)"
);

$count = 0;
while (($row = fgetcsv($handle)) !== false) {
    $data = array_combine($header, $row);

    $catStmt->execute(['slug' => $data['category_slug']]);
    $categoryId = $catStmt->fetchColumn() ?: null;

    $embedding = mfano_get_embedding($data['content_chunk']);
    $embLiteral = $embedding ? mfano_vector_to_pg_literal($embedding) : null;

    $insertStmt->execute([
        'cat'      => $categoryId,
        'title'    => $data['dc_title'],
        'source'   => $data['dc_source'] ?? null,
        'audience' => $data['target_audience'] ?? null,
        'chunk'    => $data['content_chunk'],
        'emb'      => $embLiteral,
    ]);

    $count++;
    echo "Ingested: {$data['dc_title']}\n";
    usleep(300000); // be polite to the free HF inference API rate limit
}

fclose($handle);
echo "\nDone. {$count} knowledge_base rows ingested/updated.\n";
