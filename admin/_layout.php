<?php
/**
 * Admin layout helper.
 * Include at top of admin pages AFTER calling require_login().
 * Set $page_title and $active_nav before including.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($page_title ?? 'Admin') ?> — <?= htmlspecialchars(APP_NAME) ?></title>
<link rel="stylesheet" href="../assets/css/admin.css">
<?php require_once __DIR__ . '/../includes/version.php'; ?>
</head>
<body>
<div class="admin-wrapper">

  <!-- Sidebar -->
  <aside class="admin-sidebar" id="admin-sidebar">
    <div class="admin-brand">
      &#9881; Admin
      <small><?= htmlspecialchars(APP_NAME) ?></small>
    </div>
    <nav class="admin-nav">
      <div class="nav-section">Content</div>
      <a class="nav-link <?= ($active_nav??'') === 'bookmarks' ? 'active' : '' ?>" href="bookmarks.php">
        <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor"><path d="M2 2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v13.5a.5.5 0 0 1-.777.416L8 13.101l-5.223 2.815A.5.5 0 0 1 2 15.5z"/></svg>
        Bookmarks
      </a>
      <a class="nav-link <?= ($active_nav??'') === 'categories' ? 'active' : '' ?>" href="categories.php">
        <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor"><path d="M1 2.5A1.5 1.5 0 0 1 2.5 1h3A1.5 1.5 0 0 1 7 2.5v3A1.5 1.5 0 0 1 5.5 7h-3A1.5 1.5 0 0 1 1 5.5zm8 0A1.5 1.5 0 0 1 10.5 1h3A1.5 1.5 0 0 1 15 2.5v3A1.5 1.5 0 0 1 13.5 7h-3A1.5 1.5 0 0 1 9 5.5zm-8 8A1.5 1.5 0 0 1 2.5 9h3A1.5 1.5 0 0 1 7 10.5v3A1.5 1.5 0 0 1 5.5 15h-3A1.5 1.5 0 0 1 1 13.5zm8 0A1.5 1.5 0 0 1 10.5 9h3A1.5 1.5 0 0 1 15 10.5v3A1.5 1.5 0 0 1 13.5 15h-3A1.5 1.5 0 0 1 9 13.5z"/></svg>
        Categories
      </a>
      <a class="nav-link <?= ($active_nav??'') === 'notes' ? 'active' : '' ?>" href="notes.php">
        <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor"><path d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2z"/></svg>
        Notes
      </a>
      <div class="nav-section">Widgets</div>
      <a class="nav-link <?= ($active_nav??'') === 'settings' ? 'active' : '' ?>" href="settings.php">
        <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor"><path d="M9.405 1.05c-.413-1.4-2.397-1.4-2.81 0l-.1.34a1.464 1.464 0 0 1-2.105.872l-.31-.17c-1.283-.698-2.686.705-1.987 1.987l.169.311c.446.82.023 1.841-.872 2.105l-.34.1c-1.4.413-1.4 2.397 0 2.81l.34.1a1.464 1.464 0 0 1 .872 2.105l-.17.31c-.698 1.283.705 2.686 1.987 1.987l.311-.169a1.464 1.464 0 0 1 2.105.872l.1.34c.413 1.4 2.397 1.4 2.81 0l.1-.34a1.464 1.464 0 0 1 2.105-.872l.31.17c1.283.698 2.686-.705 1.987-1.987l-.169-.311a1.464 1.464 0 0 1 .872-2.105l.34-.1c1.4-.413 1.4-2.397 0-2.81l-.34-.1a1.464 1.464 0 0 1-.872-2.105l.17-.31c.698-1.283-.705-2.686-1.987-1.987l-.311.169a1.464 1.464 0 0 1-2.105-.872zM8 10.93a2.929 2.929 0 1 1 0-5.86 2.929 2.929 0 0 1 0 5.858z"/></svg>
        Settings
      </a>
      <a class="nav-link <?= ($active_nav??'') === 'portal_users' ? 'active' : '' ?>" href="portal_users.php">
        <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor"><path d="M15 14s1 0 1-1-1-4-5-4-5 3-5 4 1 1 1 1zm-7.978-1A.261.261 0 0 1 7 12.996c.001-.264.167-1.03.76-1.72C8.312 10.629 9.282 10 11 10c1.717 0 2.687.63 3.24 1.276.593.69.758 1.457.76 1.72l-.008.002-.014.002zM11 7a2 2 0 1 0 0-4 2 2 0 0 0 0 4m3-2a3 3 0 1 1-6 0 3 3 0 0 1 6 0M6.936 9.28a6 6 0 0 0-1.23-.247A7 7 0 0 0 5 9c-4 0-5 3-5 4q0 1 1 1h4.216A2.24 2.24 0 0 1 5 13c0-1.01.377-2.042 1.09-2.904.243-.294.526-.569.846-.816M4.92 10A5.5 5.5 0 0 0 4 13H1c0-.26.164-1.03.76-1.724.545-.636 1.492-1.256 3.16-1.275ZM1.5 5.5a3 3 0 1 1 6 0 3 3 0 0 1-6 0m3-2a2 2 0 1 0 0 4 2 2 0 0 0 0-4"/></svg>
        Portal Users
      </a>
      <div class="nav-section">Account</div>
      <a class="nav-link <?= ($active_nav??'') === 'password' ? 'active' : '' ?>" href="password.php">
        <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor"><path d="M8 1a2 2 0 0 1 2 2v4H6V3a2 2 0 0 1 2-2m3 6V3a3 3 0 0 0-6 0v4a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2"/></svg>
        Change Password
      </a>
      <a class="nav-link <?= ($active_nav??'') === 'mfa' ? 'active' : '' ?>" href="mfa_setup.php">
        <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor"><path d="M0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8m8-7a7 7 0 0 0-5.468 11.37C3.242 11.226 4.805 10 8 10s4.757 1.225 5.468 2.37A7 7 0 0 0 8 1M8 5.5a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3"/></svg>
        Two-Factor Auth
        <?php
          // Show a badge if MFA is not yet enabled
          if (!isset($_mfa_badge_checked)) {
              $_mfa_badge_checked = true;
              try {
                  require_once __DIR__ . '/../includes/totp.php';
                  $_mfa_enabled = TOTP::isEnabled(db());
              } catch (Throwable $e) { $_mfa_enabled = false; }
          }
          if (empty($_mfa_enabled)):
        ?>
        <span style="margin-left:auto;background:var(--accent-orange);color:#fff;
                     font-size:.65rem;padding:.1rem .35rem;border-radius:4px;font-weight:700">OFF</span>
        <?php endif; ?>
      </a>
    </nav>
    <div class="admin-sidebar-footer">
      <a href="../index.php" class="nav-link">&#8592; Portal</a>
      <a href="logout.php" class="nav-link" style="color:var(--accent-red)">&#8594; Logout</a>
      <div style="font-size:.7rem;color:var(--text-muted);padding:.5rem .75rem 0;opacity:.6">
        v<?= htmlspecialchars(APP_VERSION) ?>
      </div>
    </div>
  </aside>

  <!-- Main Content -->
  <main class="admin-main">
