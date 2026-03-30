<?php
/**
 * Bookmarks API — public read, admin write.
 * GET  /api/bookmarks.php          → all categories + bookmarks
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/portal_auth.php';
portal_require_login_api();

header('Content-Type: application/json; charset=utf-8');

$db = db();

$categories = $db->query(
    'SELECT id, name, icon, color, sort_order
     FROM bookmark_categories
     ORDER BY sort_order, name'
)->fetchAll();

$bookmarks = $db->query(
    'SELECT id, category_id, title, url, description, sort_order
     FROM bookmarks
     ORDER BY category_id, sort_order, title'
)->fetchAll();

// Group bookmarks under their category
$byCat = [];
foreach ($bookmarks as $bm) {
    $byCat[$bm['category_id']][] = $bm;
}

$result = [];
foreach ($categories as $cat) {
    $cat['bookmarks'] = $byCat[$cat['id']] ?? [];
    $result[] = $cat;
}

echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
