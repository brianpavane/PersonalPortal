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
    $title       = trim($_POST['title']   ?? '');
    $content     = $_POST['content']      ?? '';
    $color       = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['color'] ?? '') ? $_POST['color'] : '#58a6ff';
    $sort_order  = (int)($_POST['sort_order'] ?? 0);
    $edit_id     = (int)($_POST['id']     ?? 0);

    if (!$title) $errors[] = 'Title is required.';

    if (!$errors) {
        if ($post_action === 'add') {
            $db->prepare('INSERT INTO notes (title,content,color,sort_order) VALUES (?,?,?,?)')
               ->execute([$title, $content, $color, $sort_order]);
            $msg = 'Note added.';
            $action = 'list';
        } elseif ($post_action === 'edit' && $edit_id) {
            $db->prepare('UPDATE notes SET title=?,content=?,color=?,sort_order=? WHERE id=?')
               ->execute([$title, $content, $color, $sort_order, $edit_id]);
            $msg = 'Note updated.';
            $action = 'list';
        } elseif ($post_action === 'delete' && $edit_id) {
            $db->prepare('DELETE FROM notes WHERE id=?')->execute([$edit_id]);
            $msg = 'Note deleted.';
            $action = 'list';
        }
    }
}

$edit_note = null;
if ($action === 'edit' && $id) {
    $s = $db->prepare('SELECT * FROM notes WHERE id=?');
    $s->execute([$id]);
    $edit_note = $s->fetch();
    if (!$edit_note) $action = 'list';
}

$notes = $db->query('SELECT * FROM notes ORDER BY sort_order, title')->fetchAll();

$page_title = 'Notes';
$active_nav = 'notes';
include __DIR__ . '/_layout.php';
?>

<div class="page-header">
  <div class="page-title">Notes <small>Bulletin-style notes</small></div>
  <a href="?action=add" class="btn btn-success btn-sm">+ New Note</a>
</div>

<?php if ($msg):   ?><div class="alert alert-success"><?= h($msg) ?></div><?php endif; ?>
<?php if ($errors):?><div class="alert alert-danger"><?= implode('<br>', array_map('h', $errors)) ?></div><?php endif; ?>

<?php if ($action === 'add' || $action === 'edit'): ?>
<div class="card" style="margin-bottom:1.5rem">
  <div class="card-header"><?= $action === 'add' ? 'New Note' : 'Edit Note' ?></div>
  <div class="card-body">
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="<?= $action ?>">
      <?php if ($action === 'edit'): ?><input type="hidden" name="id" value="<?= (int)$edit_note['id'] ?>"><?php endif; ?>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Title</label>
          <input type="text" name="title" class="form-control" value="<?= h($edit_note['title'] ?? '') ?>" required maxlength="200">
        </div>
        <div class="form-row" style="align-items:end">
          <div class="form-group">
            <label class="form-label">Accent Color</label>
            <input type="color" name="color" class="form-control" style="height:42px;padding:.25rem"
                   value="<?= h($edit_note['color'] ?? '#58a6ff') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Sort Order</label>
            <input type="number" name="sort_order" class="form-control"
                   value="<?= (int)($edit_note['sort_order'] ?? 0) ?>" min="0">
          </div>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Content</label>
        <textarea name="content" class="form-control" rows="12" style="font-family:monospace;font-size:.88rem"><?= h($edit_note['content'] ?? '') ?></textarea>
        <div class="form-hint">
          Formatting: <code>## Heading</code> for headings &bull; <code>- item</code> or <code>* item</code> for bullets &bull; Plain text for paragraphs
        </div>
      </div>

      <!-- Live preview -->
      <div class="form-group">
        <label class="form-label">Preview</label>
        <div id="note-preview" style="background:var(--bg-elevated);border:1px solid var(--border);border-radius:6px;
             padding:.85rem 1rem;font-size:.85rem;min-height:60px;color:var(--text-muted)"></div>
      </div>

      <div style="display:flex;gap:.75rem;margin-top:.5rem">
        <button type="submit" class="btn btn-success"><?= $action === 'add' ? 'Add Note' : 'Save Note' ?></button>
        <a href="notes.php" class="btn btn-secondary">Cancel</a>
      </div>
    </form>
  </div>
</div>

<script>
(function() {
  const textarea = document.querySelector('textarea[name="content"]');
  const preview  = document.getElementById('note-preview');
  if (!textarea || !preview) return;

  function escHtml(s) {
    const d = document.createElement('div');
    d.textContent = String(s ?? '');
    return d.innerHTML;
  }

  function bulletsToHtml(text) {
    const lines = String(text || '').split('\n');
    let html = '', inList = false;
    lines.forEach(rawLine => {
      const line = rawLine.replace(/\r$/, '');
      if (!line) { if (inList) { html += '</ul>'; inList = false; } return; }
      if (line.startsWith('## ')) {
        if (inList) { html += '</ul>'; inList = false; }
        html += `<h4 style="color:var(--text-primary);font-size:.82rem;margin:.4rem 0 .2rem;text-transform:uppercase;letter-spacing:.05em">${escHtml(line.slice(3))}</h4>`;
        return;
      }
      const m = line.match(/^[-*]\s+(.+)$/);
      if (m) {
        if (!inList) { html += '<ul style="padding-left:1.2rem;margin:.2rem 0">'; inList = true; }
        html += `<li style="margin:.15rem 0">${escHtml(m[1])}</li>`;
        return;
      }
      if (inList) { html += '</ul>'; inList = false; }
      html += `<p style="margin:.2rem 0">${escHtml(line)}</p>`;
    });
    if (inList) html += '</ul>';
    return html || '<em>Start typing above…</em>';
  }

  function update() { preview.innerHTML = bulletsToHtml(textarea.value); }
  textarea.addEventListener('input', update);
  update();
})();
</script>
<?php endif; ?>

<div class="card">
  <div class="card-header">All Notes (<?= count($notes) ?>)</div>
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>Title</th>
        <th>Preview</th>
        <th>Updated</th>
        <th>Order</th>
        <th>Actions</th>
      </tr></thead>
      <tbody>
      <?php if (!$notes): ?>
        <tr><td colspan="5" style="color:var(--text-muted);text-align:center;padding:1.5rem">No notes yet.</td></tr>
      <?php endif; ?>
      <?php foreach ($notes as $note): ?>
      <tr>
        <td>
          <span class="color-dot" style="background:<?= h($note['color']) ?>"></span>
          <strong><?= h($note['title']) ?></strong>
        </td>
        <td style="color:var(--text-muted);font-size:.8rem;max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
          <?= h(mb_substr($note['content'], 0, 80)) ?>…
        </td>
        <td style="font-size:.8rem;color:var(--text-muted)"><?= h(substr($note['updated_at'], 0, 10)) ?></td>
        <td><?= (int)$note['sort_order'] ?></td>
        <td style="white-space:nowrap">
          <a href="?action=edit&id=<?= $note['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
          <form method="post" style="display:inline" onsubmit="return confirm('Delete this note?')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $note['id'] ?>">
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
