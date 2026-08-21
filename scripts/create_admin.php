<?php
/**
 * create_admin.php
 * Usage: php create_admin.php admin@mfanoboraafrica.com "StrongPassword123!" super_admin
 */
declare(strict_types=1);
if (php_sapi_name() !== 'cli') { die("Run from CLI only.\n"); }

require_once __DIR__ . '/../api/config.php';
require_once __DIR__ . '/../api/db.php';

[$script, $email, $password, $role] = array_pad($argv, 4, null);
$role = $role ?? 'editor';

if (!$email || !$password) {
    die("Usage: php create_admin.php <email> <password> [role: super_admin|editor|viewer]\n");
}

$pdo = mfano_db();
$stmt = $pdo->prepare(
    "INSERT INTO admin_users (email, password_hash, role) VALUES (:email, :hash, :role)
     ON CONFLICT (email) DO UPDATE SET password_hash = EXCLUDED.password_hash, role = EXCLUDED.role"
);
$stmt->execute([
    'email' => $email,
    'hash'  => password_hash($password, PASSWORD_BCRYPT),
    'role'  => $role,
]);

echo "Admin account ready: {$email} ({$role})\n";
