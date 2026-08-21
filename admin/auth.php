<?php
declare(strict_types=1);
require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/db.php';

session_start();

function mfano_current_admin(): ?array
{
    return $_SESSION['admin'] ?? null;
}

function mfano_require_login(): array
{
    $admin = mfano_current_admin();
    if (!$admin) {
        header('Location: index.php');
        exit;
    }
    return $admin;
}

function mfano_require_role(array $allowedRoles): array
{
    $admin = mfano_require_login();
    if (!in_array($admin['role'], $allowedRoles, true)) {
        http_response_code(403);
        die('You do not have permission to access this page.');
    }
    return $admin;
}

function mfano_attempt_login(string $email, string $password): bool
{
    $pdo = mfano_db();
    $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE email = :email AND is_active = 1");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['admin'] = [
            'id'    => $user['id'],
            'email' => $user['email'],
            'role'  => $user['role'],
        ];
        $update = $pdo->prepare("UPDATE admin_users SET last_login_at = CURRENT_TIMESTAMP WHERE id = :id");
        $update->execute(['id' => $user['id']]);
        return true;
    }
    return false;
}