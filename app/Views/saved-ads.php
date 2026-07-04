<!doctype html>
<html lang="ar" dir="rtl">
  <head>
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
    <div class="app-shell" style="grid-template-columns: 1fr">
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
            <a href="<?= base_url('/') ?>" class="btn btn-secondary"
              >🏠 العودة للوحة التحكم</a
            >
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
            <a href="<?= base_url('settings') ?>" class="btn btn-secondary">⚙️ الإعدادات</a>
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

    <div class="toast-container" id="toast-container"></div>

    <script src="https://vjs.zencdn.net/8.16.1/video.min.js"></script>
    <script src="<?= base_url('saved-ads.js') ?>?v=2.3"></script>
  </body>
</html>
