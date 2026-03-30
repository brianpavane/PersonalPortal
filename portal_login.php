<?php
/**
 * Portal Login Page
 * Shown to visitors when portal access control is enabled.
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/version.php';
require_once __DIR__ . '/includes/portal_auth.php';
require_once __DIR__ . '/includes/functions.php';

date_default_timezone_set(TIMEZONE);

// If portal auth is disabled or user is already logged in — skip to portal
if (!portal_auth_enabled() || portal_is_logged_in()) {
    header('Location: index.php');
    exit;
}

// Safe redirect whitelist
$redirect_raw = $_GET['redirect'] ?? 'index.php';
$redirect = basename(preg_replace('/[^a-zA-Z0-9._\-]/', '', $redirect_raw));
if (!in_array($redirect, ['index.php'], true)) {
    $redirect = 'index.php';
}

$error  = '';
$locked = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request token. Please reload and try again.';
    } else {
        $client_ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']
                   ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0')[0]);

        if (!rate_limit_check($client_ip)) {
            $locked = true;
            $error  = 'Too many failed attempts. Please wait 15 minutes before trying again.';
        } else {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $remember = !empty($_POST['remember']);

            if (portal_login($username, $password)) {
                rate_limit_clear($client_ip);
                if ($remember) {
                    portal_set_remember($_SESSION['portal_user_id'], $username);
                }
                header('Location: ' . $redirect);
                exit;
            }
            $error = 'Invalid username or password.';
            usleep(500000);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= h(APP_NAME) ?> — Sign In</title>
<link rel="stylesheet" href="assets/css/style.css">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><text y='26' font-size='28'>🚀</text></svg>">
<style>
  .auth-page {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    background: var(--bg-base);
  }
  .auth-card {
    background: var(--bg-surface);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 2.5rem;
    width: 100%;
    max-width: 380px;
    box-shadow: 0 8px 40px rgba(0,0,0,.5);
  }
  .auth-card h1 { font-size: 1.5rem; color: var(--accent-blue); margin-bottom: .2rem; }
  .auth-subtitle { color: var(--text-muted); font-size: .85rem; margin-bottom: 2rem; }
  .auth-form-group { margin-bottom: 1.1rem; }
  .auth-label { display:block; margin-bottom:.4rem; font-size:.82rem; font-weight:600; color:var(--text-muted); }
  .auth-input {
    width: 100%; padding: .6rem .85rem;
    background: var(--bg-elevated); border: 1px solid var(--border);
    border-radius: 6px; color: var(--text-primary);
    font-size: .95rem; font-family: inherit; outline: none;
  }
  .auth-input:focus { border-color: var(--accent-blue); box-shadow: 0 0 0 3px rgba(88,166,255,.15); }
  .auth-btn {
    width: 100%; padding: .75rem;
    background: #1f6feb; border: 1px solid #388bfd80;
    border-radius: 6px; color: #fff;
    font-size: 1rem; font-weight: 600; cursor: pointer;
    margin-top: .5rem;
  }
  .auth-btn:hover { background: #388bfd; }
  .auth-alert-danger {
    background: #2d1012; border: 1px solid var(--accent-red);
    border-radius: 6px; padding: .75rem 1rem;
    color: var(--accent-red); font-size: .875rem; margin-bottom: 1.25rem;
  }
  .auth-alert-info {
    background: #0c1f3f; border: 1px solid var(--accent-blue);
    border-radius: 6px; padding: .75rem 1rem;
    color: var(--accent-blue); font-size: .875rem; margin-bottom: 1.25rem;
  }
  .remember-row {
    display: flex; align-items: center; gap: .5rem;
    font-size: .85rem; color: var(--text-muted); margin-top: .5rem;
  }
  .remember-row input[type="checkbox"] { accent-color: var(--accent-blue); width:15px; height:15px; }
  .auth-footer { text-align: center; margin-top: 1.5rem; font-size: .78rem; color: var(--text-muted); }
</style>
</head>
<body>
<div class="auth-page">
  <div class="auth-card">
    <h1>&#128640; <?= h(APP_NAME) ?></h1>
    <p class="auth-subtitle">Sign in to access your portal</p>

    <?php if ($error): ?>
    <div class="<?= $locked ? 'auth-alert-info' : 'auth-alert-danger' ?>"><?= h($error) ?></div>
    <?php endif; ?>

    <?php if (!$locked): ?>
    <form method="post" autocomplete="on">
      <?= csrf_field() ?>
      <div class="auth-form-group">
        <label class="auth-label" for="username">Username</label>
        <input type="text" id="username" name="username" class="auth-input"
               autofocus autocomplete="username" required maxlength="100">
      </div>
      <div class="auth-form-group">
        <label class="auth-label" for="password">Password</label>
        <input type="password" id="password" name="password" class="auth-input"
               autocomplete="current-password" required maxlength="200">
      </div>
      <div class="remember-row">
        <input type="checkbox" id="remember" name="remember" value="1">
        <label for="remember">Keep me signed in for 30 days</label>
      </div>
      <button type="submit" class="auth-btn">Sign In</button>
    </form>
    <?php endif; ?>

    <div class="auth-footer">v<?= h(APP_VERSION) ?></div>
  </div>
</div>
</body>
</html>
