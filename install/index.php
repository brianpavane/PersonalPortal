<?php
/**
 * PersonalPortal — Installation Wizard
 * DELETE this directory after setup is complete!
 */

define('INSTALL_MODE', true);

$step   = (int)($_GET['step'] ?? 1);
$errors = [];
$success = false;

$lock_file = __DIR__ . '/installed.lock';
if (file_exists($lock_file)) {
    // Show nothing useful to a potential attacker
    http_response_code(404);
    die('Not found.');
}

// Valid PHP timezones for whitelist validation
$valid_timezones = array_flip(DateTimeZone::listIdentifiers());

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 2) {
    // ── Read & validate inputs ────────────────────────────────────────────────
    $db_host  = trim($_POST['db_host']  ?? '');
    $db_name  = trim($_POST['db_name']  ?? '');
    $db_user  = trim($_POST['db_user']  ?? '');
    $db_pass  = $_POST['db_pass']  ?? '';
    $app_url  = rtrim(trim($_POST['app_url'] ?? ''), '/');
    $timezone = trim($_POST['timezone'] ?? 'America/New_York');
    $adm_user = trim($_POST['adm_user'] ?? 'admin');
    $adm_pass = $_POST['adm_pass']  ?? '';
    $adm_pass2= $_POST['adm_pass2'] ?? '';

    // Generate a cryptographically random secret key (32 bytes = 64 hex chars)
    $secret = bin2hex(random_bytes(32));

    // Validate DB hostname: only safe chars, no semicolons (DSN injection)
    if (!$db_host) {
        $errors[] = 'Database host is required.';
    } elseif (!preg_match('/^[a-zA-Z0-9.\-]+$/', $db_host)) {
        $errors[] = 'Database host contains invalid characters.';
    }
    if (!$db_name) $errors[] = 'Database name is required.';
    if (!$db_user) $errors[] = 'Database username is required.';

    // Validate App URL: must be http or https
    if (!$app_url) {
        $errors[] = 'Application URL is required.';
    } elseif (!filter_var($app_url, FILTER_VALIDATE_URL)
              || !in_array(strtolower((string)parse_url($app_url, PHP_URL_SCHEME)), ['http','https'], true)) {
        $errors[] = 'Application URL must start with http:// or https://.';
    }

    // Validate timezone against PHP's known list
    if (!isset($valid_timezones[$timezone])) {
        $errors[] = 'Invalid timezone selected.';
        $timezone = 'UTC';
    }

    if (!$adm_user) $errors[] = 'Admin username is required.';
    if (!preg_match('/^[a-zA-Z0-9_.\-]{3,50}$/', $adm_user)) {
        $errors[] = 'Admin username: 3–50 alphanumeric characters only.';
    }
    if (strlen($adm_pass) < 8)     $errors[] = 'Admin password must be at least 8 characters.';
    if ($adm_pass !== $adm_pass2)  $errors[] = 'Passwords do not match.';

    // ── Database connection test ──────────────────────────────────────────────
    if (!$errors) {
        try {
            $dsn = 'mysql:host=' . $db_host . ';dbname=' . $db_name . ';charset=utf8mb4';
            $pdo = new PDO($dsn, $db_user, $db_pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // Sanitize error message — don't expose credentials in output
            $errors[] = 'Database connection failed. Check hostname, name, user and password.';
        }
    }

    // ── Import schema ─────────────────────────────────────────────────────────
    if (!$errors) {
        $sql_raw    = file_get_contents(__DIR__ . '/schema.sql');
        $statements = array_filter(array_map('trim', explode(';', $sql_raw)));
        foreach ($statements as $stmt) {
            if ($stmt && !preg_match('/^--/', $stmt) && !preg_match('/^SET\s+NAMES/i', $stmt)) {
                try {
                    $pdo->exec($stmt);
                } catch (PDOException $e) {
                    // 42S01 = table already exists (safe to ignore)
                    if ((string)$e->getCode() !== '42S01') {
                        $errors[] = 'Schema import error — check DB user privileges.';
                        break;
                    }
                }
            }
        }
    }

    // ── Seed data & write config ──────────────────────────────────────────────
    if (!$errors) {
        // Admin credentials go into DB, not config file
        $hash = password_hash($adm_pass, PASSWORD_BCRYPT, ['cost' => 12]);
        foreach ([
            ['admin_username',      $adm_user],
            ['admin_password_hash', $hash],
        ] as [$k, $v]) {
            $pdo->prepare('REPLACE INTO portal_settings (setting_key, setting_value) VALUES (?,?)')
                ->execute([$k, $v]);
        }

        // Insert default news feeds (hardcoded — no dependency on config constants)
        $default_feeds = [
            ['Reuters',     'https://feeds.reuters.com/reuters/topNews'],
            ['BBC News',    'https://feeds.bbci.co.uk/news/rss.xml'],
            ['Hacker News', 'https://news.ycombinator.com/rss'],
        ];
        $feed_stmt = $pdo->prepare('INSERT IGNORE INTO news_feeds (name,url,sort_order) VALUES (?,?,?)');
        foreach ($default_feeds as $i => [$fname, $furl]) {
            try { $feed_stmt->execute([$fname, $furl, $i + 1]); } catch (PDOException $e) {}
        }

        // ── Write config.php using var_export() to safely escape ALL values ──
        // IMPORTANT: Never use string interpolation (heredoc) for user-supplied
        // values in generated PHP files — it allows code injection.
        $config_lines = [
            '<?php',
            '// Auto-generated by PersonalPortal installer — do not edit manually',
            '// To change settings, edit this file carefully or re-run the installer.',
            '',
            "define('DB_HOST',    " . var_export($db_host,  true) . ');',
            "define('DB_NAME',    " . var_export($db_name,  true) . ');',
            "define('DB_USER',    " . var_export($db_user,  true) . ');',
            "define('DB_PASS',    " . var_export($db_pass,  true) . ');',
            "define('DB_CHARSET', 'utf8mb4');",
            '',
            "define('APP_NAME',   'Personal Portal');",
            "define('APP_URL',    " . var_export($app_url,  true) . ');',
            "define('TIMEZONE',   " . var_export($timezone, true) . ');',
            '',
            "define('SESSION_NAME',     'portal_session');",
            "define('SESSION_LIFETIME', 3600);",
            '',
            "define('ALPHAVANTAGE_KEY', '');",
            "define('SECRET_KEY', "    . var_export($secret,   true) . ');',
            '',
            "define('STOCK_CACHE_TTL',  300);",
            "define('NEWS_CACHE_TTL',   600);",
            "define('CACHE_DIR', __DIR__ . '/../cache');",
        ];

        $config_path = __DIR__ . '/../config/config.php';
        if (file_put_contents($config_path, implode("\n", $config_lines) . "\n") === false) {
            $errors[] = 'Could not write config file. Check directory permissions.';
        }
    }

    // ── Post-write setup ──────────────────────────────────────────────────────
    if (!$errors) {
        // Create and protect the cache directory
        $cache_dir = __DIR__ . '/../cache';
        if (!is_dir($cache_dir)) @mkdir($cache_dir, 0750, true);
        if (!file_exists($cache_dir . '/.htaccess')) {
            file_put_contents($cache_dir . '/.htaccess', "Require all denied\n");
        }

        // Write lock file — prevents re-running the installer
        file_put_contents($lock_file, date('c') . "\n");
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>PersonalPortal — Installation</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;
     background:#0d1117;color:#c9d1d9;min-height:100vh;display:flex;
     align-items:center;justify-content:center;padding:2rem}
.card{background:#161b22;border:1px solid #30363d;border-radius:12px;
      padding:2.5rem;width:100%;max-width:560px}
h1{color:#58a6ff;font-size:1.6rem;margin-bottom:.25rem}
.subtitle{color:#8b949e;margin-bottom:2rem;font-size:.9rem}
.form-group{margin-bottom:1.2rem}
label{display:block;margin-bottom:.4rem;font-size:.875rem;color:#8b949e;font-weight:500}
input,select{width:100%;padding:.65rem .85rem;background:#0d1117;border:1px solid #30363d;
             border-radius:6px;color:#c9d1d9;font-size:.95rem;outline:none}
input:focus,select:focus{border-color:#58a6ff}
.btn{width:100%;padding:.8rem;background:#238636;border:none;border-radius:6px;
     color:#fff;font-size:1rem;font-weight:600;cursor:pointer;margin-top:.5rem}
.btn:hover{background:#2ea043}
.errors{background:#2d1012;border:1px solid #f85149;border-radius:6px;
        padding:1rem;margin-bottom:1.5rem}
.errors li{color:#f85149;font-size:.875rem;margin-left:1rem;margin-bottom:.2rem}
.success{background:#0d2119;border:1px solid #3fb950;border-radius:6px;padding:1.5rem}
.success h2{color:#3fb950;margin-bottom:.75rem}
.success a{color:#58a6ff}
.section-title{color:#8b949e;font-size:.75rem;text-transform:uppercase;
               letter-spacing:.1em;margin:1.5rem 0 1rem;border-bottom:1px solid #30363d;padding-bottom:.5rem}
.warning{background:#2d1f00;border:1px solid #f0883e;border-radius:6px;
         padding:.75rem 1rem;margin-bottom:1.5rem;font-size:.85rem;color:#f0883e}
code{background:#0d1117;padding:.1rem .35rem;border-radius:3px;font-size:.85em}
ol{margin:.75rem 0 0 1.5rem}
li{margin:.35rem 0}
</style>
</head>
<body>
<div class="card">
  <h1>&#128640; PersonalPortal</h1>
  <p class="subtitle">Installation Wizard</p>

  <?php if ($success): ?>
  <div class="success">
    <h2>&#10003; Installation Complete!</h2>
    <p style="margin:.5rem 0;color:#c9d1d9">Your portal has been configured successfully.</p>
    <ol style="color:#c9d1d9;font-size:.9rem">
      <li><strong style="color:#f85149">Delete the <code>/install/</code> directory immediately</strong></li>
      <li>Visit <a href="<?= htmlspecialchars($app_url ?? '', ENT_QUOTES) ?>">your portal</a></li>
      <li>Log into the <a href="<?= htmlspecialchars(($app_url ?? '') . '/admin/', ENT_QUOTES) ?>">Admin Panel</a></li>
    </ol>
  </div>

  <?php elseif ($step === 1): ?>
  <div class="warning">&#9888; After setup, <strong>delete <code>/install/</code></strong> to prevent re-configuration by anyone with web access.</div>
  <p style="color:#c9d1d9;margin-bottom:1.5rem">This wizard configures your database connection and creates an admin account.</p>
  <a href="?step=2" style="display:block;text-align:center;padding:.8rem;background:#238636;
     border-radius:6px;color:#fff;text-decoration:none;font-weight:600">Begin Setup &rarr;</a>

  <?php else: ?>
  <?php if ($errors): ?>
  <ul class="errors">
    <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e, ENT_QUOTES) ?></li><?php endforeach; ?>
  </ul>
  <?php endif; ?>

  <form method="post" action="?step=2">
    <div class="section-title">Database Connection</div>
    <div class="form-group">
      <label>MySQL Hostname</label>
      <input name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? 'mysql.yourdomain.com', ENT_QUOTES) ?>"
             placeholder="mysql.yourdomain.com" required pattern="[a-zA-Z0-9.\-]+">
    </div>
    <div class="form-group">
      <label>Database Name</label>
      <input name="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? '', ENT_QUOTES) ?>" required>
    </div>
    <div class="form-group">
      <label>Database Username</label>
      <input name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? '', ENT_QUOTES) ?>" required>
    </div>
    <div class="form-group">
      <label>Database Password</label>
      <input type="password" name="db_pass" autocomplete="new-password">
    </div>

    <div class="section-title">Application Settings</div>
    <div class="form-group">
      <label>Site URL (no trailing slash)</label>
      <input name="app_url" type="url"
             value="<?= htmlspecialchars($_POST['app_url'] ?? 'https://', ENT_QUOTES) ?>" required>
    </div>
    <div class="form-group">
      <label>Timezone</label>
      <select name="timezone">
        <?php foreach (DateTimeZone::listIdentifiers() as $tz): ?>
        <option value="<?= htmlspecialchars($tz, ENT_QUOTES) ?>"
          <?= (($_POST['timezone'] ?? 'America/New_York') === $tz) ? 'selected' : '' ?>>
          <?= htmlspecialchars($tz, ENT_QUOTES) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="section-title">Admin Account</div>
    <div class="form-group">
      <label>Admin Username</label>
      <input name="adm_user" value="<?= htmlspecialchars($_POST['adm_user'] ?? 'admin', ENT_QUOTES) ?>"
             required pattern="[a-zA-Z0-9_.\-]{3,50}">
    </div>
    <div class="form-group">
      <label>Admin Password (min 8 characters)</label>
      <input type="password" name="adm_pass" required minlength="8" autocomplete="new-password">
    </div>
    <div class="form-group">
      <label>Confirm Password</label>
      <input type="password" name="adm_pass2" required autocomplete="new-password">
    </div>
    <button type="submit" class="btn">Install PersonalPortal</button>
  </form>
  <?php endif; ?>
</div>
</body>
</html>
