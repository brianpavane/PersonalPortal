<?php
/**
 * News RSS Proxy — aggregates enabled RSS feeds.
 * GET /api/news.php?limit=20
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=' . NEWS_CACHE_TTL);

$limit     = min((int)($_GET['limit'] ?? 25), 60);
$cache_key = 'news_' . $limit;
$cached    = cache_get($cache_key);

if ($cached !== null) {
    echo json_encode($cached);
    exit;
}

$feeds = db()->query(
    'SELECT id, name, url FROM news_feeds WHERE active = 1 ORDER BY sort_order, name'
)->fetchAll();

$items = [];

foreach ($feeds as $feed) {
    $body = curl_fetch($feed['url'], 8);
    if (!$body) continue;

    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA);
    if (!$xml) continue;

    // Handle both RSS 2.0 and Atom
    $entries = $xml->channel->item ?? $xml->entry ?? [];

    foreach ($entries as $entry) {
        $title = (string)($entry->title ?? '');
        $link  = (string)($entry->link ?? $entry->id ?? '');
        if (is_object($link)) $link = (string)$entry->link['href'];

        // Atom <link href="...">
        if (empty($link) && isset($entry->link)) {
            foreach ($entry->link->attributes() as $attr => $val) {
                if ($attr === 'href') { $link = (string)$val; break; }
            }
        }

        $pubDate = (string)($entry->pubDate ?? $entry->published ?? $entry->updated ?? '');
        $ts      = $pubDate ? strtotime($pubDate) : 0;

        if ($title && $link) {
            $items[] = [
                'source'  => $feed['name'],
                'title'   => html_entity_decode(strip_tags($title), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'url'     => $link,
                'ts'      => $ts,
                'date'    => $ts ? date('M j, g:i a', $ts) : '',
            ];
        }
    }
}

// Sort by timestamp descending
usort($items, fn($a, $b) => $b['ts'] - $a['ts']);
$items = array_slice($items, 0, $limit);

cache_set($cache_key, $items, NEWS_CACHE_TTL);
echo json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
