<?php
/**
 * db.php
 * Direct PDO connection to the local SQLite database.
 * Replaces the remote Supabase Postgres pooler with a lightweight local file-based database.
 */

declare(strict_types=1);
require_once __DIR__ . '/config.php';

function mfano_db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    // Database sits locally in the folder, no external host needed
    $dbPath = __DIR__ . '/../database/chatbot.sqlite';

    try {
        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        // Enable foreign keys and Write-Ahead Logging for performance
        $pdo->exec('PRAGMA foreign_keys = ON; PRAGMA journal_mode = WAL;');
    } catch (PDOException $e) {
        http_response_code(500);
        die(json_encode(['error' => 'Local database connection failed.']));
    }

    return $pdo;
}