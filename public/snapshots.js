let allSnapshots = [];

function showToast(msg, type = 'success') {
  const container = document.getElementById('toast-container');
  const toast = document.createElement('div');
  toast.className = `toast ${type}`;
  toast.textContent = msg;
  container.appendChild(toast);
  setTimeout(() => toast.remove(), 3000);
}

async function loadSnapshots() {
  const container = document.getElementById('snapshots-container');
  container.innerHTML = '<div class="loading-spinner">⏳ جاري تحميل اللقطات...</div>';

  const origin = document.getElementById('filter-origin').value;
  const params = new URLSearchParams();
  if (origin) params.set('origin', origin);

  try {
    const res = await fetch(`/api/products/snapshots?${params.toString()}`);
    if (!res.ok) throw new Error('فشل تحميل اللقطات');
    allSnapshots = await res.json();
    renderSnapshots(allSnapshots);
    renderVersionFilter(allSnapshots);
  } catch (e) {
    container.innerHTML = `<div class="empty-state"><div class="empty-icon">⚠️</div><h3>خطأ في تحميل البيانات</h3><p>${e.message}</p></div>`;
  }
}

function renderVersionFilter(snapshots) {
  const container = document.getElementById('version-filter');
  const versions = [...new Set(snapshots.map(s => s.api_version).filter(Boolean))];
  if (versions.length === 0) {
    container.innerHTML = '';
    return;
  }
  let html = '<span style="font-size:0.8rem;color:var(--color-text-muted);margin-left:8px">🔖 الإصدار:</span>';
  html += '<span class="version-chip active" data-v="" onclick="filterByVersion(this,\'\')">الكل</span>';
  versions.forEach(v => {
    html += `<span class="version-chip" data-v="${v}" onclick="filterByVersion(this,'${v}')">${v}</span>`;
  });
  container.innerHTML = html;
}

function filterByVersion(el, version) {
  document.querySelectorAll('.version-chip').forEach(c => c.classList.remove('active'));
  el.classList.add('active');
  const filtered = version ? allSnapshots.filter(s => s.api_version === version) : allSnapshots;
  renderSnapshots(filtered);
}

function renderSnapshots(snapshots) {
  const container = document.getElementById('snapshots-container');

  if (!snapshots || snapshots.length === 0) {
    container.innerHTML = '<div class="empty-state"><div class="empty-icon">📂</div><h3>لا توجد لقطات</h3><p>لم يتم العثور على أي لقطات بيانات. قم بعملية مزامنة (sync) أولاً.</p></div>';
    return;
  }

  let html = '';
  snapshots.forEach(s => {
    const date = s.created_at ? new Date(s.created_at + 'Z').toLocaleString('ar-SA', { timeZone: 'UTC' }) : '--';
    const originLabel = { Local: 'محلي', Winning: 'رابحة', China: 'الصين', Japan: 'اليابان' }[s.origin] || s.origin;

    html += `
      <div class="snapshot-card">
        <div class="snapshot-meta">
          <span class="snapshot-badge origin-${s.origin}">📌 ${originLabel}</span>
          ${s.api_version ? `<span class="snapshot-badge" style="background:rgba(99,102,241,0.1);color:#6366f1">🔖 v${s.api_version}</span>` : ''}
          <span class="snapshot-date">🕐 ${date}</span>
        </div>
        <div class="snapshot-stats">
          <span><span class="stat-label">🆔</span> <span class="stat-value">#${s.id}</span></span>
          <span><span class="stat-label">📦 المنتجات:</span> <span class="stat-value">${s.product_count ?? 0}</span></span>
        </div>
        <div class="snapshot-actions">
          <button onclick="viewSnapshotJson(${s.id})">📄 عرض JSON</button>
          <button class="btn-restore" onclick="restoreSnapshot(${s.id})">🔄 استعادة</button>
        </div>
      </div>
    `;
  });
  container.innerHTML = html;
}

async function viewSnapshotJson(id) {
  try {
    const res = await fetch(`/api/products/snapshots/${id}`);
    if (!res.ok) throw new Error('فشل تحميل JSON');
    const snapshot = await res.json();

    const info = document.getElementById('json-modal-info');
    info.textContent = `🆔 #${snapshot.id} | ${snapshot.origin} | ${snapshot.api_version ? 'الإصدار: ' + snapshot.api_version : ''} | ${snapshot.product_count ?? 0} منتج`;

    const pre = document.getElementById('json-modal-content');
    try {
      const parsed = JSON.parse(snapshot.raw_json);
      pre.textContent = JSON.stringify(parsed, null, 2);
    } catch {
      pre.textContent = snapshot.raw_json || '(فارغ)';
    }

    document.getElementById('snapshot-json-modal').style.display = 'flex';
  } catch (e) {
    showToast('⚠️ ' + e.message, 'error');
  }
}

function closeJsonModal() {
  document.getElementById('snapshot-json-modal').style.display = 'none';
}

function copyJsonContent() {
  const pre = document.getElementById('json-modal-content');
  navigator.clipboard.writeText(pre.textContent).then(() => {
    showToast('✅ تم نسخ JSON');
  }).catch(() => {
    showToast('⚠️ فشل النسخ', 'error');
  });
}

async function restoreSnapshot(id) {
  if (!confirm(`هل أنت متأكد من استعادة البيانات من اللقطة #${id}؟ سيتم تحديث المنتجات الحالية.`)) return;

  try {
    const res = await fetch(`/api/products/snapshots/${id}/restore`, { method: 'POST' });
    if (!res.ok) throw new Error('فشل الاستعادة');
    const result = await res.json();
    showToast(`✅ تمت الاستعادة: ${result.inserted} إدراج، ${result.updated} تحديث`);
    loadSnapshots();
  } catch (e) {
    showToast('⚠️ ' + e.message, 'error');
  }
}

// Close modal on overlay click
document.addEventListener('click', function(e) {
  const modal = document.getElementById('snapshot-json-modal');
  if (e.target === modal) closeJsonModal();
});

async function runSync() {
  const btn = document.getElementById('sync-btn');
  const originalText = btn.innerHTML;
  btn.innerHTML = '⏳ جاري المزامنة...';
  btn.disabled = true;

  try {
    const res = await fetch('/api/products/sync');
    if (!res.ok) throw new Error('فشلت المزامنة');
    const result = await res.json();
    const stats = result.stats || {};
    let msg = '✅ تمت المزامنة بنجاح';
    const details = [];
    for (const [origin, s] of Object.entries(stats)) {
      details.push(`${origin}: +${s.inserted} ↻${s.updated}`);
    }
    if (details.length) msg += ' (' + details.join(' | ') + ')';
    showToast(msg);
    loadSnapshots();
  } catch (e) {
    showToast('⚠️ ' + e.message, 'error');
  } finally {
    btn.innerHTML = originalText;
    btn.disabled = false;
  }
}

// =========================================
// Theme Engine (same as index.js / saved-ads.js)
// =========================================
async function setupTheme() {
  const btn = document.getElementById("theme-toggle-btn");
  if (!btn) return;

  try {
    const res = await fetch("/api/settings/app-theme");
    if (res.ok) {
      const data = await res.json();
      document.documentElement.setAttribute("data-theme", data.value || "light");
    }
  } catch (err) {
    console.error("Error fetching theme setting:", err);
  }

  btn.onclick = async () => {
    const isDark = document.documentElement.getAttribute("data-theme") === "dark";
    const nextTheme = isDark ? "light" : "dark";
    document.documentElement.setAttribute("data-theme", nextTheme);
    try {
      await fetch("/api/settings", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ key: "app-theme", value: nextTheme }),
      });
    } catch (err) {
      console.error("Error saving theme:", err);
    }
  };
}

// Load on page load
document.addEventListener('DOMContentLoaded', () => {
  setupTheme();
  loadSnapshots();
});
