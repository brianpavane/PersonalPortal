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
    $edit_id     = (int)($_POST['id'] ?? 0);

    // ── Move up / down ────────────────────────────────────────────────────────
    if ($post_action === 'move') {
        $direction = $_POST['direction'] ?? '';
        if (in_array($direction, ['up', 'down'], true) && $edit_id) {
            $all = $db->query(
                'SELECT id FROM bookmark_categories ORDER BY sort_order, name'
            )->fetchAll(PDO::FETCH_COLUMN);
            $pos = array_search($edit_id, $all);
            if ($pos !== false) {
                if ($direction === 'up' && $pos > 0) {
                    [$all[$pos], $all[$pos - 1]] = [$all[$pos - 1], $all[$pos]];
                } elseif ($direction === 'down' && $pos < count($all) - 1) {
                    [$all[$pos], $all[$pos + 1]] = [$all[$pos + 1], $all[$pos]];
                }
                $upd = $db->prepare('UPDATE bookmark_categories SET sort_order=? WHERE id=?');
                foreach ($all as $i => $cid) {
                    $upd->execute([($i + 1) * 10, $cid]);
                }
            }
        }
        header('Location: categories.php');
        exit;
    }

    $name       = trim($_POST['name'] ?? '');
    $icon       = trim($_POST['icon'] ?? 'folder');
    $color      = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['color'] ?? '') ? $_POST['color'] : '#58a6ff';
    $sort_order = (int)($_POST['sort_order'] ?? 0);

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

$last_cat_idx = count($categories) - 1;

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
      <div class="form-group">
        <label class="form-label">Accent Color</label>
        <?= color_palette_field('color', $edit_cat['color'] ?? '#58a6ff') ?>
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
        <th>Order</th>
        <th>Name</th>
        <th>Color</th>
        <th>Bookmarks</th>
        <th>Actions</th>
      </tr></thead>
      <tbody>
      <?php if (!$categories): ?>
        <tr><td colspan="5" style="color:var(--text-muted);text-align:center;padding:1.5rem">No categories yet.</td></tr>
      <?php endif; ?>
      <?php foreach ($categories as $i => $cat): ?>
      <tr>
        <td style="white-space:nowrap">
          <form method="post" style="display:inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="move">
            <input type="hidden" name="id" value="<?= $cat['id'] ?>">
            <input type="hidden" name="direction" value="up">
            <button type="submit" class="btn btn-sm btn-secondary" <?= $i === 0 ? 'disabled' : '' ?> title="Move up">&#9650;</button>
          </form>
          <form method="post" style="display:inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="move">
            <input type="hidden" name="id" value="<?= $cat['id'] ?>">
            <input type="hidden" name="direction" value="down">
            <button type="submit" class="btn btn-sm btn-secondary" <?= $i === $last_cat_idx ? 'disabled' : '' ?> title="Move down">&#9660;</button>
          </form>
        </td>
        <td>
          <span class="color-dot" style="background:<?= h($cat['color']) ?>"></span>
          <?= h($cat['name']) ?>
        </td>
        <td><code style="font-size:.8rem"><?= h($cat['color']) ?></code></td>
        <td><?= (int)$cat['bm_count'] ?></td>
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
