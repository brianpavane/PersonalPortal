<?php
/**
 * MFA verification — shown after successful password login when MFA is enabled.
 * User must supply their current Authy/authenticator 6-digit code.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/totp.php';

// Must be in MFA-pending state to access this page
if (!is_mfa_pending()) {
    header('Location: index.php');
    exit;
}

// Already fully logged in — skip ahead
if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$error  = '';
$locked = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request token. Please reload.';
    } else {
        // Rate-limit TOTP attempts by IP (shares the same rate-limit store)
        $client_ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']
                   ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0')[0]);

        if (!rate_limit_check($client_ip)) {
            $locked = true;
            $error  = 'Too many failed attempts. Please wait 15 minutes.';
        } else {
            $code = preg_replace('/\s+/', '', $_POST['code'] ?? '');
            $db   = db();

            $secret = TOTP::getPlainSecret($db);
            if ($secret && TOTP::verify($secret, $code)) {
                rate_limit_clear($client_ip);
                mfa_complete();   // elevates session to fully authenticated
                $redirect = $_SESSION['mfa_redirect'] ?? 'dashboard.php';
                unset($_SESSION['mfa_redirect']);

                // Whitelist the stored redirect too
                $allowed = ['dashboard.php','bookmarks.php','categories.php',
                            'notes.php','settings.php','password.php','mfa_setup.php'];
                if (!in_array($redirect, $allowed, true)) $redirect = 'dashboard.php';

                header('Location: ' . $redirect);
                exit;
            }

            $error = 'Invalid code. Please try again.';
            usleep(500000);  // 0.5 s delay for wrong codes
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Two-Factor Authentication — <?= h(APP_NAME) ?></title>
<link rel="stylesheet" href="../assets/css/admin.css">
<style>
  .mfa-icon { font-size: 2.5rem; text-align: center; margin-bottom: .5rem; }
  .code-input {
    font-size: 1.8rem;
    letter-spacing: .35em;
    text-align: center;
    font-variant-numeric: tabular-nums;
    font-family: monospace;
    padding: .6rem .5rem;
  }
  .hint { font-size: .8rem; color: var(--text-muted); text-align: center; margin-top: .75rem; }
</style>
</head>
<body>
<div class="login-page">
  <div class="login-card">
    <div class="mfa-icon">&#128272;</div>
    <h1 style="text-align:center">Two-Factor Auth</h1>
    <p class="subtitle" style="text-align:center">Enter the 6-digit code from your authenticator app</p>

    <?php if ($error): ?>
    <div class="alert <?= $locked ? 'alert-info' : 'alert-danger' ?>"><?= h($error) ?></div>
    <?php endif; ?>

    <?php if (!$locked): ?>
    <form method="post" autocomplete="off" id="mfa-form">
      <?= csrf_field() ?>
      <div class="form-group">
        <input type="text" name="code" class="form-control code-input"
               inputmode="numeric" pattern="\d{6}" maxlength="6"
               autofocus autocomplete="one-time-code"
               placeholder="000000" required>
      </div>
      <button type="submit" class="btn btn-primary"
              style="width:100%;justify-content:center">Verify</button>
    </form>
    <p class="hint">Open Authy or your authenticator app and enter the 6-digit code shown for <strong><?= h(APP_NAME) ?></strong>.</p>
    <?php endif; ?>

    <p style="text-align:center;margin-top:1.25rem">
      <a href="index.php" style="font-size:.8rem;color:var(--text-muted)">&#8592; Back to login</a>
    </p>
  </div>
</div>
<script>
  // Auto-submit when 6 digits entered
  const inp = document.querySelector('input[name="code"]');
  if (inp) {
    inp.addEventListener('input', () => {
      const v = inp.value.replace(/\D/g, '').slice(0, 6);
      inp.value = v;
      if (v.length === 6) document.getElementById('mfa-form').submit();
    });
  }
</script>
</body>
</html>
