<?php
/**
 * Stock Data Proxy — fetches quotes from Yahoo Finance.
 * GET /api/stocks.php?symbols=AAPL,MSFT
 * Symbols pulled from DB if not passed explicitly.
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
if (!empty($_GET['symbols'])) {
    // Strict whitelist: only uppercase letters, digits, dot, hyphen, caret
    // Max 10 symbols via query string (DB query is the normal path)
    $raw     = strtoupper(substr($_GET['symbols'], 0, 200));
    $raw     = preg_replace('/[^A-Z0-9,.\-^]/', '', $raw);
    $symbols = array_slice(array_filter(explode(',', $raw)), 0, 10);
} else {
    $rows    = db()->query('SELECT symbol FROM stock_symbols ORDER BY sort_order LIMIT 20')->fetchAll();
    $symbols = array_column($rows, 'symbol');
}

if (!$symbols) {
    echo json_encode([]);
    exit;
}

$cache_key = 'stocks_' . implode(',', $symbols);
$cached    = cache_get($cache_key);
if ($cached !== null) {
    echo json_encode($cached, JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Fetch from Yahoo Finance ──────────────────────────────────────────────────
$sym_str = implode('%2C', array_map('urlencode', $symbols));
$url     = "https://query1.finance.yahoo.com/v7/finance/quote?symbols={$sym_str}"
         . "&fields=symbol,shortName,regularMarketPrice,regularMarketChange,"
         . "regularMarketChangePercent,regularMarketPreviousClose,"
         . "regularMarketOpen,regularMarketDayHigh,regularMarketDayLow";

$body = curl_fetch($url);
$data = [];

if ($body) {
    $json = json_decode($body, true);

    // Validate response structure before touching it
    if (is_array($json)
        && isset($json['quoteResponse']['result'])
        && is_array($json['quoteResponse']['result'])) {

        foreach ($json['quoteResponse']['result'] as $q) {
            if (!is_array($q)) continue;

            // Validate and sanitize each field with strict type checking
            $symbol = isset($q['symbol']) && is_string($q['symbol'])
                    ? strtoupper(preg_replace('/[^A-Z0-9.\-^]/', '', $q['symbol']))
                    : null;
            if (!$symbol || strlen($symbol) > 20) continue;

            $name = isset($q['shortName']) && is_string($q['shortName'])
                  ? mb_substr(strip_tags($q['shortName']), 0, 100)
                  : $symbol;

            // All numeric fields: cast explicitly, reject non-numeric
            $price     = _safe_float($q['regularMarketPrice']         ?? null);
            $change    = _safe_float($q['regularMarketChange']        ?? null);
            $changePct = _safe_float($q['regularMarketChangePercent'] ?? null);
            $prevClose = _safe_float($q['regularMarketPreviousClose'] ?? null);
            $open      = _safe_float($q['regularMarketOpen']         ?? null);
            $high      = _safe_float($q['regularMarketDayHigh']      ?? null);
            $low       = _safe_float($q['regularMarketDayLow']       ?? null);

            $data[] = [
                'symbol'    => $symbol,
                'name'      => $name,
                'price'     => $price,
                'change'    => $change,
                'changePct' => $changePct,
                'prevClose' => $prevClose,
                'open'      => $open,
                'high'      => $high,
                'low'       => $low,
                'direction' => $change >= 0 ? 'up' : 'down',
            ];
        }
    }
}

cache_set($cache_key, $data, STOCK_CACHE_TTL);
echo json_encode($data, JSON_UNESCAPED_UNICODE);

// ── Helper ────────────────────────────────────────────────────────────────────
function _safe_float(mixed $v): float {
    if (!isset($v) || !is_numeric($v)) return 0.0;
    $f = (float)$v;
    // Reject NaN / Infinity which json_encode turns into null
    if (!is_finite($f)) return 0.0;
    return round($f, 2);
}
