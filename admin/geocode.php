<?php
/**
 * Geocoding proxy — used by admin/settings.php Weather Locate button.
 * Proxies city name / zip code queries to Nominatim (OpenStreetMap) server-side
 * so the browser never has to make a cross-origin request.
 *
 * GET /admin/geocode.php?q=<city+or+zip>
 * Returns: {"lat": float, "lon": float, "display": string} or {"error": string}
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');

$q = trim($_GET['q'] ?? '');
if ($q === '' || mb_strlen($q) > 200) {
    echo json_encode(['error' => 'Missing or invalid query.']);
    exit;
}

$url = 'https://nominatim.openstreetmap.org/search?q=' . urlencode($q)
     . '&format=json&limit=1&addressdetails=1';

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 8,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS      => 3,
    CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
    CURLOPT_REDIR_PROTOCOLS=> CURLPROTO_HTTP | CURLPROTO_HTTPS,
    CURLOPT_USERAGENT      => 'PersonalPortal/1.0 (admin geocode proxy)',
    CURLOPT_HTTPHEADER     => [
        'Accept: application/json',
        'Accept-Language: en',
    ],
]);

$body     = curl_exec($ch);
$curl_err = curl_errno($ch);
$http     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($curl_err !== 0 || !$body || $http !== 200) {
    echo json_encode(['error' => 'Geocoding service unavailable.']);
    exit;
}

$data = json_decode($body, true);
$r    = is_array($data) ? ($data[0] ?? null) : null;

if (!$r || !isset($r['lat'], $r['lon'])) {
    echo json_encode(['error' => 'Location not found.']);
    exit;
}

$a       = $r['address'] ?? [];
$city    = $a['city'] ?? $a['town'] ?? $a['village'] ?? $a['county'] ?? '';
$region  = $a['state'] ?? '';
$country = isset($a['country_code']) ? strtoupper($a['country_code']) : '';
$parts   = array_filter([$city, $region, $country]);
$display = $parts ? implode(', ', $parts) : ($r['display_name'] ?? '');

echo json_encode([
    'lat'     => round((float)$r['lat'], 4),
    'lon'     => round((float)$r['lon'], 4),
    'display' => mb_substr(strip_tags($display), 0, 150),
]);
