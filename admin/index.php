<?php
/**
 * Admin Login Page
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Already logged in → dashboard
if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

// ── Safe redirect whitelist ───────────────────────────────────────────────────
// Only allow redirecting back to known admin pages — never to external URLs.
$allowed_redirects = [
    'dashboard.php', 'bookmarks.php', 'categories.php',
    'notes.php', 'settings.php', 'password.php',
];
$redirect_raw = $_GET['redirect'] ?? '';
// Strip path components and parameters; only keep the bare filename
$redirect = basename(preg_replace('/[^a-zA-Z0-9._\-]/', '', $redirect_raw));
if (!in_array($redirect, $allowed_redirects, true)) {
    $redirect = 'dashboard.php';
}

$error     = '';
$locked    = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. CSRF check first
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request token. Please reload and try again.';
    } else {
        // 2. Rate-limit check (by IP)
        $client_ip = $_SERVER['HTTP_X_FORWARDED_FOR']
                   ?? $_SERVER['REMOTE_ADDR']
                   ?? '0.0.0.0';
        // Use only the first IP if behind a proxy (don't trust full header)
        $client_ip = trim(explode(',', $client_ip)[0]);

        if (!rate_limit_check($client_ip)) {
            $locked = true;
            $error  = 'Too many failed attempts. Please wait 15 minutes before trying again.';
        } else {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';

            $result = login($username, $password);
            if ($result === 'ok') {
                rate_limit_clear($client_ip);
                header('Location: ' . $redirect);
                exit;
            } elseif ($result === 'mfa') {
                rate_limit_clear($client_ip);
                // Store redirect destination across the MFA step
                session_start();
                $_SESSION['mfa_redirect'] = $redirect;
                header('Location: mfa_verify.php');
                exit;
            }
            // Credentials invalid — rate_limit_check() already recorded the attempt
            $error = 'Invalid username or password.';
            // Additional artificial delay to slow automated attacks
            usleep(500000); // 0.5 seconds
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Login — <?= h(APP_NAME) ?></title>
<link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<div class="login-page">
  <div class="login-card">
    <h1>&#9881; Admin</h1>
    <p class="subtitle"><?= h(APP_NAME) ?></p>

    <?php if ($error): ?>
    <div class="alert <?= $locked ? 'alert-info' : 'alert-danger' ?>"><?= h($error) ?></div>
    <?php endif; ?>

    <?php if (!$locked): ?>
    <form method="post" autocomplete="off">
      <?= csrf_field() ?>
      <div class="form-group">
        <label class="form-label">Username</label>
        <input type="text" name="username" class="form-control" autofocus
               autocomplete="username" required maxlength="100">
      </div>
      <div class="form-group">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control"
               autocomplete="current-password" required maxlength="200">
      </div>
      <button type="submit" class="btn btn-primary"
              style="width:100%;justify-content:center;margin-top:.5rem">Sign In</button>
    </form>
    <?php endif; ?>

    <p style="text-align:center;margin-top:1.5rem">
      <a href="../index.php" style="font-size:.8rem;color:var(--text-muted)">&#8592; Back to Portal</a>
    </p>
  </div>
</div>
</body>
</html>
