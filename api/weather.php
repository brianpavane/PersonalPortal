<?php
/**
 * Weather API — fetches current conditions via Open-Meteo (no API key required).
 * GET /api/weather.php
 * Returns up to 3 city objects from portal_settings > weather_cities config.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/portal_auth.php';
portal_require_login_api();

header('Content-Type: application/json; charset=utf-8');
$cache_visibility = portal_auth_enabled() ? 'private' : 'public';
header('Cache-Control: ' . $cache_visibility . ', max-age=900'); // 15 min

$cache_key = 'weather_v1';
$cached    = cache_get($cache_key);
if ($cached !== null) {
    echo json_encode($cached, JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Load city config from portal_settings ────────────────────────────────────
$cfg_raw = db()->query(
    "SELECT setting_value FROM portal_settings WHERE setting_key = 'weather_cities'"
)->fetchColumn();
$cities = [];
if ($cfg_raw) {
    $decoded = json_decode($cfg_raw, true);
    if (is_array($decoded)) $cities = $decoded;
}

if (!$cities) {
    echo json_encode([]);
    exit;
}

// Load temperature unit preference (F/C)
$unit_raw = db()->query(
    "SELECT setting_value FROM portal_settings WHERE setting_key = 'weather_unit'"
)->fetchColumn();
$unit = ($unit_raw === 'celsius') ? 'celsius' : 'fahrenheit';
$unit_sym = $unit === 'celsius' ? '°C' : '°F';

$results = [];

foreach (array_slice($cities, 0, 3) as $city) {
    $name = mb_substr(trim((string)($city['name'] ?? '')), 0, 100);
    $lat  = (float)($city['lat'] ?? 0);
    $lon  = (float)($city['lon'] ?? 0);

    if (!$name || $lat === 0.0 || $lon === 0.0) continue;

    // Validate coordinate ranges
    if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) continue;

    $url = 'https://api.open-meteo.com/v1/forecast'
         . '?latitude='  . urlencode((string)round($lat, 4))
         . '&longitude=' . urlencode((string)round($lon, 4))
         . '&current=temperature_2m,apparent_temperature,relative_humidity_2m'
         . ',weather_code,wind_speed_10m,precipitation'
         . '&temperature_unit=' . urlencode($unit)
         . '&wind_speed_unit=mph'
         . '&timezone=auto';

    $body = curl_fetch($url, 8);
    if (!$body) {
        $results[] = ['name' => $name, 'error' => true, 'unit' => $unit_sym];
        continue;
    }

    $json    = json_decode($body, true);
    $current = $json['current'] ?? null;
    if (!is_array($current)) {
        $results[] = ['name' => $name, 'error' => true, 'unit' => $unit_sym];
        continue;
    }

    $code = (int)($current['weather_code'] ?? 0);

    $results[] = [
        'name'       => $name,
        'temp'       => (int)round((float)($current['temperature_2m']        ?? 0)),
        'feels_like' => (int)round((float)($current['apparent_temperature']   ?? 0)),
        'humidity'   => (int)($current['relative_humidity_2m'] ?? 0),
        'wind'       => (int)round((float)($current['wind_speed_10m']         ?? 0)),
        'precip'     => round((float)($current['precipitation'] ?? 0), 2),
        'code'       => $code,
        'condition'  => wmo_condition($code),
        'icon'       => wmo_icon($code),
        'unit'       => $unit_sym,
        'error'      => false,
    ];
}

cache_set($cache_key, $results, 900);
echo json_encode($results, JSON_UNESCAPED_UNICODE);

// ── WMO Weather Code helpers ──────────────────────────────────────────────────

function wmo_condition(int $code): string {
    return match(true) {
        $code === 0                      => 'Clear Sky',
        $code === 1                      => 'Mainly Clear',
        $code === 2                      => 'Partly Cloudy',
        $code === 3                      => 'Overcast',
        in_array($code, [45, 48])        => 'Foggy',
        in_array($code, [51, 53, 55])    => 'Drizzle',
        in_array($code, [56, 57])        => 'Freezing Drizzle',
        in_array($code, [61, 63, 65])    => 'Rain',
        in_array($code, [66, 67])        => 'Freezing Rain',
        in_array($code, [71, 73, 75])    => 'Snow',
        $code === 77                     => 'Snow Grains',
        in_array($code, [80, 81, 82])    => 'Rain Showers',
        in_array($code, [85, 86])        => 'Snow Showers',
        $code === 95                     => 'Thunderstorm',
        in_array($code, [96, 99])        => 'Thunderstorm + Hail',
        default                          => 'Unknown',
    };
}

function wmo_icon(int $code): string {
    return match(true) {
        $code === 0                      => '☀️',
        $code === 1                      => '🌤️',
        $code === 2                      => '⛅',
        $code === 3                      => '☁️',
        in_array($code, [45, 48])        => '🌫️',
        in_array($code, [51, 53, 55, 56, 57]) => '🌦️',
        in_array($code, [61, 63, 65, 66, 67, 80, 81, 82]) => '🌧️',
        in_array($code, [71, 73, 75, 77, 85, 86]) => '❄️',
        in_array($code, [95, 96, 99])    => '⛈️',
        default                          => '🌡️',
    };
}
