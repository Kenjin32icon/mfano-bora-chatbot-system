<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../api/embeddings.php';
$admin = mfano_require_role(['super_admin', 'editor']);
$pdo = mfano_db();

$message = '';

// --- Handle create / update ---------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $id            = $_POST['id'] ?? null;
    $title         = trim($_POST['dc_title'] ?? '');
    $categoryId    = $_POST['category_id'] ?: null;
    $audience      = trim($_POST['target_audience'] ?? '');
    $source        = trim($_POST['dc_source'] ?? '');
    $chunk         = trim($_POST['content_chunk'] ?? '');

    $embedding = mfano_get_embedding($chunk);
    $embLiteral = $embedding ? mfano_vector_to_pg_literal($embedding) : null;

    if ($id) {
        $stmt = $pdo->prepare(
            "UPDATE knowledge_base
             SET dc_title=:t, category_id=:c, target_audience=:a, dc_source=:s,
                 content_chunk=:ch, embedding=COALESCE(:emb, embedding),
                 version = version + 1, updated_at = now()
             WHERE id=:id"
        );
        $stmt->execute(['t'=>$title,'c'=>$categoryId,'a'=>$audience,'s'=>$source,'ch'=>$chunk,'emb'=>$embLiteral,'id'=>$id]);
        $message = 'Entry updated.';
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO knowledge_base (dc_title, category_id, target_audience, dc_source, content_chunk, embedding)
             VALUES (:t,:c,:a,:s,:ch,:emb)"
        );
        $stmt->execute(['t'=>$title,'c'=>$categoryId,'a'=>$audience,'s'=>$source,'ch'=>$chunk,'emb'=>$embLiteral]);
        $message = 'Entry created.';
    }
}

// --- Handle toggle active / delete --------------------------------------
if (isset($_GET['toggle'])) {
    $pdo->prepare("UPDATE knowledge_base SET is_active = NOT is_active WHERE id = :id")
        ->execute(['id' => $_GET['toggle']]);
    header('Location: kb_manage.php'); exit;
}
if (isset($_GET['delete']) && $admin['role'] === 'super_admin') {
    $pdo->prepare("DELETE FROM knowledge_base WHERE id = :id")->execute(['id' => $_GET['delete']]);
    header('Location: kb_manage.php'); exit;
}

$categories = $pdo->query("SELECT id, name FROM kb_categories ORDER BY name")->fetchAll();
$entries    = $pdo->query(
    "SELECT kb.*, c.name AS category_name FROM knowledge_base kb
     LEFT JOIN kb_categories c ON c.id = kb.category_id
     ORDER BY kb.updated_at DESC LIMIT 200"
)->fetchAll();

$editEntry = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM knowledge_base WHERE id = :id");
    $stmt->execute(['id' => $_GET['edit']]);
    $editEntry = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Knowledge Base - Chatbot Admin</title>
<link rel="stylesheet" href="admin-style.css">
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<main class="admin-container">
  <h1>Knowledge Base Entries</h1>
  <?php if ($message): ?><p class="flash"><?= htmlspecialchars($message) ?></p><?php endif; ?>

  <section class="form-card">
    <h2><?= $editEntry ? 'Edit Entry' : 'Add New Entry' ?></h2>
    <form method="post">
      <input type="hidden" name="action" value="save">
      <?php if ($editEntry): ?><input type="hidden" name="id" value="<?= htmlspecialchars($editEntry['id']) ?>"><?php endif; ?>

      <label>Title
        <input type="text" name="dc_title" required value="<?= htmlspecialchars($editEntry['dc_title'] ?? '') ?>">
      </label>
      <label>Category
        <select name="category_id">
          <option value="">-- none --</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat['id'] ?>" <?= (($editEntry['category_id'] ?? '') === $cat['id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($cat['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Target Audience
        <input type="text" name="target_audience" value="<?= htmlspecialchars($editEntry['target_audience'] ?? '') ?>">
      </label>
      <label>Source URL
        <input type="text" name="dc_source" value="<?= htmlspecialchars($editEntry['dc_source'] ?? '') ?>">
      </label>
      <label>Content Chunk (what the chatbot will use to answer)
        <textarea name="content_chunk" rows="5" required><?= htmlspecialchars($editEntry['content_chunk'] ?? '') ?></textarea>
      </label>
      <button type="submit"><?= $editEntry ? 'Save Changes' : 'Create Entry' ?></button>
    </form>
  </section>

  <section>
    <h2>Existing Entries</h2>
    <table class="kb-table">
      <thead><tr><th>Title</th><th>Category</th><th>Active</th><th>Updated</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($entries as $e): ?>
        <tr>
          <td><?= htmlspecialchars($e['dc_title']) ?></td>
          <td><?= htmlspecialchars($e['category_name'] ?? '-') ?></td>
          <td><?= $e['is_active'] === 't' || $e['is_active'] === true ? 'Yes' : 'No' ?></td>
          <td><?= htmlspecialchars($e['updated_at']) ?></td>
          <td>
            <a href="?edit=<?= $e['id'] ?>">Edit</a> |
            <a href="?toggle=<?= $e['id'] ?>">Toggle Active</a>
            <?php if ($admin['role'] === 'super_admin'): ?>
              | <a href="?delete=<?= $e['id'] ?>" onclick="return confirm('Delete permanently?')">Delete</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>
</main>
</body>
</html>
