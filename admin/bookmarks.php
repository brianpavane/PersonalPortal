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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) die('Invalid CSRF token.');

    $post_action = $_POST['action'] ?? '';
    $title       = trim($_POST['title']       ?? '');
    $url         = trim($_POST['url']         ?? '');
    $cat_id      = (int)($_POST['category_id'] ?? 0);
    $desc        = trim($_POST['description'] ?? '');
    $sort_order  = (int)($_POST['sort_order'] ?? 0);
    $edit_id     = (int)($_POST['id']         ?? 0);

    // Validate URL
    if (!$title)  $errors[] = 'Title is required.';
    if (!$url)    $errors[] = 'URL is required.';
    if ($url && !filter_var($url, FILTER_VALIDATE_URL)) $errors[] = 'URL does not appear valid.';
    if (!$cat_id) $errors[] = 'Category is required.';

    // Ensure category exists
    if ($cat_id && !$db->prepare('SELECT id FROM bookmark_categories WHERE id=?')->execute([$cat_id]) ) {
        $errors[] = 'Invalid category.';
    }

    if (!$errors) {
        if ($post_action === 'add') {
            $db->prepare('INSERT INTO bookmarks (category_id,title,url,description,sort_order) VALUES (?,?,?,?,?)')
               ->execute([$cat_id, $title, $url, $desc, $sort_order]);
            $msg = 'Bookmark added.';
            $action = 'list';
        } elseif ($post_action === 'edit' && $edit_id) {
            $db->prepare('UPDATE bookmarks SET category_id=?,title=?,url=?,description=?,sort_order=? WHERE id=?')
               ->execute([$cat_id, $title, $url, $desc, $sort_order, $edit_id]);
            $msg = 'Bookmark updated.';
            $action = 'list';
        } elseif ($post_action === 'delete' && $edit_id) {
            $db->prepare('DELETE FROM bookmarks WHERE id=?')->execute([$edit_id]);
            $msg = 'Bookmark deleted.';
            $action = 'list';
        }
    }
}

// Load edit target
$edit_bm = null;
if ($action === 'edit' && $id) {
    $s = $db->prepare('SELECT * FROM bookmarks WHERE id=?');
    $s->execute([$id]);
    $edit_bm = $s->fetch();
    if (!$edit_bm) $action = 'list';
}

// Category filter
$filter_cat = (int)($_GET['cat'] ?? 0);

// Load bookmarks + categories
$categories = $db->query('SELECT id,name,color FROM bookmark_categories ORDER BY sort_order,name')->fetchAll();

$bm_query = 'SELECT b.*, c.name AS cat_name, c.color AS cat_color
             FROM bookmarks b
             JOIN bookmark_categories c ON b.category_id = c.id';
$params = [];
if ($filter_cat) {
    $bm_query .= ' WHERE b.category_id = ?';
    $params[] = $filter_cat;
}
$bm_query .= ' ORDER BY c.sort_order, b.sort_order, b.title';
$stmt = $db->prepare($bm_query);
$stmt->execute($params);
$bookmarks = $stmt->fetchAll();

$page_title = 'Bookmarks';
$active_nav = 'bookmarks';
include __DIR__ . '/_layout.php';
?>

<div class="page-header">
  <div class="page-title">Bookmarks <small>Manage your links</small></div>
  <a href="?action=add" class="btn btn-success btn-sm">+ Add Bookmark</a>
</div>

<?php if ($msg):   ?><div class="alert alert-success"><?= h($msg) ?></div><?php endif; ?>
<?php if ($errors):?><div class="alert alert-danger"><?= implode('<br>', array_map('h', $errors)) ?></div><?php endif; ?>

<?php if ($action === 'add' || $action === 'edit'): ?>
<div class="card" style="max-width:600px;margin-bottom:1.5rem">
  <div class="card-header"><?= $action === 'add' ? 'New Bookmark' : 'Edit Bookmark' ?></div>
  <div class="card-body">
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="<?= $action ?>">
      <?php if ($action === 'edit'): ?><input type="hidden" name="id" value="<?= (int)$edit_bm['id'] ?>"><?php endif; ?>

      <div class="form-group">
        <label class="form-label">Title</label>
        <input type="text" name="title" class="form-control" value="<?= h($edit_bm['title'] ?? '') ?>" required maxlength="200">
      </div>
      <div class="form-group">
        <label class="form-label">URL</label>
        <input type="url" name="url" class="form-control" value="<?= h($edit_bm['url'] ?? '') ?>" required
               placeholder="https://example.com">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Category</label>
          <select name="category_id" class="form-control" required>
            <option value="">— Select —</option>
            <?php foreach ($categories as $cat): ?>
            <option value="<?= $cat['id'] ?>" <?= ($edit_bm['category_id'] ?? 0) == $cat['id'] ? 'selected' : '' ?>>
              <?= h($cat['name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Sort Order</label>
          <input type="number" name="sort_order" class="form-control" value="<?= (int)($edit_bm['sort_order'] ?? 0) ?>" min="0">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Description <span style="font-weight:400;color:var(--text-muted)">(optional tooltip)</span></label>
        <input type="text" name="description" class="form-control" value="<?= h($edit_bm['description'] ?? '') ?>" maxlength="500">
      </div>
      <div style="display:flex;gap:.75rem;margin-top:.5rem">
        <button type="submit" class="btn btn-success"><?= $action === 'add' ? 'Add Bookmark' : 'Save Changes' ?></button>
        <a href="bookmarks.php" class="btn btn-secondary">Cancel</a>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- Filter bar -->
<div style="display:flex;align-items:center;gap:.5rem;margin-bottom:1rem;flex-wrap:wrap">
  <span style="font-size:.8rem;color:var(--text-muted)">Filter:</span>
  <a href="bookmarks.php" class="btn btn-sm <?= !$filter_cat ? 'btn-primary' : 'btn-secondary' ?>">All</a>
  <?php foreach ($categories as $cat): ?>
  <a href="?cat=<?= $cat['id'] ?>" class="btn btn-sm <?= $filter_cat == $cat['id'] ? 'btn-primary' : 'btn-secondary' ?>">
    <span class="color-dot" style="background:<?= h($cat['color']) ?>"></span>
    <?= h($cat['name']) ?>
  </a>
  <?php endforeach; ?>
</div>

<div class="card">
  <div class="card-header">Bookmarks (<?= count($bookmarks) ?>)</div>
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>Title</th>
        <th>URL</th>
        <th>Category</th>
        <th>Order</th>
        <th>Actions</th>
      </tr></thead>
      <tbody>
      <?php if (!$bookmarks): ?>
        <tr><td colspan="5" style="color:var(--text-muted);text-align:center;padding:1.5rem">
          No bookmarks yet. <?php if (!$categories): ?><a href="categories.php?action=add">Create a category first</a>.<?php else: ?><a href="?action=add">Add one now</a>.<?php endif; ?>
        </td></tr>
      <?php endif; ?>
      <?php foreach ($bookmarks as $bm): ?>
      <tr>
        <td>
          <img src="https://www.google.com/s2/favicons?domain=<?= urlencode(parse_url($bm['url'], PHP_URL_HOST) ?: '') ?>&sz=16"
               width="16" height="16" style="vertical-align:middle;margin-right:.4rem;border-radius:2px" alt="" loading="lazy">
          <?= h($bm['title']) ?>
        </td>
        <td><a href="<?= h($bm['url']) ?>" target="_blank" rel="noopener" style="font-size:.82rem;max-width:200px;display:inline-block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;vertical-align:middle" title="<?= h($bm['url']) ?>"><?= h($bm['url']) ?></a></td>
        <td><span class="color-dot" style="background:<?= h($bm['cat_color']) ?>"></span><?= h($bm['cat_name']) ?></td>
        <td><?= (int)$bm['sort_order'] ?></td>
        <td style="white-space:nowrap">
          <a href="?action=edit&id=<?= $bm['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
          <form method="post" style="display:inline" onsubmit="return confirm('Delete this bookmark?')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $bm['id'] ?>">
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
