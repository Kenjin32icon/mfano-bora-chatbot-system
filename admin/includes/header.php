<?php $admin = mfano_current_admin(); ?>
<header class="admin-nav">
  <div class="brand">Mfano Bora Chatbot Admin</div>
  <nav>
    <a href="kb_manage.php">Knowledge Base</a>
    <a href="analytics.php">Analytics</a>
  </nav>
  <div class="admin-user">
    <?= htmlspecialchars($admin['email'] ?? '') ?> (<?= htmlspecialchars($admin['role'] ?? '') ?>)
    &middot; <a href="logout.php">Log out</a>
  </div>
</header>
