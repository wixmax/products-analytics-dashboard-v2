<!doctype html>
<html lang="ar" dir="rtl">
  <head>
    <script>
      (function() {
        try {
          var t = localStorage.getItem("app-theme") || "dark";
          document.documentElement.setAttribute("data-theme", t);
        } catch (e) {}
      })();
    </script>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>دليل المنتجات من قاعدة البيانات | Overview Insights</title>
    <meta name="description" content="استعراض وتصفية جميع المنتجات المخزنة مباشرة في قاعدة البيانات بسرعة وكفاءة عالية." />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap"
      rel="stylesheet"
    />

    <link rel="stylesheet" href="https://vjs.zencdn.net/8.16.1/video-js.css" />
    <link rel="stylesheet" href="<?= base_url('index.css') ?>?v=1.6" />
    <style>
      .catalog-metrics-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
      }
      .catalog-metric-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-md);
        padding: 1.25rem;
        display: flex;
        align-items: center;
        gap: 12px;
        box-shadow: var(--shadow-sm);
        transition: var(--transition-all);
      }
      .catalog-metric-card:hover {
        border-color: var(--color-primary);
        box-shadow: var(--shadow-md);
      }
      .catalog-metric-icon {
        width: 44px;
        height: 44px;
        border-radius: var(--radius-sm);
        background: var(--bg-primary-soft);
        color: var(--color-primary);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        flex-shrink: 0;
      }
      .catalog-metric-val {
        font-size: 1.5rem;
        font-weight: 800;
        color: var(--color-text-main);
        line-height: 1.2;
      }
      .catalog-metric-lbl {
        font-size: 0.8rem;
        color: var(--color-text-muted);
        font-weight: 600;
      }

      .filter-toolbar {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-md);
        padding: 1.25rem;
        margin-bottom: 1.5rem;
        display: flex;
        flex-direction: column;
        gap: 1rem;
        box-shadow: var(--shadow-sm);
      }
      .filter-controls-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 12px;
        align-items: end;
      }
      .filter-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
      }
      .filter-group label {
        font-size: 0.8rem;
        font-weight: 700;
        color: var(--color-text-muted);
        display: flex;
        align-items: center;
        gap: 4px;
      }
      .filter-group select,
      .filter-group input {
        padding: 0.55rem 0.8rem;
        border-radius: var(--radius-sm);
        border: 1px solid var(--border-color);
        background: var(--bg-input);
        color: var(--color-text-main);
        font-size: 0.85rem;
        width: 100%;
        transition: var(--transition-all);
      }
      .filter-group select:focus,
      .filter-group input:focus {
        border-color: var(--color-primary);
        outline: none;
      }

      .product-card-origin-badge {
        position: absolute;
        top: 10px;
        right: 10px;
        z-index: 5;
        padding: 4px 8px;
        border-radius: 6px;
        font-size: 0.72rem;
        font-weight: 700;
        background: rgba(15, 23, 42, 0.75);
        backdrop-filter: blur(4px);
        color: #ffffff;
        border: 1px solid rgba(255, 255, 255, 0.2);
      }

      .star-save-btn {
        background: none;
        border: none;
        font-size: 1.4rem;
        cursor: pointer;
        color: var(--color-text-muted);
        transition: transform 0.2s, color 0.2s;
        padding: 4px;
      }
      .star-save-btn.saved {
        color: #f59e0b;
        transform: scale(1.15);
      }
      .star-save-btn:hover {
        transform: scale(1.2);
      }

      .pagination-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 2rem;
        padding: 1rem 1.25rem;
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-md);
        flex-wrap: wrap;
        gap: 1rem;
      }
      .pagination-btns {
        display: flex;
        gap: 6px;
        align-items: center;
      }
      .page-btn {
        padding: 6px 12px;
        border-radius: var(--radius-sm);
        border: 1px solid var(--border-color);
        background: var(--bg-input);
        color: var(--color-text-main);
        font-size: 0.85rem;
        cursor: pointer;
        font-weight: 600;
        transition: var(--transition-all);
      }
      .page-btn.active {
        background: var(--color-primary);
        color: white;
        border-color: var(--color-primary);
      }
      .page-btn:hover:not(.active):not(:disabled) {
        border-color: var(--color-primary);
        color: var(--color-primary);
      }
      .page-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
      }
    </style>
  </head>
  <body>
    <?php if (session()->has('impersonator_user_id')): ?>
      <div style="background: linear-gradient(90deg, #f59e0b, #d97706); color: white; padding: 10px 20px; text-align: center; font-weight: bold; display: flex; justify-content: center; align-items: center; gap: 15px; z-index: 9999; font-size: 0.9rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); width: 100%;">
        <span>⚠️ أنت تتصفح النظام حالياً بصفتك: <strong><?= esc(auth()->user()->username) ?></strong> (محاكاة حساب)</span>
        <a href="<?= base_url('admin/users/stop-impersonating') ?>" style="background: white; color: #b45309; padding: 4px 12px; border-radius: 4px; text-decoration: none; font-size: 0.8rem; font-weight: 700; transition: all 0.2s;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background='white'">العودة لحساب المسؤول 🚪</a>
      </div>
    <?php endif; ?>
    <div class="app-shell">
      <?= $this->include('partials/sidebar', ['subtitle' => 'دليل قاعدة البيانات']) ?>

      <!-- Main Content Area -->
      <main class="main-content">
        <!-- Top Nav Header -->
        <div class="top-nav">
          <div>
            <h2 style="font-weight: 800; font-size: 1.6rem; letter-spacing: -0.01em;">
              🛍️ دليل المنتجات المباشر من قاعدة البيانات (Database Catalog)
            </h2>
            <p style="color: var(--color-text-muted); font-size: 0.85rem">
              عرض واستعلام كافة سجلات المنتجات المخزنة بالنظام مباشرة مع التصفية والبحث الفوري بدون تعقيدات.
            </p>
          </div>

          <div class="actions-group">
            <button class="btn btn-primary" onclick="openAiAnalysisModal()" style="background: linear-gradient(135deg, #6366f1, #8b5cf6); border: none; color: white; font-weight: 700; gap: 6px; display: flex; align-items: center;">
              🚀 تحليل الذكاء الاصطناعي
            </button>
            <button class="btn btn-secondary" onclick="openAiHistoryDrawer()" title="عرض التحليلات السابقة المحفوظة">
              📜 التحاليل المحفوظة
            </button>
            <button class="theme-toggle" id="theme-toggle-btn">🌓</button>
          </div>
        </div>

        <!-- Metric Badges Row -->
        <div class="catalog-metrics-grid">
          <div class="catalog-metric-card">
            <div class="catalog-metric-icon">📦</div>
            <div>
              <div class="catalog-metric-val" id="metric-total-products">0</div>
              <div class="catalog-metric-lbl">إجمالي المنتجات بالمطابقة</div>
            </div>
          </div>

          <div class="catalog-metric-card">
            <div class="catalog-metric-icon">🟢</div>
            <div>
              <div class="catalog-metric-val" id="metric-active-ads">0</div>
              <div class="catalog-metric-lbl">إعلانات نشطة</div>
            </div>
          </div>

          <div class="catalog-metric-card">
            <div class="catalog-metric-icon">🌍</div>
            <div>
              <div class="catalog-metric-val" id="metric-countries-count">0</div>
              <div class="catalog-metric-lbl">الدول المتاحة</div>
            </div>
          </div>

          <div class="catalog-metric-card">
            <div class="catalog-metric-icon">⭐</div>
            <div>
              <div class="catalog-metric-val" id="metric-saved-count">0</div>
              <div class="catalog-metric-lbl">منتجات محفوظة بالمفضلة</div>
            </div>
          </div>
        </div>

        <!-- Filter Toolbar -->
        <div class="filter-toolbar">
          <div class="filter-controls-grid">
            <div class="filter-group" style="grid-column: span 2;">
              <label for="catalog-search">🔍 البحث في العنوان والوصف والرابط:</label>
              <input type="text" id="catalog-search" placeholder="اكتب اسم المنتج أو كلمة مفتاحية..." oninput="debounceFetchProducts()" />
            </div>

            <div class="filter-group">
              <label for="catalog-origin">🏷️ التصنيف / المنشأ:</label>
              <select id="catalog-origin" onchange="fetchCatalogProducts(1)">
                <option value="all" selected>🌐 الجميع (All Categories)</option>
                <option value="Winning">🏆 Winning Products</option>
                <option value="Local">🇲🇦 Local Products</option>
                <option value="China">🇨🇳 China Products</option>
                <option value="Japan">🇯🇵 Japan Products</option>
                <option value="Competitor">⚔️ Competitor Ads</option>
              </select>
            </div>

            <div class="filter-group">
              <label for="catalog-country">🌐 الدولة:</label>
              <select id="catalog-country" onchange="fetchCatalogProducts(1)">
                <option value="all" selected>جميع الدول (All)</option>
                <option value="MA">🇲🇦 المغرب (MA)</option>
                <option value="SA">🇸🇦 السعودية (SA)</option>
                <option value="DZ">🇩🇿 الجزائر (DZ)</option>
                <option value="AE">🇦🇪 الإمارات (AE)</option>
                <option value="KW">🇰🇼 الكويت (KW)</option>
                <option value="QA">🇶🇦 قطر (QA)</option>
                <option value="EG">🇪🇬 مصر (EG)</option>
              </select>
            </div>

            <div class="filter-group">
              <label for="catalog-status">⚡ حالة الإعلانات:</label>
              <select id="catalog-status" onchange="fetchCatalogProducts(1)">
                <option value="all" selected>جميع الحالات</option>
                <option value="active">🟢 إعلانات نشطة فقط</option>
                <option value="inactive">🔴 إعلانات متوقفة</option>
              </select>
            </div>

            <div class="filter-group">
              <label for="catalog-date">📅 النطاق الزمني:</label>
              <select id="catalog-date" onchange="fetchCatalogProducts(1)">
                <option value="all" selected>جميع التواريخ</option>
                <option value="today">اليوم (Today)</option>
                <option value="yesterday">الأمس (Yesterday)</option>
                <option value="7days">آخر 7 أيام</option>
                <option value="30days">آخر 30 يوم</option>
              </select>
            </div>

            <div class="filter-group">
              <label for="catalog-sort">🔃 الترتيب حسب:</label>
              <select id="catalog-sort" onchange="fetchCatalogProducts(1)">
                <option value="ads-desc" selected>الأكثر إعلانات (Desc)</option>
                <option value="ads-asc">الأقل إعلانات (Asc)</option>
                <option value="date-desc">الأحدث تاريخاً</option>
                <option value="date-asc">الأقدم تاريخاً</option>
                <option value="title-asc">اسم المنتج أبجدياً</option>
              </select>
            </div>

            <div class="filter-group">
              <label for="catalog-per-page">📄 عناصر الصفحة:</label>
              <select id="catalog-per-page" onchange="fetchCatalogProducts(1)">
                <option value="12">12 منتج</option>
                <option value="24" selected>24 منتج</option>
                <option value="48">48 منتج</option>
                <option value="96">96 منتج</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Product Grid Container -->
        <div class="products-grid" id="catalog-products-container">
          <div style="grid-column: 1 / -1; text-align: center; color: var(--color-text-muted); padding: 3rem 0;">
            ⏳ جاري تحميل المنتجات من قاعدة البيانات...
          </div>
        </div>

        <!-- Pagination Bar -->
        <div class="pagination-bar" id="catalog-pagination-bar" style="display: none;">
          <div style="font-size: 0.85rem; color: var(--color-text-muted); font-weight: 600;" id="pagination-summary">
            عرض 0 - 0 من إجمالي 0 منتج
          </div>
          <div class="pagination-btns" id="pagination-buttons">
            <!-- Dynamic page buttons -->
          </div>
        </div>
      </main>
    </div>

    <!-- Product Details Modal (Reused) -->
    <div class="details-modal-overlay" id="details-modal">
      <div class="details-modal-card">
        <div class="details-modal-header">
          <div class="details-modal-title" id="details-title">
            تفاصيل المنتج والإعلانات
          </div>
          <div class="details-modal-header-actions">
            <button class="btn btn-success" id="details-store-btn" onclick="toggleStoreListAction()">
              ➕ إضافة المتجر للقائمة
            </button>
            <button class="btn btn-secondary" id="details-save-btn" style="border: 1px solid var(--color-success); color: var(--color-success); background: transparent;">
              احفظ المنتج
            </button>
            <button class="details-modal-close" onclick="closeDetailsModal()">
              &times;
            </button>
          </div>
        </div>
        <div class="details-modal-body">
          <!-- Left Panel: Timeline & Metrics -->
          <div class="details-left-panel">
            <div class="details-section-card">
              <div class="details-section-title">
                🕒 المخطط الزمني لنشاط الإعلان
              </div>
              <div class="details-timeline-chart" id="details-chart">
                <!-- Dynamic timeline bars -->
              </div>
            </div>

            <div class="strategy-analysis-card">
              <div class="details-section-title" style="color: var(--color-primary)">
                ⚡ تحليل استراتيجية الإعلان
              </div>
              <div class="strategy-badge">✓ سجل قاعدة البيانات</div>
              <p id="details-analysis-text" style="font-size: 0.85rem; line-height: 1.6; color: var(--color-text-main); margin-top: 8px;">
                المنتج مخزن في قاعدة البيانات مع كامل التفاصيل الفنية والإعلانات المرتبطة.
              </p>
            </div>

            <div class="details-section-card">
              <div class="details-section-title">⚙️ المؤشرات الرئيسية</div>
              <div class="indicators-grid">
                <div class="indicator-card">
                  <div class="indicator-title">👁️ المشاهدات المقدرة</div>
                  <div class="indicator-value" id="details-views">0</div>
                </div>
                <div class="indicator-card">
                  <div class="indicator-title">❤️ التفاعل المقدر</div>
                  <div class="indicator-value" id="details-engagement">0</div>
                </div>
                <div class="indicator-card">
                  <div class="indicator-title">📅 أول ظهور</div>
                  <div class="indicator-value" id="details-first-seen">-</div>
                </div>
                <div class="indicator-card">
                  <div class="indicator-title">📅 آخر ظهور</div>
                  <div class="indicator-value" id="details-last-seen">-</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Right Panel: Media & Raw JSON -->
          <div class="details-right-panel">
            <div class="details-media-showcase" id="details-media">
              <!-- Dynamic media items -->
            </div>

            <div class="details-product-info">
              <div class="details-product-title" id="details-info-title">-</div>
              <p class="details-product-desc" id="details-info-desc">-</p>
            </div>

            <div class="details-raw-data-card" style="background: var(--bg-input); border-radius: var(--radius-sm); border: 1px solid var(--border-color); padding: 12px; margin-top: 15px;">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <span style="font-weight: 700; font-size: 0.85rem; color: var(--color-primary);">📋 بيانات المنتج (JSON)</span>
              </div>
              <div id="details-raw-data-list" style="max-height: 150px; overflow-y: auto; font-size: 0.75rem; color: var(--color-text-muted); font-family: var(--font-mono); line-height: 1.4; display: flex; flex-direction: column; gap: 4px; direction: ltr; text-align: left;">
              </div>
            </div>

            <div class="details-action-buttons">
              <a href="#" target="_blank" class="btn btn-dashed" id="details-fb-library-btn">🌐 عرض في مكتبة الإعلانات</a>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- AI Saved History Slide Drawer -->
    <div class="modal-overlay" id="ai-history-drawer" style="display: none; z-index: 10002; justify-content: flex-start;">
      <div style="width: 480px; max-width: 90%; height: 100vh; background: var(--bg-card); border-left: 1px solid var(--border-color); padding: 1.5rem; display: flex; flex-direction: column; box-shadow: var(--shadow-lg); overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 1rem; margin-bottom: 1rem;">
          <div>
            <h3 style="font-weight: 800; font-size: 1.15rem; color: var(--color-primary); margin: 0;">
              📜 التحليلات المحفوظة لحسابك
            </h3>
            <span style="font-size: 0.78rem; color: var(--color-text-muted);">سجل عمليات التقييم السابقة للرجوع إليها</span>
          </div>
          <button style="background: none; border: none; font-size: 1.6rem; cursor: pointer; color: var(--color-text-muted);" onclick="closeAiHistoryDrawer()">&times;</button>
        </div>

        <div id="ai-history-list" style="display: flex; flex-direction: column; gap: 12px; flex: 1;">
          <div style="text-align: center; color: var(--color-text-muted); padding: 2rem 0;">جاري تحميل السجل...</div>
        </div>
      </div>
    </div>

    <!-- Toast Notifications Container -->
    <div class="toast-container" id="toast-container"></div>

    <script src="https://vjs.zencdn.net/8.16.1/video.min.js"></script>
    <script src="<?= base_url('video-thumbnail-generator.js') ?>?v=1.0"></script>
    <script>
      let currentPage = 1;
      let totalPages = 1;
      let debounceTimer = null;
      let catalogProducts = [];
      let savedProductIds = new Set();
      let currentActiveProduct = null;

      const COUNTRIES_LIST = [
        { code: "DZ", name: "الجزائر", flag: "🇩🇿" },
        { code: "TN", name: "تونس", flag: "🇹🇳" },
        { code: "MA", name: "المغرب", flag: "🇲🇦" },
        { code: "LY", name: "ليبيا", flag: "🇱🇾" },
        { code: "EG", name: "مصر", flag: "🇪🇬" },
        { code: "SA", name: "السعودية", flag: "🇸🇦" },
        { code: "QA", name: "قطر", flag: "🇶🇦" },
        { code: "OM", name: "عُمان", flag: "🇴🇲" },
        { code: "BH", name: "البحرين", flag: "🇧🇭" },
        { code: "KW", name: "الكويت", flag: "🇰🇼" },
        { code: "AE", name: "الإمارات", flag: "🇦🇪" },
        { code: "GB", name: "بريطانيا", flag: "🇬🇧" },
        { code: "FR", name: "فرنسا", flag: "🇫🇷" },
        { code: "ES", name: "إسبانيا", flag: "🇪🇸" },
        { code: "DE", name: "ألمانيا", flag: "🇩🇪" }
      ];

      document.addEventListener("DOMContentLoaded", () => {
        setupThemeToggle();
        fetchSavedProductsList();
        fetchCatalogProducts(1);
      });

      function setupThemeToggle() {
        const themeBtn = document.getElementById("theme-toggle-btn");
        if (!themeBtn) return;
        themeBtn.onclick = () => {
          const theme = document.documentElement.getAttribute("data-theme") === "dark" ? "light" : "dark";
          document.documentElement.setAttribute("data-theme", theme);
          localStorage.setItem("app-theme", theme);
        };
      }

      function debounceFetchProducts() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
          fetchCatalogProducts(1);
        }, 350);
      }

      async function fetchSavedProductsList() {
        try {
          const res = await fetch('/api/products/saved');
          if (res.ok) {
            const data = await res.json();
            const savedList = data.saved_products || data.products || (Array.isArray(data) ? data : []);
            savedProductIds = new Set((Array.isArray(savedList) ? savedList : []).map(p => String(p.id || p.product_id)));
            document.getElementById('metric-saved-count').textContent = savedProductIds.size;
          }
        } catch (e) {
          console.error("Failed to fetch saved products list:", e);
        }
      }

      async function fetchCatalogProducts(page = 1) {
        currentPage = page;
        const container = document.getElementById('catalog-products-container');
        container.innerHTML = '<div style="grid-column: 1 / -1; text-align: center; color: var(--color-text-muted); padding: 3rem 0;">⏳ جاري الاستعلام من قاعدة البيانات...</div>';

        const origin = document.getElementById('catalog-origin').value;
        const country = document.getElementById('catalog-country').value;
        const status = document.getElementById('catalog-status').value;
        const date = document.getElementById('catalog-date').value;
        const sort = document.getElementById('catalog-sort').value;
        const search = document.getElementById('catalog-search').value.trim();
        const perPage = document.getElementById('catalog-per-page').value;

        const params = new URLSearchParams({
          origin: origin,
          country: country,
          status: status,
          date: date,
          sort: sort,
          search: search,
          page: page,
          per_page: perPage
        });

        try {
          const res = await fetch(`/api/products?${params.toString()}`);
          if (!res.ok) throw new Error("HTTP error " + res.status);
          const data = await res.json();

          catalogProducts = data.results || data.products || data.data || [];
          const total = data.total || catalogProducts.length;
          totalPages = Math.ceil(total / parseInt(perPage));

          updateMetrics(total, catalogProducts);
          renderProductCards(catalogProducts);
          renderPagination(total, page, parseInt(perPage));
        } catch (err) {
          console.error("Error fetching catalog products:", err);
          container.innerHTML = `<div style="grid-column: 1 / -1; text-align: center; color: var(--color-error); padding: 3rem 0;">❌ فشل جلب المنتجات: ${err.message}</div>`;
        }
      }

      function updateMetrics(totalMatching, products) {
        document.getElementById('metric-total-products').textContent = totalMatching.toLocaleString('ar-EG');
        const activeCount = products.filter(p => p.active_ads == 1 || p.active_ads === true || p.is_active).length;
        document.getElementById('metric-active-ads').textContent = activeCount.toLocaleString('ar-EG');
        
        const countries = new Set();
        products.forEach(p => {
          if (p.country) {
            p.country.split(';').forEach(c => countries.add(c.trim().toUpperCase()));
          }
        });
        document.getElementById('metric-countries-count').textContent = countries.size || 1;
      }

      function renderProductCards(products) {
        const container = document.getElementById('catalog-products-container');
        if (!products || products.length === 0) {
          container.innerHTML = '<div style="grid-column: 1 / -1; text-align: center; color: var(--color-text-muted); padding: 4rem 0; font-size: 1.1rem;">📭 لا توجد منتجات مطابقة لخيارات الفلترة الحالية.</div>';
          return;
        }

        container.innerHTML = products.map((p, idx) => {
          const isSaved = savedProductIds.has(String(p.id));
          const title = p.title || p.product_title || 'بدون عنوان';
          const productUrl = p.product_url || p.productUrl || '#';

          // Extract Images & Videos safely
          const imageUrls = (p.ad_image_urls || p.thumbnail_url || p.image_url || p.media_url || '')
            .split(';')
            .map(u => u.trim())
            .filter(Boolean);
          const videoUrls = (p.ad_video_urls || '')
            .split(';')
            .map(u => u.trim())
            .filter(Boolean);

          const countryCode = (p.country || 'MA').toUpperCase();
          const countryMeta = COUNTRIES_LIST.find(c => c.code === countryCode);
          const flag = countryMeta ? countryMeta.flag : '🌍';

          let domain = 'متجر خارجي';
          try {
            if (productUrl && productUrl !== '#') {
              domain = new URL(productUrl).hostname.replace('www.', '');
            }
          } catch(e) {
            domain = productUrl || 'رابط غير معروف';
          }

          const isActive = p.active_ads == 1 || p.active_ads === true || p.is_active;
          const safeId = p.id || idx;

          // Time ago calculation
          let timeAgoText = "";
          const rawDate = p.ad_start_date || p.created_at || "";
          const adStartDate = rawDate ? rawDate.split(' ')[0] : "--";
          if (p.ad_start_date) {
            const startDate = new Date(p.ad_start_date);
            if (!isNaN(startDate.getTime())) {
              const now = new Date();
              now.setHours(0, 0, 0, 0);
              startDate.setHours(0, 0, 0, 0);

              const diffTime = now.getTime() - startDate.getTime();
              const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));

              if (diffDays === 0) {
                timeAgoText = ' <span style="font-size: 0.7rem; color: var(--color-primary); font-weight: 700;">(اليوم)</span>';
              } else if (diffDays === 1) {
                timeAgoText = ' <span style="font-size: 0.7rem; color: var(--color-primary); font-weight: 700;">(أمس)</span>';
              } else if (diffDays > 1 && diffDays < 7) {
                timeAgoText = ` <span style="font-size: 0.7rem; color: var(--color-primary); font-weight: 700;">(منذ ${diffDays} أيام)</span>`;
              } else if (diffDays >= 7 && diffDays < 30) {
                const weeks = Math.floor(diffDays / 7);
                timeAgoText = ` <span style="font-size: 0.7rem; color: var(--color-primary); font-weight: 700;">(منذ ${weeks} أسبوع)</span>`;
              } else if (diffDays >= 30 && diffDays < 365) {
                const months = Math.floor(diffDays / 30);
                timeAgoText = ` <span style="font-size: 0.7rem; color: var(--color-primary); font-weight: 700;">(منذ ${months} شهر)</span>`;
              } else if (diffDays >= 365) {
                const years = Math.floor(diffDays / 365);
                timeAgoText = ` <span style="font-size: 0.7rem; color: var(--color-primary); font-weight: 700;">(منذ ${years} سنة)</span>`;
              }
            }
          }

          let mediaHtml = '';
          if (videoUrls.length > 0) {
            mediaHtml = `
              <div class="media-badge">🎥 فيديو (${videoUrls.length})</div>
              <div class="vid-placeholder" data-vid-src="${videoUrls[0]}" data-vid-poster="${imageUrls[0] || ''}" data-product-id="${p.id || ''}" data-product-url="${productUrl}" id="vp-${safeId}">
                ${imageUrls[0] ? `<img src="${imageUrls[0]}" alt="" class="vid-placeholder-img">` : `<div class="vid-placeholder-bg"></div>`}
                <div class="vid-play-btn">▶</div>
              </div>
            `;
          } else if (imageUrls.length > 0) {
            mediaHtml = `
              <div class="media-badge">📸 صور (${imageUrls.length})</div>
              <img src="${imageUrls[0]}" alt="${title}" loading="lazy">
            `;
          } else {
            mediaHtml = `
              <div class="no-media">
                <span>📦 لا توجد وسائط معاينة</span>
              </div>
            `;
          }

          const saveBtnHtml = `
            <button onclick="toggleSaveProduct('${p.id}', this)" 
                    class="btn ${isSaved ? 'btn-success' : 'btn-secondary'}" 
                    id="save-btn-${safeId}"
                    title="${isSaved ? 'محفوظ' : 'حفظ المنتج'}"
                    style="padding: 0.4rem 0.6rem; font-size: 0.85rem;">
              ${isSaved ? '⭐' : '☆'}
            </button>
          `;

          return `
            <article class="product-card index-product-card" id="product-${safeId}">
              <div class="product-media">
                ${mediaHtml}
                <div class="status-badge ${isActive ? 'active' : 'inactive'}">
                  ${isActive ? '🟢 نشط' : '🔴 متوقف'}
                </div>
                <div class="country-flag-badge">
                  <span>${flag}</span>
                  <span>${countryCode}</span>
                </div>
              </div>

              <div class="card-body">
                <h4 class="p-title" title="${title}">${title}</h4>
                <div style="color: var(--color-text-muted); font-size: 0.75rem; margin-top: -2px; display: flex; justify-content: space-between; align-items: center;">
                  <a href="https://www.facebook.com/ads/library/?active_status=active&ad_type=all&country=MA&q=${encodeURIComponent(domain)}" 
                     target="_blank" 
                     style="color: var(--color-primary); text-decoration: none; font-weight: bold; font-size: 0.75rem; transition: var(--transition-all);"
                     onmouseover="this.style.color='var(--color-primary-hover)'"
                     onmouseout="this.style.color='var(--color-primary)'">🏪 ${domain}</a>
                  <span style="font-size: 0.65rem; color: var(--color-text-muted);">${adStartDate}${timeAgoText}</span>
                </div>
              </div>

              <div class="card-footer" style="gap: 6px; padding: 8px;">
                ${productUrl && productUrl !== '#' ? `<a href="${productUrl}" target="_blank" class="btn btn-primary" style="flex: 1; font-size: 0.75rem; padding: 0.4rem 0.5rem;">🛒 زيارة</a>` : ''}
                <button onclick="openProductDetailsModal(${idx})" class="btn btn-secondary" style="flex: 0 0 auto; padding: 0.4rem 0.6rem; font-size: 0.7rem;">ℹ️ معلومات</button>
                <button onclick="openProductDetailsModal(${idx})" class="btn btn-secondary" style="flex: 1; font-size: 0.75rem; padding: 0.4rem 0.5rem;">📊 تفاصيل</button>
                ${saveBtnHtml}
              </div>
            </article>
          `;
        }).join('');

        if (typeof ensureVideoThumbnails === 'function') {
          ensureVideoThumbnails(container);
        }
        initCatalogVideoEvents(container);
      }

      function initCatalogVideoEvents(scope) {
        (scope || document).querySelectorAll(".vid-placeholder:not([data-vid-inited])").forEach(ph => {
          ph.dataset.vidInited = "1";
          const videoSrc = ph.getAttribute("data-vid-src");
          if (!videoSrc) return;

          let vidEl = null;

          const createVid = () => {
            if (vidEl) return vidEl;
            const poster = ph.getAttribute("data-vid-poster") || "";
            vidEl = document.createElement("video");
            vidEl.src = videoSrc;
            if (poster) vidEl.poster = poster;
            vidEl.controls = true;
            vidEl.autoplay = true;
            vidEl.playsInline = true;
            vidEl.style.width = "100%";
            vidEl.style.height = "100%";
            vidEl.style.objectFit = "cover";
            ph.innerHTML = "";
            ph.appendChild(vidEl);
            return vidEl;
          };

          ph.addEventListener("click", (e) => {
            e.stopPropagation();
            const v = createVid();
            v.play();
          });

          const card = ph.closest(".product-card") || ph;
          card.addEventListener("mouseenter", () => {
            const v = createVid();
            v.muted = false;
            v.play().catch(() => {
              v.muted = true;
              v.play();
            });
          });

          card.addEventListener("mouseleave", () => {
            if (vidEl) vidEl.pause();
          });
        });
      }

      function renderPagination(totalItems, page, perPage) {
        const bar = document.getElementById('catalog-pagination-bar');
        const summary = document.getElementById('pagination-summary');
        const btns = document.getElementById('pagination-buttons');

        if (totalItems <= perPage) {
          bar.style.display = 'none';
          return;
        }

        bar.style.display = 'flex';
        const start = ((page - 1) * perPage) + 1;
        const end = Math.min(page * perPage, totalItems);
        summary.textContent = `عرض ${start} - ${end} من إجمالي ${totalItems} منتج`;

        let btnsHtml = `
          <button class="page-btn" ${page <= 1 ? 'disabled' : ''} onclick="fetchCatalogProducts(${page - 1})">السابق</button>
        `;

        for (let i = 1; i <= totalPages; i++) {
          if (i === 1 || i === totalPages || (i >= page - 2 && i <= page + 2)) {
            btnsHtml += `<button class="page-btn ${i === page ? 'active' : ''}" onclick="fetchCatalogProducts(${i})">${i}</button>`;
          } else if (i === page - 3 || i === page + 3) {
            btnsHtml += `<span style="color: var(--color-text-muted);">...</span>`;
          }
        }

        btnsHtml += `
          <button class="page-btn" ${page >= totalPages ? 'disabled' : ''} onclick="fetchCatalogProducts(${page + 1})">التالي</button>
        `;

        btns.innerHTML = btnsHtml;
      }

      async function toggleSaveProduct(productId, btnElem) {
        try {
          const res = await fetch('/api/products/saved/toggle', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ product_id: productId })
          });
          const data = await res.json();
          if (data.status === 'success' || data.saved !== undefined) {
            if (data.saved) {
              savedProductIds.add(String(productId));
              btnElem.classList.add('saved');
              showToast('✅ تم حفظ المنتج للمفضلة بنجاح!');
            } else {
              savedProductIds.delete(String(productId));
              btnElem.classList.remove('saved');
              showToast('🗑️ تم إزالة المنتج من المفضلة.');
            }
            document.getElementById('metric-saved-count').textContent = savedProductIds.size;
          }
        } catch (err) {
          console.error("Save toggle error:", err);
          showToast('❌ حدث خطأ أثناء التبديل في المفضلة.');
        }
      }

      function openProductDetailsModal(index) {
        const p = catalogProducts[index];
        if (!p) return;
        currentActiveProduct = p;

        document.getElementById('details-info-title').textContent = p.title || 'منتج من قاعدة البيانات';
        document.getElementById('details-info-desc').textContent = p.ad_body || p.ad_title || 'لا يوجد وصف تفصيلي متوفر.';
        document.getElementById('details-views').textContent = (p.views || 0).toLocaleString();
        document.getElementById('details-engagement').textContent = (p.engagement || '7%');
        document.getElementById('details-first-seen').textContent = p.ad_start_date || p.created_at || '-';
        document.getElementById('details-last-seen').textContent = p.updated_at || p.created_at || '-';

        const rawList = document.getElementById('details-raw-data-list');
        rawList.innerHTML = `
          <div><strong>ID:</strong> ${p.id}</div>
          <div><strong>Origin:</strong> ${p.origin || 'Winning'}</div>
          <div><strong>Country:</strong> ${p.country || 'MA'}</div>
          <div><strong>Ads Count:</strong> ${p.ads_count || 1}</div>
          <div><strong>Price:</strong> ${p.price_1 || '0'} DH</div>
          <div><strong>URL:</strong> <a href="${p.product_url || '#'}" target="_blank" style="color:var(--color-primary);">${p.product_url || '-'}</a></div>
        `;

        const fbBtn = document.getElementById('details-fb-library-btn');
        fbBtn.href = `https://www.facebook.com/ads/library/?q=${encodeURIComponent(p.title || '')}`;

        document.getElementById('details-modal').style.display = 'flex';
      }

      function closeDetailsModal() {
        document.getElementById('details-modal').style.display = 'none';
      }

      function showToast(msg) {
        const toast = document.createElement('div');
        toast.style.cssText = "background: var(--bg-card); color: var(--color-text-main); border: 1px solid var(--border-color); padding: 10px 16px; border-radius: var(--radius-sm); margin-bottom: 8px; box-shadow: var(--shadow-md); font-size: 0.85rem; font-weight: 700;";
        toast.textContent = msg;
        const container = document.getElementById('toast-container');
        container.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
      }

      function openAiAnalysisModal() {
        showToast('🚀 أداة التحليل التلقائي مفعّلة عبر النوافذ والإعدادات المنفصلة.');
      }

      function openAiHistoryDrawer() {
        document.getElementById('ai-history-drawer').style.display = 'flex';
      }

      function closeAiHistoryDrawer() {
        document.getElementById('ai-history-drawer').style.display = 'none';
      }
    </script>
  </body>
</html>
