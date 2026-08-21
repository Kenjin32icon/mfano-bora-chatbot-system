<?php
/**
 * db.php
 * Direct PDO connection to the Supabase Postgres database.
 * Uses the Supabase "Session pooler" or "Direct connection" string found in:
 * Supabase Dashboard -> Project Settings -> Database -> Connection string
 */

declare(strict_types=1);
require_once __DIR__ . '/config.php';

function mfano_db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s;sslmode=require',
        SUPABASE_DB_HOST,
        SUPABASE_DB_PORT,
        SUPABASE_DB_NAME
    );

    try {
        $pdo = new PDO($dsn, SUPABASE_DB_USER, SUPABASE_DB_PASSWORD, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        die(json_encode(['error' => 'Database connection failed.']));
    }

    return $pdo;
}
