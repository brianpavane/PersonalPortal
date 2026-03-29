<?php
/**
 * Authentication helpers.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';

function start_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        // Harden PHP session settings before starting
        ini_set('session.use_strict_mode',   '1');
        ini_set('session.use_only_cookies',  '1');
        ini_set('session.gc_maxlifetime',    (string)SESSION_LIFETIME);
        ini_set('session.cookie_httponly',   '1');
        ini_set('session.cookie_samesite',   'Strict');

        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => 0,        // Session cookie — expires when browser closes
            'path'     => '/',
            'secure'   => true,
            'httponly' => true,
            'samesite' => 'Strict', // Strict: cookie not sent on any cross-site request
        ]);
        session_start();
    }
}

function is_logged_in(): bool {
    start_session();
    return !empty($_SESSION['admin_logged_in'])
        && !empty($_SESSION['admin_user'])
        && !empty($_SESSION['login_time'])
        && (time() - $_SESSION['login_time']) < SESSION_LIFETIME;
}

function require_login(): void {
    if (!is_logged_in()) {
        // Build a safe redirect back-URL: only allow own admin pages
        $back = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '');
        header('Location: ' . APP_URL . '/admin/index.php?redirect=' . urlencode($back));
        exit;
    }
    // Regenerate session ID every 5 minutes to limit session fixation window
    if (empty($_SESSION['last_regen']) || (time() - $_SESSION['last_regen']) > 300) {
        session_regenerate_id(true);
        $_SESSION['last_regen'] = time();
    }
}

/**
 * Validate credentials and establish session.
 *
 * Returns:
 *   'ok'  — credentials valid, MFA not enabled, fully logged in.
 *   'mfa' — credentials valid, MFA required; session set to pending state.
 *   false — credentials invalid.
 */
function login(string $username, string $password): string|false
{
    require_once __DIR__ . '/totp.php';

    $db   = db();
    $stmt = $db->prepare('SELECT setting_value FROM portal_settings WHERE setting_key = ?');

    $stmt->execute(['admin_username']);
    $stored_user = $stmt->fetchColumn();

    $stmt->execute(['admin_password_hash']);
    $stored_hash = $stmt->fetchColumn();

    if (!$stored_user || !$stored_hash
        || !hash_equals((string)$stored_user, $username)
        || !password_verify($password, (string)$stored_hash)) {
        return false;
    }

    start_session();
    session_regenerate_id(true);

    if (TOTP::isEnabled($db)) {
        // Credentials valid but MFA not yet verified — set limited pending state.
        // is_logged_in() will return false until mfa_complete() is called.
        $_SESSION['mfa_pending']   = true;
        $_SESSION['mfa_user']      = $username;
        $_SESSION['mfa_issued_at'] = time();  // used to expire the pending window
        return 'mfa';
    }

    // No MFA — complete login immediately
    _complete_login($username);
    return 'ok';
}

/** Called after successful TOTP verification to fully establish the session. */
function mfa_complete(): void
{
    start_session();
    $username = $_SESSION['mfa_user'] ?? '';
    // Clear pending state before elevating privileges
    unset($_SESSION['mfa_pending'], $_SESSION['mfa_user'], $_SESSION['mfa_issued_at']);
    session_regenerate_id(true);
    _complete_login($username);
}

/** Returns true if we are in the MFA-pending state (password ok, code not yet verified). */
function is_mfa_pending(): bool
{
    start_session();
    if (empty($_SESSION['mfa_pending']) || empty($_SESSION['mfa_issued_at'])) return false;
    // Expire the pending window after 5 minutes
    if ((time() - $_SESSION['mfa_issued_at']) > 300) {
        unset($_SESSION['mfa_pending'], $_SESSION['mfa_user'], $_SESSION['mfa_issued_at']);
        return false;
    }
    return true;
}

function _complete_login(string $username): void
{
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_user']      = $username;
    $_SESSION['login_time']      = time();
    $_SESSION['last_regen']      = time();
}

function logout(): void {
    start_session();
    $_SESSION = [];
    // Expire the session cookie immediately
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

function generate_csrf(): string {
    start_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(string $token): bool {
    start_session();
    return !empty($_SESSION['csrf_token'])
        && strlen($token) > 0
        && hash_equals($_SESSION['csrf_token'], $token);
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generate_csrf()) . '">';
}

// ── IP-based Login Rate Limiting ──────────────────────────────────────────────
// Max 5 failed attempts per 15-minute window before a 15-minute lockout.
// State stored in the cache directory as JSON files (no Redis required).

define('RATE_LIMIT_MAX',    5);    // max failures before lockout
define('RATE_LIMIT_WINDOW', 900);  // seconds (15 min) for attempt window
define('RATE_LIMIT_LOCKOUT',900);  // seconds (15 min) lockout duration

/**
 * Returns false if this IP is rate-limited (too many recent failures).
 * Records a new failure attempt.
 */
function rate_limit_check(string $ip): bool {
    $file = _rate_limit_file($ip);
    $now  = time();
    $data = _rate_limit_read($file);

    // Check lockout
    if (!empty($data['locked_until']) && $now < $data['locked_until']) {
        return false;
    }

    // Prune attempts outside the rolling window
    $data['attempts'] = array_values(
        array_filter($data['attempts'] ?? [], fn($ts) => ($now - $ts) < RATE_LIMIT_WINDOW)
    );

    // Record this attempt
    $data['attempts'][] = $now;

    // Trigger lockout if threshold exceeded
    if (count($data['attempts']) > RATE_LIMIT_MAX) {
        $data['locked_until'] = $now + RATE_LIMIT_LOCKOUT;
        $data['attempts']     = [];  // reset after locking
        _rate_limit_write($file, $data);
        return false;
    }

    _rate_limit_write($file, $data);
    return true;
}

/** Clear rate-limit state on successful login. */
function rate_limit_clear(string $ip): void {
    @unlink(_rate_limit_file($ip));
}

function _rate_limit_file(string $ip): string {
    $dir = defined('CACHE_DIR') ? CACHE_DIR : __DIR__ . '/../cache';
    if (!is_dir($dir)) @mkdir($dir, 0750, true);
    // Prefix with dot so it's not served even without .htaccess
    return $dir . '/.' . hash('sha256', 'rl_' . $ip) . '.rl';
}

function _rate_limit_read(string $file): array {
    if (!file_exists($file)) return ['attempts' => []];
    $fp = @fopen($file, 'r');
    if (!$fp) return ['attempts' => []];
    flock($fp, LOCK_SH);
    $raw = stream_get_contents($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    $data = json_decode($raw ?: '{}', true);
    return is_array($data) ? $data : ['attempts' => []];
}

function _rate_limit_write(string $file, array $data): void {
    $encoded = json_encode($data);
    if ($encoded !== false) {
        file_put_contents($file, $encoded, LOCK_EX);
    }
}
