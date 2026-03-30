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

    // ── Toggle portal auth on/off ─────────────────────────────────────────────
    if ($post_action === 'toggle_auth') {
        $current = $db->query("SELECT setting_value FROM portal_settings WHERE setting_key='portal_auth_enabled'")->fetchColumn();
        $new_val = ($current === '1') ? '0' : '1';
        $db->prepare("REPLACE INTO portal_settings (setting_key, setting_value) VALUES ('portal_auth_enabled',?)")
           ->execute([$new_val]);
        $msg = $new_val === '1' ? 'Portal access control enabled.' : 'Portal access control disabled.';
    }

    // ── Add user ──────────────────────────────────────────────────────────────
    if ($post_action === 'add') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password']  ?? '';
        $password2= $_POST['password2'] ?? '';

        if (!$username) $errors[] = 'Username is required.';
        if (!preg_match('/^[a-zA-Z0-9_.\-]{2,60}$/', $username)) {
            $errors[] = 'Username: 2–60 alphanumeric characters only.';
        }
        if (strlen($password) < 8)      $errors[] = 'Password must be at least 8 characters.';
        if ($password !== $password2)   $errors[] = 'Passwords do not match.';

        // Unique check
        if (!$errors) {
            $chk = $db->prepare('SELECT id FROM portal_users WHERE username = ?');
            $chk->execute([$username]);
            if ($chk->fetchColumn()) $errors[] = 'Username already exists.';
        }

        if (!$errors) {
            $db->prepare('INSERT INTO portal_users (username, password_hash) VALUES (?,?)')
               ->execute([$username, password_hash($password, PASSWORD_BCRYPT, ['cost' => 12])]);
            $msg    = 'User "' . htmlspecialchars($username) . '" added.';
            $action = 'list';
        }
    }

    // ── Edit user (change password / toggle active) ───────────────────────────
    if ($post_action === 'edit') {
        $edit_id   = (int)($_POST['id'] ?? 0);
        $password  = $_POST['password']  ?? '';
        $password2 = $_POST['password2'] ?? '';
        $active    = (int)(!empty($_POST['active']));

        if ($password !== '') {
            if (strlen($password) < 8) $errors[] = 'New password must be at least 8 characters.';
            if ($password !== $password2) $errors[] = 'Passwords do not match.';
        }

        if (!$errors) {
            if ($password !== '') {
                $db->prepare('UPDATE portal_users SET password_hash=?, active=? WHERE id=?')
                   ->execute([password_hash($password, PASSWORD_BCRYPT, ['cost'=>12]), $active, $edit_id]);
            } else {
                $db->prepare('UPDATE portal_users SET active=? WHERE id=?')
                   ->execute([$active, $edit_id]);
            }
            $msg = 'User updated.';
            $action = 'list';
        }
    }

    // ── Delete user ───────────────────────────────────────────────────────────
    if ($post_action === 'delete') {
        $del_id = (int)($_POST['id'] ?? 0);
        $db->prepare('DELETE FROM portal_users WHERE id=?')->execute([$del_id]);
        $msg = 'User deleted.';
    }
}

// Load edit target
$edit_user = null;
if ($action === 'edit' && $id) {
    $s = $db->prepare('SELECT * FROM portal_users WHERE id=?');
    $s->execute([$id]);
    $edit_user = $s->fetch();
    if (!$edit_user) $action = 'list';
}

$auth_enabled = $db->query("SELECT setting_value FROM portal_settings WHERE setting_key='portal_auth_enabled'")->fetchColumn();
$users        = $db->query('SELECT * FROM portal_users ORDER BY username')->fetchAll();

$page_title = 'Portal Users';
$active_nav = 'portal_users';
include __DIR__ . '/_layout.php';
?>

<div class="page-header">
  <div class="page-title">Portal Users <small>Control who can access the portal</small></div>
  <a href="?action=add" class="btn btn-success btn-sm">+ Add User</a>
</div>

<?php if ($msg):   ?><div class="alert alert-success"><?= h($msg) ?></div><?php endif; ?>
<?php if ($errors):?><div class="alert alert-danger"><?= implode('<br>', array_map('h', $errors)) ?></div><?php endif; ?>

<!-- Access control toggle -->
<div class="card" style="margin-bottom:1.25rem">
  <div class="card-header">
    Portal Access Control
    <span style="font-size:.8rem;font-weight:400;color:var(--text-muted)">— controls whether visitors must log in to view the portal</span>
  </div>
  <div class="card-body" style="display:flex;align-items:center;justify-content:space-between">
    <div>
      <strong style="color:<?= $auth_enabled === '1' ? 'var(--accent-green)' : 'var(--accent-orange)' ?>">
        <?= $auth_enabled === '1' ? '&#10003; Enabled — portal requires login' : '&#9711; Disabled — portal is public' ?>
      </strong>
      <div style="font-size:.8rem;color:var(--text-muted);margin-top:.3rem">
        <?php if ($auth_enabled === '1'): ?>
        Visitors must sign in with a portal user account to view the portal.
        <?php else: ?>
        The portal is accessible to anyone without a password. Enable to restrict access.
        <?php endif; ?>
      </div>
    </div>
    <form method="post" style="flex-shrink:0;margin-left:1rem">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="toggle_auth">
      <button type="submit" class="btn <?= $auth_enabled === '1' ? 'btn-danger' : 'btn-success' ?> btn-sm">
        <?= $auth_enabled === '1' ? 'Disable' : 'Enable' ?>
      </button>
    </form>
  </div>
</div>

<?php if ($action === 'add' || $action === 'edit'): ?>
<div class="card" style="max-width:480px;margin-bottom:1.5rem">
  <div class="card-header"><?= $action === 'add' ? 'New Portal User' : 'Edit User' ?></div>
  <div class="card-body">
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="<?= $action ?>">
      <?php if ($action === 'edit'): ?><input type="hidden" name="id" value="<?= (int)$edit_user['id'] ?>"><?php endif; ?>

      <?php if ($action === 'add'): ?>
      <div class="form-group">
        <label class="form-label">Username</label>
        <input type="text" name="username" class="form-control"
               pattern="[a-zA-Z0-9_.\-]{2,60}" required maxlength="60">
      </div>
      <?php else: ?>
      <div class="form-group">
        <label class="form-label">Username</label>
        <input type="text" class="form-control" value="<?= h($edit_user['username']) ?>" disabled>
      </div>
      <?php endif; ?>

      <div class="form-group">
        <label class="form-label"><?= $action === 'edit' ? 'New Password <span style="font-weight:400;color:var(--text-muted)">(leave blank to keep current)</span>' : 'Password (min 8 chars)' ?></label>
        <input type="password" name="password" class="form-control"
               <?= $action === 'add' ? 'required minlength="8"' : '' ?>
               autocomplete="new-password" maxlength="200">
      </div>
      <div class="form-group">
        <label class="form-label">Confirm Password</label>
        <input type="password" name="password2" class="form-control"
               autocomplete="new-password" maxlength="200">
      </div>
      <?php if ($action === 'edit'): ?>
      <div class="form-group">
        <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer">
          <input type="checkbox" name="active" value="1" <?= $edit_user['active'] ? 'checked' : '' ?>
                 style="accent-color:var(--accent-blue);width:15px;height:15px">
          <span class="form-label" style="margin:0">Account active</span>
        </label>
      </div>
      <?php endif; ?>
      <div style="display:flex;gap:.75rem;margin-top:.5rem">
        <button type="submit" class="btn btn-success"><?= $action === 'add' ? 'Add User' : 'Save' ?></button>
        <a href="portal_users.php" class="btn btn-secondary">Cancel</a>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-header">Portal Users (<?= count($users) ?>)</div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Username</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
      <tbody>
      <?php if (!$users): ?>
        <tr><td colspan="4" style="text-align:center;color:var(--text-muted);padding:1.5rem">
          No portal users yet. <a href="?action=add">Add one</a> to start restricting access.
        </td></tr>
      <?php endif; ?>
      <?php foreach ($users as $u): ?>
      <tr>
        <td><strong><?= h($u['username']) ?></strong></td>
        <td>
          <span style="color:<?= $u['active'] ? 'var(--accent-green)' : 'var(--text-muted)' ?>;font-size:.82rem">
            <?= $u['active'] ? '&#10003; Active' : '&#8212; Inactive' ?>
          </span>
        </td>
        <td style="font-size:.8rem;color:var(--text-muted)"><?= h(substr($u['created_at'], 0, 10)) ?></td>
        <td style="white-space:nowrap">
          <a href="?action=edit&id=<?= $u['id'] ?>" class="btn btn-secondary btn-sm">Edit</a>
          <form method="post" style="display:inline" onsubmit="return confirm('Delete user <?= h(addslashes($u['username'])) ?>?')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $u['id'] ?>">
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
