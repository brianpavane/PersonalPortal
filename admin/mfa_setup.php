<?php
/**
 * MFA Setup — enable or disable TOTP two-factor authentication.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/totp.php';
require_login();

$db      = db();
$msg     = '';
$errs    = [];
$enabled = TOTP::isEnabled($db);

// ── Generate a pending secret for the setup form ──────────────────────────────
// We keep a pending (unconfirmed) secret in the session until the user
// successfully verifies a code from their app.  This way we never persist
// a secret that the user can't actually scan.

if ($_SERVER['REQUEST_METHOD'] === 'GET' && !$enabled) {
    // Fresh GET: generate a new pending secret for display
    if (empty($_SESSION['totp_pending_secret'])) {
        $_SESSION['totp_pending_secret'] = TOTP::generateSecret();
    }
}

// ── Handle POST actions ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token.');
    }

    $action = $_POST['action'] ?? '';

    // ── Enable: user submits verification code ────────────────────────────────
    if ($action === 'enable') {
        $pending = $_SESSION['totp_pending_secret'] ?? '';
        $code    = preg_replace('/\s+/', '', $_POST['code'] ?? '');

        if (!$pending) {
            $errs[] = 'No pending secret found. Please reload.';
        } elseif (!preg_match('/^\d{6}$/', $code)) {
            $errs[] = 'Please enter a valid 6-digit code.';
        } elseif (!TOTP::verify($pending, $code)) {
            $errs[] = 'Code did not match. Make sure your device clock is correct and try again.';
        } else {
            TOTP::enable($db, $pending);
            unset($_SESSION['totp_pending_secret']);
            $enabled = true;
            $msg     = 'Two-factor authentication is now enabled.';
        }
    }

    // ── Disable: requires current password confirmation ───────────────────────
    if ($action === 'disable') {
        $password = $_POST['password'] ?? '';
        $stmt     = $db->prepare("SELECT setting_value FROM portal_settings WHERE setting_key='admin_password_hash'");
        $stmt->execute();
        $hash = $stmt->fetchColumn();

        if (!$hash || !password_verify($password, (string)$hash)) {
            $errs[] = 'Incorrect password. MFA has not been disabled.';
        } else {
            TOTP::disable($db);
            unset($_SESSION['totp_pending_secret']);
            $enabled = false;
            $msg     = 'Two-factor authentication has been disabled.';
        }
    }
}

// ── Data for the setup form ───────────────────────────────────────────────────
$pending_secret = '';
$otpauth_uri    = '';

if (!$enabled) {
    if (empty($_SESSION['totp_pending_secret'])) {
        $_SESSION['totp_pending_secret'] = TOTP::generateSecret();
    }
    $pending_secret = $_SESSION['totp_pending_secret'];

    // Issuer = site hostname; account = admin username
    $issuer         = parse_url(APP_URL, PHP_URL_HOST) ?: APP_NAME;
    $account        = $_SESSION['admin_user'] ?? 'admin';
    $otpauth_uri    = TOTP::otpauthUri($pending_secret, $issuer, $account);
}

$page_title = 'Two-Factor Authentication';
$active_nav = 'mfa';
include __DIR__ . '/_layout.php';
?>

<div class="page-header">
  <div class="page-title">Two-Factor Authentication
    <small>TOTP — compatible with Authy, Google Authenticator, 1Password &amp; others</small>
  </div>
</div>

<?php if ($msg):  ?><div class="alert alert-success"><?= h($msg) ?></div><?php endif; ?>
<?php if ($errs): ?><div class="alert alert-danger"><?= implode('<br>', array_map('h', $errs)) ?></div><?php endif; ?>

<?php if ($enabled): ?>
<!-- ── MFA is ON ──────────────────────────────────────────────────────────── -->
<div class="card" style="max-width:540px">
  <div class="card-header">
    <span style="color:var(--accent-green)">&#10003; MFA Enabled</span>
  </div>
  <div class="card-body">
    <p style="margin-bottom:1.25rem;color:var(--text-muted)">
      Your admin account is protected by two-factor authentication.
      Each login requires your password <em>and</em> a 6-digit code from your authenticator app.
    </p>

    <div class="alert alert-info" style="margin-bottom:1.25rem">
      &#9888; Disabling MFA requires your current password.
    </div>

    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="disable">
      <div class="form-group">
        <label class="form-label">Confirm Current Password to Disable</label>
        <input type="password" name="password" class="form-control"
               autocomplete="current-password" required>
      </div>
      <button type="submit" class="btn btn-danger"
              onclick="return confirm('Disable two-factor authentication? Your account will be less secure.')">
        Disable MFA
      </button>
    </form>
  </div>
</div>

<?php else: ?>
<!-- ── MFA setup ──────────────────────────────────────────────────────────── -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;align-items:start">

  <!-- Left: instructions -->
  <div class="card">
    <div class="card-header">Setup Instructions</div>
    <div class="card-body">
      <ol style="padding-left:1.3rem;color:var(--text-muted);font-size:.875rem;line-height:1.8">
        <li>Install <strong style="color:var(--text-primary)">Authy</strong> on your phone
            (or any TOTP app: Google Authenticator, 1Password, Bitwarden, etc.)</li>
        <li>In Authy, tap <em>Add Account</em> and scan the QR code on the right,
            <strong>or</strong> enter the secret key manually</li>
        <li>Enter the 6-digit code your app shows below to confirm the setup</li>
      </ol>

      <hr style="border-color:var(--border-subtle);margin:1.25rem 0">

      <!-- Manual entry fallback -->
      <p style="font-size:.82rem;color:var(--text-muted);margin-bottom:.5rem">
        <strong style="color:var(--text-primary)">Manual entry key</strong> (use if QR doesn't scan):
      </p>
      <div style="display:flex;align-items:center;gap:.5rem">
        <code id="secret-display"
              style="background:var(--bg-base);border:1px solid var(--border);border-radius:6px;
                     padding:.5rem .75rem;font-size:.9rem;letter-spacing:.12em;flex:1;word-break:break-all">
          <?= h(chunk_split($pending_secret, 4, ' ')) ?>
        </code>
        <button onclick="copySecret()" class="btn btn-secondary btn-sm" title="Copy secret">&#128203;</button>
      </div>
      <p style="font-size:.75rem;color:var(--text-muted);margin-top:.4rem">
        Time-based (TOTP) &bull; SHA-1 &bull; 6 digits &bull; 30-second period
      </p>

      <hr style="border-color:var(--border-subtle);margin:1.25rem 0">

      <!-- Confirm form -->
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="enable">
        <div class="form-group">
          <label class="form-label">Enter 6-digit code from your app to confirm</label>
          <input type="text" name="code" class="form-control"
                 inputmode="numeric" pattern="\d{6}" maxlength="6"
                 autocomplete="one-time-code" placeholder="000000"
                 style="font-size:1.4rem;letter-spacing:.3em;text-align:center;font-family:monospace"
                 required>
        </div>
        <button type="submit" class="btn btn-success">Activate MFA</button>
      </form>
    </div>
  </div>

  <!-- Right: QR code -->
  <div class="card">
    <div class="card-header">Scan QR Code</div>
    <div class="card-body" style="text-align:center">
      <div id="qrcode" style="display:inline-block;background:#fff;padding:16px;border-radius:8px;margin-bottom:1rem"></div>
      <p style="font-size:.8rem;color:var(--text-muted)">
        Scan with Authy or your authenticator app.<br>
        QR code is generated locally in your browser — the secret never leaves this page.
      </p>
      <p style="font-size:.75rem;color:var(--text-muted);margin-top:.75rem">
        <strong>otpauth URI</strong> (advanced):<br>
        <span id="otpauth-uri" style="font-size:.7rem;word-break:break-all;opacity:.6"><?= h($otpauth_uri) ?></span>
      </p>
    </div>
  </div>

</div><!-- /grid -->
<?php endif; ?>

<!-- QR code library (loaded only on setup page, SRI verified) -->
<?php if (!$enabled): ?>
<script
  src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"
  integrity="sha512-CNgIRecGo7nphbeZ04Sc13ka07paqdeTu0WR1IM4kNcpmBAUSHSi2jIQCFoSW2R5JV1dFd5ks7M/PrCr7TQaQ=="
  crossorigin="anonymous"
  referrerpolicy="no-referrer"></script>
<script>
(function() {
  var uri = <?= json_encode($otpauth_uri) ?>;
  var el  = document.getElementById('qrcode');
  if (el && typeof QRCode !== 'undefined') {
    new QRCode(el, {
      text:           uri,
      width:          220,
      height:         220,
      colorDark:      '#000000',
      colorLight:     '#ffffff',
      correctLevel:   QRCode.CorrectLevel.M
    });
  } else if (el) {
    el.innerHTML = '<p style="color:#666;font-size:.85rem">QR library failed to load.<br>Use the manual key instead.</p>';
  }

  // Auto-format code input as user types
  var codeInput = document.querySelector('input[name="code"]');
  if (codeInput) {
    codeInput.addEventListener('input', function() {
      this.value = this.value.replace(/\D/g, '').slice(0, 6);
    });
  }
})();

function copySecret() {
  var raw = <?= json_encode($pending_secret) ?>;
  navigator.clipboard.writeText(raw).then(function() {
    var btn = event.target;
    btn.textContent = '✓';
    setTimeout(function() { btn.textContent = '📋'; }, 1500);
  }).catch(function() {
    // Fallback: select the text
    var el = document.getElementById('secret-display');
    var range = document.createRange();
    range.selectNode(el);
    window.getSelection().removeAllRanges();
    window.getSelection().addRange(range);
  });
}
</script>
<?php endif; ?>

<?php include __DIR__ . '/_layout_end.php'; ?>
