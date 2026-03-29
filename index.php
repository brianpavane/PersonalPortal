<?php
/**
 * PersonalPortal — Main Portal Page
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

date_default_timezone_set(TIMEZONE);
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
  <div class="header-clock">
    <span id="clock-time">--:--:--</span>
    <span id="clock-date"></span>
  </div>
  <div class="header-actions">
    <a href="admin/" title="Admin Panel">⚙ Admin</a>
  </div>
</header>

<!-- ── Ticker Tape ─────────────────────────────────────────── -->
<div class="ticker-tape" id="ticker-tape" aria-hidden="true">
  <div class="ticker-inner" id="ticker-inner">
    <span class="ticker-item" style="color:var(--text-muted)">Loading stock data…</span>
  </div>
</div>

<!-- ── Main Grid ──────────────────────────────────────────── -->
<div class="portal-grid">

  <!-- Left: Bookmarks + Notes -->
  <div class="main-area">

    <!-- Bookmarks Widget -->
    <div class="widget" id="widget-bookmarks">
      <div class="widget-header">
        <span class="widget-title">
          <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor"><path d="M2 2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v13.5a.5.5 0 0 1-.777.416L8 13.101l-5.223 2.815A.5.5 0 0 1 2 15.5zm2-1a1 1 0 0 0-1 1v12.566l4.723-2.482a.5.5 0 0 1 .554 0L13 14.566V2a1 1 0 0 0-1-1z"/></svg>
          Bookmarks
        </span>
        <button class="refresh-btn" id="refresh-bookmarks" title="Refresh">↻</button>
      </div>
      <div class="widget-body" id="bookmarks-container">
        <div class="stock-loading"><span class="spinner"></span></div>
      </div>
    </div>

    <!-- Notes Widget -->
    <div class="widget" id="widget-notes">
      <div class="widget-header">
        <span class="widget-title">
          <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor"><path d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2zm8.93 4L7.555 2H2a1 1 0 0 0-1 1v4zm.14 0H15V5H9.146zm4.844 1H9.146l1.375 5H14a1 1 0 0 0 1-1zm-4.69 0H4.93l-1.25 4.546A1 1 0 0 0 2 14.5V14H7.555z"/></svg>
          Notes
        </span>
        <button class="refresh-btn" id="refresh-notes" title="Refresh">↻</button>
      </div>
      <div class="widget-body" id="notes-container">
        <div class="stock-loading"><span class="spinner"></span></div>
      </div>
    </div>

  </div><!-- /main-area -->

  <!-- Right Sidebar: Stocks + News -->
  <div class="sidebar">

    <!-- Stocks Widget -->
    <div class="widget" id="widget-stocks">
      <div class="widget-header">
        <span class="widget-title">
          <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor"><path d="M0 0h1v15h15v1H0zm14.817 3.113a.5.5 0 0 1 .07.704l-4.5 5.5a.5.5 0 0 1-.74.037L7.06 6.767l-3.656 5.027a.5.5 0 0 1-.808-.588l4-5.5a.5.5 0 0 1 .758-.06l2.609 2.61 4.15-5.073a.5.5 0 0 1 .704-.07"/></svg>
          Markets
        </span>
        <button class="refresh-btn" id="refresh-stocks" title="Refresh">↻</button>
      </div>
      <div id="stocks-container">
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
        <button class="refresh-btn" id="refresh-news" title="Refresh">↻</button>
      </div>
      <div id="news-container">
        <div class="news-loading"><span class="spinner"></span></div>
      </div>
    </div>

  </div><!-- /sidebar -->

</div><!-- /portal-grid -->

<footer class="portal-footer">
  <?= h(APP_NAME) ?> &mdash; <?= date('Y') ?>
  &bull; <a href="admin/">Admin</a>
</footer>

<script src="assets/js/portal.js"></script>
</body>
</html>
