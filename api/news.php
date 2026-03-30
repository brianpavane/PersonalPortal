<?php
/**
 * News RSS Proxy — aggregates enabled RSS feeds.
 * GET /api/news.php?limit=20
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/portal_auth.php';
portal_require_login_api();

header('Content-Type: application/json; charset=utf-8');
// Use private cache when auth is required so proxy caches never store authenticated data
$cache_visibility = portal_auth_enabled() ? 'private' : 'public';
header('Cache-Control: ' . $cache_visibility . ', max-age=' . NEWS_CACHE_TTL);

$limit     = min((int)($_GET['limit'] ?? 25), 60);
$cache_key = 'news_' . $limit;
$cached    = cache_get($cache_key);

if ($cached !== null) {
    echo json_encode($cached, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$feeds = db()->query(
    'SELECT id, name, url FROM news_feeds WHERE active = 1 ORDER BY sort_order, name'
)->fetchAll();

$items = [];

foreach ($feeds as $feed) {
    // Only fetch http/https feeds (already validated on insert, but verify again)
    if (!is_safe_url($feed['url'])) continue;

    $body = curl_fetch($feed['url'], 8);
    if (!$body) continue;

    // Disable external entity loading to prevent XXE attacks
    // (In PHP 8.0+ entity loading is disabled by default; this is belt-and-suspenders)
    $prev = libxml_use_internal_errors(true);
    if (PHP_VERSION_ID < 80000) {
        // @phpstan-ignore-next-line
        libxml_disable_entity_loader(true);
    }

    $xml = simplexml_load_string(
        $body,
        'SimpleXMLElement',
        LIBXML_NOCDATA | LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING
    );

    libxml_clear_errors();
    libxml_use_internal_errors($prev);

    if (!$xml) continue;

    // Handle both RSS 2.0 (<channel><item>) and Atom (<entry>)
    $entries = $xml->channel->item ?? $xml->entry ?? [];

    foreach ($entries as $entry) {
        $title = (string)($entry->title ?? '');

        // Extract link — RSS uses <link>, Atom uses <link href="...">
        $link = '';
        if (isset($entry->link)) {
            $link = (string)$entry->link;
            // Atom: <link> may be empty string but have href attribute
            if ($link === '' || is_object($link)) {
                foreach (($entry->link->attributes() ?? []) as $attr => $val) {
                    if ($attr === 'href') { $link = (string)$val; break; }
                }
            }
        }
        if (!$link && isset($entry->id)) {
            $candidate = (string)$entry->id;
            // Only use <id> as URL if it looks like one
            if (str_starts_with($candidate, 'http')) $link = $candidate;
        }

        // Validate: only include http/https URLs
        if (!$link || !is_safe_url($link)) continue;
        if (!filter_var($link, FILTER_VALIDATE_URL)) continue;

        $title = html_entity_decode(strip_tags($title), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $title = mb_substr(trim($title), 0, 300); // cap title length
        if (!$title) continue;

        $pubDate = (string)($entry->pubDate ?? $entry->published ?? $entry->updated ?? '');
        $ts      = $pubDate ? (int)strtotime($pubDate) : 0;
        // Reject future timestamps beyond 1 hour (malformed or malicious feed)
        if ($ts > time() + 3600) $ts = 0;

        $items[] = [
            'source' => mb_substr((string)$feed['name'], 0, 100),
            'title'  => $title,
            'url'    => $link,
            'ts'     => $ts,
            'date'   => $ts ? date('M j, g:i a', $ts) : '',
        ];
    }
}

// Sort by timestamp descending, then cap
usort($items, fn($a, $b) => $b['ts'] - $a['ts']);
$items = array_slice($items, 0, $limit);

cache_set($cache_key, $items, NEWS_CACHE_TTL);
echo json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
