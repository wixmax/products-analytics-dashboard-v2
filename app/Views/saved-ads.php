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
    <title>Saved Ads | الإعلانات المحفوظة</title>
    <meta name="description" content="إدارة وتقييم المنتجات التي قمت بحفظها." />

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
      .rating-stars {
        display: flex;
        gap: 5px;
        margin-bottom: 10px;
      }
      .star {
        font-size: 1.5rem;
        cursor: pointer;
        color: var(--color-text-muted);
        transition: color 0.2s;
      }
      .star.filled {
        color: var(--color-warning);
      }
      .notes-area {
        width: 100%;
        min-height: 80px;
        margin-top: 10px;
        padding: 8px;
        border-radius: var(--radius-sm);
        border: 1px solid var(--border-color);
        background: var(--bg-input);
        font-family: var(--font-sans);
        font-size: 0.85rem;
        resize: vertical;
      }
      .saved-meta {
        font-size: 0.75rem;
        color: var(--color-text-muted);
        margin-bottom: 5px;
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
      <?= $this->include('partials/sidebar', ['subtitle' => 'الإعلانات المحفوظة']) ?>

      <!-- Main Area -->
      <main class="main-content">
        <!-- Top Navigation -->
        <div class="top-nav">
          <div>
            <h2
              style="
                font-weight: 800;
                font-size: 1.6rem;
                letter-spacing: -0.01em;
              "
            >
              ⭐ الإعلانات المحفوظة والتقييمات
            </h2>
            <p style="color: var(--color-text-muted); font-size: 0.85rem">
              إدارة منتجاتك المختارة وإضافة تقييمات وملاحظات عليها
            </p>
          </div>

          <div class="actions-group">
            <button class="btn btn-primary" onclick="openAiAnalysisModal()" style="background: linear-gradient(135deg, #6366f1, #8b5cf6); border: none; color: white; font-weight: 700; gap: 6px; display: flex; align-items: center;">
              🚀 تحليل الذكاء الاصطناعي
            </button>
            <button class="btn btn-secondary" onclick="openAiHistoryDrawer()" title="عرض التحليلات السابقة المحفوظة">
              📜 التحاليل المحفوظة
            </button>
            <button class="btn btn-secondary" onclick="openSavedPhase2Modal()" title="عرض تحليلات Phase 2 المحفوظة" style="border-color: #10b981; color: #10b981;">
              📂 تحليلات Phase 2
            </button>
            <button class="btn btn-success" onclick="downloadSavedJSON()">
              📥 تحميل JSON
            </button>
            <button class="btn btn-secondary" onclick="document.getElementById('saved-import-input').click()">
              📂 استيراد JSON
            </button>
            <input type="file" id="saved-import-input" accept=".json" style="display:none" onchange="importSavedAdsFile(event)">
            <button class="btn btn-error" onclick="clearAllSaved()">
              🗑️ مسح الكل
            </button>
            <?php if (auth()->loggedIn() && auth()->user()->inGroup('superadmin', 'admin')): ?>
              <button class="btn btn-secondary" onclick="generateAllVideoThumbnails()" title="توليد وتخزين صور الفيديوهات لجميع البطاقات الظاهرة">
                🎬 توليد صور الفيديوهات
              </button>
            <?php endif; ?>
            <button class="theme-toggle" id="theme-toggle-btn">🌓</button>
          </div>
        </div>

        <!-- Toolbar -->
        <div class="product-toolbar">
          <div class="search-box">
            <input
              type="text"
              id="saved-search"
              placeholder="ابحث في الإعلانات المحفوظة..."
              oninput="renderSavedGrid()"
            />
          </div>
          <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
            <select id="collection-filter" onchange="renderSavedGrid()" style="width: 170px;">
              <option value="all">جميع المجموعات 📁</option>
            </select>
            <button class="btn btn-secondary" onclick="openCollectionsModal()" style="padding: 0.5rem 1rem; font-size: 0.85rem;">📁 المجموعات</button>
            <select id="status-filter" onchange="renderSavedGrid()">
              <option value="all">جميع الحالات</option>
              <option value="active" selected>نشط (Active)</option>
              <option value="tested">تمت التجربة (Tested)</option>
              <option value="archived">مؤرشف (Archived)</option>
            </select>
            <select id="saved-sort" onchange="renderSavedGrid()">
              <option value="newest">الأحدث حفظاً</option>
              <option value="oldest">الأقدم حفظاً</option>
              <option value="rating-desc">الأعلى تقييماً</option>
              <option value="rating-asc">الأقل تقييماً</option>
            </select>
          </div>
        </div>

        <!-- Product Grid -->
        <div class="products-grid" id="saved-products-container">
          <!-- Dynamic Content -->
        </div>
      </main>
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
            >
            </select>
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
                قام المعلن باختيار مكثف (High hhhhh Peak)، ثم أوقف الإعلانات الخاسرة.
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
            <div class="details-raw-data-card" style="background: var(--bg-input); border-radius: var(--radius-sm); border: 1px solid var(--border-color); padding: 12px; margin-top: 15px;">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <span style="font-weight: 700; font-size: 0.85rem; color: var(--color-primary);">📋 بيانات المنتج الكاملة (JSON)</span>
                <button class="btn btn-secondary" id="details-json-download-btn" onclick="downloadProductDataJSON()" style="padding: 4px 8px; font-size: 0.75rem; display: flex; align-items: center; gap: 4px;">
                  📥 تحميل JSON
                </button>
              </div>
              <div id="details-raw-data-list" style="max-height: 150px; overflow-y: auto; font-size: 0.75rem; color: var(--color-text-muted); font-family: var(--font-mono); line-height: 1.4; display: flex; flex-direction: column; gap: 4px; direction: ltr; text-align: left;">
                <!-- Dynamically populated key-value list -->
              </div>
            </div>

            <div class="details-action-buttons">
              <button
                class="btn btn-success"
                id="details-product-action-btn"
                onclick="openPhase2FromDetails()"
              >
                📊 تحليل المنتج (Phase 2)
              </button>
              <button
                class="btn btn-secondary"
                id="details-product-history-btn"
                onclick="openSavedPhase2ForCurrentProduct()"
                style="border-color: #6366f1; color: #6366f1;"
              >
                📂 التحاليل المحفوظة
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

    <!-- Collections Management Modal -->
    <div class="modal-overlay" id="collections-modal" style="display: none; align-items: center; justify-content: center; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); z-index: 200;">
      <div class="modal-card" style="background: var(--bg-card); padding: 2rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); width: 90%; max-width: 480px; box-shadow: var(--shadow-lg); transition: var(--transition-all);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
          <h3 style="font-weight: 700; font-size: 1.25rem;">📁 إدارة مجموعات المنتجات</h3>
          <button style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--color-text-muted);" onclick="closeCollectionsModal()">×</button>
        </div>
        
        <div class="form-group" style="margin-bottom: 1.5rem;">
          <label for="new-collection-input" style="margin-bottom: 0.5rem; font-weight: 600;">➕ إضافة مجموعة جديدة:</label>
          <div style="display: flex; gap: 8px;">
            <input type="text" id="new-collection-input" placeholder="اسم المجموعة..." style="flex: 1;" />
            <button class="btn btn-success" onclick="handleAddCollection()" style="padding: 0.5rem 1rem;">إضافة</button>
          </div>
        </div>

        <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">📋 المجموعات الحالية:</label>
        <div id="collections-list-container" style="max-height: 200px; overflow-y: auto; background: var(--bg-input); border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 8px; display: flex; flex-direction: column; gap: 6px;">
          <!-- Dynamically populated -->
        </div>

        <div style="display: flex; justify-content: flex-end; margin-top: 1.5rem;">
          <button class="btn btn-secondary" onclick="closeCollectionsModal()">إغلاق</button>
        </div>
      </div>
    </div>

    <!-- Info Modal for saved products -->
    <div class="modal-overlay" id="saved-info-modal" style="display: none; align-items: center; justify-content: center; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); z-index: 200;">
      <div class="modal-card" style="background: var(--bg-card); padding: 2rem; border-radius: var(--radius-md); border: 1px solid var(--border-color); width: 90%; max-width: 560px; box-shadow: var(--shadow-lg); transition: var(--transition-all); max-height: 90vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
          <div style="flex: 1; min-width: 0;">
            <h3 id="saved-info-title" style="font-weight: 700; font-size: 1.1rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">-</h3>
            <div id="saved-info-domain" style="font-size: 0.8rem; color: var(--color-text-muted); margin-top: 2px;">-</div>
          </div>
          <button class="details-modal-close" onclick="closeInfoModal()">&times;</button>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 1rem;">
          <div class="p-stat-box" style="background: var(--bg-input); padding: 10px; border-radius: var(--radius-sm); text-align: center;">
            <span class="p-stat-val" id="saved-info-ads">0</span>
            <span class="p-stat-lbl">الإعلانات</span>
          </div>
          <div class="p-stat-box" style="background: var(--bg-input); padding: 10px; border-radius: var(--radius-sm); text-align: center;">
            <span class="p-stat-val" id="saved-info-images">0</span>
            <span class="p-stat-lbl">الصور</span>
          </div>
          <div class="p-stat-box" style="background: var(--bg-input); padding: 10px; border-radius: var(--radius-sm); text-align: center;">
            <span class="p-stat-val" id="saved-info-creatives">0</span>
            <span class="p-stat-lbl">الإبداعية</span>
          </div>
          <div class="p-stat-box" style="background: var(--bg-input); padding: 10px; border-radius: var(--radius-sm); text-align: center;">
            <span class="p-stat-val" id="saved-info-date">--</span>
            <span class="p-stat-lbl">تاريخ الإطلاق</span>
          </div>
        </div>

        <div class="ad-copy-section" style="margin-bottom: 1rem;">
          <div class="ad-copy-title" id="saved-info-ad-title">💬 نص الإعلان</div>
          <p class="ad-copy-text" id="saved-info-ad-body" style="font-size: 0.85rem; line-height: 1.6; color: var(--color-text-main);">لا يوجد نص تفصيلي.</p>
        </div>

        <div style="margin-bottom: 1rem; padding: 12px; background: var(--bg-input); border-radius: var(--radius-sm);">
          <label style="font-size: 0.8rem; font-weight: 600; display: block; margin-bottom: 6px;">⭐ تقييمك الشخصي:</label>
          <div class="rating-stars" id="saved-info-stars"></div>
        </div>

        <div style="margin-bottom: 1rem;">
          <label style="font-size: 0.8rem; font-weight: 600; display: block; margin-bottom: 6px;">📝 ملاحظاتك:</label>
          <textarea id="saved-info-notes" class="notes-area" placeholder="أضف ملاحظاتك أو استراتيجيتك هنا..." style="width: 100%; min-height: 80px;" onchange="handleInfoNotesChange(this.value)"></textarea>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 1rem;">
          <div>
            <label style="font-size: 0.75rem; font-weight: 600; display: block; margin-bottom: 4px;">الحالة:</label>
            <select id="saved-info-status" style="font-size: 0.8rem; padding: 6px; width: 100%; border-radius: var(--radius-sm); border: 1px solid var(--border-color); background: var(--bg-input); color: var(--color-text-main);" onchange="handleInfoStatusChange(this.value)">
              <option value="active">🟢 نشط</option>
              <option value="tested">🧪 تمت التجربة</option>
              <option value="archived">📁 مؤرشف</option>
            </select>
          </div>
          <div>
            <label style="font-size: 0.75rem; font-weight: 600; display: block; margin-bottom: 4px;">المجموعة:</label>
            <select id="saved-info-collection" style="font-size: 0.8rem; padding: 6px; width: 100%; border-radius: var(--radius-sm); border: 1px solid var(--border-color); background: var(--bg-input); color: var(--color-text-main);" onchange="handleInfoCollectionChange(this.value)"></select>
          </div>
        </div>

        <div style="display: flex; gap: 8px; justify-content: flex-end; border-top: 1px solid var(--border-color); padding-top: 1rem;">
          <button class="btn btn-primary" id="saved-info-visit-btn" style="font-size: 0.85rem;">🛒 زيارة المنتج</button>
          <button class="btn btn-secondary" onclick="closeInfoModal()" style="font-size: 0.85rem;">إغلاق</button>
        </div>
      </div>
    </div>

    <!-- AI Analysis Input Configuration Modal -->
    <div class="modal-overlay" id="ai-analysis-modal" style="display: none; z-index: 10001;">
      <div class="modal-card" style="max-width: 600px; width: 92%; padding: 1.75rem; border-radius: var(--radius-md); box-shadow: var(--shadow-lg);">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 1rem; margin-bottom: 1.25rem;">
          <h3 style="font-weight: 800; font-size: 1.2rem; color: var(--color-primary); display: flex; align-items: center; gap: 8px; margin: 0;">
            🤖 اعدادات تحليل الذكاء الاصطناعي (Phase 1)
          </h3>
          <button style="background: none; border: none; font-size: 1.6rem; cursor: pointer; color: var(--color-text-muted);" onclick="closeAiAnalysisModal()">&times;</button>
        </div>

        <form id="ai-analysis-form" onsubmit="handleRunAiAnalysis(event)" style="display: flex; flex-direction: column; gap: 1rem;">
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div>
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                <label style="font-weight: 700; font-size: 0.85rem; margin: 0;">🤖 المزود (AI Provider):</label>
                <a href="<?= base_url('settings') ?>" target="_blank" style="font-size: 0.75rem; color: var(--color-primary); text-decoration: none; font-weight: 600;">⚙️ إدارة الإعدادات</a>
              </div>
              <select id="ai-provider-select" class="form-control" style="width: 100%; padding: 8px 10px; border-radius: var(--radius-sm); border: 1px solid var(--border-color); background: var(--bg-input); color: var(--color-text-main); font-weight: 600;" onchange="handleAiProviderChangeInModal()">
                <option value="auto" selected>✨ التلقائي الافتراضي</option>
                <option value="openrouter">🌐 OpenRouter</option>
                <option value="apiyi">🚀 APIyi</option>
                <option value="openai">🤖 OpenAI (ChatGPT)</option>
                <option value="gemini">💎 Google Gemini</option>
                <option value="deepseek">🐋 DeepSeek</option>
                <option value="custom">⚡ محرك مخصص / Ollama</option>
                <option value="internal">⚡ المحرك الداخلي السريع</option>
              </select>
            </div>

            <div>
              <label style="display: block; font-weight: 700; font-size: 0.85rem; margin-bottom: 6px;">🎯 الموديل (AI Model):</label>
              <select id="ai-model-select" class="form-control" style="width: 100%; padding: 8px 10px; border-radius: var(--radius-sm); border: 1px solid var(--border-color); background: var(--bg-input); color: var(--color-text-main); font-weight: 600;">
                <option value="">✨ الموديل الافتراضي للمورد</option>
              </select>
            </div>
          </div>

          <div>
            <label style="display: block; font-weight: 700; font-size: 0.9rem; margin-bottom: 6px;">🎯 نمط البحث ومعيار التقييم:</label>
            <select id="ai-mode-select" class="form-control" style="width: 100%; padding: 10px; border-radius: var(--radius-sm); border: 1px solid var(--border-color); background: var(--bg-input); color: var(--color-text-main); font-weight: 600;">
              <option value="comprehensive" selected>⭐ تقييم شامل (نظام 100 نقطة المتكامل)</option>
              <option value="seasonal">📅 حسب الموسم ومناسبة السوق المغربي</option>
              <option value="ad_volume">🔥 حسب كثرة الإعلانات والطلب في السوق</option>
              <option value="max_margin">💰 حسب أعلى هامش ربح مالي صافي (ROI)</option>
              <option value="easy_logistics">🚚 حسب سهولة اللوجستيك وانخفاض مخاطر التوصيل</option>
            </select>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div>
              <label style="display: block; font-weight: 700; font-size: 0.85rem; margin-bottom: 6px;">💵 الميزانية الإعلانية الإجمالية (درهم):</label>
              <input type="number" id="ai-budget-input" value="5000" min="500" step="500" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: var(--radius-sm); border: 1px solid var(--border-color); background: var(--bg-input); color: var(--color-text-main);" required />
            </div>

            <div>
              <label style="display: block; font-weight: 700; font-size: 0.85rem; margin-bottom: 6px;">☀️ الموسم التجاري المستهدف:</label>
              <select id="ai-season-select" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: var(--radius-sm); border: 1px solid var(--border-color); background: var(--bg-input); color: var(--color-text-main);">
                <option value="auto" selected>✨ تحديد تلقائي حسب التاريخ الحالي</option>
                <option value="الصيف">☀️ موسم الصيف والشواطئ</option>
                <option value="الشتاء">❄️ موسم الشتاء والبرد</option>
                <option value="رمضان">🌙 موسم رمضان والعيد</option>
                <option value="الدخول المدرسي">🎒 موسم الدخول المدرسي</option>
              </select>
            </div>
          </div>

          <div>
            <label style="display: block; font-weight: 700; font-size: 0.85rem; margin-bottom: 6px;">🚛 تكلفة التوصيل الأساسية التقديرية (DH):</label>
            <input type="number" id="ai-shipping-input" value="35" min="10" step="5" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: var(--radius-sm); border: 1px solid var(--border-color); background: var(--bg-input); color: var(--color-text-main);" />
          </div>

          <div style="background: rgba(99, 102, 241, 0.08); padding: 10px 14px; border-radius: var(--radius-sm); border-right: 4px solid var(--color-primary); font-size: 0.82rem; color: var(--color-text-muted); line-height: 1.5;">
            💡 يتم التقييم بناءً على معايير الجدوى التجارية واللوجستية للسوق المغربي نظام COD.
          </div>

          <!-- Live AI Operation & Payload Status Box -->
          <div id="ai-modal-live-status" style="display: none; margin-top: 0.75rem; background: var(--bg-input); border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 10px 12px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px; flex-wrap: wrap; gap: 6px;">
              <span id="ai-modal-status-text" style="font-size: 0.82rem; font-weight: 700; color: var(--color-primary);">⏳ جاري تنفيذ تحليل الذكاء الاصطناعي...</span>
              <div style="display: flex; gap: 6px;">
                <button type="button" class="btn btn-secondary" style="font-size: 0.72rem; padding: 2px 10px; font-weight: 700;" onclick="openAiInputOutputInspectorModal('input')">🔍 إظهار Input</button>
                <button type="button" class="btn btn-secondary" style="font-size: 0.72rem; padding: 2px 10px; font-weight: 700;" onclick="openAiInputOutputInspectorModal('output')">📥 إظهار Output</button>
              </div>
            </div>
            <div id="ai-modal-status-detail" style="font-family: var(--font-mono, monospace); font-size: 0.75rem; color: var(--color-text-muted); line-height: 1.4; word-break: break-word;"></div>
          </div>

          <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 0.5rem; border-top: 1px solid var(--border-color); padding-top: 1rem;">
            <button type="button" class="btn btn-secondary" onclick="closeAiAnalysisModal()">إلغاء</button>
            <button type="submit" class="btn btn-primary" id="ai-submit-btn" style="background: linear-gradient(135deg, #6366f1, #8b5cf6); border: none; font-weight: 700; padding: 0.6rem 1.4rem;">
              🚀 بدء التحليل بالذكاء الاصطناعي
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- AI Saved History Slide Drawer -->
    <div class="modal-overlay" id="ai-history-drawer" style="display: none; z-index: 10002; justify-content: flex-start;">
      <div style="width: 480px; max-width: 90%; height: 100vh; background: var(--bg-card); border-left: 1px solid var(--border-color); padding: 1.5rem; display: flex; flex-direction: column; box-shadow: var(--shadow-lg); overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 1rem; margin-bottom: 1rem;">
          <div>
            <h3 style="font-weight: 800; font-size: 1.15rem; color: var(--color-primary); margin: 0; display: flex; align-items: center; gap: 8px;">
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

    <!-- AI Full Detailed Report Modal -->
    <div class="modal-overlay" id="ai-full-report-modal" style="display: none; z-index: 10003;">
      <div class="modal-card" style="max-width: 950px; width: 95%; padding: 1.75rem; border-radius: var(--radius-md); box-shadow: var(--shadow-lg); max-height: 90vh; display: flex; flex-direction: column;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 1rem; margin-bottom: 1rem;">
          <div>
            <h3 id="ai-report-modal-title" style="font-weight: 800; font-size: 1.2rem; color: var(--color-primary); margin: 0; display: flex; align-items: center; gap: 8px;">
              📊 التقرير المفصل لتقييم المنتجات الرابحة (Phase 1)
            </h3>
            <span id="ai-report-modal-subtitle" style="font-size: 0.8rem; color: var(--color-text-muted);">تحليل مالي ولوجستي شامل</span>
          </div>
          <button style="background: none; border: none; font-size: 1.6rem; cursor: pointer; color: var(--color-text-muted);" onclick="closeAiFullReportModal()">&times;</button>
        </div>

        <div id="ai-report-modal-body" style="overflow-y: auto; flex: 1; padding-left: 5px;">
        </div>

        <div style="display: flex; justify-content: flex-end; margin-top: 1rem; border-top: 1px solid var(--border-color); padding-top: 0.85rem;">
          <button class="btn btn-primary" onclick="closeAiFullReportModal()" style="padding: 0.5rem 1.4rem;">إغلاق التقرير</button>
        </div>
      </div>
    </div>

    <!-- AI Single Product Detailed Analysis & Narrative Modal -->
    <div class="modal-overlay" id="ai-product-text-modal" style="display: none; z-index: 10004;">
      <div class="modal-card" style="max-width: 850px; width: 94%; padding: 1.75rem; border-radius: var(--radius-md); box-shadow: var(--shadow-lg); max-height: 90vh; display: flex; flex-direction: column;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 1rem; margin-bottom: 1rem;">
          <div style="display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 1.6rem; background: rgba(99, 102, 241, 0.15); padding: 6px 12px; border-radius: var(--radius-sm);">📖</span>
            <div>
              <h3 id="ai-text-modal-title" style="font-weight: 800; font-size: 1.2rem; color: var(--color-primary); margin: 0;">
                التقرير والتحليل النصي للمنتج
              </h3>
              <span id="ai-text-modal-subtitle" style="font-size: 0.8rem; color: var(--color-text-muted);">تشخيص وتوصيات الذكاء الاصطناعي للسوق المغربي</span>
            </div>
          </div>
          <button style="background: none; border: none; font-size: 1.6rem; cursor: pointer; color: var(--color-text-muted);" onclick="closeAiProductTextModal()">&times;</button>
        </div>

        <div id="ai-text-modal-body" style="overflow-y: auto; flex: 1; padding-left: 5px; font-size: 0.9rem; line-height: 1.7;">
        </div>

        <div style="display: flex; justify-content: flex-end; margin-top: 1rem; border-top: 1px solid var(--border-color); padding-top: 0.85rem;">
          <button class="btn btn-primary" onclick="closeAiProductTextModal()" style="padding: 0.5rem 1.4rem;">إغلاق النافذة</button>
        </div>
      </div>
    </div>

    <!-- Phase 2 Single Product Deep-Dive Data Entry Modal -->
    <div class="modal-overlay" id="phase2-input-modal" style="display: none; z-index: 10005;">
      <div class="modal-card" style="max-width: 650px; width: 92%; padding: 1.75rem; border-radius: var(--radius-md); box-shadow: var(--shadow-lg);">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 1rem; margin-bottom: 1.25rem;">
          <h3 style="font-weight: 800; font-size: 1.25rem; color: #10b981; display: flex; align-items: center; gap: 8px; margin: 0;">
            📝 إدخال البيانات المتقدمة وتحليل المنتج التفصيلي (Phase 2)
          </h3>
          <button style="background: none; border: none; font-size: 1.6rem; cursor: pointer; color: var(--color-text-muted);" onclick="closePhase2InputModal()">&times;</button>
        </div>

        <form id="phase2-input-form" onsubmit="handleRunPhase2DeepAnalysis(event)" style="display: flex; flex-direction: column; gap: 1rem;">
          <input type="hidden" id="p2-product-id" value="" />
          <input type="hidden" id="p2-product-raw-json" value="" />

          <div>
            <label style="display: block; font-weight: 700; font-size: 0.9rem; margin-bottom: 6px;">📦 المنتج المستهدف:</label>
            <input type="text" id="p2-product-title-input" class="form-control" style="width: 100%; padding: 10px; border-radius: var(--radius-sm); border: 1px solid var(--border-color); background: var(--bg-input); color: var(--color-text-main); font-weight: 700;" readonly />
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div>
              <label style="display: block; font-weight: 700; font-size: 0.85rem; margin-bottom: 6px;">💰 ثمن شراء الجملة (Wholesale C_wholesale) (DH):</label>
              <input type="number" id="p2-c-wholesale" value="70" min="0" step="1" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: var(--radius-sm); border: 1px solid var(--border-color); background: var(--bg-input); color: var(--color-text-main);" required />
            </div>

            <div>
              <label style="display: block; font-weight: 700; font-size: 0.85rem; margin-bottom: 6px;">🏷️ سعر المنافس / سعر البيع للمستهلك (DH):</label>
              <input type="number" id="p2-price-selling" value="250" min="10" step="1" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: var(--radius-sm); border: 1px solid var(--border-color); background: var(--bg-input); color: var(--color-text-main);" required />
            </div>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div>
              <label style="display: block; font-weight: 700; font-size: 0.85rem; margin-bottom: 6px;">🚛 تكلفة التوصيل الأساسية (C_shipping) (DH):</label>
              <input type="number" id="p2-c-shipping" value="35" min="0" step="1" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: var(--radius-sm); border: 1px solid var(--border-color); background: var(--bg-input); color: var(--color-text-main);" required />
            </div>

            <div>
              <label style="display: block; font-weight: 700; font-size: 0.85rem; margin-bottom: 6px;">📦 التغليف والتأكيد (Confirmation & Packaging) (DH):</label>
              <input type="number" id="p2-c-packaging" value="15" min="0" step="1" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: var(--radius-sm); border: 1px solid var(--border-color); background: var(--bg-input); color: var(--color-text-main);" required />
            </div>
          </div>

          <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
            <div>
              <label style="display: block; font-weight: 700; font-size: 0.85rem; margin-bottom: 6px;">📦 كمية المخزون المتاحة (Units):</label>
              <input type="number" id="p2-stock-quantity" value="100" min="1" step="1" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: var(--radius-sm); border: 1px solid var(--border-color); background: var(--bg-input); color: var(--color-text-main);" required />
            </div>

            <div>
              <label style="display: block; font-weight: 700; font-size: 0.85rem; margin-bottom: 6px;">💰 الميزانية الإعلانية الإجمالية (DH):</label>
              <input type="number" id="p2-total-ad-budget" value="1000" min="100" step="50" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: var(--radius-sm); border: 1px solid var(--border-color); background: var(--bg-input); color: var(--color-text-main);" required />
            </div>

            <div>
              <label style="display: block; font-weight: 700; font-size: 0.85rem; margin-bottom: 6px;">🤖 موديل الذكاء الاصطناعي (AI Model):</label>
              <select id="p2-provider-select" class="form-control" style="width: 100%; padding: 8px 12px; border-radius: var(--radius-sm); border: 1px solid var(--border-color); background: var(--bg-input); color: var(--color-text-main); font-weight: 600;">
                <option value="auto" data-provider="auto" data-model="" selected>✨ التلقائي (حسب إعدادات النظام)</option>
                <option value="internal" data-provider="internal" data-model="internal-engine">⚡ المحرك الداخلي السريع (Internal Engine)</option>
              </select>
            </div>
          </div>

          <div>
            <label style="display: block; font-weight: 700; font-size: 0.85rem; margin-bottom: 6px;">📝 ملاحظات إضافية أو تفاصيل المنتج الخاصة:</label>
            <textarea id="p2-extra-notes" rows="2" placeholder="أضف أي تفاصيل خاصة مثل جودة المنتج، الفئة المستهدفة، أو معلومات التوريد..." class="form-control" style="width: 100%; padding: 8px 12px; border-radius: var(--radius-sm); border: 1px solid var(--border-color); background: var(--bg-input); color: var(--color-text-main); font-size: 0.85rem;"></textarea>
          </div>

          <!-- Live Phase 2 Status & Payload Box -->
          <div id="p2-modal-live-status" style="display: none; margin-top: 0.75rem; background: var(--bg-input); border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 10px 12px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px; flex-wrap: wrap; gap: 6px;">
              <span id="p2-modal-status-text" style="font-size: 0.82rem; font-weight: 700; color: #10b981;">⏳ جاري إعداد دراسة الجدوى وتوليد النصوص الإعلانية...</span>
              <div style="display: flex; gap: 6px;">
                <button type="button" class="btn btn-secondary" style="font-size: 0.72rem; padding: 2px 10px; font-weight: 700;" onclick="openAiInputOutputInspectorModal('input')">🔍 إظهار Input</button>
                <button type="button" class="btn btn-secondary" style="font-size: 0.72rem; padding: 2px 10px; font-weight: 700;" onclick="openAiInputOutputInspectorModal('output')">📥 إظهار Output</button>
              </div>
            </div>
            <div id="p2-modal-status-detail" style="font-family: var(--font-mono, monospace); font-size: 0.75rem; color: var(--color-text-muted); line-height: 1.4; word-break: break-word;"></div>
          </div>

          <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 0.5rem; border-top: 1px solid var(--border-color); padding-top: 1rem;">
            <button type="button" class="btn btn-secondary" onclick="closePhase2InputModal()">إلغاء</button>
            <button type="submit" class="btn btn-success" id="p2-submit-btn" style="background: linear-gradient(135deg, #10b981, #059669); border: none; font-weight: 700; padding: 0.6rem 1.4rem;">
              🚀 إجراء الدراسة والتحليل التفصيلي (Phase 2)
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Phase 2 Single Product Deep-Dive Strategy Blueprint Modal -->
    <div class="modal-overlay" id="phase2-results-modal" style="display: none; z-index: 10006;">
      <div class="modal-card" style="max-width: 960px; width: 95%; padding: 1.75rem; border-radius: var(--radius-md); box-shadow: var(--shadow-lg); max-height: 90vh; display: flex; flex-direction: column;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 1rem; margin-bottom: 1rem;">
          <div style="display: flex; align-items: center; gap: 12px;">
            <span style="font-size: 1.8rem; background: rgba(16, 185, 129, 0.15); padding: 8px 14px; border-radius: var(--radius-sm);">🚀</span>
            <div>
              <h3 id="p2-results-title" style="font-weight: 800; font-size: 1.25rem; color: #10b981; margin: 0;">
                خطة الإطلاق والجدوى التفصيلية (Phase 2 Deep Blueprint)
              </h3>
              <span id="p2-results-subtitle" style="font-size: 0.82rem; color: var(--color-text-muted);">تحليل مالي، واستهداف، ونصوص إعلانية موجهة للسوق المغربي</span>
            </div>
          </div>
          <button style="background: none; border: none; font-size: 1.6rem; cursor: pointer; color: var(--color-text-muted);" onclick="closePhase2ResultsModal()">&times;</button>
        </div>

        <div id="p2-results-body" style="overflow-y: auto; flex: 1; padding-left: 5px;">
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1rem; border-top: 1px solid var(--border-color); padding-top: 0.85rem;">
          <button class="btn btn-secondary" onclick="window.print()" style="font-size: 0.85rem;">🖨️ طباعة التقرير / حفظ PDF</button>
          <button class="btn btn-primary" onclick="closePhase2ResultsModal()" style="padding: 0.5rem 1.4rem;">إغلاق النافذة</button>
        </div>
      </div>
    </div>

    <!-- Phase 2 Saved Analyses History Modal -->
    <div class="modal-overlay" id="phase2-history-modal" style="display: none; z-index: 10007;">
      <div class="modal-card" style="max-width: 850px; width: 92%; padding: 1.75rem; border-radius: var(--radius-md); box-shadow: var(--shadow-lg); max-height: 85vh; display: flex; flex-direction: column;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 1rem; margin-bottom: 1rem;">
          <div style="display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 1.6rem;">📂</span>
            <div>
              <h3 style="font-weight: 800; font-size: 1.2rem; color: #6366f1; margin: 0;">
                التحليلات التفصيلية المحفوظة (Phase 2 Saved History)
              </h3>
              <span style="font-size: 0.8rem; color: var(--color-text-muted);">سجل دراسات الجدوى المالية وتكتيكات الإطلاق السابقة</span>
            </div>
          </div>
          <button style="background: none; border: none; font-size: 1.6rem; cursor: pointer; color: var(--color-text-muted);" onclick="closePhase2HistoryModal()">&times;</button>
        </div>

        <div style="margin-bottom: 1rem;">
          <input type="text" id="p2-history-search-input" placeholder="🔍 ابحث عن اسم المنتج في التحليلات المحفوظة..." class="form-control" style="width: 100%; padding: 8px 12px; border-radius: var(--radius-sm); border: 1px solid var(--border-color); background: var(--bg-input); color: var(--color-text-main);" oninput="filterPhase2HistoryList()" />
        </div>

        <div id="p2-history-list-body" style="overflow-y: auto; flex: 1; padding-left: 4px; display: flex; flex-direction: column; gap: 10px;">
        </div>
      </div>
    </div>

    <!-- AI Input/Output Payload Inspector Modal -->
    <div class="modal-overlay" id="ai-io-modal" style="display: none; z-index: 10008;">
      <div class="modal-card" style="max-width: 800px; width: 92%; padding: 1.5rem; border-radius: var(--radius-md); box-shadow: var(--shadow-lg);">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 0.75rem; margin-bottom: 1rem;">
          <h3 style="font-weight: 800; font-size: 1.1rem; color: var(--color-primary); display: flex; align-items: center; gap: 8px; margin: 0;">
            🖥️ مستعرض مدخلات ومخرجات الذكاء الاصطناعي (Input / Output Inspector)
          </h3>
          <button style="background: none; border: none; font-size: 1.6rem; cursor: pointer; color: var(--color-text-muted);" onclick="closeAiIoModal()">&times;</button>
        </div>

        <div style="display: flex; gap: 8px; margin-bottom: 1rem; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">
          <button id="ai-io-tab-input" onclick="switchAiIoTab('input')" class="btn btn-secondary" style="font-weight: 700; font-size: 0.85rem; padding: 6px 16px; border-color: var(--color-primary); color: var(--color-primary);">
            📤 المدخلات (Input Payload / Prompt)
          </button>
          <button id="ai-io-tab-output" onclick="switchAiIoTab('output')" class="btn btn-secondary" style="font-weight: 700; font-size: 0.85rem; padding: 6px 16px; border-color: var(--border-color); color: var(--color-text-muted);">
            📥 المخرجات (Raw Output / API Response)
          </button>
          <button onclick="copyAiIoContent()" class="btn btn-secondary" style="margin-right: auto; font-size: 0.8rem; padding: 6px 14px; font-weight: 700;">
            📋 نسخ النص
          </button>
        </div>

        <div style="position: relative;">
          <textarea id="ai-io-content-box" readonly style="width: 100%; height: 360px; font-family: var(--font-mono, monospace); font-size: 0.82rem; background: var(--bg-input); color: var(--color-text-main); border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 12px; direction: ltr; text-align: left; line-height: 1.5; resize: vertical;"></textarea>
        </div>

        <div style="display: flex; justify-content: flex-end; margin-top: 1rem;">
          <button class="btn btn-secondary" onclick="closeAiIoModal()">إغلاق</button>
        </div>
      </div>
    </div>

    <div class="toast-container" id="toast-container"></div>

    <script src="https://vjs.zencdn.net/8.16.1/video.min.js"></script>
    <script src="<?= base_url('analysis-helper.js') ?>?v=1.0"></script>
    <script src="<?= base_url('video-thumbnail-generator.js') ?>?v=1.0"></script>
    <script src="<?= base_url('saved-ads.js') ?>?v=2.4"></script>
  </body>
</html>
