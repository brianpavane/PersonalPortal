<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();

$db   = db();
$msg  = '';
$errs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) die('Invalid CSRF token.');

    $section = $_POST['section'] ?? '';

    // ── Stocks ──────────────────────────────────────────────────────────────
    if ($section === 'stocks') {
        // Delete all and reinsert (simple approach)
        $db->exec('DELETE FROM stock_symbols');
        $symbols = array_filter(array_map('trim', explode("\n", $_POST['symbols'] ?? '')));
        $stmt    = $db->prepare('INSERT IGNORE INTO stock_symbols (symbol,label,sort_order) VALUES (?,?,?)');
        foreach (array_values($symbols) as $i => $line) {
            // Format: SYMBOL[|Label]
            [$sym, $label] = array_pad(explode('|', $line, 2), 2, '');
            $sym = strtoupper(preg_replace('/[^A-Z0-9.\-^]/', '', $sym));
            if ($sym) $stmt->execute([$sym, trim($label), $i]);
        }
        $msg = 'Stock symbols saved.';
    }

    // ── News Feeds ───────────────────────────────────────────────────────────
    if ($section === 'feeds_add') {
        $name = trim($_POST['feed_name'] ?? '');
        $url  = trim($_POST['feed_url']  ?? '');
        if (!$name || !$url) $errs[] = 'Feed name and URL are required.';
        if ($url && !filter_var($url, FILTER_VALIDATE_URL)) $errs[] = 'Invalid feed URL.';
        // Restrict to http/https — reject file://, gopher://, ftp://, etc.
        if ($url && !in_array(strtolower((string)parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            $errs[] = 'Feed URL must use http:// or https://.';
        }
        if (!$errs) {
            $max = $db->query('SELECT COALESCE(MAX(sort_order),0)+1 FROM news_feeds')->fetchColumn();
            $db->prepare('INSERT INTO news_feeds (name,url,sort_order) VALUES (?,?,?)')->execute([$name,$url,$max]);
            $msg = 'Feed added.';
        }
    }

    if ($section === 'feeds_toggle') {
        $feed_id = (int)($_POST['feed_id'] ?? 0);
        $active  = (int)($_POST['active']  ?? 0);
        $db->prepare('UPDATE news_feeds SET active=? WHERE id=?')->execute([$active, $feed_id]);
        $msg = 'Feed updated.';
    }

    if ($section === 'feeds_delete') {
        $db->prepare('DELETE FROM news_feeds WHERE id=?')->execute([(int)($_POST['feed_id'] ?? 0)]);
        $msg = 'Feed removed.';
    }

    // Clear news cache
    if (in_array($section, ['feeds_add','feeds_toggle','feeds_delete'])) {
        array_map('unlink', glob(CACHE_DIR . '/news_*.cache'));
    }
    if ($section === 'stocks') {
        array_map('unlink', glob(CACHE_DIR . '/stocks_*.cache'));
    }
}

// Load data
$symbols = $db->query('SELECT * FROM stock_symbols ORDER BY sort_order')->fetchAll();
$feeds   = $db->query('SELECT * FROM news_feeds ORDER BY sort_order, name')->fetchAll();

// Build textarea text
$sym_text = implode("\n", array_map(fn($s) => $s['symbol'] . ($s['label'] ? '|' . $s['label'] : ''), $symbols));

$page_title = 'Settings';
$active_nav = 'settings';
include __DIR__ . '/_layout.php';
?>

<div class="page-header">
  <div class="page-title">Settings <small>Stocks &amp; News Feeds</small></div>
</div>

<?php if ($msg):  ?><div class="alert alert-success"><?= h($msg) ?></div><?php endif; ?>
<?php if ($errs): ?><div class="alert alert-danger"><?= implode('<br>', array_map('h', $errs)) ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;align-items:start">

  <!-- Stock Symbols -->
  <div class="card">
    <div class="card-header">&#128200; Stock Symbols</div>
    <div class="card-body">
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="section" value="stocks">
        <div class="form-group">
          <label class="form-label">Symbols (one per line)</label>
          <textarea name="symbols" class="form-control" rows="10" style="font-family:monospace"
                    placeholder="AAPL|Apple&#10;MSFT|Microsoft&#10;SPY"><?= h($sym_text) ?></textarea>
          <div class="form-hint">Format: <code>SYMBOL</code> or <code>SYMBOL|Label</code> — e.g. <code>AAPL|Apple</code></div>
        </div>
        <button type="submit" class="btn btn-success">Save Symbols</button>
      </form>
    </div>
  </div>

  <!-- News Feeds -->
  <div>
    <div class="card" style="margin-bottom:1rem">
      <div class="card-header">&#128240; News Feeds</div>
      <div style="padding:0">
        <?php if ($feeds): ?>
        <table style="width:100%">
          <thead><tr>
            <th>Name</th><th>Active</th><th></th>
          </tr></thead>
          <tbody>
          <?php foreach ($feeds as $f): ?>
          <tr>
            <td style="font-size:.85rem">
              <?= h($f['name']) ?><br>
              <small style="color:var(--text-muted);font-size:.72rem"><?= h(mb_substr($f['url'],0,50)) ?>…</small>
            </td>
            <td>
              <form method="post" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="section" value="feeds_toggle">
                <input type="hidden" name="feed_id" value="<?= $f['id'] ?>">
                <input type="hidden" name="active" value="<?= $f['active'] ? 0 : 1 ?>">
                <button type="submit" class="btn btn-sm <?= $f['active'] ? 'btn-success' : 'btn-secondary' ?>">
                  <?= $f['active'] ? 'ON' : 'OFF' ?>
                </button>
              </form>
            </td>
            <td>
              <form method="post" style="display:inline" onsubmit="return confirm('Remove this feed?')">
                <?= csrf_field() ?>
                <input type="hidden" name="section" value="feeds_delete">
                <input type="hidden" name="feed_id" value="<?= $f['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm btn-icon">✕</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php else: ?>
        <p style="padding:1rem;color:var(--text-muted);font-size:.85rem">No feeds configured. Add one below.</p>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-header">Add News Feed</div>
      <div class="card-body">
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="section" value="feeds_add">
          <div class="form-group">
            <label class="form-label">Feed Name</label>
            <input type="text" name="feed_name" class="form-control" placeholder="Reuters" required>
          </div>
          <div class="form-group">
            <label class="form-label">RSS URL</label>
            <input type="url" name="feed_url" class="form-control"
                   placeholder="https://feeds.reuters.com/reuters/topNews" required>
          </div>
          <button type="submit" class="btn btn-primary">Add Feed</button>
        </form>
      </div>
    </div>
  </div>

</div>

<?php include __DIR__ . '/_layout_end.php'; ?>
