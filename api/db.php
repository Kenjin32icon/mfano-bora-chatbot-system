<?php
/**
 * db.php
 * Direct PDO connection to the local SQLite database used for alpha/local
 * testing. Replaces the remote Supabase/Postgres connection entirely.
 */

declare(strict_types=1);
require_once __DIR__ . '/config.php';

function mfano_db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    try {
        $pdo = new PDO('sqlite:' . SQLITE_DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Performance & integrity optimizations for local SQLite
        $pdo->exec('PRAGMA foreign_keys = ON;');
        $pdo->exec('PRAGMA journal_mode = WAL;');
    } catch (PDOException $e) {
        error_log('Database Connection Error: ' . $e->getMessage());
        http_response_code(500);
        die(json_encode(['error' => 'Database connection failed. Check server configuration.']));
    }

    return $pdo;
}