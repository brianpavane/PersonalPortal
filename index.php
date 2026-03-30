<?php
/**
 * PersonalPortal — Main Portal Page
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/version.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/portal_auth.php';

date_default_timezone_set(TIMEZONE);

// Enforce portal login if access control is enabled
portal_require_login();

$portal_user = $_SESSION['portal_user'] ?? ($_SESSION['admin_user'] ?? null);

// Load stock display mode
try {
    $stock_display = db()->query(
        "SELECT setting_value FROM portal_settings WHERE setting_key='stock_display_mode'"
    )->fetchColumn() ?: 'ticker';
} catch (Throwable $e) {
    $stock_display = 'ticker';
}

// Load timezone zones config to embed in page (avoids extra API call)
try {
    $tz_raw   = db()->query(
        "SELECT setting_value FROM portal_settings WHERE setting_key='timezone_zones'"
    )->fetchColumn();
    $tz_zones = [];
    if ($tz_raw) {
        $decoded = json_decode($tz_raw, true);
        if (is_array($decoded)) {
            $tz_zones = array_slice($decoded, 0, 10);
        }
    }
} catch (Throwable $e) {
    $tz_zones = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= h(APP_NAME) ?></title>
<link rel="stylesheet" href="assets/css/style.css">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><text y='26' font-size='28'>🚀</text></svg>">
</head>
<body>

<!-- ── Header ─────────────────────────────────────────────── -->
<header class="portal-header">
  <div class="brand"><?= h(APP_NAME) ?></div>
  <div class="header-actions">
    <a href="admin/" title="Admin Panel">&#9881; Admin</a>
    <?php if (portal_auth_enabled() && !is_logged_in()): ?>
    <a href="portal_logout.php" title="Sign out <?= h($portal_user ?? '') ?>">&#8594; Sign Out</a>
    <?php endif; ?>
  </div>
</header>

<!-- ── Ticker Tape ─────────────────────────────────────────── -->
<?php if ($stock_display !== 'widget'): ?>
<div class="ticker-tape" id="ticker-tape" aria-hidden="true">
  <div class="ticker-inner" id="ticker-inner">
    <span class="ticker-item" style="color:var(--text-muted)">Loading stock data…</span>
  </div>
</div>
<?php endif; ?>

<!-- ── Main Grid ──────────────────────────────────────────── -->
<div class="portal-grid">

  <!-- Left: Bookmarks + World Clock + Notes -->
  <div class="main-area">

    <!-- Bookmarks Widget -->
    <div class="widget" id="widget-bookmarks">
      <div class="widget-header">
        <span class="widget-title">
          <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor"><path d="M2 2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v13.5a.5.5 0 0 1-.777.416L8 13.101l-5.223 2.815A.5.5 0 0 1 2 15.5zm2-1a1 1 0 0 0-1 1v12.566l4.723-2.482a.5.5 0 0 1 .554 0L13 14.566V2a1 1 0 0 0-1-1z"/></svg>
          Bookmarks
        </span>
        <button class="refresh-btn" id="refresh-bookmarks" title="Refresh">&#8635;</button>
      </div>
      <div class="widget-body" id="bookmarks-container">
        <div class="stock-loading"><span class="spinner"></span></div>
      </div>
    </div>

    <!-- World Clock Widget -->
    <?php if (!empty($tz_zones)): ?>
    <div class="widget" id="widget-clocks">
      <div class="widget-header">
        <span class="widget-title">
          <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor"><path d="M8 3.5a.5.5 0 0 0-1 0V9a.5.5 0 0 0 .252.434l3.5 2a.5.5 0 0 0 .496-.868L8 8.71zm4.5 4.5a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0M8 0a8 8 0 1 0 0 16A8 8 0 0 0 8 0"/></svg>
          World Clock
        </span>
      </div>
      <div class="widget-body">
        <div class="clocks-grid" id="clocks-container"></div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Notes Widget -->
    <div class="widget" id="widget-notes">
      <div class="widget-header">
        <span class="widget-title">
          <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor"><path d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2zm8.93 4L7.555 2H2a1 1 0 0 0-1 1v4zm.14 0H15V5H9.146zm4.844 1H9.146l1.375 5H14a1 1 0 0 0 1-1zm-4.69 0H4.93l-1.25 4.546A1 1 0 0 0 2 14.5V14H7.555z"/></svg>
          Notes
        </span>
        <button class="refresh-btn" id="refresh-notes" title="Refresh">&#8635;</button>
      </div>
      <div class="widget-body" id="notes-container">
        <div class="stock-loading"><span class="spinner"></span></div>
      </div>
    </div>

  </div><!-- /main-area -->

  <!-- Right Sidebar: Stocks + Weather + News -->
  <div class="sidebar">

    <!-- Stocks Widget -->
    <?php if ($stock_display !== 'ticker'): ?>
    <div class="widget" id="widget-stocks">
      <div class="widget-header">
        <span class="widget-title">
          <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor"><path d="M0 0h1v15h15v1H0zm14.817 3.113a.5.5 0 0 1 .07.704l-4.5 5.5a.5.5 0 0 1-.74.037L7.06 6.767l-3.656 5.027a.5.5 0 0 1-.808-.588l4-5.5a.5.5 0 0 1 .758-.06l2.609 2.61 4.15-5.073a.5.5 0 0 1 .704-.07"/></svg>
          Markets
        </span>
        <button class="refresh-btn" id="refresh-stocks" title="Refresh">&#8635;</button>
      </div>
      <div id="stocks-container">
        <div class="stock-loading"><span class="spinner"></span></div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Weather Widget -->
    <div class="widget" id="widget-weather">
      <div class="widget-header">
        <span class="widget-title">
          <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor"><path d="M.036 3.314a.5.5 0 0 1 .65-.278l1.757.703a1.5 1.5 0 0 1 1.879-1.085l3.1.62L8.5 3a3 3 0 0 1 2.674 1.634l.853-.271a2 2 0 0 1 2.494 1.992l.147 1.35a2.5 2.5 0 0 1-.981 2.218l-1.862 1.364a2 2 0 0 1-1.174.386H2.5a2 2 0 0 1-1.9-1.37L.036 3.95a.5.5 0 0 1 0-.636z"/></svg>
          Weather
        </span>
        <button class="refresh-btn" id="refresh-weather" title="Refresh">&#8635;</button>
      </div>
      <div id="weather-container">
        <div class="stock-loading"><span class="spinner"></span></div>
      </div>
    </div>

    <!-- News Widget -->
    <div class="widget" id="widget-news">
      <div class="widget-header">
        <span class="widget-title">
          <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor"><path d="M0 2.5A1.5 1.5 0 0 1 1.5 1h11A1.5 1.5 0 0 1 14 2.5v10.528c0 .3-.05.654-.238.972h.738a.5.5 0 0 0 .5-.5v-9a.5.5 0 0 1 1 0v9a1.5 1.5 0 0 1-1.5 1.5H1.497A1.497 1.497 0 0 1 0 13.5zM12 14c.37 0 .654-.211.853-.441.092-.106.147-.279.147-.531V2.5a.5.5 0 0 0-.5-.5h-11a.5.5 0 0 0-.5.5v11c0 .278.223.5.497.5z"/><path d="M2 3h10v2H2zm0 3h4v3H2zm0 4h4v1H2zm0 2h4v1H2zm5-6h2v1H7zm3 0h2v1h-2zM7 8h2v1H7zm3 0h2v1h-2zm-3 2h2v1H7zm3 0h2v1h-2zm-3 2h2v1H7zm3 0h2v1h-2z"/></svg>
          News
        </span>
        <button class="refresh-btn" id="refresh-news" title="Refresh">&#8635;</button>
      </div>
      <div id="news-container">
        <div class="news-loading"><span class="spinner"></span></div>
      </div>
    </div>

  </div><!-- /sidebar -->

</div><!-- /portal-grid -->

<footer class="portal-footer">
  <?= h(APP_NAME) ?> &mdash; v<?= h(APP_VERSION) ?> &mdash; <?= date('Y') ?>
  &bull; <a href="admin/">Admin</a>
  <?php if (portal_auth_enabled() && !is_logged_in()): ?>
  &bull; <a href="portal_logout.php">Sign Out</a>
  <?php endif; ?>
</footer>

<!-- Timezone config embedded for client-side world clock (no extra HTTP round-trip) -->
<script>
window.PORTAL_TIMEZONES = <?= json_encode(array_values($tz_zones), JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="assets/js/portal.js"></script>
</body>
</html>
