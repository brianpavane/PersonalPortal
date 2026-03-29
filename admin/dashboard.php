<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
$db = db();

$bm_count  = $db->query('SELECT COUNT(*) FROM bookmarks')->fetchColumn();
$cat_count = $db->query('SELECT COUNT(*) FROM bookmark_categories')->fetchColumn();
$note_count= $db->query('SELECT COUNT(*) FROM notes')->fetchColumn();
$sym_count = $db->query('SELECT COUNT(*) FROM stock_symbols')->fetchColumn();
$feed_count= $db->query('SELECT COUNT(*) FROM news_feeds WHERE active=1')->fetchColumn();

$page_title = 'Dashboard';
$active_nav = 'dashboard';
include __DIR__ . '/_layout.php';
?>

<div class="page-header">
  <div class="page-title">Dashboard <small>Overview &amp; quick stats</small></div>
  <a href="../index.php" class="btn btn-secondary btn-sm" target="_blank">&#8599; View Portal</a>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:1rem;margin-bottom:2rem">
  <?php foreach ([
    ['Bookmarks', $bm_count,   '#58a6ff', 'bookmarks.php'],
    ['Categories',$cat_count,  '#f0883e', 'categories.php'],
    ['Notes',     $note_count, '#3fb950', 'notes.php'],
    ['Symbols',   $sym_count,  '#bc8cff', 'settings.php'],
    ['Feeds',     $feed_count, '#ff7b72', 'settings.php'],
  ] as [$label, $count, $color, $link]): ?>
  <a href="<?= $link ?>" style="text-decoration:none">
    <div class="card" style="border-top:3px solid <?= $color ?>;text-align:center;padding:1.25rem .75rem">
      <div style="font-size:1.8rem;font-weight:800;color:<?= $color ?>"><?= (int)$count ?></div>
      <div style="font-size:.78rem;color:var(--text-muted);margin-top:.2rem"><?= $label ?></div>
    </div>
  </a>
  <?php endforeach; ?>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
  <div class="card">
    <div class="card-header">Recent Bookmarks</div>
    <div class="card-body" style="padding:0">
      <?php
      $recent = $db->query('SELECT b.title, b.url, c.name AS cat
                             FROM bookmarks b
                             JOIN bookmark_categories c ON b.category_id=c.id
                             ORDER BY b.created_at DESC LIMIT 8')->fetchAll();
      ?>
      <?php if ($recent): ?>
      <table style="width:100%">
        <?php foreach ($recent as $r): ?>
        <tr>
          <td style="padding:.5rem .85rem;font-size:.85rem">
            <span style="color:var(--text-muted);font-size:.75rem"><?= htmlspecialchars($r['cat']) ?></span><br>
            <a href="<?= htmlspecialchars($r['url']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($r['title']) ?></a>
          </td>
        </tr>
        <?php endforeach; ?>
      </table>
      <?php else: ?>
      <p style="padding:1rem;color:var(--text-muted);font-size:.85rem">No bookmarks yet. <a href="bookmarks.php">Add some</a>.</p>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-header">Quick Actions</div>
    <div class="card-body" style="display:flex;flex-direction:column;gap:.75rem">
      <a href="bookmarks.php?action=add" class="btn btn-success">+ Add Bookmark</a>
      <a href="categories.php?action=add" class="btn btn-primary">+ Add Category</a>
      <a href="notes.php?action=add" class="btn btn-secondary">+ Add Note</a>
      <a href="settings.php" class="btn btn-secondary">&#9881; Configure Stocks &amp; Feeds</a>
    </div>
  </div>
</div>

<?php include __DIR__ . '/_layout_end.php'; ?>
