<?php
/**
 * Stock Data Proxy — fetches quotes via Yahoo Finance v8 chart API.
 * GET /api/stocks.php
 * Symbols pulled from the stock_symbols table.
 */

// Buffer all output so PHP warnings/notices never corrupt the JSON response
ob_start();

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/portal_auth.php';
portal_require_login_api();

// Discard any buffered warnings before sending headers
ob_clean();

header('Content-Type: application/json; charset=utf-8');
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

// ── Obtain Yahoo Finance crumb + cookies (required since ~2024) ───────────────
$yf_cred = _yf_get_crumb();

// ── Fetch from Yahoo Finance v8 chart API (one request per symbol) ────────────
$data = [];
$ua   = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';

foreach ($symbols as $sym) {
    $sym = strtoupper(preg_replace('/[^A-Z0-9.\-^]/', '', $sym));
    if (!$sym || strlen($sym) > 20) continue;

    $url = 'https://query2.finance.yahoo.com/v8/finance/chart/' . urlencode($sym)
         . '?range=1d&interval=1d&includePrePost=false'
         . ($yf_cred ? '&crumb=' . urlencode($yf_cred['crumb']) : '');

    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_REDIR_PROTOCOLS=> CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_USERAGENT      => $ua,
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json, text/plain, */*',
            'Accept-Language: en-US,en;q=0.9',
            'Referer: https://finance.yahoo.com/',
        ],
        CURLOPT_ENCODING       => 'gzip,deflate',
    ];
    if ($yf_cred) {
        $opts[CURLOPT_COOKIEFILE] = $yf_cred['cookie_file'];
    }
    curl_setopt_array($ch, $opts);

    $body     = curl_exec($ch);
    $curl_err = curl_errno($ch);
    curl_close($ch);

    if ($curl_err !== 0 || !$body) continue;

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

// ── Yahoo Finance crumb helper ────────────────────────────────────────────────
/**
 * Fetches (and caches) a Yahoo Finance crumb + cookie file.
 * Returns ['crumb' => string, 'cookie_file' => string] or null on failure.
 */
function _yf_get_crumb(): ?array {
    $cookie_file = CACHE_DIR . '/yf_cookies.txt';
    $crumb_file  = CACHE_DIR . '/yf_crumb.txt';
    $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';

    // Return cached crumb if it exists and is less than 1 hour old
    if (file_exists($crumb_file) && file_exists($cookie_file)
        && (time() - filemtime($crumb_file)) < 3600) {
        $crumb = trim((string)file_get_contents($crumb_file));
        if ($crumb !== '') return ['crumb' => $crumb, 'cookie_file' => $cookie_file];
    }

    // Step 1: Visit Yahoo Finance home to obtain session cookies
    $ch = curl_init('https://finance.yahoo.com/');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT      => $ua,
        CURLOPT_COOKIEJAR      => $cookie_file,
        CURLOPT_COOKIEFILE     => $cookie_file,
        CURLOPT_HTTPHEADER     => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.9',
        ],
    ]);
    curl_exec($ch);
    curl_close($ch);

    // Step 2: Fetch crumb using the session cookies
    $ch = curl_init('https://query2.finance.yahoo.com/v1/test/getcrumb');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT      => $ua,
        CURLOPT_COOKIEFILE     => $cookie_file,
        CURLOPT_HTTPHEADER     => [
            'Accept: */*',
            'Referer: https://finance.yahoo.com/',
        ],
    ]);
    $crumb     = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!$crumb || $http_code !== 200 || strlen($crumb) > 50 || str_contains((string)$crumb, '<')) {
        return null;
    }

    $crumb = trim((string)$crumb);
    file_put_contents($crumb_file, $crumb);
    return ['crumb' => $crumb, 'cookie_file' => $cookie_file];
}

// ── Helper ────────────────────────────────────────────────────────────────────
function _safe_float($v): float {
    if (!isset($v) || !is_numeric($v)) return 0.0;
    $f = (float)$v;
    if (!is_finite($f)) return 0.0;
    return round($f, 2);
}
