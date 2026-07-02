<!doctype html>
<html lang="ar" dir="rtl">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>OverviewData Insights Dashboard | لوحة تحليلات المنتجات</title>
    <meta
      name="description"
      content="واجهة متطورة لعرض المنتجات الرابحة، التحليلات المتقدمة، وتشفير روابط tRPC."
    />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap"
      rel="stylesheet"
    />

    <link rel="stylesheet" href="https://vjs.zencdn.net/8.16.1/video-js.css" />
    <link rel="stylesheet" href="<?= base_url('index.css') ?>?v=1.3" />
  </head>
  <body>
    <div class="app-shell">
      <!-- Sidebar Panel -->
      <aside class="sidebar">
        <div class="logo-container">
          <div class="logo-icon">⚡</div>
          <div class="logo-text">
            <h1>Overview Insights</h1>
            <p>بناء استعلام API وتصفح النتائج</p>
          </div>
        </div>

        <!-- API Mode Selector -->
        <div class="form-group">
          <label for="api-endpoint-select">🎯 نوع الاستعلام / البيانات</label>
          <select id="api-endpoint-select" onchange="toggleApiMode()">
            <option value="" disabled selected>
              -- اختر نوع الاستعلام / البيانات أولاً --
            </option>
            <option value="insights">Overview Insights (مؤشرات السوق)</option>
            <option value="winning">Winning Products (المنتجات الرابحة)</option>
          </select>
          <a href="<?= base_url('snapshots') ?>" class="btn btn-secondary btn-block" style="text-align:center;margin-top:8px;font-size:0.85rem;display:flex;align-items:center;justify-content:center;gap:6px">
            📸 لقطات البيانات (Snapshots)
          </a>
        </div>

        <!-- Form Inputs for filters -->
        <div class="form-group insights-only">
          <label for="filter-title">الكلمة المفتاحية (Title)</label>
          <input
            type="text"
            id="filter-title"
            value=""
            placeholder="مثلاً: brush, car, tool..."
          />
          <a
            href="https://www.facebook.com/ads/library/?active_status=active&ad_type=all&country=MA&q="
            target="_blank"
            id="fb-search-link"
            style="
              margin-top: 4px;
              display: inline-flex;
              align-items: center;
              gap: 4px;
              font-size: 0.8rem;
              color: var(--color-primary);
              text-decoration: none;
              font-weight: bold;
              transition: var(--transition-all);
            "
            onmouseover="this.style.color = 'var(--color-primary-hover)'"
            onmouseout="this.style.color = 'var(--color-primary)'"
          >
            🌐 بحث في مكتبة إعلانات فيسبوك
          </a>
        </div>

        <div class="form-group">
          <label>التصنيفات (Categories)</label>
          <select id="api-filter-category" multiple style="min-height: 120px">
            <!-- Will be generated via JS -->
          </select>
          <small style="color: var(--color-text-muted); font-size: 0.7rem"
            >استمر بالضغط على Ctrl (أو Cmd) لتحديد أكثر من خيار</small
          >
        </div>

        <div class="form-group">
          <label>الدول (Countries)</label>
          <select id="api-filter-country" multiple style="min-height: 150px">
            <!-- Will be generated via JS -->
          </select>
          <small style="color: var(--color-text-muted); font-size: 0.7rem"
            >استمر بالضغط على Ctrl (أو Cmd) لتحديد أكثر من خيار</small
          >
        </div>

        <div
          class="form-group insights-only"
          style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px"
        >
          <div>
            <label for="filter-priceFrom">السعر من</label>
            <input type="number" id="filter-priceFrom" value="-1" />
          </div>
          <div>
            <label for="filter-priceTo">السعر إلى</label>
            <input type="number" id="filter-priceTo" value="-1" />
          </div>
        </div>

        <div
          class="form-group"
          style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px"
        >
          <div class="insights-only">
            <label for="filter-weeks">عدد الأسابيع</label>
            <input type="number" id="filter-weeks" value="12" min="1" />
          </div>
          <div>
            <label for="filter-v">نسخة API (v)</label>
            <input type="text" id="filter-v" value="1.3--5" />
          </div>
        </div>

        <div class="form-group insights-only">
          <label for="filter-transformation">التحويل (Transformation)</label>
          <select id="filter-transformation">
            <option value="market-reaction" selected>market-reaction</option>
            <option value="none">بدون تحويل</option>
          </select>
        </div>

        <!-- URL Output & Copy Panel -->
        <button
          class="btn btn-primary btn-block"
          id="apply-filters-btn"
          onclick="handleFetchAPI()"
        >
          🚀 جلب البيانات من الرابط المولد
        </button>
        <div class="url-preview-card">
          <label>رابط tRPC المشفر والمولد:</label>
          <div class="url-box" id="generated-url">
            https://www.overviewdata.io/...
          </div>
          <div style="display: flex; gap: 8px; margin-top: 5px">
            <button
              class="btn btn-secondary btn-block"
              style="padding: 0.5rem; font-size: 0.8rem"
              onclick="copyGeneratedURL()"
            >
              🔗 نسخ الرابط
            </button>
            <button
              class="btn btn-primary btn-block"
              style="padding: 0.5rem; font-size: 0.8rem"
              onclick="openGeneratedURL()"
            >
              🌍 فتح في نافذة جديدة
            </button>
          </div>
        </div>
      </aside>

      <!-- Main Area -->
      <main class="main-content">
        <!-- Top Navigation & Import Actions -->
        <div class="top-nav">
          <div>
            <h2
              style="
                font-weight: 800;
                font-size: 1.6rem;
                letter-spacing: -0.01em;
              "
            >
              لوحة تحليلات المنتجات الرابحة
            </h2>
            <p style="color: var(--color-text-muted); font-size: 0.85rem">
              تصفح البيانات المحللة من overviewdata.io والتفاعل معها
            </p>
          </div>

          <div class="actions-group">
            <a href="<?= base_url('saved-ads') ?>" class="btn btn-secondary"
              >⭐ الإعلانات المحفوظة</a
            >
            <a href="<?= base_url('international-products') ?>" class="btn btn-primary"
              >🌏 منتجات الصين واليابان</a
            >
            <div class="file-input-wrapper">
              <button class="btn btn-secondary">
                📁 استيراد ملف JSON محلي
              </button>
              <input
                type="file"
                id="local-file-input"
                accept=".json"
                onchange="handleLocalFile(event)"
              />
            </div>
            <button class="btn btn-secondary" onclick="openManualPasteModal()">
              📝 لصق البيانات يدوياً
            </button>
            <a href="<?= base_url('snapshots') ?>" class="btn btn-secondary">📸 اللقطات</a>
            <a href="<?= base_url('settings') ?>" class="btn btn-secondary">⚙️ الإعدادات</a>
            <button
              class="theme-toggle"
              id="theme-toggle-btn"
              aria-label="تبديل الثيم"
            >
              🌓
            </button>
          </div>
        </div>

        <!-- Statistics / Analysis Visualization -->
        <div
          class="analytics-panel"
          id="analytics-section"
          style="display: none"
        >
          <!-- Main Stat Chart based on adaptedResult -->
          <div class="chart-card">
            <div class="card-title">
              📊 مخطط حركة الإدراجات الأسبوعية (Weekly New Listings)
            </div>
            <div class="mini-chart-container" id="listings-chart">
              <!-- Dynamic Bars generated via JS -->
            </div>
            <div
              style="
                display: flex;
                justify-content: space-between;
                font-size: 0.8rem;
                color: var(--color-text-muted);
              "
            >
              <span>منذ 12 أسبوعاً</span>
              <span>هذا الأسبوع</span>
            </div>
          </div>

          <!-- Summary of Total Shops & Supply -->
          <div class="chart-card" style="justify-content: center">
            <div class="card-title">🛍️ تحليل المتاجر والعرض</div>
            <div style="display: flex; flex-direction: column; gap: 15px">
              <div
                style="
                  display: flex;
                  justify-content: space-between;
                  align-items: center;
                  border-bottom: 1px solid var(--border-color);
                  padding-bottom: 8px;
                "
              >
                <span style="font-size: 0.9rem; color: var(--color-text-muted)"
                  >الزخم في المعروض:</span
                >
                <span
                  id="stat-momentum"
                  class="alg-badge"
                  style="font-size: 0.8rem"
                  >نعم</span
                >
              </div>
              <div
                style="
                  display: flex;
                  justify-content: space-between;
                  align-items: center;
                  border-bottom: 1px solid var(--border-color);
                  padding-bottom: 8px;
                "
              >
                <span style="font-size: 0.9rem; color: var(--color-text-muted)"
                  >المتاجر النشطة حالياً:</span
                >
                <span
                  id="stat-shops-count"
                  style="font-weight: 800; font-size: 1.1rem"
                  >0</span
                >
              </div>
              <div
                style="
                  display: flex;
                  justify-content: space-between;
                  align-items: center;
                "
              >
                <span style="font-size: 0.9rem; color: var(--color-text-muted)"
                  >الزيادة في المتاجر:</span
                >
                <span
                  id="stat-shops-trend"
                  class="trend-up"
                  style="font-weight: 700"
                  >+0%</span
                >
              </div>
            </div>
          </div>
        </div>

        <!-- Big KPI Cards -->
        <div class="stats-grid">
          <div class="stat-card">
            <div class="stat-label">
              إجمالي المنتجات المتاحة <span>📦</span>
            </div>
            <div class="stat-value" id="kpi-total-products">0</div>
            <div class="stat-trend text-muted" id="kpi-loaded-from">
              غير محمل
            </div>
          </div>

          <div class="stat-card secondary">
            <div class="stat-label">مجموع الإعلانات النشطة <span>🔥</span></div>
            <div class="stat-value" id="kpi-total-ads">0</div>
            <div class="stat-trend text-muted">إعلان رائد</div>
          </div>

          <div class="stat-card success">
            <div class="stat-label">إعلانات فيديو فريدة <span>🎥</span></div>
            <div class="stat-value" id="kpi-video-ads">0</div>
            <div class="stat-trend text-muted">نسبة ممتازة للعروض المرئية</div>
          </div>

          <div class="stat-card warning">
            <div class="stat-label">
              متوسط المشاهد الإبداعية <span>🎨</span>
            </div>
            <div class="stat-value" id="kpi-avg-creatives">0.0</div>
            <div class="stat-trend text-muted">لكل منتج</div>
          </div>
        </div>

        <!-- Product Controls & Search -->
        <div class="product-toolbar">
          <div class="search-box">
            <input
              type="text"
              id="product-search"
              placeholder="ابحث في العناوين، نصوص الإعلانات أو الروابط..."
              oninput="filterProducts()"
            />
          </div>
          <div style="display: flex; gap: 10px; flex-wrap: wrap">
            <select
              id="sort-by"
              style="width: 180px"
              onchange="filterProducts()"
            >
              <option value="ads-desc" selected>الأكثر إعلانات 🔥</option>
              <option value="ads-asc">الأقل إعلانات ❄️</option>
              <option value="date-desc">الأحدث إطلاقاً 📅</option>
              <option value="date-asc">الأقدم إطلاقاً 🗓️</option>
              <option value="title-asc">الاسم (أ-ي)</option>
            </select>
            <select
              id="country-filter"
              style="width: 140px"
              onchange="filterProducts()"
            >
              <option value="all">جميع الدول 🌍</option>
            </select>
            <select
              id="launch-date-filter"
              style="width: 160px"
              onchange="filterProducts()"
            >
              <option value="all">مدة الإطلاق (الكل)</option>
              <option value="today">اليوم فقط</option>
              <option value="yesterday">أمس فقط</option>
              <option value="7days">آخر 7 أيام</option>
              <option value="30days">آخر 30 يوم</option>
            </select>
            <select
              id="status-active-filter"
              style="width: 130px"
              onchange="filterProducts()"
            >
              <option value="all">الحالة (الكل)</option>
              <option value="active">🟢 نشط فقط</option>
              <option value="inactive">🔴 متوقف فقط</option>
            </select>
            <button
              class="btn btn-secondary"
              style="padding: 0.5rem 1rem; font-size: 0.85rem"
              onclick="downloadFilteredJSON()"
            >
              📥 تحميل JSON
            </button>
          </div>
        </div>

        <!-- Product Rendering Grid -->
        <div class="products-grid" id="products-container">
          <div class="empty-state">
            <div class="empty-icon">📂</div>
            <h3>لم يتم تحميل أي بيانات بعد</h3>
            <p>
              قم باستيراد ملف <b>data (1).json</b> من جهازك، أو قم بجلب البيانات
              من الـ API باستخدام لوحة التحكم الجانبية.
            </p>
          </div>
        </div>
      </main>
    </div>

    <!-- Manual Paste Modal (Fallback for CORS errors) -->
    <div class="modal-overlay" id="paste-modal">
      <div class="modal-card">
        <div
          style="
            display: flex;
            justify-content: space-between;
            align-items: center;
          "
        >
          <h3 style="font-weight: 700">📝 لصق بيانات JSON يدوياً</h3>
          <button
            style="
              background: none;
              border: none;
              font-size: 1.5rem;
              cursor: pointer;
            "
            onclick="closeManualPasteModal()"
          >
            ×
          </button>
        </div>
        <p style="font-size: 0.85rem; color: var(--color-text-muted)">
          بسبب قيود أمن المتصفحات (CORS)، قد تفشل عملية الجلب المباشر من موقع
          OverviewData. قم بفتح الرابط في علامة تبويب جديدة، انسخ محتويات JSON
          بالكامل، ثم الصقها هنا.
        </p>
        <textarea
          id="manual-json-input"
          style="
            min-height: 200px;
            font-family: var(--font-mono);
            font-size: 0.8rem;
          "
          placeholder="[{ 'result': { ... } }] أو محتوى ملف data (1).json"
        ></textarea>
        <div style="display: flex; justify-content: flex-end; gap: 10px">
          <button class="btn btn-secondary" onclick="closeManualPasteModal()">
            إلغاء
          </button>
          <button class="btn btn-success" onclick="processManualJSON()">
            معالجة وعرض البيانات
          </button>
        </div>
      </div>
    </div>

    <!-- Product Details Modal -->
    <div class="details-modal-overlay" id="details-modal">
      <div class="details-modal-card">
        <div class="details-modal-header">
          <div class="details-modal-title" id="details-title">
            تفاصيل الإعلان والنشاط
          </div>
          <div class="details-modal-header-actions">
            <button
              class="btn btn-secondary"
              onclick="openDetailsHelpModal()"
              style="
                border: 1px solid var(--color-primary);
                color: var(--color-primary);
                background: transparent;
                margin-left: 8px;
                font-weight: 600;
              "
            >
              💡 دليل القراءة
            </button>
            <button
              class="btn btn-success"
              id="details-store-btn"
              onclick="toggleStoreListAction()"
            >
              ➕ إضافة المتجر للقائمة
            </button>
            <button
              class="btn btn-secondary"
              id="details-save-btn"
              style="
                border: 1px solid var(--color-success);
                color: var(--color-success);
                background: transparent;
              "
            >
              احفظ المنتج
            </button>
            <select
              id="details-collection-select"
              style="
                font-size: 0.8rem;
                padding: 0.5rem;
                border-radius: var(--radius-sm);
                border: 1px solid var(--border-color);
                background: var(--bg-input);
                color: var(--color-text-main);
                width: 145px;
                display: none;
                margin-left: 8px;
                cursor: pointer;
              "
              onchange="handleDetailsCollectionChange()"
            ></select>
            <button class="details-modal-close" onclick="closeDetailsModal()">
              &times;
            </button>
          </div>
        </div>
        <div class="details-modal-body">
          <!-- Left Panel: Chart & Metrics -->
          <div class="details-left-panel">
            <!-- Timeline Section -->
            <div class="details-section-card">
              <div class="details-section-title">
                🕒 المخطط الزمني
                <span
                  style="
                    font-size: 0.85rem;
                    color: var(--color-text-muted);
                    font-weight: normal;
                    margin-right: 8px;
                  "
                  >تاريخ مرئي لنشاط الإعلان وعدد مرات إعادة تفعيله.</span
                >
              </div>
              <div class="details-timeline-chart" id="details-chart">
                <!-- Dynamic Bars generated via JS -->
              </div>
              <div class="details-chart-legend">
                <div class="legend-item">
                  <div class="legend-marker bar"></div>
                  <span
                    >ارتفاع العمود: يمثل عدد الكرياتيف النشطة (الكثافة).</span
                  >
                </div>
                <div class="legend-item">
                  <div class="legend-marker dot"></div>
                  <span
                    >النقطة الحمراء: تشير إلى "تاريخ انتهاء مجدول". إذا تجاوزت
                    الإعلانات هذا التاريخ باستمرار، فهذه إشارة قوية للربحية
                    (استمروا بالدفع لتشغيلها).</span
                  >
                </div>
                <div class="legend-item">
                  <div class="legend-marker orange"></div>
                  <span
                    >الشريط البرتقالي: إعادة إحياء (بدأ الإعلان بالعمل مرة أخرى
                    بعد توقف ملحوظ).</span
                  >
                </div>
              </div>
            </div>

            <!-- Ad Strategy Analysis Section -->
            <div class="strategy-analysis-card">
              <div
                class="details-section-title"
                style="color: var(--color-primary)"
              >
                ⚡ تحليل استراتيجية الإعلان
              </div>
              <div class="strategy-badge">✓ منتج رابح (تم التحسين)</div>
              <p
                id="details-analysis-text"
                style="
                  font-size: 0.85rem;
                  line-height: 1.6;
                  color: var(--color-text-main);
                  margin-top: 8px;
                "
              >
                قام المعلن باختيار مكثف (High hhhh Peak)، ثم أوقف الإعلانات الخاسرة.
                ويركز الآن فقط على الإعلانات الرابحة لزيادة المبيعات (Scaling).
                هذه إشارة قوية جداً لمنتج مربح. توقفت الإعلانات ثم عادت أو
                اختفت، هذا يعني غالباً أن المنتج نفذ من المخزون.
              </p>
            </div>

            <!-- Key Indicators Section -->
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
                <div class="indicator-card">
                  <div class="indicator-title">🔝 أقصى عدد كرياتيف</div>
                  <div class="indicator-value" id="details-max-creatives">
                    0
                  </div>
                </div>
                <div class="indicator-card">
                  <div class="indicator-title">🔄 إعادة النشاط</div>
                  <div class="indicator-value" id="details-reactivations">
                    0
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Right Panel: Showcase Media & Description -->
          <div class="details-right-panel">
            <div class="details-media-showcase" id="details-media">
              <!-- Dynamic media items -->
            </div>

            <div class="details-product-info">
              <div class="details-product-title" id="details-info-title">-</div>
              <p class="details-product-desc" id="details-info-desc">-</p>
            </div>

            <!-- All Data & JSON Download Section -->
            <div
              class="details-raw-data-card"
              style="
                background: var(--bg-input);
                border-radius: var(--radius-sm);
                border: 1px solid var(--border-color);
                padding: 12px;
                margin-top: 15px;
              "
            >
              <div
                style="
                  display: flex;
                  justify-content: space-between;
                  align-items: center;
                  margin-bottom: 8px;
                "
              >
                <span
                  style="
                    font-weight: 700;
                    font-size: 0.85rem;
                    color: var(--color-primary);
                  "
                  >📋 بيانات المنتج الكاملة (JSON)</span
                >
                <button
                  class="btn btn-secondary"
                  id="details-json-download-btn"
                  onclick="downloadProductDataJSON()"
                  style="
                    padding: 4px 8px;
                    font-size: 0.75rem;
                    display: flex;
                    align-items: center;
                    gap: 4px;
                  "
                >
                  📥 تحميل JSON
                </button>
              </div>
              <div
                id="details-raw-data-list"
                style="
                  max-height: 150px;
                  overflow-y: auto;
                  font-size: 0.75rem;
                  color: var(--color-text-muted);
                  font-family: var(--font-mono);
                  line-height: 1.4;
                  display: flex;
                  flex-direction: column;
                  gap: 4px;
                  direction: ltr;
                  text-align: left;
                "
              >
                <!-- Dynamically populated key-value list -->
              </div>
            </div>

            <div class="details-action-buttons">
              <button
                class="btn btn-success"
                id="details-product-action-btn"
                onclick="showProductAnalysisToast()"
              >
                📊 تحليل المنتج
              </button>
              <button
                class="btn btn-primary"
                id="details-download-btn"
                onclick="downloadProductMedia()"
              >
                📥 تحميل
              </button>
              <button
                class="btn btn-purple"
                id="details-ad-analysis-btn"
                onclick="showAdAnalysisToast()"
              >
                ✨ تحليل الإعلان
              </button>
              <a
                href="#"
                target="_blank"
                class="btn btn-dashed"
                id="details-fb-library-btn"
                >🌐 عرض في مكتبة الإعلانات</a
              >
              <button
                class="btn btn-secondary"
                id="details-refresh-activity-btn"
                onclick="refreshActivityData()"
                style="border:1px solid var(--color-primary);color:var(--color-primary)"
              >
                🔄 تحديث النشاط
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Details Help Modal -->
    <div class="help-modal-overlay" id="details-help-modal">
      <div class="help-modal-card">
        <div class="help-modal-header">
          <div class="help-modal-title">💡 دليل قراءة وتحليل الإحصائيات</div>
          <button class="help-modal-close" onclick="closeDetailsHelpModal()">
            &times;
          </button>
        </div>
        <div class="help-modal-body">
          <div class="help-section">
            <div class="help-section-title">
              🕒 المخطط الزمني (Activity Timeline)
            </div>
            <div class="help-section-desc">
              يُظهر حجم ونشاط إعلانات المنتج على مدار الـ 12 أسبوعاً الماضية.
              <br />• <b>ارتفاع الأعمدة</b>: يُمثل كثافة الإعلانات النشطة وحجم
              الميزانيات المخصصة من المنافسين في ذلك الأسبوع. <br />•
              <b>النقاط الحمراء</b>: تُشير لانتهاء وتوقف حملات إعلانية معينة
              (غربلة الإعلانات الخاسرة).
            </div>
          </div>

          <div
            class="help-section"
            style="border-left: 4px solid var(--color-success)"
          >
            <div class="help-section-title" style="color: var(--color-success)">
              🔄 أحداث إعادة التنشيط (Reactivation Events)
            </div>
            <div class="help-section-desc">
              <b>الإشارة الذهبية للأرباح!</b> تُحسب عند رصد فترة خمول كامل (0
              إعلان نشط) تليها عودة قوية ومفاجئة للإعلانات. يدل هذا على أن
              المنتج مربح للغاية، وأن المعلن أوقفه مؤقتاً فقط بسبب
              <b>نفاد المخزون (Out of Stock)</b> ثم أعاد تشغيله فور التزود
              بالسلعة مجدداً.
            </div>
          </div>

          <div class="help-section">
            <div class="help-section-title">
              🧠 تحليل استراتيجية الإعلان (Marketing Strategy)
            </div>
            <div class="help-section-desc">
              قراءة تسويقية ذكية تلخص لك توجه المنافس الإعلاني:
              <br />• <b>التكبير والتوسع (Scaling)</b>: تفعيل ميزانيات ضخمة
              وعشرات الإعلانات النشطة في نفس الوقت مما يثبت نجاح العائد
              الإعلاني. <br />• <b>الاختبار الأولي (Testing)</b>: تشغيل إعلانات
              محدودة ومواد إعلانية بسيطة لاستكشاف اهتمام السوق.
            </div>
          </div>

          <div class="help-section">
            <div class="help-section-title">
              📊 المؤشرات الرئيسية (KPIs Metrics)
            </div>
            <div class="help-section-desc">
              • <b>المشاهدات المقدرة (Estimated Views)</b>: حساب المدى المحتمل
              للانتشار والوصول بناءً على أوزان الميزانيات الإعلانية ومعدل
              التكرار. <br />• <b>التفاعل المقدر (Estimated Engagement)</b>:
              معدل التفاعل التفاعلي المتوقع للمنتج في السوق المحلي بمتوسط 7%.
              <br />• <b>أقصى عدد كرياتيف (Max Creatives)</b>: عدد الزوايا
              الإعلانية الفريدة والفيديوهات التي يختبرها المنافس لمعرفة أيها
              يجذب المبيعات.
            </div>
          </div>

          <div class="help-section">
            <div class="help-section-title">⚡ مركز العمليات السريعة</div>
            <div class="help-section-desc">
              • <b>إضافة المتجر للقائمة</b>: لمراقبة نطاق المنافس وتتبع حركته
              الإجمالية. <br />• <b>مكتبة إعلانات فيسبوك</b>: للبحث المباشر عن
              اسم المنتج والبلد لمشاهدة إعلانات المنافسين النشطة والاطلاع على
              تفاصيلها الحية.
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Info Modal for index products -->
    <div class="modal-overlay" id="index-info-modal" style="display: none; align-items: center; justify-content: center; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); z-index: 200;">
      <div class="modal-card" style="background: var(--bg-card); padding: 2rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); width: 90%; max-width: 520px; box-shadow: var(--shadow-lg); transition: var(--transition-all); max-height: 90vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
          <div style="flex: 1; min-width: 0;">
            <h3 id="index-info-title" style="font-weight: 700; font-size: 1.1rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">-</h3>
            <div id="index-info-domain" style="font-size: 0.8rem; color: var(--color-text-muted); margin-top: 2px;">-</div>
          </div>
          <button class="details-modal-close" onclick="closeIndexInfoModal()">&times;</button>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 1rem;">
          <div class="p-stat-box" style="background: var(--bg-input); padding: 10px; border-radius: var(--radius-sm); text-align: center;">
            <span class="p-stat-val" id="index-info-ads">0</span>
            <span class="p-stat-lbl">الإعلانات</span>
          </div>
          <div class="p-stat-box" style="background: var(--bg-input); padding: 10px; border-radius: var(--radius-sm); text-align: center;">
            <span class="p-stat-val" id="index-info-images">0</span>
            <span class="p-stat-lbl">الصور</span>
          </div>
          <div class="p-stat-box" style="background: var(--bg-input); padding: 10px; border-radius: var(--radius-sm); text-align: center;">
            <span class="p-stat-val" id="index-info-creatives">0</span>
            <span class="p-stat-lbl">الإبداعية</span>
          </div>
          <div class="p-stat-box" style="background: var(--bg-input); padding: 10px; border-radius: var(--radius-sm); text-align: center;">
            <span class="p-stat-val" id="index-info-date">--</span>
            <span class="p-stat-lbl">تاريخ الإطلاق</span>
          </div>
        </div>

        <div class="ad-copy-section" style="margin-bottom: 1rem;">
          <div class="ad-copy-title" id="index-info-ad-title">💬 نص الإعلان</div>
          <p class="ad-copy-text" id="index-info-ad-body" style="font-size: 0.85rem; line-height: 1.6; color: var(--color-text-main);">لا يوجد نص تفصيلي.</p>
        </div>

        <div style="display: flex; gap: 8px; justify-content: flex-end; border-top: 1px solid var(--border-color); padding-top: 1rem;">
          <button class="btn btn-primary" id="index-info-visit-btn" style="font-size: 0.85rem;">🛒 زيارة المنتج</button>
          <button class="btn btn-secondary" onclick="closeIndexInfoModal()" style="font-size: 0.85rem;">إغلاق</button>
        </div>
      </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toast-container"></div>

    <script>
      window.INITIAL_PRODUCTS_FROM_DB = <?= isset($initialData) ? json_encode($initialData) : 'null' ?>;
    </script>
    <script src="https://vjs.zencdn.net/8.16.1/video.min.js"></script>
    <script src="<?= base_url('index.js') ?>?v=1.8"></script>
  </body>
</html>
