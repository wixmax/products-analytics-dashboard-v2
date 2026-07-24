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

    // Detect duplicates by api_version (same version = multiple snapshots)
    renderDuplicateAlert(allSnapshots);
    renderSnapshots(allSnapshots);
    renderVersionFilter(allSnapshots);
  } catch (e) {
    container.innerHTML = `<div class="empty-state"><div class="empty-icon">⚠️</div><h3>خطأ في تحميل البيانات</h3><p>${e.message}</p></div>`;
  }
}

/**
 * Detect REAL duplicate snapshots using data_hash (identical content).
 * Two snapshots are duplicates only if they share the same data_hash value.
 */
function renderDuplicateAlert(snapshots) {
  const alertContainer = document.getElementById('duplicate-alert-container');
  if (!alertContainer) return;

  // Group by data_hash — only non-null hashes
  const hashGroups = {};
  snapshots.forEach(s => {
    if (!s.data_hash) return; // skip records with no hash (legacy without hash)
    if (!hashGroups[s.data_hash]) hashGroups[s.data_hash] = [];
    hashGroups[s.data_hash].push(s);
  });

  // Keep only groups with more than one snapshot (true duplicates)
  const duplicateGroups = Object.entries(hashGroups).filter(([, arr]) => arr.length > 1);

  if (duplicateGroups.length === 0) {
    alertContainer.innerHTML = '';
    return;
  }

  const totalExtra = duplicateGroups.reduce((sum, [, arr]) => sum + (arr.length - 1), 0);

  let rows = '';
  duplicateGroups.forEach(([hash, arr]) => {
    // Sort ascending by id so the first (lowest id) is the "original"
    const sorted = [...arr].sort((a, b) => a.id - b.id);
    const keepId = sorted[0].id;
    const deleteIds = sorted.slice(1).map(s => s.id);
    const versions = [...new Set(arr.map(s => s.api_version).filter(Boolean))];
    const versionLabel = versions.length ? versions.join(', ') : '(بدون إصدار)';

    rows += `<li style="margin: 6px 0; padding: 6px 10px; background: rgba(0,0,0,0.15); border-radius: 6px;">
      <span style="font-size:0.78rem; color: var(--color-text-muted);">🔑 Hash: <code style="font-size:0.75rem;">${hash.slice(0, 12)}…</code></span>
      &nbsp;|&nbsp; <strong>${arr.length} نسخ</strong>
      &nbsp;|&nbsp; الإصدار(ات): <strong>${versionLabel}</strong><br>
      <span style="font-size:0.79rem;">
        ✅ احتفظ بـ: <strong>#${keepId}</strong>
        &nbsp;—&nbsp;
        🗑️ يمكن حذف: <strong>${deleteIds.map(id => `#${id}`).join(', ')}</strong>
      </span>
    </li>`;
  });

  alertContainer.innerHTML = `
    <div style="
      background: rgba(245,158,11,0.1);
      border: 1px solid rgba(245,158,11,0.45);
      border-right: 4px solid #f59e0b;
      border-radius: 8px;
      padding: 14px 18px;
      margin-bottom: 16px;
    ">
      <div style="display:flex; align-items:center; gap:10px; margin-bottom: 8px;">
        <span style="font-size:1.2rem;">⚠️</span>
        <strong style="color:#f59e0b; font-size:0.95rem;">
          تم اكتشاف ${duplicateGroups.length} مجموعة نسخ مكررة — ${totalExtra} نسخة زائدة تحتاج للحذف
        </strong>
      </div>
      <p style="font-size:0.82rem; color:var(--color-text-muted); margin-bottom:10px;">
        النسخ التالية تمتلك نفس البصمة الرقمية (data_hash)، مما يعني أنها تحتوي على بيانات متطابقة تماماً.
        يُنصح بالاحتفاظ بالنسخة الأقدم (ID الأصغر) وحذف التكرارات.
      </p>
      <ul style="list-style: none; padding: 0; font-size:0.83rem; color: var(--color-text-main);">
        ${rows}
      </ul>
    </div>
  `;
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

  // Build a set of data_hashes that appear more than once (true duplicates)
  const hashCounts = {};
  snapshots.forEach(s => {
    if (!s.data_hash) return;
    hashCounts[s.data_hash] = (hashCounts[s.data_hash] || 0) + 1;
  });
  // For each hash, track which id to keep (lowest id = original)
  const hashKeepId = {};
  const hashTotal = {};
  const hashGrouped = {};
  snapshots.forEach(s => {
    if (!s.data_hash || hashCounts[s.data_hash] <= 1) return;
    if (!hashGrouped[s.data_hash]) hashGrouped[s.data_hash] = [];
    hashGrouped[s.data_hash].push(s.id);
  });
  Object.entries(hashGrouped).forEach(([hash, ids]) => {
    const sorted = [...ids].sort((a, b) => a - b);
    hashKeepId[hash] = sorted[0]; // lowest id = original to keep
    hashTotal[hash] = sorted.length;
  });

  let html = '';
  snapshots.forEach(s => {
    const date = s.created_at ? new Date(s.created_at + 'Z').toLocaleString('ar-SA', { timeZone: 'UTC' }) : '--';
    const originLabel = { Local: 'محلي', Winning: 'رابحة', China: 'الصين', Japan: 'اليابان' }[s.origin] || s.origin;

    // A snapshot is a TRUE duplicate if its hash appears multiple times AND it's not the "keep" copy
    const isDuplicate = s.data_hash
      && (hashCounts[s.data_hash] || 0) > 1
      && s.id !== hashKeepId[s.data_hash];
    const isOriginalWithDupe = s.data_hash
      && (hashCounts[s.data_hash] || 0) > 1
      && s.id === hashKeepId[s.data_hash];
    const totalForHash = hashTotal[s.data_hash] || 1;

    const duplicateBadge = isDuplicate ? `
      <span style="
        background: rgba(245,158,11,0.15);
        color: #f59e0b;
        border: 1px solid rgba(245,158,11,0.4);
        font-size: 0.72rem;
        padding: 2px 8px;
        border-radius: 99px;
        font-weight: 700;
      " title="هذه نسخة مكررة (data_hash متطابق مع ${totalForHash} نسخ) — يُنصح بحذفها">
        ⚠️ نسخة مكررة
      </span>
    ` : isOriginalWithDupe ? `
      <span style="
        background: rgba(16,185,129,0.12);
        color: #10b981;
        border: 1px solid rgba(16,185,129,0.35);
        font-size: 0.72rem;
        padding: 2px 8px;
        border-radius: 99px;
        font-weight: 600;
      " title="هذه النسخة الأصلية التي يُنصح بالاحتفاظ بها (${totalForHash} نسخ متطابقة)">
        ✅ الأصل (احتفظ بها)
      </span>
    ` : '';

    const cardBorder = isDuplicate
      ? 'border-right: 3px solid #f59e0b;'
      : isOriginalWithDupe
        ? 'border-right: 3px solid #10b981;'
        : '';

    html += `
      <div class="snapshot-card" style="${cardBorder}">
        <div class="snapshot-meta">
          <span class="snapshot-badge origin-${s.origin}">📌 ${originLabel}</span>
          ${s.api_version ? `<span class="snapshot-badge" style="background:rgba(99,102,241,0.1);color:#6366f1">🔖 v${s.api_version}</span>` : ''}
          ${duplicateBadge}
          <span class="snapshot-date">🕐 ${date}</span>
        </div>
        <div class="snapshot-stats">
          <span><span class="stat-label">🆔</span> <span class="stat-value">#${s.id}</span></span>
          <span><span class="stat-label">📦 المنتجات:</span> <span class="stat-value">${s.product_count ?? 0}</span></span>
          ${s.data_hash ? `<span style="font-size:0.75rem; color:var(--color-text-muted);">🔑 <code>${s.data_hash.slice(0, 10)}…</code></span>` : ''}
        </div>
        <div class="snapshot-actions">
          <button onclick="viewSnapshotJson(${s.id})">📄 عرض JSON</button>
          <button onclick="exportSingleSnapshot(${s.id})">📥 تصدير</button>
          <button class="btn-restore" onclick="restoreSnapshot(${s.id})">🔄 استعادة</button>
          ${window.userIsAdmin ? `<button style="background:var(--color-error);color:white;border-color:var(--color-error)" onclick="deleteSnapshot(${s.id})">🗑️ حذف</button>` : ''}
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

async function exportSingleSnapshot(id) {
  try {
    const res = await fetch(`/api/products/snapshots/${id}`);
    if (!res.ok) throw new Error('فشل جلب اللقطة');
    const s = await res.json();
    
    // Save metadata wrapper so it can be restored directly without asking for version
    const exportData = {
      is_snapshot_backup: true,
      origin: s.origin,
      api_version: s.api_version,
      raw_json: s.raw_json
    };
    
    const dataStr = JSON.stringify(exportData, null, 2);
    const blob = new Blob([dataStr], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `snapshot_${s.id}_${s.origin}_${s.api_version || 'noversion'}_${(s.created_at || '').slice(0, 10)}.json`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    showToast('✅ تم تصدير اللقطة مع البيانات الوصفية');
  } catch (e) {
    showToast('⚠️ ' + e.message, 'error');
  }
}

async function exportSnapshotsJSON() {
  showToast('⏳ جاري تجهيز بيانات التصدير...', 'info');
  try {
    const res = await fetch('/api/products/snapshots?include_raw=1');
    if (!res.ok) throw new Error('فشل جلب البيانات');
    const data = await res.json();
    if (!data || data.length === 0) {
      showToast('لا توجد لقطات للتصدير', 'warning');
      return;
    }
    const dataStr = JSON.stringify(data, null, 2);
    const blob = new Blob([dataStr], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `snapshots_${new Date().toISOString().slice(0, 10)}.json`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    showToast('✅ تم تصدير اللقطات مع البيانات الكاملة');
  } catch (e) {
    showToast('⚠️ ' + e.message, 'error');
  }
}

async function importSnapshotFile(event) {
  const file = event.target.files[0];
  if (!file) return;
  event.target.value = '';

  try {
    const text = await file.text();
    let parsed;
    try {
      parsed = JSON.parse(text);
    } catch (e) {
      throw new Error('الملف ليس بتنسيق JSON صالح');
    }

    // 1. Check if bulk snapshots export (Array of snapshots)
    if (Array.isArray(parsed)) {
      if (parsed.length === 0) {
        showToast('⚠️ ملف فارغ لا يحتوي على أي لقطات بيانات', 'error');
        return;
      }
      showToast('⏳ جاري استيراد اللقطات المتعددة...', 'info');
      const res = await fetch('/api/products/snapshots/import', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(parsed)
      });
      if (!res.ok) throw new Error('فشل استيراد الملف المجمع');
      const result = await res.json();
      showToast(`✅ ${result.message}`);
      loadSnapshots();
      return;
    }

    // 2. Check if single snapshot backup with metadata wrapper
    if (parsed && (parsed.is_snapshot_backup || (parsed.raw_json !== undefined && parsed.origin !== undefined))) {
      showToast('⏳ جاري استيراد اللقطة...', 'info');
      const res = await fetch('/api/products/snapshots/import', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          raw_json: parsed.raw_json,
          origin: parsed.origin || 'Local',
          api_version: parsed.api_version || ''
        })
      });
      if (!res.ok) throw new Error('فشل استيراد اللقطة');
      const result = await res.json();
      showToast(`✅ تم استيراد اللقطة #${result.id} بنجاح`);
      loadSnapshots();
      return;
    }

    // 3. Fallback: Raw products JSON from API or other source - ask user
    const origin = prompt('أدخل المصدر (Local, Winning, China, Japan):', 'Local') || 'Local';
    const apiVersion = prompt('أدخل إصدار API (اختياري):', '') || '';

    const res = await fetch('/api/products/snapshots/import', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ raw_json: text, origin, api_version: apiVersion })
    });
    if (!res.ok) throw new Error('فشل الاستيراد');
    const result = await res.json();
    showToast(`✅ تم استيراد اللقطة #${result.id} بنجاح`);
    loadSnapshots();
  } catch (e) {
    showToast('⚠️ ' + e.message, 'error');
  }
}

async function deleteSnapshot(id) {
  if (!confirm(`هل أنت متأكد من حذف اللقطة #${id}؟ هذا الإجراء لا يمكن التراجع عنه.`)) return;

  try {
    const res = await fetch(`/api/products/snapshots/${id}/delete`, { method: 'POST' });
    if (!res.ok) {
      let errMsg = 'فشل الحذف';
      try {
        const errData = await res.json();
        if (errData.messages && errData.messages.error) {
          errMsg = errData.messages.error;
        } else if (errData.message) {
          errMsg = errData.message;
        } else if (errData.error) {
          errMsg = errData.error;
        }
      } catch (pErr) {}
      throw new Error(errMsg);
    }
    showToast(`✅ تم حذف اللقطة #${id}`);
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

  const localTheme = localStorage.getItem("app-theme");
  if (localTheme) {
    document.documentElement.setAttribute("data-theme", localTheme);
  }

  try {
    const res = await fetch("/api/settings/app-theme");
    if (res.ok) {
      const data = await res.json();
      if (data.value) {
        document.documentElement.setAttribute("data-theme", data.value);
        localStorage.setItem("app-theme", data.value);
      }
    }
  } catch (err) {
    console.error("Error fetching theme setting:", err);
  }

  btn.onclick = async () => {
    const isDark = document.documentElement.getAttribute("data-theme") === "dark";
    const nextTheme = isDark ? "light" : "dark";
    document.documentElement.setAttribute("data-theme", nextTheme);
    localStorage.setItem("app-theme", nextTheme);
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

function initBackToTop() {
  if (document.getElementById("back-to-top-btn")) return;
  const btn = document.createElement("button");
  btn.id = "back-to-top-btn";
  btn.className = "back-to-top-btn";
  btn.setAttribute("aria-label", "التوجه إلى الأعلى");
  btn.setAttribute("title", "التوجه إلى الأعلى");
  btn.innerHTML = "⬆️";
  document.body.appendChild(btn);

  const mainContent = document.querySelector(".main-content");

  const toggleBtn = () => {
    const windowScroll = window.scrollY || window.pageYOffset || document.documentElement.scrollTop || document.body.scrollTop || 0;
    const mainScroll = mainContent ? mainContent.scrollTop : 0;
    const currentScroll = Math.max(windowScroll, mainScroll);

    if (currentScroll > 150) {
      btn.classList.add("visible");
    } else {
      btn.classList.remove("visible");
    }
  };

  window.addEventListener("scroll", toggleBtn, { passive: true });
  document.addEventListener("scroll", toggleBtn, { passive: true });
  if (mainContent) {
    mainContent.addEventListener("scroll", toggleBtn, { passive: true });
  }

  toggleBtn();

  btn.addEventListener("click", () => {
    window.scrollTo({ top: 0, behavior: "smooth" });
    document.documentElement.scrollTo({ top: 0, behavior: "smooth" });
    document.body.scrollTo({ top: 0, behavior: "smooth" });
    if (mainContent) {
      mainContent.scrollTo({ top: 0, behavior: "smooth" });
    }
  });
}

// Load on page load
if (document.readyState === "loading") {
  document.addEventListener('DOMContentLoaded', () => {
    setupTheme();
    initBackToTop();
    loadSnapshots();
  });
} else {
  setupTheme();
  initBackToTop();
  loadSnapshots();
}
