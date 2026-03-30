<?php
/**
 * Notes API — public read only.
 * GET /api/notes.php  → all notes
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/portal_auth.php';
portal_require_login_api();

header('Content-Type: application/json; charset=utf-8');

$notes = db()->query(
    'SELECT id, title, content, color, sort_order, updated_at
     FROM notes
     ORDER BY sort_order, title'
)->fetchAll();

echo json_encode($notes, JSON_UNESCAPED_UNICODE);
