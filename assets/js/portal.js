/**
 * PersonalPortal — Main Frontend Script
 */

/* ── Clock ─────────────────────────────────────────────── */
(function initClock() {
  const timeEl = document.getElementById('clock-time');
  const dateEl = document.getElementById('clock-date');
  if (!timeEl) return;

  function tick() {
    const now = new Date();
    timeEl.textContent = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    if (dateEl) {
      dateEl.textContent = now.toLocaleDateString([], {
        weekday: 'short', month: 'short', day: 'numeric', year: 'numeric'
      });
    }
  }
  tick();
  setInterval(tick, 1000);
})();


/* ── Bookmarks ─────────────────────────────────────────── */
(function initBookmarks() {
  const container = document.getElementById('bookmarks-container');
  if (!container) return;

  async function loadBookmarks() {
    container.innerHTML = '<div class="stock-loading"><span class="spinner"></span> Loading bookmarks…</div>';
    try {
      const res  = await fetch('api/bookmarks.php');
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


/* ── Stocks ─────────────────────────────────────────────── */
(function initStocks() {
  const container = document.getElementById('stocks-container');
  const tape      = document.getElementById('ticker-inner');
  if (!container) return;

  let interval = null;

  async function loadStocks() {
    try {
      const res    = await fetch('api/stocks.php');
      const quotes = await res.json();
      renderStocks(quotes);
      if (tape) renderTicker(quotes);
    } catch (e) {
      container.innerHTML = '<div class="stock-error">Stock data unavailable.</div>';
    }
  }

  function renderStocks(quotes) {
    if (!quotes.length) {
      container.innerHTML = '<div class="stock-loading">No symbols configured. <a href="admin/settings.php">Add in Admin</a>.</div>';
      return;
    }
    container.innerHTML = quotes.map(q => `
      <div class="stock-row" title="Open: $${q.open}  High: $${q.high}  Low: $${q.low}  Prev Close: $${q.prevClose}">
        <span class="stock-symbol">${escHtml(q.symbol)}</span>
        <span class="stock-name">${escHtml(q.name)}</span>
        <span class="stock-price">$${q.price.toFixed(2)}</span>
        <span class="stock-change ${q.direction}">${q.change >= 0 ? '+' : ''}${q.change.toFixed(2)} (${q.changePct >= 0 ? '+' : ''}${q.changePct.toFixed(2)}%)</span>
      </div>`).join('');
  }

  function renderTicker(quotes) {
    tape.innerHTML = quotes.map(q =>
      `<span class="ticker-item"><span class="ts">${escHtml(q.symbol)}</span> $${q.price.toFixed(2)} <span class="${q.direction}">${q.change >= 0 ? '▲' : '▼'} ${Math.abs(q.changePct).toFixed(2)}%</span></span>`
    ).join('');
  }

  loadStocks();
  // Refresh every 5 minutes
  interval = setInterval(loadStocks, 5 * 60 * 1000);
  document.getElementById('refresh-stocks')?.addEventListener('click', loadStocks);
})();


/* ── News ───────────────────────────────────────────────── */
(function initNews() {
  const container = document.getElementById('news-container');
  if (!container) return;

  async function loadNews() {
    container.innerHTML = '<div class="news-loading"><span class="spinner"></span> Loading news…</div>';
    try {
      const res   = await fetch('api/news.php?limit=30');
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
  // Refresh every 10 minutes
  setInterval(loadNews, 10 * 60 * 1000);
  document.getElementById('refresh-news')?.addEventListener('click', loadNews);
})();


/* ── Notes ──────────────────────────────────────────────── */
(function initNotes() {
  const container = document.getElementById('notes-container');
  if (!container) return;

  async function loadNotes() {
    try {
      const res   = await fetch('api/notes.php');
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


/* ── Utilities ──────────────────────────────────────────── */
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
