<?php
/**
 * Stock Data Proxy — fetches quotes via Yahoo Finance v8 chart API.
 * GET /api/stocks.php
 * Symbols pulled from the stock_symbols table.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/portal_auth.php';
portal_require_login_api();

header('Content-Type: application/json; charset=utf-8');
// Use private cache when auth is required so proxy caches never store authenticated data
$cache_visibility = portal_auth_enabled() ? 'private' : 'public';
header('Cache-Control: ' . $cache_visibility . ', max-age=' . STOCK_CACHE_TTL);

// ── Symbol resolution ─────────────────────────────────────────────────────────
$rows    = db()->query('SELECT symbol FROM stock_symbols ORDER BY sort_order LIMIT 20')->fetchAll();
$symbols = array_column($rows, 'symbol');

if (!$symbols) {
    echo json_encode(['quotes' => [], 'no_symbols' => true]);
    exit;
}

$cache_key = 'stocks_v8_' . implode(',', $symbols);
$cached    = cache_get($cache_key);
if ($cached !== null) {
    echo json_encode($cached, JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Fetch from Yahoo Finance v8 chart API (one request per symbol) ────────────
// The v8 chart endpoint is more stable than v7 quote and does not require auth.
$data = [];

foreach ($symbols as $sym) {
    $sym = strtoupper(preg_replace('/[^A-Z0-9.\-^]/', '', $sym));
    if (!$sym || strlen($sym) > 20) continue;

    $url = 'https://query1.finance.yahoo.com/v8/finance/chart/' . urlencode($sym)
         . '?range=1d&interval=1d&includePrePost=false';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS=> CURLPROTO_HTTP | CURLPROTO_HTTPS,
        // Browser-like User-Agent; Yahoo Finance blocks non-browser agents on some endpoints
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json, text/plain, */*',
            'Accept-Language: en-US,en;q=0.9',
            'Referer: https://finance.yahoo.com/',
        ],
        CURLOPT_ENCODING       => 'gzip,deflate',
        CURLOPT_HEADERFUNCTION => fn($ch, $h) => strlen($h),
    ]);

    $body = curl_exec($ch);
    $err  = curl_errno($ch);
    curl_close($ch);

    if ($err !== 0 || !$body) continue;

    $json = json_decode($body, true);
    if (!is_array($json)) continue;

    $meta = $json['chart']['result'][0]['meta'] ?? null;
    if (!is_array($meta)) continue;

    $symbol_out = isset($meta['symbol']) && is_string($meta['symbol'])
        ? strtoupper(preg_replace('/[^A-Z0-9.\-^]/', '', $meta['symbol']))
        : $sym;

    $name = '';
    foreach (['shortName', 'longName'] as $k) {
        if (isset($meta[$k]) && is_string($meta[$k]) && $meta[$k] !== '') {
            $name = mb_substr(strip_tags($meta[$k]), 0, 100);
            break;
        }
    }
    if (!$name) $name = $symbol_out;

    $price  = _safe_float($meta['regularMarketPrice']   ?? null);
    $prev   = _safe_float($meta['chartPreviousClose']   ?? $meta['previousClose'] ?? null);
    $open   = _safe_float($meta['regularMarketOpen']    ?? null);
    $high   = _safe_float($meta['regularMarketDayHigh'] ?? null);
    $low    = _safe_float($meta['regularMarketDayLow']  ?? null);

    // Calculate change from previous close if not directly provided
    $change    = isset($meta['regularMarketChange'])
        ? _safe_float($meta['regularMarketChange'])
        : round($price - $prev, 2);
    $changePct = isset($meta['regularMarketChangePercent'])
        ? _safe_float($meta['regularMarketChangePercent'])
        : ($prev > 0 ? round(($change / $prev) * 100, 2) : 0.0);

    $data[] = [
        'symbol'    => $symbol_out,
        'name'      => $name,
        'price'     => $price,
        'change'    => $change,
        'changePct' => $changePct,
        'prevClose' => $prev,
        'open'      => $open,
        'high'      => $high,
        'low'       => $low,
        'direction' => $change >= 0 ? 'up' : 'down',
    ];
}

$response = ['quotes' => $data, 'no_symbols' => false];
cache_set($cache_key, $response, STOCK_CACHE_TTL);
echo json_encode($response, JSON_UNESCAPED_UNICODE);

// ── Helper ────────────────────────────────────────────────────────────────────
function _safe_float(mixed $v): float {
    if (!isset($v) || !is_numeric($v)) return 0.0;
    $f = (float)$v;
    if (!is_finite($f)) return 0.0;
    return round($f, 2);
}
