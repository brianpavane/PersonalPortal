<?php
/**
 * Authentication helpers.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';

function start_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => true,
            'httponly' => true,
            'samesite' => 'Lax',
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
        header('Location: ' . APP_URL . '/admin/index.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
    // Regenerate session ID periodically
    if (empty($_SESSION['last_regen']) || (time() - $_SESSION['last_regen']) > 300) {
        session_regenerate_id(true);
        $_SESSION['last_regen'] = time();
    }
}

function login(string $username, string $password): bool {
    $db = db();
    $stmt = $db->prepare('SELECT setting_value FROM portal_settings WHERE setting_key = ?');

    $stmt->execute(['admin_username']);
    $stored_user = $stmt->fetchColumn();

    $stmt->execute(['admin_password_hash']);
    $stored_hash = $stmt->fetchColumn();

    if ($stored_user && $stored_hash
        && $username === $stored_user
        && password_verify($password, $stored_hash)) {
        start_session();
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_user']      = $username;
        $_SESSION['login_time']      = time();
        $_SESSION['last_regen']      = time();
        return true;
    }
    return false;
}

function logout(): void {
    start_session();
    $_SESSION = [];
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
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generate_csrf()) . '">';
}
