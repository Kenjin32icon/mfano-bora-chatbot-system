<?php
/**
 * db.php
 * Direct PDO connection to the local SQLite database.
 * Replaces remote PostgreSQL with a lightweight, local file-based SQLite database.
 */

declare(strict_types=1);
require_once __DIR__ . '/config.php';

/**
 * Returns a static PDO connection instance to the local SQLite database.
 *
 * @return PDO
 */
function mfano_db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    // Points to the chatbot.sqlite database file relative to the api directory
    $dbPath = __DIR__ . '/../database/chatbot.sqlite';

    try {
        $pdo = new PDO('sqlite:' . $dbPath);
        
        // Enable exceptions for error handling and return associative arrays by default
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Performance & integrity optimizations for local SQLite
        $pdo->exec('PRAGMA foreign_keys = ON;');
        $pdo->exec('PRAGMA journal_mode = WAL;');

    } catch (PDOException $e) {
        error_log("Database Connection Error: " . $e->getMessage());
        http_response_code(500);
        die(json_encode(['error' => 'Database connection failed. Check server configuration.']));
    }

    return $pdo;
}