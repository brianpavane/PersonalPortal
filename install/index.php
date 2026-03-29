<?php
/**
 * PersonalPortal — Installation Wizard
 * DELETE or rename this directory after setup is complete!
 */

define('INSTALL_MODE', true);

$step    = (int)($_GET['step'] ?? 1);
$errors  = [];
$success = false;

// Check if already installed
$config_exists = file_exists(__DIR__ . '/../config/config.php');
$lock_file     = __DIR__ . '/installed.lock';

if (file_exists($lock_file)) {
    die('<h2>Already installed.</h2><p>Delete <code>install/installed.lock</code> to re-run setup.</p>');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 2) {
    $db_host  = trim($_POST['db_host']  ?? '');
    $db_name  = trim($_POST['db_name']  ?? '');
    $db_user  = trim($_POST['db_user']  ?? '');
    $db_pass  = $_POST['db_pass']  ?? '';
    $app_url  = rtrim(trim($_POST['app_url']  ?? ''), '/');
    $timezone = trim($_POST['timezone'] ?? 'America/New_York');
    $secret   = bin2hex(random_bytes(16));
    $adm_user = trim($_POST['adm_user'] ?? 'admin');
    $adm_pass = $_POST['adm_pass'] ?? '';
    $adm_pass2= $_POST['adm_pass2'] ?? '';

    if (!$db_host)  $errors[] = 'Database host is required.';
    if (!$db_name)  $errors[] = 'Database name is required.';
    if (!$db_user)  $errors[] = 'Database user is required.';
    if (!$app_url)  $errors[] = 'Application URL is required.';
    if (!$adm_user) $errors[] = 'Admin username is required.';
    if (strlen($adm_pass) < 8) $errors[] = 'Admin password must be at least 8 characters.';
    if ($adm_pass !== $adm_pass2) $errors[] = 'Passwords do not match.';

    if (!$errors) {
        // Test DB connection
        try {
            $dsn = "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4";
            $pdo = new PDO($dsn, $db_user, $db_pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        } catch (PDOException $e) {
            $errors[] = 'Database connection failed: ' . $e->getMessage();
        }
    }

    if (!$errors) {
        // Import schema
        $sql = file_get_contents(__DIR__ . '/schema.sql');
        // Split on semicolons (rough but works for our controlled schema)
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($statements as $stmt) {
            if ($stmt) {
                try { $pdo->exec($stmt); } catch (PDOException $e) {
                    if ($e->getCode() != '42S01') { // ignore "table already exists"
                        $errors[] = 'Schema error: ' . $e->getMessage();
                        break;
                    }
                }
            }
        }
    }

    if (!$errors) {
        // Save admin credentials & seed settings
        $hash = password_hash($adm_pass, PASSWORD_BCRYPT);
        $settings = [
            ['admin_username',    $adm_user],
            ['admin_password_hash', $hash],
        ];
        // Insert default news feeds
        $feeds = unserialize(constant('DEFAULT_NEWS_FEEDS') ?: serialize([]));
        foreach ($feeds as $i => $f) {
            try {
                $pdo->prepare('INSERT IGNORE INTO news_feeds (name,url,sort_order) VALUES (?,?,?)')
                    ->execute([$f['name'], $f['url'], $i + 1]);
            } catch (PDOException $e) {}
        }
        foreach ($settings as [$k, $v]) {
            $pdo->prepare('REPLACE INTO portal_settings (setting_key, setting_value) VALUES (?,?)')
                ->execute([$k, $v]);
        }

        // Write config file
        $config_content = <<<PHP
<?php
define('DB_HOST',    '{$db_host}');
define('DB_NAME',    '{$db_name}');
define('DB_USER',    '{$db_user}');
define('DB_PASS',    '{$db_pass}');
define('DB_CHARSET', 'utf8mb4');
define('APP_NAME',   'Personal Portal');
define('APP_URL',    '{$app_url}');
define('TIMEZONE',   '{$timezone}');
define('SESSION_NAME',     'portal_session');
define('SESSION_LIFETIME', 3600);
define('ALPHAVANTAGE_KEY', '');
define('DEFAULT_NEWS_FEEDS', '');
define('SECRET_KEY', '{$secret}');
define('STOCK_CACHE_TTL',  300);
define('NEWS_CACHE_TTL',   600);
define('CACHE_DIR', __DIR__ . '/../cache');
PHP;
        // Prepend PHP open tag properly
        file_put_contents(__DIR__ . '/../config/config.php', $config_content);

        // Create cache dir
        $cache_dir = __DIR__ . '/../cache';
        if (!is_dir($cache_dir)) mkdir($cache_dir, 0750, true);
        file_put_contents($cache_dir . '/.htaccess', "Deny from all\n");

        // Write lock file
        file_put_contents($lock_file, date('c'));

        $success = true;
    }
}

// Load timezones list for select
$timezones = DateTimeZone::listIdentifiers();
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
.errors li{color:#f85149;font-size:.875rem;margin-left:1rem}
.success{background:#0d2119;border:1px solid #3fb950;border-radius:6px;
         padding:1.5rem;text-align:center}
.success h2{color:#3fb950;margin-bottom.75rem}
.success a{color:#58a6ff}
.section-title{color:#8b949e;font-size:.75rem;text-transform:uppercase;
               letter-spacing:.1em;margin:1.5rem 0 1rem;border-bottom:1px solid #30363d;padding-bottom:.5rem}
.warning{background:#2d1f00;border:1px solid #f0883e;border-radius:6px;
         padding:.75rem 1rem;margin-bottom:1.5rem;font-size:.85rem;color:#f0883e}
</style>
</head>
<body>
<div class="card">
  <h1>&#128640; PersonalPortal</h1>
  <p class="subtitle">Installation Wizard — Step <?= $step ?> of 2</p>

  <?php if ($success): ?>
  <div class="success">
    <h2>&#10003; Installation Complete!</h2>
    <p style="margin:.75rem 0;color:#c9d1d9">Your portal has been configured successfully.</p>
    <p style="margin:.5rem 0"><strong>Next steps:</strong></p>
    <ol style="text-align:left;margin:1rem 0 1rem 1.5rem;color:#c9d1d9;font-size:.9rem">
      <li>Delete or rename the <code>/install/</code> directory</li>
      <li>Visit <a href="<?= h($app_url ?? '') ?>">your portal</a></li>
      <li>Log in at <a href="<?= h(($app_url ?? '') . '/admin/') ?>">Admin Panel</a> to add bookmarks</li>
    </ol>
  </div>
  <?php elseif ($step === 1): ?>
  <div class="warning">&#9888; Complete setup then <strong>delete the /install/ directory</strong> to prevent re-configuration.</div>
  <p style="color:#c9d1d9;margin-bottom:1.5rem">Welcome! This wizard will configure your database connection and create an admin account.</p>
  <a href="?step=2" style="display:block;text-align:center;padding:.8rem;background:#238636;
     border-radius:6px;color:#fff;text-decoration:none;font-weight:600">Begin Setup &rarr;</a>
  <?php else: ?>
  <?php if ($errors): ?>
  <ul class="errors">
    <?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
  </ul>
  <?php endif; ?>
  <form method="post" action="?step=2">
    <div class="section-title">Database Connection</div>
    <div class="form-group">
      <label>MySQL Hostname</label>
      <input name="db_host" value="<?= h($_POST['db_host'] ?? 'mysql.yourdomain.com') ?>" placeholder="mysql.yourdomain.com" required>
    </div>
    <div class="form-group">
      <label>Database Name</label>
      <input name="db_name" value="<?= h($_POST['db_name'] ?? '') ?>" required>
    </div>
    <div class="form-group">
      <label>Database Username</label>
      <input name="db_user" value="<?= h($_POST['db_user'] ?? '') ?>" required>
    </div>
    <div class="form-group">
      <label>Database Password</label>
      <input type="password" name="db_pass">
    </div>
    <div class="section-title">Application Settings</div>
    <div class="form-group">
      <label>Site URL (no trailing slash)</label>
      <input name="app_url" value="<?= h($_POST['app_url'] ?? 'https://') ?>" required>
    </div>
    <div class="form-group">
      <label>Timezone</label>
      <select name="timezone">
        <?php foreach ($timezones as $tz): ?>
        <option value="<?= h($tz) ?>" <?= (($_POST['timezone'] ?? 'America/New_York') === $tz) ? 'selected' : '' ?>><?= h($tz) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="section-title">Admin Account</div>
    <div class="form-group">
      <label>Admin Username</label>
      <input name="adm_user" value="<?= h($_POST['adm_user'] ?? 'admin') ?>" required>
    </div>
    <div class="form-group">
      <label>Admin Password (min 8 chars)</label>
      <input type="password" name="adm_pass" required minlength="8">
    </div>
    <div class="form-group">
      <label>Confirm Password</label>
      <input type="password" name="adm_pass2" required>
    </div>
    <button type="submit" class="btn">Install PersonalPortal</button>
  </form>
  <?php endif; ?>
</div>
</body>
</html>
