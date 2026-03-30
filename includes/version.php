<?php
/**
 * PersonalPortal — Version
 *
 * Bump APP_VERSION with every release.
 * History:
 *   1.0.0  2026-03-30  Initial release
 *   1.1.0  2026-03-30  Security audit & hardening
 *   1.2.0  2026-03-30  TOTP MFA support (Authy)
 *   1.2.1  2026-03-30  Installer SQL-parser bug fix
 *   1.3.0  2026-03-30  Version display, 16-colour palette, portal access control
 *   1.3.1  2026-03-30  Arrow-based sort ordering; Cache-Control security fix for auth'd APIs
 *   1.4.0  2026-03-30  Weather + World Clock widgets; Yahoo Finance v8; header clock removed
 *   1.4.1  2026-03-30  Fix settings.php 500 (POST block brace); add config.php.example
 *   1.4.2  2026-03-30  Timezones up to 10; city↔TZ auto-fill; weather geocode via Nominatim (zip support)
 *   1.4.3  2026-03-30  Fix stocks API: crumb auth for Yahoo Finance; PHP 7.4 compat; output buffering
 *   1.4.4  2026-03-30  Fix weather Locate (server-side geocode proxy); add Dashboard link to admin sidebar
 */
define('APP_VERSION',      '1.4.4');
define('APP_VERSION_DATE', '2026-03-30');
