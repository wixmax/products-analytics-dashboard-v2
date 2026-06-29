<!doctype html>
<html lang="ar" dir="rtl">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Snapshots | لقطات البيانات</title>
    <meta name="description" content="عرض وإدارة لقطات البيانات المحفوظة من API." />

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap"
      rel="stylesheet"
    />

    <link rel="stylesheet" href="<?= base_url('index.css') ?>?v=1.1" />
    <style>
      .snapshots-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 24px;
      }
      .snapshots-header h2 {
        font-weight: 800;
        font-size: 1.5rem;
      }
      .snapshots-controls {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
      }
      .snapshots-controls select,
      .snapshots-controls button {
        padding: 0.5rem 1rem;
        border-radius: var(--radius-sm);
        border: 1px solid var(--border-color);
        background: var(--bg-input);
        color: var(--color-text-main);
        font-family: var(--font-sans);
        font-size: 0.85rem;
        cursor: pointer;
      }
      .snapshot-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-md);
        padding: 16px 20px;
        margin-bottom: 12px;
        transition: var(--transition-all);
      }
      .snapshot-card:hover {
        border-color: var(--color-primary);
        box-shadow: var(--shadow-md);
      }
      .snapshot-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 16px;
        align-items: center;
        margin-bottom: 10px;
      }
      .snapshot-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 3px 10px;
        border-radius: var(--radius-full);
        font-size: 0.75rem;
        font-weight: 600;
        background: var(--bg-primary-soft);
        color: var(--color-primary);
      }
      .snapshot-badge.origin-Local { background: rgba(16,185,129,0.12); color: var(--color-success); }
      .snapshot-badge.origin-Winning { background: rgba(245,158,11,0.12); color: var(--color-warning); }
      .snapshot-badge.origin-China { background: rgba(239,68,68,0.12); color: var(--color-error); }
      .snapshot-badge.origin-Japan { background: rgba(99,102,241,0.12); color: #6366f1; }
      .snapshot-date {
        font-size: 0.8rem;
        color: var(--color-text-muted);
      }
      .snapshot-stats {
        display: flex;
        gap: 20px;
        font-size: 0.85rem;
      }
      .snapshot-stats span {
        display: flex;
        align-items: center;
        gap: 4px;
      }
      .snapshot-stats .stat-label {
        color: var(--color-text-muted);
      }
      .snapshot-stats .stat-value {
        font-weight: 700;
      }
      .snapshot-actions {
        display: flex;
        gap: 8px;
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px solid var(--border-color);
      }
      .snapshot-actions button {
        padding: 0.4rem 1rem;
        font-size: 0.8rem;
        border-radius: var(--radius-sm);
        border: 1px solid var(--border-color);
        background: var(--bg-card);
        color: var(--color-text-main);
        cursor: pointer;
        transition: var(--transition-all);
        font-family: var(--font-sans);
      }
      .snapshot-actions button:hover {
        border-color: var(--color-primary);
        background: var(--bg-primary-soft);
      }
      .snapshot-actions .btn-restore {
        background: var(--color-success);
        color: white;
        border-color: var(--color-success);
      }
      .snapshot-actions .btn-restore:hover {
        background: #059669;
        border-color: #059669;
      }
      .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: var(--color-text-muted);
      }
      .empty-state .empty-icon {
        font-size: 3rem;
        margin-bottom: 12px;
      }
      #snapshot-json-modal .modal-card {
        max-width: 90vw;
        width: 900px;
      }
      #snapshot-json-modal pre {
        background: var(--bg-input);
        padding: 16px;
        border-radius: var(--radius-sm);
        font-family: var(--font-mono);
        font-size: 0.75rem;
        overflow: auto;
        max-height: 60vh;
        direction: ltr;
        text-align: left;
        white-space: pre-wrap;
        word-break: break-word;
        border: 1px solid var(--border-color);
      }
      .loading-spinner {
        text-align: center;
        padding: 40px;
        color: var(--color-text-muted);
      }
      .version-filter {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-bottom: 16px;
      }
      .version-filter .version-chip {
        padding: 4px 12px;
        border-radius: var(--radius-full);
        border: 1px solid var(--border-color);
        font-size: 0.78rem;
        cursor: pointer;
        transition: var(--transition-all);
        background: var(--bg-card);
        color: var(--color-text-muted);
      }
      .version-filter .version-chip:hover,
      .version-filter .version-chip.active {
        border-color: var(--color-primary);
        background: var(--bg-primary-soft);
        color: var(--color-primary);
      }
    </style>
  </head>
  <body>
    <div class="app-shell">
      <!-- Sidebar Panel -->
      <aside class="sidebar">
        <div class="logo-container">
          <div class="logo-icon">📸</div>
          <div class="logo-text">
            <h1>لقطات البيانات</h1>
            <p>إدارة نسخ JSON المحفوظة</p>
          </div>
        </div>
        <div style="margin-top: 20px; display: flex; flex-direction: column; gap: 8px">
          <a href="<?= base_url('/') ?>" class="btn btn-secondary btn-block" style="text-align: center">
            🔙 العودة للوحة الرئيسية
          </a>
          <a href="<?= base_url('saved-ads') ?>" class="btn btn-secondary btn-block" style="text-align: center">
            ⭐ الإعلانات المحفوظة
          </a>
          <a href="<?= base_url('international-products') ?>" class="btn btn-primary btn-block" style="text-align: center">
            🌏 منتجات الصين واليابان
          </a>
          <a href="<?= base_url('settings') ?>" class="btn btn-secondary btn-block" style="text-align: center">
            ⚙️ الإعدادات
          </a>
        </div>
      </aside>

      <!-- Main Area -->
      <main class="main-content">
        <div class="top-nav">
          <div>
            <h2 style="font-weight: 800; font-size: 1.6rem;">
              📸 لقطات البيانات (Snapshots)
            </h2>
            <p style="color: var(--color-text-muted); font-size: 0.85rem">
              جميع نسخ JSON المحفوظة عند كل عملية جلب من API، مرتبة حسب الأحدث
            </p>
          </div>
        </div>

        <!-- Controls -->
        <div class="snapshots-controls" style="margin-bottom: 16px">
          <select id="filter-origin" onchange="loadSnapshots()">
            <option value="">جميع المصادر</option>
            <option value="Local">محلي (Local)</option>
            <option value="Winning">رابحة (Winning)</option>
            <option value="China">الصين (China)</option>
            <option value="Japan">اليابان (Japan)</option>
          </select>
          <button class="btn btn-secondary" onclick="loadSnapshots()">
            🔄 تحديث
          </button>
          <button class="btn btn-primary" onclick="runSync()" id="sync-btn">
            🚀 Sync from API
          </button>
        </div>

        <!-- Version chips -->
        <div class="version-filter" id="version-filter"></div>

        <!-- Snapshots list -->
        <div id="snapshots-container">
          <div class="loading-spinner">⏳ جاري تحميل اللقطات...</div>
        </div>
      </main>
    </div>

    <!-- JSON Viewer Modal -->
    <div class="modal-overlay" id="snapshot-json-modal" style="display: none">
      <div class="modal-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px">
          <h3 style="font-weight: 700">📄 محتوى JSON للقطة</h3>
          <button style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--color-text-main)" onclick="closeJsonModal()">×</button>
        </div>
        <div style="margin-bottom: 8px; font-size: 0.8rem; color: var(--color-text-muted)" id="json-modal-info"></div>
        <pre id="json-modal-content">...</pre>
        <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 12px">
          <button class="btn btn-secondary" onclick="closeJsonModal()">إغلاق</button>
          <button class="btn btn-primary" onclick="copyJsonContent()">📋 نسخ JSON</button>
        </div>
      </div>
    </div>

    <!-- Toast -->
    <div class="toast-container" id="toast-container"></div>

    <script src="<?= base_url('snapshots.js') ?>?v=1.0"></script>
  </body>
</html>
