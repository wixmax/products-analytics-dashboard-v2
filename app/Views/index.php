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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css" />
    <link rel="stylesheet" href="<?= base_url('index.css') ?>?v=1.6" />
  </head>
  <body>
    <?php if (session()->has('impersonator_user_id')): ?>
      <div style="background: linear-gradient(90deg, #f59e0b, #d97706); color: white; padding: 10px 20px; text-align: center; font-weight: bold; display: flex; justify-content: center; align-items: center; gap: 15px; z-index: 9999; font-size: 0.9rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); width: 100%;">
        <span>⚠️ أنت تتصفح النظام حالياً بصفتك: <strong><?= esc(auth()->user()->username) ?></strong> (محاكاة حساب)</span>
        <a href="<?= base_url('admin/users/stop-impersonating') ?>" style="background: white; color: #b45309; padding: 4px 12px; border-radius: 4px; text-decoration: none; font-size: 0.8rem; font-weight: 700; transition: all 0.2s;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background='white'">العودة لحساب المسؤول 🚪</a>
      </div>
    <?php endif; ?>
    <div class="app-shell">
      <?= $this->include('partials/sidebar', ['subtitle' => 'بناء استعلام API وتصفح النتائج']) ?>

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
            <button
              class="theme-toggle"
              id="theme-toggle-btn"
              aria-label="تبديل الثيم"
            >
              🌓
            </button>
          </div>
        </div>

        <!-- Dashboard Filter Panel -->
        <div class="dashboard-filter-card" id="dashboard-filter-panel" style="padding: 1.25rem;">
          <div class="filter-card-header" onclick="toggleFilterPanel()" style="display: flex; justify-content: space-between; align-items: center; cursor: pointer; user-select: none; border-bottom: 1px solid var(--border-color); padding-bottom: 0.75rem; margin-bottom: 0.75rem;">
            <div style="display: flex; align-items: center; gap: 8px;">
              <span style="font-size: 1.1rem;">🔍</span>
              <h3 style="font-size: 0.95rem; font-weight: 700; margin: 0; color: var(--color-text-main);">تصفية واستعلام البيانات (tRPC API Filter)</h3>
            </div>
            <span id="filter-toggle-icon" style="font-size: 0.9rem; color: var(--color-text-muted); font-weight: bold;">🔼</span>
          </div>

          <div id="filter-panel-body" style="display: block;">
            <div class="dashboard-filter-grid">
              
              <!-- Column 1: Mode & Keyword -->
              <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                <div class="form-group">
                  <label for="api-endpoint-select">🎯 نوع الاستعلام / البيانات</label>
                  <select id="api-endpoint-select" onchange="toggleApiMode()">
                    <option value="" disabled>
                      -- اختر نوع الاستعلام / البيانات أولاً --
                    </option>
                    <option value="insights">Overview Insights (مؤشرات السوق)</option>
                    <option value="winning" selected>Winning Products (المنتجات الرابحة)</option>
                  </select>
                </div>
                
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
              </div>

              <!-- Column 2: Categories -->
              <div class="form-group">
                <label>التصنيفات (Categories)</label>
                <select id="api-filter-category" multiple style="min-height: 120px; height: 100%;">
                  <!-- Will be generated via JS -->
                </select>
                <small style="color: var(--color-text-muted); font-size: 0.7rem"
                  >استمر بالضغط على Ctrl (أو Cmd) لتحديد أكثر من خيار</small
                >
              </div>

              <!-- Column 3: Countries -->
              <div class="form-group">
                <label>الدول (Countries)</label>
                <select id="api-filter-country" multiple style="min-height: 120px; height: 100%;">
                  <!-- Will be generated via JS -->
                </select>
                <small style="color: var(--color-text-muted); font-size: 0.7rem"
                  >استمر بالضغط على Ctrl (أو Cmd) لتحديد أكثر من خيار</small
                >
              </div>

              <!-- Column 4: Version, Date, Prices, Weeks, Transformation -->
              <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px">
                  <div class="form-group">
                    <label for="filter-date">📅 التاريخ</label>
                    <input type="text" id="filter-date" class="flatpickr-date" placeholder="اختر تاريخاً" />
                  </div>
                  <div class="form-group">
                    <label for="filter-version">🔢 رقم الإصدار (v)</label>
                    <input type="text" id="filter-version" value="1.10" placeholder="مثال: 1.10" />
                  </div>
                </div>

                <div class="form-group insights-only" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px">
                  <div>
                    <label for="filter-priceFrom">السعر من</label>
                    <input type="number" id="filter-priceFrom" value="-1" />
                  </div>
                  <div>
                    <label for="filter-priceTo">السعر إلى</label>
                    <input type="number" id="filter-priceTo" value="-1" />
                  </div>
                </div>

                <div class="form-group insights-only" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px">
                  <div>
                    <label for="filter-weeks">عدد الأسابيع</label>
                    <input type="number" id="filter-weeks" value="12" min="1" />
                  </div>
                  <div>
                    <label for="filter-transformation">التحويل</label>
                    <select id="filter-transformation">
                      <option value="market-reaction" selected>market-reaction</option>
                      <option value="none">بدون تحويل</option>
                    </select>
                  </div>
                </div>
              </div>

            </div>

            <!-- Actions & URL Generation Preview Row -->
            <div style="display: grid; grid-template-columns: 1fr 1.5fr; gap: 1.5rem; align-items: end; border-top: 1px solid var(--border-color); padding-top: 1.25rem; margin-top: 1.25rem;">
              <div>
                <button
                  class="btn btn-primary btn-block"
                  id="apply-filters-btn"
                  onclick="handleFetchAPI()"
                  style="height: 50px; font-size: 1rem;"
                >
                  🚀 جلب البيانات من الرابط المولد
                </button>
              </div>
              
              <div class="url-preview-card" style="padding: 0.75rem 1rem; gap: 8px;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                  <label style="font-weight: 700; font-size: 0.85rem; margin-bottom: 0;">رابط tRPC المشفر والمولد:</label>
                  <div style="display: flex; gap: 6px;">
                    <button
                      class="btn btn-secondary"
                      style="padding: 4px 10px; font-size: 0.75rem;"
                      onclick="copyGeneratedURL()"
                    >
                      🔗 نسخ الرابط
                    </button>
                    <button
                      class="btn btn-primary"
                      style="padding: 4px 10px; font-size: 0.75rem;"
                      onclick="openGeneratedURL()"
                    >
                      🌍 فتح في نافذة جديدة
                    </button>
                  </div>
                </div>
                <div class="url-box" id="generated-url" style="max-height: 45px; font-size: 0.75rem; padding: 6px 10px;">
                  https://www.overviewdata.io/...
                </div>
              </div>
            </div>
          </div>

        </div>

        <script>
          function toggleFilterPanel() {
            const panel = document.getElementById('dashboard-filter-panel');
            const body = document.getElementById('filter-panel-body');
            const icon = document.getElementById('filter-toggle-icon');
            const header = document.querySelector('.filter-card-header');
            
            const isCollapsed = panel.classList.toggle('collapsed');
            
            if (isCollapsed) {
              body.style.display = 'none';
              icon.textContent = '🔽';
              header.style.borderBottom = 'none';
              header.style.marginBottom = '0';
              header.style.paddingBottom = '0';
              localStorage.setItem('filter_panel_collapsed', 'true');
            } else {
              body.style.display = 'block';
              icon.textContent = '🔼';
              header.style.borderBottom = '1px solid var(--border-color)';
              header.style.marginBottom = '0.75rem';
              header.style.paddingBottom = '0.75rem';
              localStorage.removeItem('filter_panel_collapsed');
            }
          }

          // Restore state on load
          document.addEventListener('DOMContentLoaded', () => {
            if (localStorage.getItem('filter_panel_collapsed') === 'true') {
              const panel = document.getElementById('dashboard-filter-panel');
              const body = document.getElementById('filter-panel-body');
              const icon = document.getElementById('filter-toggle-icon');
              const header = document.querySelector('.filter-card-header');
              
              panel.classList.add('collapsed');
              body.style.display = 'none';
              icon.textContent = '🔽';
              header.style.borderBottom = 'none';
              header.style.marginBottom = '0';
              header.style.paddingBottom = '0';
            }
          });
        </script>

        <!-- Statistics / Analysis Visualization -->
        <div
          class="analytics-panel"
          id="analytics-section"
          style="display: none"
        >
          <!-- Main Stat Chart based on adaptedResult -->
          <div class="chart-card">
            <div class="card-title" style="display: flex; justify-content: space-between; align-items: center;">
              <span>📊 مخطط حركة الإدراجات الأسبوعية (Weekly New Listings)</span>
              <button class="btn btn-secondary" onclick="openAnalyticsHelpModal('listings')" style="padding: 4px 10px; font-size: 0.75rem; border-color: var(--color-primary); color: var(--color-primary); border-radius: var(--radius-sm); font-weight: 700; cursor: pointer;" title="شرح مفصل للمخطط الأسبوعي">
                💡 دليل القراءة
              </button>
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
            <div class="card-title" style="display: flex; justify-content: space-between; align-items: center;">
              <span>🛍️ تحليل المتاجر والعرض</span>
              <button class="btn btn-secondary" onclick="openAnalyticsHelpModal('shops')" style="padding: 4px 10px; font-size: 0.75rem; border-color: var(--color-primary); color: var(--color-primary); border-radius: var(--radius-sm); font-weight: 700; cursor: pointer;" title="شرح مفصل لتحليل المتاجر والعرض">
                💡 دليل القراءة
              </button>
            </div>
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
              
              <!-- حقل تعديل السعر يدوياً -->
              <div class="details-price-edit" style="margin-top: 15px; display: flex; align-items: center; gap: 10px; background: var(--bg-card); padding: 8px 12px; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
                <span style="font-weight: 700; font-size: 0.85rem; color: var(--color-primary);">💰 سعر المنتج (تعديل يدوي):</span>
                <input type="text" id="details-price-input" value="0" style="width: 100px; padding: 6px 10px; border-radius: var(--radius-sm); border: 1px solid var(--border-color); background: var(--bg-input); color: var(--color-text-main); font-size: 0.85rem; text-align: center; font-weight: 600;" onchange="handleDetailsPriceChange(this.value)" />
                <span style="font-size: 0.85rem; color: var(--color-text-muted);">USD / local currency</span>
              </div>
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

    <!-- Analytics Explanation Modal -->
    <div class="modal-overlay" id="analytics-help-modal" style="display: none; z-index: 10000;">
      <div class="modal-card" style="max-width: 650px; width: 90%; padding: 1.75rem; border-radius: var(--radius-md); box-shadow: var(--shadow-lg);">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 1rem; margin-bottom: 1.25rem;">
          <h3 id="analytics-help-title" style="font-weight: 800; font-size: 1.15rem; color: var(--color-primary); display: flex; align-items: center; gap: 8px; margin: 0;">
            💡 دليل ودور التحليلات
          </h3>
          <button style="background: none; border: none; font-size: 1.6rem; cursor: pointer; color: var(--color-text-muted); line-height: 1;" onclick="closeAnalyticsHelpModal()">&times;</button>
        </div>
        <div id="analytics-help-body" style="font-size: 0.9rem; line-height: 1.7; color: var(--color-text-main); max-height: 70vh; overflow-y: auto;">
          <!-- Dynamic Help Content -->
        </div>
        <div style="display: flex; justify-content: flex-end; margin-top: 1.25rem; border-top: 1px solid var(--border-color); padding-top: 0.85rem;">
          <button class="btn btn-primary" onclick="closeAnalyticsHelpModal()" style="padding: 0.5rem 1.4rem; font-size: 0.9rem;">فهمت ذلك 👍</button>
        </div>
      </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toast-container"></div>

    <script>
      function openAnalyticsHelpModal(type) {
        const modal = document.getElementById('analytics-help-modal');
        const title = document.getElementById('analytics-help-title');
        const body = document.getElementById('analytics-help-body');

        if (type === 'listings') {
          title.innerHTML = '📊 دليل مخطط حركة الإدراجات الأسبوعية (Weekly New Listings)';
          body.innerHTML = `
            <div style="display: flex; flex-direction: column; gap: 14px;">
              <p style="margin: 0; color: var(--color-text-muted); font-size: 0.95rem; line-height: 1.6;">
                يقوم هذا المخطط بتتبع وتقييم حركة إدراج وإطلاق المنتجات الإعلانية الجديدة أسبوعياً خلال آخر 12 أسبوعاً.
              </p>
              
              <div style="background: var(--bg-input); padding: 14px; border-radius: var(--radius-sm); border-right: 4px solid var(--color-primary);">
                <strong style="color: var(--color-primary); font-size: 0.95rem; font-weight: 700;">🎯 الهدف والدور الأساسي:</strong>
                <p style="margin: 6px 0 0 0; font-size: 0.88rem; color: var(--color-text-main);">
                  معرفة ما إذا كان السوق ينشط ويدخله معروض جديد من المنتجات، أم أنه يمر بمرحلة هدوء واستقرار في الإدراجات.
                </p>
              </div>

              <div style="display: flex; flex-direction: column; gap: 10px;">
                <strong style="font-weight: 700; font-size: 0.95rem;">📌 كيفية قراءة عناصر المخطط:</strong>
                <ul style="margin: 0; padding-right: 20px; font-size: 0.88rem; display: flex; flex-direction: column; gap: 8px; color: var(--color-text-main);">
                  <li><strong>ارتفاع العمود (Bar Height):</strong> يمثل إجمالي عدد المنتجات والإعلانات التي تم إطلاقها في ذلك الأسبوع المحدد.</li>
                  <li><strong>التدفق الزمني (12 أسبوعاً):</strong> يتيح لك رؤية منحنى صعود أو هبوط المعروض بمرور الوقت لملاحظة أوقات الذروة والمواسم.</li>
                  <li><strong>شريط الزخم (Supply Momentum):</strong> يحسب متوسط آخر 4 أسابيع مقارنة بالأربعة أسابيع السابقة لمعرفة ما إذا كان الاتجاه <span style="color: var(--color-success); font-weight: 700;">📈 تصاعدياً</span> أم <span style="color: var(--color-error); font-weight: 700;">📉 تنازلياً</span>.</li>
                </ul>
              </div>
            </div>
          `;
        } else if (type === 'shops') {
          title.innerHTML = '🛍️ دليل تحليل المتاجر والعرض (Shops & Supply Analysis)';
          body.innerHTML = `
            <div style="display: flex; flex-direction: column; gap: 14px;">
              <p style="margin: 0; color: var(--color-text-muted); font-size: 0.95rem; line-height: 1.6;">
                توفر هذه البطاقة مؤشرات حول حركة المنافسين، عدد المتاجر النشطة، ومعدل نموها وتوزع المعروض في السوق.
              </p>

              <div style="display: flex; flex-direction: column; gap: 12px;">
                <div style="background: var(--bg-input); padding: 12px 14px; border-radius: var(--radius-sm); border-right: 4px solid var(--color-success);">
                  <strong style="color: var(--color-success); font-size: 0.95rem;">1️⃣ الزخم في المعروض (Supply Momentum)</strong>
                  <p style="margin: 4px 0 0 0; font-size: 0.88rem; color: var(--color-text-main);">
                    يُظهر اتجاه حركة المنتجات. <b>تصاعدي 📈</b> يعني إقبالاً وتزايداً في المعروض، بينما <b>مستقر / تنازلي 📉</b> يعبر عن هدوء الإدراجات.
                  </p>
                </div>

                <div style="background: var(--bg-input); padding: 12px 14px; border-radius: var(--radius-sm); border-right: 4px solid var(--color-primary);">
                  <strong style="color: var(--color-primary); font-size: 0.95rem;">2️⃣ المتاجر النشطة حالياً (Active Stores)</strong>
                  <p style="margin: 4px 0 0 0; font-size: 0.88rem; color: var(--color-text-main);">
                    عدد المتاجر الإلكترونية المستقلة الفريدة (Unique Domains) التي تبيع وتعلن عن هذه المنتجات حالياً.
                  </p>
                </div>

                <div style="background: var(--bg-input); padding: 12px 14px; border-radius: var(--radius-sm); border-right: 4px solid var(--color-warning);">
                  <strong style="color: var(--color-warning); font-size: 0.95rem;">3️⃣ الزيادة في المتاجر (Shops Growth Trend %)</strong>
                  <p style="margin: 4px 0 0 0; font-size: 0.88rem; color: var(--color-text-main);">
                    نسبة التغير المئوية لدخول متاجر جديدة مقارنة بالفترة السابقة. الزيادة الإيجابية تدل على انتعاش وإقبال التجار على السوق.
                  </p>
                </div>
              </div>
            </div>
          `;
        }

        modal.style.display = 'flex';
      }

      function closeAnalyticsHelpModal() {
        document.getElementById('analytics-help-modal').style.display = 'none';
      }
    </script>

    <script>
      window.INITIAL_PRODUCTS_FROM_DB = <?= isset($initialData) ? json_encode($initialData) : 'null' ?>;
    </script>
    <script src="https://vjs.zencdn.net/8.16.1/video.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="<?= base_url('analysis-helper.js') ?>?v=1.0"></script>
    <script src="<?= base_url('index.js') ?>?v=2.7"></script>
  </body>
</html>
