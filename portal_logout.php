<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/portal_auth.php';
portal_logout();
header('Location: ' . APP_URL . '/portal_login.php');
exit;
