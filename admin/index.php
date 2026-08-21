<?php
require_once __DIR__ . '/auth.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if (mfano_attempt_login($email, $password)) {
        header('Location: kb_manage.php');
        exit;
    }
    $error = 'Invalid email or password.';
}

if (mfano_current_admin()) {
    header('Location: kb_manage.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Mfano Bora Chatbot Admin - Login</title>
<link rel="stylesheet" href="admin-style.css">
</head>
<body class="login-page">
  <form class="login-card" method="post">
    <h1>Chatbot Admin</h1>
    <p class="subtitle">Mfano Bora Africa Knowledge Base</p>
    <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <label>Email
      <input type="email" name="email" required autofocus>
    </label>
    <label>Password
      <input type="password" name="password" required>
    </label>
    <button type="submit">Sign In</button>
  </form>
</body>
</html>
