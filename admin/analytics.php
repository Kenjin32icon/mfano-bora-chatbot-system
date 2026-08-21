<?php
require_once __DIR__ . '/auth.php';
$admin = mfano_require_login();
$pdo = mfano_db();

$totals = $pdo->query(
    "SELECT count(*) AS total,
            sum(was_fallback) AS fallbacks,
            round(avg(confidence_score), 3) AS avg_conf,
            round(avg(response_time_ms), 0) AS avg_ms
     FROM chat_logs
     WHERE created_at > datetime('now', '-30 days')"
)->fetch();

// Gap analysis: unanswered questions, grouped, to guide Task 13 knowledge-base updates
$gaps = $pdo->query(
    "SELECT user_message, count(*) AS times_asked, max(created_at) AS last_asked
     FROM chat_logs
     WHERE was_fallback = 1 AND created_at > datetime('now', '-30 days')
     GROUP BY user_message
     ORDER BY times_asked DESC, last_asked DESC
     LIMIT 30"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Analytics - Chatbot Admin</title>
<link rel="stylesheet" href="admin-style.css">
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>
<main class="admin-container">
  <h1>Chatbot Analytics (Last 30 Days)</h1>
  <div class="stat-grid">
    <div class="stat-card"><span><?= (int)($totals['total'] ?? 0) ?></span>Total Queries</div>
    <div class="stat-card"><span><?= (int)($totals['fallbacks'] ?? 0) ?></span>Fallback / Gaps</div>
    <div class="stat-card"><span><?= htmlspecialchars($totals['avg_conf'] ?? '-') ?></span>Avg Confidence</div>
    <div class="stat-card"><span><?= htmlspecialchars($totals['avg_ms'] ?? '-') ?>ms</span>Avg Response Time</div>
  </div>

  <h2>Knowledge Gaps (Task 13: Gap Analysis)</h2>
  <p>Questions the chatbot could not answer confidently. Add matching entries in
     <a href="kb_manage.php">Knowledge Base Entries</a> to close each gap.</p>
  <table class="kb-table">
    <thead><tr><th>Question Asked</th><th>Times Asked</th><th>Last Asked</th></tr></thead>
    <tbody>
    <?php foreach ($gaps as $g): ?>
      <tr>
        <td><?= htmlspecialchars($g['user_message']) ?></td>
        <td><?= (int)$g['times_asked'] ?></td>
        <td><?= htmlspecialchars($g['last_asked']) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($gaps)): ?><tr><td colspan="3">No gaps recorded. 🎉</td></tr><?php endif; ?>
    </tbody>
  </table>
</main>
</body>
</html>