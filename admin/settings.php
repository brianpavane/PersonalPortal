<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_login();

$db   = db();
$msg  = '';
$errs = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) die('Invalid CSRF token.');

    $section = $_POST['section'] ?? '';

    // ── Stocks ──────────────────────────────────────────────────────────────
    if ($section === 'stocks') {
        // Delete all and reinsert (simple approach)
        $db->exec('DELETE FROM stock_symbols');
        $symbols = array_filter(array_map('trim', explode("\n", $_POST['symbols'] ?? '')));
        $stmt    = $db->prepare('INSERT IGNORE INTO stock_symbols (symbol,label,sort_order) VALUES (?,?,?)');
        foreach (array_values($symbols) as $i => $line) {
            // Format: SYMBOL[|Label]
            [$sym, $label] = array_pad(explode('|', $line, 2), 2, '');
            $sym = strtoupper(preg_replace('/[^A-Z0-9.\-^]/', '', $sym));
            if ($sym) $stmt->execute([$sym, trim($label), $i]);
        }
        $msg = 'Stock symbols saved.';
    }

    // ── News Feeds ───────────────────────────────────────────────────────────
    if ($section === 'feeds_add') {
        $name = trim($_POST['feed_name'] ?? '');
        $url  = trim($_POST['feed_url']  ?? '');
        if (!$name || !$url) $errs[] = 'Feed name and URL are required.';
        if ($url && !filter_var($url, FILTER_VALIDATE_URL)) $errs[] = 'Invalid feed URL.';
        // Restrict to http/https — reject file://, gopher://, ftp://, etc.
        if ($url && !in_array(strtolower((string)parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            $errs[] = 'Feed URL must use http:// or https://.';
        }
        if (!$errs) {
            $max = $db->query('SELECT COALESCE(MAX(sort_order),0)+1 FROM news_feeds')->fetchColumn();
            $db->prepare('INSERT INTO news_feeds (name,url,sort_order) VALUES (?,?,?)')->execute([$name,$url,$max]);
            $msg = 'Feed added.';
        }
    }

    if ($section === 'feeds_toggle') {
        $feed_id = (int)($_POST['feed_id'] ?? 0);
        $active  = (int)($_POST['active']  ?? 0);
        $db->prepare('UPDATE news_feeds SET active=? WHERE id=?')->execute([$active, $feed_id]);
        $msg = 'Feed updated.';
    }

    if ($section === 'feeds_delete') {
        $db->prepare('DELETE FROM news_feeds WHERE id=?')->execute([(int)($_POST['feed_id'] ?? 0)]);
        $msg = 'Feed removed.';
    }

    // Clear news cache
    if (in_array($section, ['feeds_add','feeds_toggle','feeds_delete'])) {
        array_map('unlink', glob(CACHE_DIR . '/news_*.cache'));
    }
    if ($section === 'stocks') {
        array_map('unlink', glob(CACHE_DIR . '/stocks_*.cache'));
    }

    // ── Weather Cities ────────────────────────────────────────────────────────
    if ($section === 'weather') {
        $cities = [];
        for ($i = 1; $i <= 3; $i++) {
            $name = mb_substr(trim($_POST['city_name_' . $i] ?? ''), 0, 100);
            $lat  = (float)($_POST['city_lat_' . $i] ?? 0);
            $lon  = (float)($_POST['city_lon_' . $i] ?? 0);
            if (!$name || $lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) continue;
            $cities[] = ['name' => $name, 'lat' => round($lat, 4), 'lon' => round($lon, 4)];
        }
        $unit = ($_POST['weather_unit'] ?? 'fahrenheit') === 'celsius' ? 'celsius' : 'fahrenheit';
        $db->prepare("REPLACE INTO portal_settings (setting_key,setting_value) VALUES ('weather_cities',?)")
           ->execute([json_encode($cities)]);
        $db->prepare("REPLACE INTO portal_settings (setting_key,setting_value) VALUES ('weather_unit',?)")
           ->execute([$unit]);
        // Bust weather cache
        @unlink(CACHE_DIR . '/' . md5('weather_v1') . '.cache');
        $msg = 'Weather cities saved.';
    }

    // ── Timezone Zones ────────────────────────────────────────────────────────
    if ($section === 'timezones') {
        $zones = [];
        for ($i = 1; $i <= 10; $i++) {
            $label = mb_substr(trim($_POST['tz_label_' . $i] ?? ''), 0, 60);
            $tz    = mb_substr(trim($_POST['tz_zone_'  . $i] ?? ''), 0, 60);
            // Validate IANA timezone name by attempting to create a DateTimeZone
            if (!$label || !$tz) continue;
            try {
                new DateTimeZone($tz);
            } catch (Exception $e) {
                $errs[] = "Invalid timezone: " . htmlspecialchars($tz);
                continue;
            }
            $zones[] = ['label' => $label, 'tz' => $tz];
        }
        if (!$errs) {
            $db->prepare("REPLACE INTO portal_settings (setting_key,setting_value) VALUES ('timezone_zones',?)")
               ->execute([json_encode($zones)]);
            $msg = 'Timezone zones saved.';
        }
    }
}

// Load data
$symbols = $db->query('SELECT * FROM stock_symbols ORDER BY sort_order')->fetchAll();
$feeds   = $db->query('SELECT * FROM news_feeds ORDER BY sort_order, name')->fetchAll();

// Load weather cities config
$weather_cities_raw = $db->query("SELECT setting_value FROM portal_settings WHERE setting_key='weather_cities'")->fetchColumn() ?: '[]';
$weather_cities     = json_decode($weather_cities_raw, true) ?: [];
while (count($weather_cities) < 3) $weather_cities[] = ['name' => '', 'lat' => '', 'lon' => ''];
$weather_unit       = $db->query("SELECT setting_value FROM portal_settings WHERE setting_key='weather_unit'")->fetchColumn() ?: 'fahrenheit';

// Load timezone zones config
$tz_zones_raw = $db->query("SELECT setting_value FROM portal_settings WHERE setting_key='timezone_zones'")->fetchColumn() ?: '[]';
$tz_zones     = json_decode($tz_zones_raw, true) ?: [];
while (count($tz_zones) < 10) $tz_zones[] = ['label' => '', 'tz' => ''];

// Build textarea text
$sym_text = implode("\n", array_map(fn($s) => $s['symbol'] . ($s['label'] ? '|' . $s['label'] : ''), $symbols));

$page_title = 'Settings';
$active_nav = 'settings';
include __DIR__ . '/_layout.php';
?>

<div class="page-header">
  <div class="page-title">Settings <small>Stocks &amp; News Feeds</small></div>
</div>

<?php if ($msg):  ?><div class="alert alert-success"><?= h($msg) ?></div><?php endif; ?>
<?php if ($errs): ?><div class="alert alert-danger"><?= implode('<br>', array_map('h', $errs)) ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;align-items:start">

  <!-- Stock Symbols -->
  <div class="card">
    <div class="card-header">&#128200; Stock Symbols</div>
    <div class="card-body">
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="section" value="stocks">
        <div class="form-group">
          <label class="form-label">Symbols (one per line)</label>
          <textarea name="symbols" class="form-control" rows="10" style="font-family:monospace"
                    placeholder="AAPL|Apple&#10;MSFT|Microsoft&#10;SPY"><?= h($sym_text) ?></textarea>
          <div class="form-hint">Format: <code>SYMBOL</code> or <code>SYMBOL|Label</code> — e.g. <code>AAPL|Apple</code></div>
        </div>
        <button type="submit" class="btn btn-success">Save Symbols</button>
      </form>
    </div>
  </div>

  <!-- News Feeds -->
  <div>
    <div class="card" style="margin-bottom:1rem">
      <div class="card-header">&#128240; News Feeds</div>
      <div style="padding:0">
        <?php if ($feeds): ?>
        <table style="width:100%">
          <thead><tr>
            <th>Name</th><th>Active</th><th></th>
          </tr></thead>
          <tbody>
          <?php foreach ($feeds as $f): ?>
          <tr>
            <td style="font-size:.85rem">
              <?= h($f['name']) ?><br>
              <small style="color:var(--text-muted);font-size:.72rem"><?= h(mb_substr($f['url'],0,50)) ?>…</small>
            </td>
            <td>
              <form method="post" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="section" value="feeds_toggle">
                <input type="hidden" name="feed_id" value="<?= $f['id'] ?>">
                <input type="hidden" name="active" value="<?= $f['active'] ? 0 : 1 ?>">
                <button type="submit" class="btn btn-sm <?= $f['active'] ? 'btn-success' : 'btn-secondary' ?>">
                  <?= $f['active'] ? 'ON' : 'OFF' ?>
                </button>
              </form>
            </td>
            <td>
              <form method="post" style="display:inline" onsubmit="return confirm('Remove this feed?')">
                <?= csrf_field() ?>
                <input type="hidden" name="section" value="feeds_delete">
                <input type="hidden" name="feed_id" value="<?= $f['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm btn-icon">✕</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php else: ?>
        <p style="padding:1rem;color:var(--text-muted);font-size:.85rem">No feeds configured. Add one below.</p>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-header">Add News Feed</div>
      <div class="card-body">
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="section" value="feeds_add">
          <div class="form-group">
            <label class="form-label">Feed Name</label>
            <input type="text" name="feed_name" class="form-control" placeholder="Reuters" required>
          </div>
          <div class="form-group">
            <label class="form-label">RSS URL</label>
            <input type="url" name="feed_url" class="form-control"
                   placeholder="https://feeds.reuters.com/reuters/topNews" required>
          </div>
          <button type="submit" class="btn btn-primary">Add Feed</button>
        </form>
      </div>
    </div>
  </div>

</div>

<!-- Weather + Timezone in a grid -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;align-items:start;margin-top:1.5rem">

  <!-- Weather Cities -->
  <div class="card">
    <div class="card-header">&#127748; Weather Cities <small style="font-weight:400;color:var(--text-muted)">(up to 3)</small></div>
    <div class="card-body">
      <form method="post" id="weather-form">
        <?= csrf_field() ?>
        <input type="hidden" name="section" value="weather">

        <div class="form-group" style="margin-bottom:.5rem">
          <label class="form-label">Temperature Unit</label>
          <label style="display:inline-flex;align-items:center;gap:.4rem;margin-right:1rem">
            <input type="radio" name="weather_unit" value="fahrenheit" <?= $weather_unit !== 'celsius' ? 'checked' : '' ?>> °F
          </label>
          <label style="display:inline-flex;align-items:center;gap:.4rem">
            <input type="radio" name="weather_unit" value="celsius" <?= $weather_unit === 'celsius' ? 'checked' : '' ?>> °C
          </label>
        </div>

        <?php for ($i = 1; $i <= 3; $i++): ?>
        <?php $c = $weather_cities[$i-1]; ?>
        <div style="border:1px solid var(--border-subtle);border-radius:6px;padding:.75rem;margin-bottom:.75rem">
          <div style="font-size:.75rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;margin-bottom:.5rem">
            City <?= $i ?>
            <button type="button" class="btn btn-sm btn-secondary"
                    style="margin-left:.5rem;font-size:.7rem;padding:.15rem .4rem"
                    onclick="geocodeCity(<?= $i ?>)" title="Auto-fill coordinates from city name">Locate &#128205;</button>
          </div>
          <div class="form-group" style="margin-bottom:.4rem">
            <input type="text" id="city_name_<?= $i ?>" name="city_name_<?= $i ?>" class="form-control"
                   value="<?= h((string)($c['name'] ?? '')) ?>" placeholder="e.g. New York" maxlength="100">
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:.4rem">
            <input type="number" id="city_lat_<?= $i ?>" name="city_lat_<?= $i ?>" class="form-control"
                   value="<?= h((string)($c['lat'] ?? '')) ?>" placeholder="Latitude" step="0.0001" min="-90" max="90">
            <input type="number" id="city_lon_<?= $i ?>" name="city_lon_<?= $i ?>" class="form-control"
                   value="<?= h((string)($c['lon'] ?? '')) ?>" placeholder="Longitude" step="0.0001" min="-180" max="180">
          </div>
        </div>
        <?php endfor; ?>

        <div class="form-hint">
          Click <strong>Locate</strong> to auto-fill coordinates, or enter them manually.
          Find coordinates at <a href="https://www.latlong.net" target="_blank" rel="noopener">latlong.net</a>.
        </div>
        <button type="submit" class="btn btn-success" style="margin-top:.75rem">Save Weather</button>
      </form>
    </div>
  </div>

  <!-- Timezone Zones -->
  <div class="card">
    <div class="card-header">&#127758; World Clock Timezones <small style="font-weight:400;color:var(--text-muted)">(up to 10)</small></div>
    <div class="card-body">
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="section" value="timezones">

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.25rem;margin-bottom:.25rem">
          <span style="font-size:.7rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;padding-left:.2rem">Label</span>
          <span style="font-size:.7rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;padding-left:.2rem">IANA Timezone</span>
        </div>

        <?php for ($i = 1; $i <= 10; $i++): ?>
        <?php $z = $tz_zones[$i-1]; ?>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.4rem;margin-bottom:.4rem">
          <input type="text" id="tz_label_<?= $i ?>" name="tz_label_<?= $i ?>" class="form-control"
                 value="<?= h((string)($z['label'] ?? '')) ?>" placeholder="e.g. San Francisco" maxlength="60"
                 list="tz-city-list" autocomplete="off"
                 onchange="tzLabelChanged(<?= $i ?>)">
          <input type="text" id="tz_zone_<?= $i ?>" name="tz_zone_<?= $i ?>" class="form-control"
                 value="<?= h((string)($z['tz'] ?? '')) ?>" placeholder="e.g. America/Los_Angeles"
                 maxlength="60" list="tz-list" autocomplete="off"
                 onchange="tzZoneChanged(<?= $i ?>)">
        </div>
        <?php endfor; ?>

        <!-- IANA timezone datalist -->
        <datalist id="tz-list">
          <option value="America/New_York">
          <option value="America/Chicago">
          <option value="America/Denver">
          <option value="America/Phoenix">
          <option value="America/Los_Angeles">
          <option value="America/Anchorage">
          <option value="America/Honolulu">
          <option value="America/Toronto">
          <option value="America/Vancouver">
          <option value="America/Sao_Paulo">
          <option value="America/Mexico_City">
          <option value="Europe/London">
          <option value="Europe/Paris">
          <option value="Europe/Berlin">
          <option value="Europe/Rome">
          <option value="Europe/Madrid">
          <option value="Europe/Amsterdam">
          <option value="Europe/Zurich">
          <option value="Europe/Stockholm">
          <option value="Europe/Moscow">
          <option value="Asia/Dubai">
          <option value="Asia/Karachi">
          <option value="Asia/Kolkata">
          <option value="Asia/Dhaka">
          <option value="Asia/Bangkok">
          <option value="Asia/Singapore">
          <option value="Asia/Shanghai">
          <option value="Asia/Tokyo">
          <option value="Asia/Seoul">
          <option value="Australia/Perth">
          <option value="Australia/Sydney">
          <option value="Australia/Melbourne">
          <option value="Pacific/Auckland">
          <option value="Pacific/Honolulu">
          <option value="UTC">
        </datalist>

        <!-- City name datalist for label field -->
        <datalist id="tz-city-list">
          <option value="New York">
          <option value="Chicago">
          <option value="Denver">
          <option value="Phoenix">
          <option value="Los Angeles">
          <option value="San Francisco">
          <option value="Anchorage">
          <option value="Honolulu">
          <option value="Toronto">
          <option value="Vancouver">
          <option value="São Paulo">
          <option value="Mexico City">
          <option value="London">
          <option value="Paris">
          <option value="Berlin">
          <option value="Rome">
          <option value="Madrid">
          <option value="Amsterdam">
          <option value="Zurich">
          <option value="Stockholm">
          <option value="Moscow">
          <option value="Dubai">
          <option value="Karachi">
          <option value="Mumbai">
          <option value="Bangalore">
          <option value="Dhaka">
          <option value="Bangkok">
          <option value="Singapore">
          <option value="Shanghai">
          <option value="Tokyo">
          <option value="Seoul">
          <option value="Perth">
          <option value="Sydney">
          <option value="Melbourne">
          <option value="Auckland">
          <option value="UTC">
        </datalist>

        <div class="form-hint">Type a city name in Label to auto-fill the timezone, or type the IANA TZ to auto-fill the label. Check <a href="https://en.wikipedia.org/wiki/List_of_tz_database_time_zones" target="_blank" rel="noopener">full TZ list</a>.</div>
        <button type="submit" class="btn btn-success" style="margin-top:.75rem">Save Timezones</button>
      </form>
    </div>
  </div>

</div><!-- /weather+tz grid -->

<script>
// City name → IANA timezone
const CITY_TO_TZ = {
  'New York':      'America/New_York',
  'Chicago':       'America/Chicago',
  'Denver':        'America/Denver',
  'Phoenix':       'America/Phoenix',
  'Los Angeles':   'America/Los_Angeles',
  'San Francisco': 'America/Los_Angeles',
  'Anchorage':     'America/Anchorage',
  'Honolulu':      'America/Honolulu',
  'Toronto':       'America/Toronto',
  'Vancouver':     'America/Vancouver',
  'São Paulo':     'America/Sao_Paulo',
  'Mexico City':   'America/Mexico_City',
  'London':        'Europe/London',
  'Paris':         'Europe/Paris',
  'Berlin':        'Europe/Berlin',
  'Rome':          'Europe/Rome',
  'Madrid':        'Europe/Madrid',
  'Amsterdam':     'Europe/Amsterdam',
  'Zurich':        'Europe/Zurich',
  'Stockholm':     'Europe/Stockholm',
  'Moscow':        'Europe/Moscow',
  'Dubai':         'Asia/Dubai',
  'Karachi':       'Asia/Karachi',
  'Mumbai':        'Asia/Kolkata',
  'Bangalore':     'Asia/Kolkata',
  'Dhaka':         'Asia/Dhaka',
  'Bangkok':       'Asia/Bangkok',
  'Singapore':     'Asia/Singapore',
  'Shanghai':      'Asia/Shanghai',
  'Tokyo':         'Asia/Tokyo',
  'Seoul':         'Asia/Seoul',
  'Perth':         'Australia/Perth',
  'Sydney':        'Australia/Sydney',
  'Melbourne':     'Australia/Melbourne',
  'Auckland':      'Pacific/Auckland',
  'UTC':           'UTC',
};

// IANA timezone → display label
const TZ_TO_LABEL = {
  'America/New_York':    'New York',
  'America/Chicago':     'Chicago',
  'America/Denver':      'Denver',
  'America/Phoenix':     'Phoenix',
  'America/Los_Angeles': 'Los Angeles',
  'America/Anchorage':   'Anchorage',
  'America/Honolulu':    'Honolulu',
  'America/Toronto':     'Toronto',
  'America/Vancouver':   'Vancouver',
  'America/Sao_Paulo':   'São Paulo',
  'America/Mexico_City': 'Mexico City',
  'Europe/London':       'London',
  'Europe/Paris':        'Paris',
  'Europe/Berlin':       'Berlin',
  'Europe/Rome':         'Rome',
  'Europe/Madrid':       'Madrid',
  'Europe/Amsterdam':    'Amsterdam',
  'Europe/Zurich':       'Zurich',
  'Europe/Stockholm':    'Stockholm',
  'Europe/Moscow':       'Moscow',
  'Asia/Dubai':          'Dubai',
  'Asia/Karachi':        'Karachi',
  'Asia/Kolkata':        'Mumbai / Bangalore',
  'Asia/Dhaka':          'Dhaka',
  'Asia/Bangkok':        'Bangkok',
  'Asia/Singapore':      'Singapore',
  'Asia/Shanghai':       'Shanghai',
  'Asia/Tokyo':          'Tokyo',
  'Asia/Seoul':          'Seoul',
  'Australia/Perth':     'Perth',
  'Australia/Sydney':    'Sydney',
  'Australia/Melbourne': 'Melbourne',
  'Pacific/Auckland':    'Auckland',
  'Pacific/Honolulu':    'Honolulu',
  'UTC':                 'UTC',
};

function tzLabelChanged(i) {
  const labelEl = document.getElementById('tz_label_' + i);
  const zoneEl  = document.getElementById('tz_zone_'  + i);
  const tz = CITY_TO_TZ[labelEl.value.trim()];
  if (tz && !zoneEl.value.trim()) zoneEl.value = tz;
}

function tzZoneChanged(i) {
  const labelEl = document.getElementById('tz_label_' + i);
  const zoneEl  = document.getElementById('tz_zone_'  + i);
  const label = TZ_TO_LABEL[zoneEl.value.trim()];
  if (label && !labelEl.value.trim()) labelEl.value = label;
}

// Geocode city name or zip code using Nominatim (OpenStreetMap)
async function geocodeCity(slot) {
  const nameInput = document.getElementById('city_name_' + slot);
  const latInput  = document.getElementById('city_lat_'  + slot);
  const lonInput  = document.getElementById('city_lon_'  + slot);
  const query = nameInput.value.trim();
  if (!query) { alert('Enter a city name or zip code first.'); return; }

  try {
    const url = 'https://nominatim.openstreetmap.org/search?q=' + encodeURIComponent(query)
              + '&format=json&limit=1&addressdetails=1';
    const res  = await fetch(url, { headers: { 'Accept-Language': 'en' } });
    const data = await res.json();
    const r    = data && data[0];
    if (r) {
      latInput.value = parseFloat(r.lat).toFixed(4);
      lonInput.value = parseFloat(r.lon).toFixed(4);
      // Build a tidy display name: city + state/region + country code
      const a = r.address || {};
      const city    = a.city || a.town || a.village || a.county || '';
      const region  = a.state || '';
      const country = a.country_code ? a.country_code.toUpperCase() : '';
      const parts   = [city, region, country].filter(Boolean);
      if (parts.length) nameInput.value = parts.join(', ');
    } else {
      alert('Location not found. Try a different city name, zip/postal code, or enter coordinates manually.');
    }
  } catch(e) {
    alert('Geocoding unavailable. Please enter coordinates manually.');
  }
}
</script>

<?php include __DIR__ . '/_layout_end.php'; ?>
