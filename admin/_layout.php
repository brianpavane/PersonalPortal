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
      <div class="nav-section">Account</div>
      <a class="nav-link <?= ($active_nav??'') === 'password' ? 'active' : '' ?>" href="password.php">
        <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor"><path d="M8 1a2 2 0 0 1 2 2v4H6V3a2 2 0 0 1 2-2m3 6V3a3 3 0 0 0-6 0v4a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2"/></svg>
        Change Password
      </a>
    </nav>
    <div class="admin-sidebar-footer">
      <a href="../index.php" class="nav-link">&#8592; Portal</a>
      <a href="logout.php" class="nav-link" style="color:var(--accent-red)">&#8594; Logout</a>
    </div>
  </aside>

  <!-- Main Content -->
  <main class="admin-main">
