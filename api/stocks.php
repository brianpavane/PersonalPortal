<?php
/**
 * Stock Data Proxy — fetches quotes from Yahoo Finance.
 * GET /api/stocks.php?symbols=AAPL,MSFT
 * Symbols pulled from DB if not passed explicitly.
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=' . STOCK_CACHE_TTL);

// Determine symbols
if (!empty($_GET['symbols'])) {
    $raw     = preg_replace('/[^A-Z0-9,.\-^]/', '', strtoupper($_GET['symbols']));
    $symbols = array_slice(array_filter(explode(',', $raw)), 0, 20);
} else {
    $rows    = db()->query('SELECT symbol FROM stock_symbols ORDER BY sort_order')->fetchAll();
    $symbols = array_column($rows, 'symbol');
}

if (!$symbols) {
    echo json_encode([]);
    exit;
}

$cache_key = 'stocks_' . implode(',', $symbols);
$cached    = cache_get($cache_key);
if ($cached !== null) {
    echo json_encode($cached);
    exit;
}

// Fetch from Yahoo Finance v7 quote endpoint
$sym_str  = implode('%2C', array_map('urlencode', $symbols));
$url      = "https://query1.finance.yahoo.com/v7/finance/quote?symbols={$sym_str}&fields=symbol,shortName,regularMarketPrice,regularMarketChange,regularMarketChangePercent,regularMarketPreviousClose,regularMarketOpen,regularMarketDayHigh,regularMarketDayLow,marketCap,fiftyTwoWeekHigh,fiftyTwoWeekLow";

$body = curl_fetch($url);
$data = [];

if ($body) {
    $json = json_decode($body, true);
    $quotes = $json['quoteResponse']['result'] ?? [];
    foreach ($quotes as $q) {
        $change  = round($q['regularMarketChange'] ?? 0, 2);
        $changePct = round($q['regularMarketChangePercent'] ?? 0, 2);
        $data[] = [
            'symbol'    => $q['symbol'],
            'name'      => $q['shortName'] ?? $q['symbol'],
            'price'     => round($q['regularMarketPrice'] ?? 0, 2),
            'change'    => $change,
            'changePct' => $changePct,
            'prevClose' => round($q['regularMarketPreviousClose'] ?? 0, 2),
            'open'      => round($q['regularMarketOpen'] ?? 0, 2),
            'high'      => round($q['regularMarketDayHigh'] ?? 0, 2),
            'low'       => round($q['regularMarketDayLow'] ?? 0, 2),
            'direction' => $change >= 0 ? 'up' : 'down',
        ];
    }
}

cache_set($cache_key, $data, STOCK_CACHE_TTL);
echo json_encode($data, JSON_UNESCAPED_UNICODE);
