<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();

$db   = db();
$msg  = '';
$errs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) die('Invalid CSRF token.');

    $current  = $_POST['current_password']  ?? '';
    $new_pass = $_POST['new_password']       ?? '';
    $confirm  = $_POST['confirm_password']   ?? '';

    // Verify current password
    $hash = $db->query("SELECT setting_value FROM portal_settings WHERE setting_key='admin_password_hash'")->fetchColumn();
    if (!$hash || !password_verify($current, $hash)) {
        $errs[] = 'Current password is incorrect.';
    }
    if (strlen($new_pass) < 8) $errs[] = 'New password must be at least 8 characters.';
    if ($new_pass !== $confirm)  $errs[] = 'Passwords do not match.';

    if (!$errs) {
        $new_hash = password_hash($new_pass, PASSWORD_BCRYPT);
        $db->prepare("UPDATE portal_settings SET setting_value=? WHERE setting_key='admin_password_hash'")
           ->execute([$new_hash]);
        $msg = 'Password changed successfully.';
    }
}

$page_title = 'Change Password';
$active_nav = 'password';
include __DIR__ . '/_layout.php';
?>

<div class="page-header">
  <div class="page-title">Change Password</div>
</div>

<?php if ($msg):  ?><div class="alert alert-success"><?= h($msg) ?></div><?php endif; ?>
<?php if ($errs): ?><div class="alert alert-danger"><?= implode('<br>', array_map('h', $errs)) ?></div><?php endif; ?>

<div class="card" style="max-width:420px">
  <div class="card-header">Update Admin Password</div>
  <div class="card-body">
    <form method="post" autocomplete="off">
      <?= csrf_field() ?>
      <div class="form-group">
        <label class="form-label">Current Password</label>
        <input type="password" name="current_password" class="form-control" autocomplete="current-password" required>
      </div>
      <div class="form-group">
        <label class="form-label">New Password</label>
        <input type="password" name="new_password" class="form-control" autocomplete="new-password" required minlength="8">
      </div>
      <div class="form-group">
        <label class="form-label">Confirm New Password</label>
        <input type="password" name="confirm_password" class="form-control" autocomplete="new-password" required>
      </div>
      <button type="submit" class="btn btn-primary" style="margin-top:.5rem">Change Password</button>
    </form>
  </div>
</div>

<?php include __DIR__ . '/_layout_end.php'; ?>
