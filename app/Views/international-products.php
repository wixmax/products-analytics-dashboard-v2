<!DOCTYPE html>
<html lang="ar" dir="rtl">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>منتجات اليابان والصين | Overview Insights</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="<?= base_url('index.css') ?>?v=1.2" />
    <style>
      /* Custom tweaks for this page */
      .app-shell {
        grid-template-columns: 320px 1fr;
      }
      .origin-filters {
        display: flex;
        flex-direction: column;
        gap: 10px;
      }
      .origin-btn {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 15px;
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-md);
        cursor: pointer;
        transition: var(--transition-all);
        text-align: right;
        font-weight: 600;
        color: var(--color-text-main);
      }
      .origin-btn:hover {
        border-color: var(--color-primary);
        background: var(--bg-primary-soft);
      }
      .origin-btn i {
        font-size: 1.5rem;
      }
      .origin-btn .count {
        margin-right: auto;
        font-size: 0.8rem;
        background: var(--bg-input);
        padding: 2px 8px;
        border-radius: var(--radius-full);
        color: var(--color-text-muted);
      }
    </style>
  </head>
  <body>
    <div class="app-shell">
      <!-- Sidebar Panel -->
      <aside class="sidebar">
        <div class="logo-container">
          <div class="logo-icon">🌏</div>
          <div class="logo-text">
            <h1>المنتجات العالمية</h1>
            <p>استكشاف منتجات اليابان والصين</p>
          </div>
        </div>

        <div class="form-group">
          <label>🔍 بحث سريع</label>
          <input type="text" id="search-input" placeholder="ابحث عن منتج..." oninput="searchProducts()">
        </div>

        <div class="form-group">
          <label>🌍 تصفية حسب المنشأ</label>
          <div class="origin-filters">
            <button class="origin-btn" onclick="filterByOrigin('all')">
              <span>🌐 الكل</span>
              <span class="count" id="kpi-total">0</span>
            </button>
            <button class="origin-btn" onclick="selectCountry('Japan')">
              <span>🇯🇵 اليابان (Makuake)</span>
              <span class="count" id="kpi-japan">0</span>
            </button>
            <button class="origin-btn" onclick="selectCountry('China')">
              <span>🇨🇳 الصين (Alibaba)</span>
              <span class="count" id="kpi-china">0</span>
            </button>
          </div>
        </div>

        <div class="form-group">
            <label>📁 استيراد ملفات JSON</label>
            <div class="file-input-wrapper">
                <button class="btn btn-secondary btn-block">
                    استيراد ملف محلي
                </button>
                <input type="file" id="local-file-input" accept=".json" onchange="handleLocalFile(event)" />
            </div>
        </div>

        <div style="margin-top: auto;">
          <a href="<?= base_url('/') ?>" class="btn btn-secondary btn-block">
            ⬅️ العودة للوحة الرئيسية
          </a>
        </div>
      </aside>

      <!-- Main Area -->
      <main class="main-content">
        <div class="top-nav">
          <div>
            <h2 style="font-weight: 800; font-size: 1.6rem;">لوحة المنتجات العالمية</h2>
            <p style="color: var(--color-text-muted); font-size: 0.85rem">عرض وتحليل أحدث المنتجات من الأسواق اليابانية والصينية</p>
          </div>
          <div class="actions-group">
            <a href="<?= base_url('settings') ?>" class="btn btn-secondary">⚙️ الإعدادات</a>
            <button class="theme-toggle" id="theme-toggle-btn">🌓</button>
          </div>
        </div>

        <!-- Product Rendering Grid -->
        <div class="products-grid" id="products-container">
          <!-- Products will be loaded here -->
        </div>

        <!-- Pagination -->
        <div id="pagination-container" class="pagination-container"></div>
      </main>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toast-container"></div>

    <script src="<?= base_url('international-products.js') ?>"></script>
  </body>
</html>
