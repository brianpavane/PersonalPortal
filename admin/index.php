<?php
/**
 * Admin Login Page
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

// Already logged in → redirect to dashboard
if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$error    = '';
$redirect = $_GET['redirect'] ?? 'dashboard.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (login($username, $password)) {
        $safe = filter_var($redirect, FILTER_VALIDATE_URL) === false
              ? ltrim($redirect, '/')
              : 'dashboard.php';
        header('Location: ' . $safe);
        exit;
    }
    // Artificial delay to slow brute-force
    sleep(1);
    $error = 'Invalid username or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Login — <?= htmlspecialchars(APP_NAME) ?></title>
<link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<div class="login-page">
  <div class="login-card">
    <h1>&#9881; Admin</h1>
    <p class="subtitle"><?= htmlspecialchars(APP_NAME) ?></p>

    <?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
      <?= csrf_field() ?>
      <div class="form-group">
        <label class="form-label">Username</label>
        <input type="text" name="username" class="form-control" autofocus autocomplete="username" required>
      </div>
      <div class="form-group">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control" autocomplete="current-password" required>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:.5rem">Sign In</button>
    </form>

    <p style="text-align:center;margin-top:1.5rem">
      <a href="../index.php" style="font-size:.8rem;color:var(--text-muted)">← Back to Portal</a>
    </p>
  </div>
</div>
</body>
</html>
