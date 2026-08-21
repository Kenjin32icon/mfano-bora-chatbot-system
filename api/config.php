<?php
/**
 * config.php
 * Loads environment variables from a .env file that sits OUTSIDE the web root
 * (or at minimum has directory listing / direct access blocked - see README).
 *
 * Copy .env.example to .env and fill in real values before deploying.
 */

declare(strict_types=1);

function mfano_load_env(string $path): void
{
    if (!file_exists($path)) {
        http_response_code(500);
        die(json_encode(['error' => 'Missing .env configuration file. See .env.example.']));
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
        $key   = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        if ($key !== '') {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

mfano_load_env(__DIR__ . '/../.env');

// ---- Local SQLite database --------------------------------------------------
// Path is relative to this file's parent (project root) by default; override
// in .env with an absolute path if you want the .sqlite file stored elsewhere.
define('SQLITE_DB_PATH', getenv('SQLITE_DB_PATH') ?: (__DIR__ . '/../database/chatbot.sqlite'));

// ---- Groq (Llama-3 generation) ---------------------------------------------
define('GROQ_API_KEY', getenv('GROQ_API_KEY') ?: '');
define('GROQ_MODEL', getenv('GROQ_MODEL') ?: 'llama-3.1-8b-instant');
define('GROQ_API_URL', 'https://api.groq.com/openai/v1/chat/completions');

// ---- Embeddings (HuggingFace Inference API) --------------------------------
define('HF_API_KEY', getenv('HF_API_KEY') ?: '');
define('HF_EMBEDDING_MODEL', getenv('HF_EMBEDDING_MODEL') ?: 'sentence-transformers/all-MiniLM-L6-v2');
define('HF_API_URL', 'https://api-inference.huggingface.co/pipeline/feature-extraction/' . HF_EMBEDDING_MODEL);

// ---- App-level settings -----------------------------------------------------
define('APP_ENV', getenv('APP_ENV') ?: 'production');
define('ALLOWED_ORIGIN', getenv('ALLOWED_ORIGIN') ?: 'https://www.mfanoboraafrica.com');
define('SESSION_SECRET', getenv('SESSION_SECRET') ?: '');

error_reporting(APP_ENV === 'production' ? 0 : E_ALL);
ini_set('display_errors', APP_ENV === 'production' ? '0' : '1');