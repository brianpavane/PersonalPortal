<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();

$db     = db();
$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);
$errors = [];
$msg    = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token.');
    }

    $post_action = $_POST['action'] ?? '';
    $name        = trim($_POST['name'] ?? '');
    $icon        = trim($_POST['icon'] ?? 'folder');
    $color       = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['color'] ?? '') ? $_POST['color'] : '#58a6ff';
    $sort_order  = (int)($_POST['sort_order'] ?? 0);
    $edit_id     = (int)($_POST['id'] ?? 0);

    if (!$name) $errors[] = 'Category name is required.';

    if (!$errors) {
        if ($post_action === 'add') {
            $db->prepare('INSERT INTO bookmark_categories (name,icon,color,sort_order) VALUES (?,?,?,?)')
               ->execute([$name, $icon, $color, $sort_order]);
            $msg = 'Category added.';
            $action = 'list';
        } elseif ($post_action === 'edit' && $edit_id) {
            $db->prepare('UPDATE bookmark_categories SET name=?,icon=?,color=?,sort_order=? WHERE id=?')
               ->execute([$name, $icon, $color, $sort_order, $edit_id]);
            $msg = 'Category updated.';
            $action = 'list';
        } elseif ($post_action === 'delete' && $edit_id) {
            $db->prepare('DELETE FROM bookmark_categories WHERE id=?')->execute([$edit_id]);
            $msg = 'Category deleted (bookmarks also removed).';
            $action = 'list';
        }
    }
}

// Load for edit
$edit_cat = null;
if ($action === 'edit' && $id) {
    $edit_cat = $db->prepare('SELECT * FROM bookmark_categories WHERE id=?');
    $edit_cat->execute([$id]);
    $edit_cat = $edit_cat->fetch();
    if (!$edit_cat) { $action = 'list'; }
}

$categories = $db->query(
    'SELECT c.*, (SELECT COUNT(*) FROM bookmarks WHERE category_id=c.id) AS bm_count
     FROM bookmark_categories c ORDER BY sort_order, name'
)->fetchAll();

$page_title = 'Categories';
$active_nav = 'categories';
include __DIR__ . '/_layout.php';
?>

<div class="page-header">
  <div class="page-title">Categories <small>Organize your bookmarks</small></div>
  <a href="?action=add" class="btn btn-success btn-sm">+ New Category</a>
</div>

<?php if ($msg): ?><div class="alert alert-success"><?= h($msg) ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert alert-danger"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div><?php endif; ?>

<?php if ($action === 'add' || $action === 'edit'): ?>
<div class="card" style="max-width:540px;margin-bottom:1.5rem">
  <div class="card-header"><?= $action === 'add' ? 'New Category' : 'Edit Category' ?></div>
  <div class="card-body">
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="<?= $action ?>">
      <?php if ($action === 'edit'): ?><input type="hidden" name="id" value="<?= (int)$edit_cat['id'] ?>"><?php endif; ?>
      <div class="form-group">
        <label class="form-label">Name</label>
        <input type="text" name="name" class="form-control" value="<?= h($edit_cat['name'] ?? '') ?>" required maxlength="100">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Accent Color</label>
          <input type="color" name="color" class="form-control" style="height:42px;padding:.25rem"
                 value="<?= h($edit_cat['color'] ?? '#58a6ff') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Sort Order</label>
          <input type="number" name="sort_order" class="form-control"
                 value="<?= (int)($edit_cat['sort_order'] ?? 0) ?>" min="0">
        </div>
      </div>
      <div style="display:flex;gap:.75rem;margin-top:.5rem">
        <button type="submit" class="btn btn-success"><?= $action === 'add' ? 'Add Category' : 'Save Changes' ?></button>
        <a href="categories.php" class="btn btn-secondary">Cancel</a>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-header">All Categories (<?= count($categories) ?>)</div>
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>Name</th>
        <th>Color</th>
        <th>Bookmarks</th>
        <th>Order</th>
        <th>Actions</th>
      </tr></thead>
      <tbody>
      <?php if (!$categories): ?>
        <tr><td colspan="5" style="color:var(--text-muted);text-align:center;padding:1.5rem">No categories yet.</td></tr>
      <?php endif; ?>
      <?php foreach ($categories as $cat): ?>
      <tr>
        <td>
          <span class="color-dot" style="background:<?= h($cat['color']) ?>"></span>
          <?= h($cat['name']) ?>
        </td>
        <td><code style="font-size:.8rem"><?= h($cat['color']) ?></code></td>
        <td><?= (int)$cat['bm_count'] ?></td>
        <td><?= (int)$cat['sort_order'] ?></td>
        <td style="white-space:nowrap">
          <a href="?action=edit&id=<?= $cat['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
          <form method="post" style="display:inline" onsubmit="return confirm('Delete category and all its bookmarks?')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $cat['id'] ?>">
            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/_layout_end.php'; ?>
