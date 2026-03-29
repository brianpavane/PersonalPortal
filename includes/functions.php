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

function cache_get(string $key): mixed {
    $file = CACHE_DIR . '/' . md5($key) . '.cache';
    if (!file_exists($file)) return null;
    $data = @unserialize(file_get_contents($file));
    if (!$data || $data['expires'] < time()) {
        @unlink($file);
        return null;
    }
    return $data['value'];
}

function cache_set(string $key, mixed $value, int $ttl): void {
    if (!is_dir(CACHE_DIR)) {
        mkdir(CACHE_DIR, 0750, true);
    }
    $file = CACHE_DIR . '/' . md5($key) . '.cache';
    file_put_contents($file, serialize(['expires' => time() + $ttl, 'value' => $value]), LOCK_EX);
}

function favicon_url(string $url): string {
    $host = parse_url($url, PHP_URL_HOST) ?: $url;
    return 'https://www.google.com/s2/favicons?domain=' . urlencode($host) . '&sz=32';
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Convert a plain-text bullet note to HTML. */
function bullets_to_html(string $text): string {
    $lines = explode("\n", $text);
    $html  = '';
    $inList = false;
    foreach ($lines as $line) {
        $line = rtrim($line);
        if ($line === '') {
            if ($inList) { $html .= '</ul>'; $inList = false; }
            $html .= '<br>';
            continue;
        }
        // Heading: lines starting with ##
        if (str_starts_with($line, '## ')) {
            if ($inList) { $html .= '</ul>'; $inList = false; }
            $html .= '<h4>' . h(substr($line, 3)) . '</h4>';
            continue;
        }
        // Bullet: lines starting with - or *
        if (preg_match('/^[-*]\s+(.+)$/', $line, $m)) {
            if (!$inList) { $html .= '<ul>'; $inList = true; }
            $html .= '<li>' . h($m[1]) . '</li>';
            continue;
        }
        // Regular text
        if ($inList) { $html .= '</ul>'; $inList = false; }
        $html .= '<p>' . h($line) . '</p>';
    }
    if ($inList) $html .= '</ul>';
    return $html;
}

/** Fetch URL with curl, return body string or false on failure. */
function curl_fetch(string $url, int $timeout = 10): string|false {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (PersonalPortal/1.0)',
        CURLOPT_ENCODING       => 'gzip,deflate',
    ]);
    $body = curl_exec($ch);
    $err  = curl_errno($ch);
    curl_close($ch);
    return $err === 0 ? $body : false;
}
