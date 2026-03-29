<?php
/**
 * General utility functions.
 */

require_once __DIR__ . '/../config/config.php';

function json_response(mixed $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ── File Cache (JSON, with flock for atomicity) ───────────────────────────────

function cache_get(string $key): mixed {
    $file = CACHE_DIR . '/' . md5($key) . '.cache';
    if (!file_exists($file)) return null;

    $fp = @fopen($file, 'r');
    if (!$fp) return null;

    flock($fp, LOCK_SH);
    $raw = stream_get_contents($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    if (!$raw) return null;
    $data = json_decode($raw, true);
    if (!is_array($data) || !isset($data['expires'], $data['value'])) return null;

    if ($data['expires'] < time()) {
        @unlink($file);
        return null;
    }
    return $data['value'];
}

function cache_set(string $key, mixed $value, int $ttl): void {
    if (!is_dir(CACHE_DIR)) {
        mkdir(CACHE_DIR, 0750, true);
    }
    $file    = CACHE_DIR . '/' . md5($key) . '.cache';
    $encoded = json_encode(['expires' => time() + $ttl, 'value' => $value]);
    if ($encoded !== false) {
        file_put_contents($file, $encoded, LOCK_EX);
    }
}

// ── URL / SSRF Helpers ────────────────────────────────────────────────────────

/**
 * Returns true only if the URL has an http or https scheme.
 * Used to reject file://, gopher://, ftp://, javascript:, data:, etc.
 */
function is_safe_url(string $url): bool {
    $scheme = parse_url($url, PHP_URL_SCHEME);
    return in_array(strtolower((string)$scheme), ['http', 'https'], true);
}

function favicon_url(string $url): string {
    $host = parse_url($url, PHP_URL_HOST) ?: '';
    // Only return a favicon URL if the host looks valid
    if (!$host || !preg_match('/^[a-zA-Z0-9.\-]+$/', $host)) {
        return '';
    }
    return 'https://www.google.com/s2/favicons?domain=' . urlencode($host) . '&sz=32';
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Convert plain-text bullet notation to safe HTML. */
function bullets_to_html(string $text): string {
    $lines  = explode("\n", $text);
    $html   = '';
    $inList = false;
    foreach ($lines as $line) {
        $line = rtrim($line);
        if ($line === '') {
            if ($inList) { $html .= '</ul>'; $inList = false; }
            $html .= '<br>';
            continue;
        }
        if (str_starts_with($line, '## ')) {
            if ($inList) { $html .= '</ul>'; $inList = false; }
            $html .= '<h4>' . h(substr($line, 3)) . '</h4>';
            continue;
        }
        if (preg_match('/^[-*]\s+(.+)$/', $line, $m)) {
            if (!$inList) { $html .= '<ul>'; $inList = true; }
            $html .= '<li>' . h($m[1]) . '</li>';
            continue;
        }
        if ($inList) { $html .= '</ul>'; $inList = false; }
        $html .= '<p>' . h($line) . '</p>';
    }
    if ($inList) $html .= '</ul>';
    return $html;
}

/**
 * Fetch URL via curl.
 * Restricted to HTTP/HTTPS only to prevent SSRF via other protocols.
 */
function curl_fetch(string $url, int $timeout = 10): string|false {
    // Reject non-http(s) URLs before curl even opens a connection
    if (!is_safe_url($url)) return false;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        // Restrict protocols at the curl layer — no file://, gopher://, ftp://, etc.
        CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS=> CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_USERAGENT      => 'PersonalPortal/1.0',
        CURLOPT_ENCODING       => 'gzip,deflate',
        // Prevent response header injection
        CURLOPT_HEADERFUNCTION => fn($ch, $h) => strlen($h),
    ]);
    $body = curl_exec($ch);
    $err  = curl_errno($ch);
    curl_close($ch);
    return $err === 0 && is_string($body) ? $body : false;
}
