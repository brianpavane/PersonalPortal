/**
 * PersonalPortal — Main Frontend Script
 */

/* ── World Clock ────────────────────────────────────────────── */
(function initClocks() {
  const zones     = window.PORTAL_TIMEZONES || [];
  const container = document.getElementById('clocks-container');
  if (!container || !zones.length) return;

  function renderClocks() {
    const now = new Date();
    container.innerHTML = zones.map(z => {
      if (!z.tz || !z.label) return '';
      try {
        const timeStr = new Intl.DateTimeFormat('en-US', {
          timeZone: z.tz, hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false
        }).format(now);
        const dateStr = new Intl.DateTimeFormat('en-US', {
          timeZone: z.tz, weekday: 'short', month: 'short', day: 'numeric'
        }).format(now);
        return `<div class="clock-card">
          <div class="clock-label">${escHtml(z.label)}</div>
          <div class="clock-time">${escHtml(timeStr)}</div>
          <div class="clock-date">${escHtml(dateStr)}</div>
        </div>`;
      } catch (e) {
        return `<div class="clock-card">
          <div class="clock-label">${escHtml(z.label)}</div>
          <div class="clock-time" style="color:var(--accent-red);font-size:.8rem">Invalid TZ</div>
        </div>`;
      }
    }).join('');
  }

  renderClocks();
  setInterval(renderClocks, 1000);
})();


/* ── Bookmarks ─────────────────────────────────────────────── */
(function initBookmarks() {
  const container = document.getElementById('bookmarks-container');
  if (!container) return;

  async function loadBookmarks() {
    container.innerHTML = '<div class="stock-loading"><span class="spinner"></span> Loading bookmarks…</div>';
    try {
      const res  = await fetch('api/bookmarks.php');
      if (!res.ok) { container.innerHTML = '<div class="stock-error">Auth required. <a href="portal_login.php">Sign in</a>.</div>'; return; }
      const cats = await res.json();
      renderBookmarks(cats);
    } catch (e) {
      container.innerHTML = '<div class="stock-error">Failed to load bookmarks.</div>';
    }
  }

  function renderBookmarks(cats) {
    if (!cats.length) {
      container.innerHTML = '<p style="color:var(--text-muted);padding:1rem">No bookmarks yet. <a href="admin/">Add some in Admin</a>.</p>';
      return;
    }
    container.innerHTML = '';
    cats.forEach(cat => {
      const div = document.createElement('div');
      div.className = 'bm-category';
      const bms = (cat.bookmarks || []).map(bm => `
        <li class="bm-item" title="${escHtml(bm.description || bm.url)}">
          <img class="bm-favicon" src="https://www.google.com/s2/favicons?domain=${encodeURIComponent(new URL(bm.url).hostname)}&sz=32"
               onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 16 16%22><rect width=%2216%22 height=%2216%22 rx=%222%22 fill=%22%2330363d%22/></svg>'"
               alt="" loading="lazy" width="16" height="16">
          <a class="bm-link" href="${escHtml(bm.url)}" target="_blank" rel="noopener noreferrer">${escHtml(bm.title)}</a>
        </li>`
      ).join('');
      div.innerHTML = `
        <div class="bm-category-header">
          <span class="bm-category-dot" style="background:${escHtml(cat.color)}"></span>
          <span class="bm-category-name">${escHtml(cat.name)}</span>
        </div>
        <ul class="bm-list">${bms || '<li style="color:var(--text-muted);font-size:.8rem;padding:.3rem 0">Empty category</li>'}</ul>
      `;
      container.appendChild(div);
    });
  }

  loadBookmarks();
  document.getElementById('refresh-bookmarks')?.addEventListener('click', loadBookmarks);
})();


/* ── Stocks ─────────────────────────────────────────────────── */
(function initStocks() {
  const container = document.getElementById('stocks-container');
  const tape      = document.getElementById('ticker-inner');
  if (!container && !tape) return;

  async function loadStocks() {
    try {
      const res  = await fetch('api/stocks.php');
      if (!res.ok) {
        if (container) container.innerHTML = '<div class="stock-error">Auth required.</div>';
        return;
      }
      const data = await res.json();

      // API returns {quotes: [...], no_symbols: bool}
      // Handle both old array format and new object format
      const quotes     = Array.isArray(data) ? data : (data.quotes || []);
      const no_symbols = Array.isArray(data) ? false : (data.no_symbols === true);

      if (container) renderStocks(quotes, no_symbols);
      if (tape)      renderTicker(quotes);
    } catch (e) {
      if (container) container.innerHTML = '<div class="stock-error">Stock data unavailable.</div>';
      if (tape)      tape.innerHTML = '<span class="ticker-item" style="color:var(--text-muted)">Market data unavailable</span>';
    }
  }

  function renderStocks(quotes, no_symbols) {
    if (no_symbols) {
      container.innerHTML = '<div class="stock-loading">No symbols configured. <a href="admin/settings.php">Add in Admin</a>.</div>';
      return;
    }
    if (!quotes.length) {
      container.innerHTML = '<div class="stock-loading">Market data unavailable — check back shortly.</div>';
      return;
    }
    container.innerHTML = quotes.map(q => `
      <div class="stock-row" title="Open: $${q.open?.toFixed(2)}  High: $${q.high?.toFixed(2)}  Low: $${q.low?.toFixed(2)}  Prev Close: $${q.prevClose?.toFixed(2)}">
        <span class="stock-symbol">${escHtml(q.symbol)}</span>
        <span class="stock-name">${escHtml(q.name)}</span>
        <span class="stock-price">$${q.price.toFixed(2)}</span>
        <span class="stock-change ${q.direction}">${q.change >= 0 ? '+' : ''}${q.change.toFixed(2)} (${q.changePct >= 0 ? '+' : ''}${q.changePct.toFixed(2)}%)</span>
      </div>`).join('');
  }

  function renderTicker(quotes) {
    if (!quotes.length) return;
    tape.innerHTML = quotes.map(q =>
      `<span class="ticker-item"><span class="ts">${escHtml(q.symbol)}</span> $${q.price.toFixed(2)} <span class="${q.direction}">${q.change >= 0 ? '▲' : '▼'} ${Math.abs(q.changePct).toFixed(2)}%</span></span>`
    ).join('');
  }

  loadStocks();
  setInterval(loadStocks, 5 * 60 * 1000);
  document.getElementById('refresh-stocks')?.addEventListener('click', loadStocks);
})();


/* ── Weather ────────────────────────────────────────────────── */
(function initWeather() {
  const container = document.getElementById('weather-container');
  if (!container) return;

  async function loadWeather() {
    container.innerHTML = '<div class="stock-loading"><span class="spinner"></span></div>';
    try {
      const res   = await fetch('api/weather.php');
      if (!res.ok) { container.innerHTML = '<div class="stock-loading">Auth required.</div>'; return; }
      const items = await res.json();
      renderWeather(items);
    } catch (e) {
      container.innerHTML = '<div class="stock-error">Weather data unavailable.</div>';
    }
  }

  function renderWeather(items) {
    if (!items.length) {
      container.innerHTML = '<div class="stock-loading">No cities configured. <a href="admin/settings.php">Add in Admin</a>.</div>';
      return;
    }
    container.innerHTML = '<div class="weather-grid">' + items.map(city => {
      if (city.error) {
        return `<div class="weather-card">
          <div class="weather-city">${escHtml(city.name)}</div>
          <div class="weather-icon">⚠️</div>
          <div class="weather-condition" style="color:var(--accent-red)">Unavailable</div>
        </div>`;
      }
      return `<div class="weather-card">
        <div class="weather-city">${escHtml(city.name)}</div>
        <div class="weather-icon">${city.icon}</div>
        <div class="weather-temp">${city.temp}${escHtml(city.unit)}</div>
        <div class="weather-condition">${escHtml(city.condition)}</div>
        <div class="weather-details">
          Feels ${city.feels_like}${escHtml(city.unit)} &bull;
          ${city.humidity}% RH &bull;
          ${city.wind} mph wind
        </div>
      </div>`;
    }).join('') + '</div>';
  }

  loadWeather();
  setInterval(loadWeather, 15 * 60 * 1000); // refresh every 15 min
  document.getElementById('refresh-weather')?.addEventListener('click', loadWeather);
})();


/* ── News ───────────────────────────────────────────────────── */
(function initNews() {
  const container = document.getElementById('news-container');
  if (!container) return;

  async function loadNews() {
    container.innerHTML = '<div class="news-loading"><span class="spinner"></span> Loading news…</div>';
    try {
      const res   = await fetch('api/news.php?limit=30');
      if (!res.ok) { container.innerHTML = '<div class="news-loading">Auth required.</div>'; return; }
      const items = await res.json();
      renderNews(items);
    } catch (e) {
      container.innerHTML = '<div class="news-loading" style="color:var(--accent-red)">News unavailable.</div>';
    }
  }

  function renderNews(items) {
    if (!items.length) {
      container.innerHTML = '<div class="news-loading">No news feeds configured. <a href="admin/settings.php">Add feeds</a>.</div>';
      return;
    }
    const ul = document.createElement('ul');
    ul.className = 'news-list';
    ul.innerHTML = items.map(item => `
      <li class="news-item">
        <a class="news-title" href="${escHtml(item.url)}" target="_blank" rel="noopener noreferrer">${escHtml(item.title)}</a>
        <div class="news-meta">
          <span class="news-source">${escHtml(item.source)}</span>
          ${item.date ? `<span>${escHtml(item.date)}</span>` : ''}
        </div>
      </li>`).join('');
    container.innerHTML = '';
    container.appendChild(ul);
  }

  loadNews();
  setInterval(loadNews, 10 * 60 * 1000);
  document.getElementById('refresh-news')?.addEventListener('click', loadNews);
})();


/* ── Notes ──────────────────────────────────────────────────── */
(function initNotes() {
  const container = document.getElementById('notes-container');
  if (!container) return;

  async function loadNotes() {
    try {
      const res   = await fetch('api/notes.php');
      if (!res.ok) { container.innerHTML = '<p style="color:var(--text-muted)">Auth required.</p>'; return; }
      const notes = await res.json();
      renderNotes(notes);
    } catch (e) {
      container.innerHTML = '<p style="color:var(--text-muted)">Could not load notes.</p>';
    }
  }

  function renderNotes(notes) {
    if (!notes.length) {
      container.innerHTML = '<p style="color:var(--text-muted);font-size:.85rem">No notes yet. <a href="admin/notes.php">Add notes in Admin</a>.</p>';
      return;
    }
    container.innerHTML = '<div class="notes-grid">' +
      notes.map(n => `
        <div class="note-card" style="border-top-color:${escHtml(n.color)}">
          <div class="note-title">${escHtml(n.title)}</div>
          <div class="note-body">${bulletsToHtml(n.content)}</div>
          ${n.updated_at ? `<div class="note-updated">Updated ${escHtml(n.updated_at.substring(0,10))}</div>` : ''}
        </div>`
      ).join('') + '</div>';
  }

  loadNotes();
  document.getElementById('refresh-notes')?.addEventListener('click', loadNotes);
})();


/* ── Utilities ──────────────────────────────────────────────── */
function escHtml(str) {
  const d = document.createElement('div');
  d.textContent = String(str ?? '');
  return d.innerHTML;
}

function bulletsToHtml(text) {
  const lines = String(text || '').split('\n');
  let html = '', inList = false;
  lines.forEach(rawLine => {
    const line = rawLine.replace(/\r$/, '');
    if (line === '') {
      if (inList) { html += '</ul>'; inList = false; }
      return;
    }
    if (line.startsWith('## ')) {
      if (inList) { html += '</ul>'; inList = false; }
      html += `<h4>${escHtml(line.slice(3))}</h4>`;
      return;
    }
    const bulletMatch = line.match(/^[-*]\s+(.+)$/);
    if (bulletMatch) {
      if (!inList) { html += '<ul>'; inList = true; }
      html += `<li>${escHtml(bulletMatch[1])}</li>`;
      return;
    }
    if (inList) { html += '</ul>'; inList = false; }
    html += `<p>${escHtml(line)}</p>`;
  });
  if (inList) html += '</ul>';
  return html;
}
