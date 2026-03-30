<?php
/**
 * Portal User Authentication
 *
 * Manages non-admin read-only portal accounts.
 * Admin sessions automatically satisfy portal auth — no double-login.
 * Remember-me uses rotating SHA-256-hashed tokens stored in portal_tokens.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';   // start_session(), is_logged_in(), rate_limit_*

define('PORTAL_SESSION_LIFETIME',  86400);   // 24 h
define('PORTAL_REMEMBER_DAYS',     30);
define('PORTAL_REMEMBER_COOKIE',   'portal_rm');

// ── Auth-enabled check ────────────────────────────────────────────────────────

function portal_auth_enabled(): bool {
    static $enabled = null;
    if ($enabled !== null) return $enabled;
    try {
        $stmt = db()->prepare(
            "SELECT setting_value FROM portal_settings WHERE setting_key = 'portal_auth_enabled'"
        );
        $stmt->execute();
        $enabled = ($stmt->fetchColumn() === '1');
    } catch (Throwable $e) {
        $enabled = false;   // fail open if table not yet created (fresh install)
    }
    return $enabled;
}

// ── Session checks ────────────────────────────────────────────────────────────

function portal_is_logged_in(): bool {
    start_session();
    // Admin session always grants portal access
    if (is_logged_in()) return true;
    return !empty($_SESSION['portal_logged_in'])
        && !empty($_SESSION['portal_user'])
        && !empty($_SESSION['portal_login_time'])
        && (time() - (int)$_SESSION['portal_login_time']) < PORTAL_SESSION_LIFETIME;
}

/** Enforce portal login for HTML pages — redirects to portal_login.php. */
function portal_require_login(): void {
    if (!portal_auth_enabled())  return;
    if (portal_is_logged_in())   return;
    if (portal_check_remember()) return;

    $back = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? 'index.php');
    header('Location: ' . APP_URL . '/portal_login.php?redirect=' . urlencode($back));
    exit;
}

/** Enforce portal login for API endpoints — returns 401 JSON. */
function portal_require_login_api(): void {
    if (!portal_auth_enabled())  return;
    if (portal_is_logged_in())   return;
    if (portal_check_remember()) return;
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

// ── Login / logout ────────────────────────────────────────────────────────────

/**
 * Validate portal credentials and populate session.
 * Returns true on success; false on bad credentials or inactive account.
 */
function portal_login(string $username, string $password): bool {
    $db   = db();
    $stmt = $db->prepare(
        'SELECT id, password_hash FROM portal_users WHERE username = ? AND active = 1'
    );
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, (string)$user['password_hash'])) {
        return false;
    }

    start_session();
    session_regenerate_id(true);
    $_SESSION['portal_logged_in']  = true;
    $_SESSION['portal_user']       = $username;
    $_SESSION['portal_user_id']    = (int)$user['id'];
    $_SESSION['portal_login_time'] = time();
    return true;
}

function portal_logout(): void {
    portal_clear_remember();
    start_session();
    unset(
        $_SESSION['portal_logged_in'],
        $_SESSION['portal_user'],
        $_SESSION['portal_user_id'],
        $_SESSION['portal_login_time']
    );
}

// ── Remember-me (rotating tokens) ────────────────────────────────────────────

/**
 * Issue a 30-day remember-me cookie and store its SHA-256 hash in DB.
 * Called after successful credential login when the user checks "Remember me".
 */
function portal_set_remember(int $user_id, string $username): void {
    $token      = bin2hex(random_bytes(32));   // 64 hex chars
    $token_hash = hash('sha256', $token);
    $expires    = date('Y-m-d H:i:s', time() + PORTAL_REMEMBER_DAYS * 86400);

    $db = db();
    // Prune stale tokens for this user before inserting a new one
    $db->prepare('DELETE FROM portal_tokens WHERE user_id = ? AND expires_at < NOW()')
       ->execute([$user_id]);
    $db->prepare('INSERT INTO portal_tokens (user_id, token_hash, expires_at) VALUES (?,?,?)')
       ->execute([$user_id, $token_hash, $expires]);

    setcookie(PORTAL_REMEMBER_COOKIE, $token, [
        'expires'  => time() + PORTAL_REMEMBER_DAYS * 86400,
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}

/**
 * Validate the remember-me cookie, auto-login, and rotate the token.
 * Token rotation: old token is deleted and a new one is issued on every use,
 * so a stolen token can only be replayed once before it's invalidated.
 */
function portal_check_remember(): bool {
    $token = $_COOKIE[PORTAL_REMEMBER_COOKIE] ?? '';
    // 64-char hex = 32 random bytes
    if (!$token || strlen($token) !== 64 || !ctype_xdigit($token)) return false;

    $token_hash = hash('sha256', $token);
    $db   = db();
    $stmt = $db->prepare(
        'SELECT t.id, t.user_id, u.username
         FROM portal_tokens t
         JOIN portal_users u ON u.id = t.user_id
         WHERE t.token_hash = ? AND t.expires_at > NOW() AND u.active = 1'
    );
    $stmt->execute([$token_hash]);
    $row = $stmt->fetch();

    if (!$row) {
        portal_clear_remember();
        return false;
    }

    // Delete old token then issue a new rotated one
    $db->prepare('DELETE FROM portal_tokens WHERE id = ?')->execute([(int)$row['id']]);

    start_session();
    session_regenerate_id(true);
    $_SESSION['portal_logged_in']  = true;
    $_SESSION['portal_user']       = $row['username'];
    $_SESSION['portal_user_id']    = (int)$row['user_id'];
    $_SESSION['portal_login_time'] = time();

    portal_set_remember((int)$row['user_id'], (string)$row['username']);
    return true;
}

function portal_clear_remember(): void {
    $token = $_COOKIE[PORTAL_REMEMBER_COOKIE] ?? '';
    if ($token && strlen($token) === 64 && ctype_xdigit($token)) {
        try {
            db()->prepare('DELETE FROM portal_tokens WHERE token_hash = ?')
               ->execute([hash('sha256', $token)]);
        } catch (Throwable $e) {}
    }
    setcookie(PORTAL_REMEMBER_COOKIE, '', [
        'expires'  => time() - 86400,
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}
