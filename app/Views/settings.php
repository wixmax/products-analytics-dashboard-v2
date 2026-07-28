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
    <title>Settings | إعدادات النظام</title>
    <meta name="description" content="إعدادات النظام والتحكم بمصادر البيانات وتنظيف قاعدة البيانات." />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap"
      rel="stylesheet"
    />

    <link rel="stylesheet" href="<?= base_url('index.css') ?>?v=1.6" />
    <!-- Flatpickr CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css" />
    <style>
      .settings-container {
        max-width: 800px;
        margin: 2rem auto;
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
        padding: 0 1rem;
      }
      .settings-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-md);
        padding: 1.75rem;
        box-shadow: var(--shadow-sm);
        transition: var(--transition-all);
      }
      .settings-card:hover {
        border-color: var(--color-primary);
        box-shadow: var(--shadow-md);
      }
      .settings-card-title {
        font-size: 1.2rem;
        font-weight: 700;
        margin-bottom: 0.75rem;
        display: flex;
        align-items: center;
        gap: 8px;
        color: var(--color-text-main);
        border-bottom: 1px solid var(--border-color);
        padding-bottom: 0.5rem;
      }
      .settings-card-desc {
        color: var(--color-text-muted);
        font-size: 0.85rem;
        line-height: 1.5;
        margin-bottom: 1.25rem;
      }
      .settings-form-group {
        margin-bottom: 1rem;
        display: flex;
        flex-direction: column;
        gap: 6px;
      }
      .settings-form-group label {
        font-weight: 600;
        font-size: 0.9rem;
      }
      .setting-radio-group {
        display: flex;
        flex-direction: column;
        gap: 10px;
        margin-top: 5px;
      }
      .setting-radio-option {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 10px;
        border-radius: var(--radius-sm);
        border: 1px solid var(--border-color);
        background: var(--bg-input);
        cursor: pointer;
        transition: var(--transition-all);
      }
      .setting-radio-option:hover {
        border-color: var(--color-primary);
      }
      .setting-radio-option input[type="radio"] {
        margin-top: 3px;
        cursor: pointer;
      }
      .setting-radio-label-wrapper {
        display: flex;
        flex-direction: column;
        gap: 2px;
      }
      .setting-radio-title {
        font-size: 0.85rem;
        font-weight: 700;
        color: var(--color-text-main);
      }
      .setting-radio-desc {
        font-size: 0.75rem;
        color: var(--color-text-muted);
      }
      .actions-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 1rem;
      }
      @media (min-width: 600px) {
        .actions-grid {
          grid-template-columns: 1fr 1fr;
        }
      }
      .action-item {
        border: 1px solid var(--border-color);
        border-radius: var(--radius-sm);
        padding: 1rem;
        background: var(--bg-input);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        gap: 12px;
      }
      .action-item-info {
        display: flex;
        flex-direction: column;
        gap: 4px;
      }
      .action-item-title {
        font-size: 0.85rem;
        font-weight: 700;
        color: var(--color-text-main);
      }
      .action-item-desc {
        font-size: 0.75rem;
        color: var(--color-text-muted);
        line-height: 1.4;
      }
      .danger-zone {
        border-color: rgba(239, 68, 68, 0.3) !important;
      }
      .danger-zone:hover {
        border-color: var(--color-error) !important;
      }
      /* Floating Toast Notifications */
      .toast-container {
        position: fixed;
        bottom: 24px;
        left: 24px;
        z-index: 9999;
        display: flex;
        flex-direction: column;
        gap: 10px;
        pointer-events: none;
      }
      .toast {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 20px;
        border-radius: 8px;
        background: #1e293b;
        color: #f8fafc;
        border: 1px solid rgba(255, 255, 255, 0.15);
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
        font-size: 0.9rem;
        font-weight: 600;
        pointer-events: auto;
        animation: toastIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        transition: all 0.3s ease;
      }
      .toast-success {
        border-right: 4px solid #10b981;
        background: #064e3b;
        color: #ecfdf5;
      }
      .toast-error {
        border-right: 4px solid #ef4444;
        background: #7f1d1d;
        color: #fef2f2;
      }
      .toast-info {
        border-right: 4px solid #3b82f6;
        background: #1e3a8a;
        color: #eff6ff;
      }
      @keyframes toastIn {
        from {
          opacity: 0;
          transform: translateY(20px) scale(0.95);
        }
        to {
          opacity: 1;
          transform: translateY(0) scale(1);
        }
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
      <?= $this->include('partials/sidebar', ['subtitle' => 'إعدادات النظام']) ?>

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
              ⚙️ إعدادات النظام وتحكم البيانات
            </h2>
            <p style="color: var(--color-text-muted); font-size: 0.85rem">
              إدارة إعدادات المزامنة الافتراضية، وخيارات تنظيف وحذف بيانات قاعدة البيانات
            </p>
          </div>

          <div class="actions-group">
            <button class="theme-toggle" id="theme-toggle-btn">🌓</button>
          </div>
        </div>

        <div class="settings-container" id="db-migration-card-container">
          <!-- Database Migration Status Card -->
          <?php if (!empty($pendingMigrations)): ?>
            <div class="settings-card" style="border: 2px solid #f59e0b; background: rgba(245, 158, 11, 0.08);">
              <div class="settings-card-title" style="color: #f59e0b; border-bottom-color: rgba(245, 158, 11, 0.3);">
                ⚡ تحديثات قاعدة البيانات المتاحة (Pending Migrations)
                <span style="background: #f59e0b; color: #fff; padding: 2px 10px; border-radius: 12px; font-size: 0.75rem; margin-right: auto; font-weight: 800;">
                  <?= count($pendingMigrations) ?> تحديث جديد
                </span>
              </div>
              <p class="settings-card-desc" style="color: var(--color-text-main); margin-bottom: 0.75rem;">
                توجد تعديلات وتحديثات جديدة على هيكل قاعدة البيانات يجب تطبيقها لضمان عمل النظام بكفاءة واستقرار دون أخطاء.
              </p>
              <div style="background: var(--bg-app); border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 12px; margin-bottom: 1.25rem;">
                <strong style="font-size: 0.85rem; display: block; margin-bottom: 6px; color: var(--color-text-main);">📋 تفاصيل الهجرات المعلقة:</strong>
                <ul style="margin: 0; padding-right: 20px; font-size: 0.85rem; font-family: var(--font-mono, monospace); color: var(--color-text-muted);">
                  <?php foreach ($pendingMigrations as $pm): ?>
                    <li>
                      <strong style="color: var(--color-text-main);"><?= esc($pm['name']) ?></strong> 
                      <span style="font-size: 0.75rem;">(<?= esc($pm['filename']) ?>)</span>
                    </li>
                  <?php endforeach; ?>
                </ul>
              </div>
              <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                <button id="run-migration-btn" onclick="runInlineMigration(event)" class="btn" style="background: linear-gradient(135deg, #f59e0b, #d97706); color: white; border: none; font-weight: 700; display: inline-flex; align-items: center; gap: 8px; cursor: pointer; padding: 10px 22px; border-radius: 8px; box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);">
                  🔄 تشغيل التحديث الآن
                </button>
                <span style="font-size: 0.8rem; color: var(--color-text-muted);">أو قم بتشغيل <code>php spark migrate</code> في cPanel Terminal</span>
              </div>
            </div>
          <?php else: ?>
            <div class="settings-card" style="border-right: 4px solid #10b981;">
              <div class="settings-card-title" style="color: #10b981; border-bottom: none; padding-bottom: 0; margin-bottom: 0.25rem;">
                ✅ حالة قاعدة البيانات (Database Status)
              </div>
              <p class="settings-card-desc" style="margin-bottom: 0; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                <span>قاعدة البيانات محدثة بالكامل وتعمل بأحدث هيكل، لا توجد أي هجرات (Migrations) معلقة.</span>
                <button onclick="runInlineMigration(event)" class="btn btn-secondary" style="font-size: 0.8rem; padding: 5px 12px; cursor: pointer;">إعادة الفحص والتحديث</button>
              </p>
            </div>
          <?php endif; ?>
        </div>
        <div class="settings-container">

          <!-- Card 0: Personal Preferences (Visible to All Users) -->
          <div class="settings-card">
            <div class="settings-card-title">
              🎨 التفضيلات الشخصية والمظهر
            </div>
            <p class="settings-card-desc">
              إدارة الخيارات والشكل الظاهري المخصص لحسابك وتفريغ الملفات المؤقتة بالمتصفح.
            </p>
            <div class="actions-grid">
              <div class="action-item">
                <div class="action-item-info">
                  <span class="action-item-title">🌓 نمط مظهر الواجهة</span>
                  <span class="action-item-desc">التبديل بين النمط المظلم (Dark Mode) والنمط المضيء (Light Mode).</span>
                </div>
                <button class="btn btn-secondary" onclick="toggleThemeFromSettings()">🌓 تبديل النمط</button>
              </div>

              <div class="action-item">
                <div class="action-item-info">
                  <span class="action-item-title">🧹 تفريغ كاش ملفات المتصفح</span>
                  <span class="action-item-desc">مسح الملفات المخزنة (CSS/JS) وإعادة تحميل الصفحة للتأكد من تشغيل أحدث إصدار.</span>
                </div>
                <button class="btn btn-secondary" onclick="clearBrowserCache()">🧹 تفريغ الكاش</button>
              </div>
            </div>
          </div>

          <?php if (auth()->loggedIn() && auth()->user()->inGroup('superadmin', 'admin')): ?>
          <!-- Card AI: AI Providers & Models Settings (Admin Only) -->
          <div class="settings-card" id="ai-settings-card">
            <div class="settings-card-title">
              🤖 إعدادات الذكاء الاصطناعي والموديلات (AI Providers & Models)
            </div>
            <p class="settings-card-desc">
              قم بإدارة مفاتيح API الخاصة بكل مورد (OpenAI, Gemini, OpenRouter, DeepSeek)، وإضافة موديلات متعددة ومخصصة لكل مورد واختيار الموديل الافتراضي للتحليل.
            </p>

            <div class="settings-form-group">
              <label style="font-weight: 700;">🌟 المزود الافتراضي للنظام (Default Active Provider):</label>
              <select id="ai-global-active-provider" class="form-control" style="width: 100%; padding: 10px; border-radius: var(--radius-sm); border: 1px solid var(--border-color); background: var(--bg-input); color: var(--color-text-main); font-weight: 700;" onchange="handleGlobalProviderChange(this.value)">
                <option value="openrouter">🌐 OpenRouter (يدعم كافة النماذج والموديلات)</option>
                <option value="apiyi">🚀 APIyi (DeepSeek / Claude / GPT / Gemini)</option>
                <option value="openai">🤖 OpenAI (ChatGPT)</option>
                <option value="gemini">💎 Google Gemini</option>
                <option value="deepseek">🐋 DeepSeek</option>
                <option value="custom">⚡ محرك مخصص / Ollama محلي</option>
                <option value="internal">⚙️ المحرك الداخلي السريع (Internal Engine)</option>
              </select>
            </div>

            <div class="settings-form-group" style="margin-top: 1rem;">
              <label style="font-weight: 700;">🔄 التبديل التلقائي بين المزودين الخارجيين (External Provider Failover):</label>
              <select id="ai-allow-provider-failover-select" class="form-control" style="width: 100%; padding: 10px; border-radius: var(--radius-sm); border: 1px solid var(--border-color); background: var(--bg-input); color: var(--color-text-main); font-weight: 700;" onchange="handleProviderFailoverToggleChange(this.value)">
                <option value="disabled" selected>🔴 معطل (الالتزام حصراً بالمزود المختار وإظهار الخطأ عند الفشل دون التبديل لمزود آخر)</option>
                <option value="enabled">🟢 مفعل (التبديل التلقائي إلى OpenRouter / APIyi عند فشل المزود الرئيسي)</option>
              </select>
            </div>

            <div class="settings-form-group" style="margin-top: 1rem;">
              <label style="font-weight: 700;">⚡ المحرك المحلي الاحتياطي (Internal Market Engine / Offline Fallback):</label>
              <select id="ai-allow-internal-fallback-select" class="form-control" style="width: 100%; padding: 10px; border-radius: var(--radius-sm); border: 1px solid var(--border-color); background: var(--bg-input); color: var(--color-text-main); font-weight: 700;" onchange="handleFallbackToggleChange(this.value)">
                <option value="enabled" selected>🟢 مفعل (التراجع إلى المحرك المحلي تلقائياً في حالة فشل الاتصال بالمزود الخارجي)</option>
                <option value="disabled">🔴 معطل (العمل فقط بالمزود الخارجي وإلغاء التراجع المحلي مع قذف خطأ الفشل)</option>
              </select>
            </div>

            <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 1.5rem 0;" />

            <h4 style="font-weight: 800; font-size: 1rem; margin-bottom: 1rem; color: var(--color-primary); display: flex; align-items: center; gap: 8px;">
              🛠️ مفاتيح API والموديلات المتاحة حسب المورد:
            </h4>

            <div id="ai-providers-accordion" style="display: flex; flex-direction: column; gap: 1rem;">
              <!-- Provider Rows will be rendered dynamically -->
            </div>

            <div style="display: flex; justify-content: flex-end; margin-top: 1.5rem;">
              <button class="btn btn-primary" style="background: linear-gradient(135deg, #6366f1, #8b5cf6); border: none; font-weight: 700; padding: 10px 24px;" onclick="saveAiProvidersSettings()">
                💾 حفظ إعدادات والموديلات
              </button>
            </div>
          </div>
          <div class="settings-card">
            <div class="settings-card-title">
              🌐 مصدر جلب البيانات الافتراضي
            </div>
            <p class="settings-card-desc">
              حدد كيف ترغب في جلب وعرض المنتجات عند استخدام لوحة التحليلات والاستعلام.
            </p>
            
            <div class="settings-form-group">
              <div class="setting-radio-group">
                <!-- Option A: PostgreSQL Database -->
                <label class="setting-radio-option">
                  <input type="radio" name="data-source-radio" value="database" id="radio-source-db" />
                  <div class="setting-radio-label-wrapper">
                    <span class="setting-radio-title">قاعدة البيانات المحلية PostgreSQL (مستحسن ⚡)</span>
                    <span class="setting-radio-desc">يتم تحميل المنتجات المحفوظة محلياً بشكل فوري دون إجراء طلبات خارجية. يوفر استقراراً تاماً وسرعة فائقة.</span>
                  </div>
                </label>

                <!-- Option B: Live API -->
                <label class="setting-radio-option">
                  <input type="radio" name="data-source-radio" value="api" id="radio-source-api" />
                  <div class="setting-radio-label-wrapper">
                    <span class="setting-radio-title">موقع OverviewData المباشر (طلب حي من الـ API 🌍)</span>
                    <span class="setting-radio-desc">يقوم بإرسال طلب خارجي في كل مرة لتحديث المنتجات مباشرة من المصدر. قد يستغرق وقتاً أطول ويعتمد على حالة السيرفر الخارجي.</span>
                  </div>
                </label>
              </div>
            </div>

            <div style="display: flex; justify-content: flex-end; margin-top: 1.5rem;">
              <button class="btn btn-primary" onclick="saveDataSourceSetting()">
                💾 حفظ التفضيلات
              </button>
            </div>
          </div>

          <!-- Card 1.5: Analytics Scope Setting (Admin Only) -->
          <div class="settings-card">
            <div class="settings-card-title">
              📊 نطاق حساب تحليلات المتاجر والإدراجات
            </div>
            <p class="settings-card-desc">
              اختر نطاق وحجم البيانات التي تعتمد عليها بطاقة المتاجر النشطة والمخطط البياني الأسبوعي.
            </p>
            
            <div class="settings-form-group">
              <div class="setting-radio-group">
                <!-- Option A: Snapshot Scope -->
                <label class="setting-radio-option">
                  <input type="radio" name="analytics-scope-radio" value="snapshot" id="radio-scope-snapshot" />
                  <div class="setting-radio-label-wrapper">
                    <span class="setting-radio-title">مرتبط باللقطة والفلتر الحالي (Snapshot Scope - مستحسن ⚡)</span>
                    <span class="setting-radio-desc">حساب المتاجر النشطة وحركة الإدراجات الأسبوعية بناءً على المنتجات الخاصة باللقطة المحددة حالياً لتفادي أي تناقض مع إجمالي المنتجات المعروضة.</span>
                  </div>
                </label>

                <!-- Option B: Global Database Scope -->
                <label class="setting-radio-option">
                  <input type="radio" name="analytics-scope-radio" value="global" id="radio-scope-global" />
                  <div class="setting-radio-label-wrapper">
                    <span class="setting-radio-title">شامل لجميع البيانات في النظام (Global Database Scope 🌐)</span>
                    <span class="setting-radio-desc">حساب المتاجر النشطة والمخطط البياني بشكل تجميعي على مستوى كافة اللقطات والمنتجات المسجلة في قاعدة البيانات ككل.</span>
                  </div>
                </label>
              </div>
            </div>

            <div style="display: flex; justify-content: flex-end; margin-top: 1.5rem;">
              <button class="btn btn-primary" onclick="saveAnalyticsScopeSetting()">
                💾 حفظ نطاق التحليلات
              </button>
            </div>
          </div>

          <!-- Card 2: Database Operations (Admin Only) -->
          <div class="settings-card">
            <div class="settings-card-title">
              🧹 تحكم وتنظيف البيانات النظام
            </div>
            <p class="settings-card-desc">
              خيارات للمشرفين لحذف بيانات المنتجات والمجموعات لتهيئة النظام وتخفيف مساحة التخزين.
            </p>

            <div class="actions-grid">
              <!-- Item 1: Clear Fetched Products -->
              <div class="action-item">
                <div class="action-item-info">
                  <span class="action-item-title">🗑️ حذف المنتجات المجلوبة المؤقتة</span>
                  <span class="action-item-desc">حذف جميع المنتجات التي تم استيرادها تلقائياً، مع الإبقاء على المنتجات المفضلة التي قمت بحفظها يدوياً (Starred).</span>
                </div>
                <button class="btn btn-secondary" onclick="clearData('fetched')">تنظيف المنتجات المجلوبة</button>
              </div>

              <!-- Item 2: Clear Saved Ads -->
              <div class="action-item">
                <div class="action-item-info">
                  <span class="action-item-title">⭐ إلغاء حفظ كل الإعلانات المفضلة</span>
                  <span class="action-item-desc">إزالة حالة الحفظ "المفضلة" وإلغاء الملاحظات والتقييمات من جميع المنتجات. لن يتم حذف المنتجات نفسها من قاعدة البيانات.</span>
                </div>
                <button class="btn btn-secondary" onclick="clearData('saved')">تصفير الإعلانات المحفوظة</button>
              </div>

              <!-- Item 3: Delete Collections -->
              <div class="action-item">
                <div class="action-item-info">
                  <span class="action-item-title">📁 حذف جميع المجموعات</span>
                  <span class="action-item-desc">حذف جميع المجلدات والمجموعات المخصصة، وإعادة تعيين مجموعة جميع المنتجات المحفوظة إلى المجموعة العامة.</span>
                </div>
                <button class="btn btn-secondary" onclick="clearData('collections')">حذف المجموعات</button>
              </div>

              <!-- Item 4: Clear Watchlist -->
              <div class="action-item">
                <div class="action-item-info">
                  <span class="action-item-title">👁️ تفريغ قائمة مراقبة المتاجر</span>
                  <span class="action-item-desc">حذف جميع نطاقات المتاجر المنافسة التي تتابعها من قائمة المراقبة بالكامل.</span>
                </div>
                <button class="btn btn-secondary" onclick="clearData('watchlist')">تفريغ قائمة المراقبة</button>
              </div>

              <!-- Item 5: Delete Data By Specific Date (Admin Only) -->
              <div class="action-item" style="grid-column: 1 / -1; background: var(--bg-card); border: 1px solid var(--border-color); padding: 1.25rem;">
                <div class="action-item-info">
                  <span class="action-item-title" style="font-size: 1rem; color: #ef4444;">📅 حذف البيانات واللقطات حسب التاريخ</span>
                  <span class="action-item-desc">حدد التاريخ المراد حذف كافة المنتجات المجلوبة ولقطات البيانات (Snapshots) الخاصة به دون حذف المنتجات المفضلة.</span>
                </div>
                <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap; margin-top: 10px;">
                  <input type="text" id="delete-by-date-picker" class="form-control flatpickr-date" style="padding: 8px 12px; border-radius: var(--radius-sm); border: 1px solid var(--border-color); background: var(--bg-input); color: var(--color-text-main); font-weight: 700; min-width: 180px;" placeholder="اختر تاريخاً" />
                  <button class="btn btn-secondary" style="border-color: #ef4444; color: #ef4444; font-weight: 700; padding: 8px 16px;" onclick="deleteDataBySelectedDate()">🗑️ حذف بيانات هذا التاريخ</button>
                </div>
              </div>
            </div>
          </div>

          <!-- Card 3: Danger Zone (Admin Only) -->
          <div class="settings-card danger-zone" style="border: 1px dashed var(--color-error);">
            <div class="settings-card-title" style="color: var(--color-error)">
              🚨 منطقة الخطر
            </div>
            <p class="settings-card-desc">
              إجراءات غير قابلة للتراجع تؤدي إلى مسح وتصفير كافة محتويات قاعدة البيانات.
            </p>

            <div style="display: flex; justify-content: space-between; align-items: center; background: rgba(239, 68, 68, 0.05); border-radius: var(--radius-sm); border: 1px solid rgba(239, 68, 68, 0.2); padding: 1rem;">
              <div style="display: flex; flex-direction: column; gap: 4px;">
                <span style="font-weight: 700; font-size: 0.9rem; color: var(--color-text-main);">مسح قاعدة البيانات بالكامل</span>
                <span style="font-size: 0.75rem; color: var(--color-text-muted);">سيتم حذف كافة المنتجات، المجموعات، قائمة المراقبة وإعادة تعيين كافة الإعدادات إلى وضع المصنع.</span>
              </div>
              <button class="btn btn-error" onclick="clearData('all')">🚨 تهيئة النظام بالكامل</button>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </main>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toast-container"></div>

    <!-- Flatpickr JS -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://npmcdn.com/flatpickr/dist/l10n/ar.js"></script>

    <script>
      // Load current settings on mount
      document.addEventListener("DOMContentLoaded", async () => {
        if (typeof flatpickr !== "undefined" && document.getElementById("delete-by-date-picker")) {
          flatpickr("#delete-by-date-picker", {
            locale: "ar",
            dateFormat: "Y-m-d",
            defaultDate: "today",
            disableMobile: "true"
          });
        }
        await setupTheme();
        await loadSettings();
      });

      // Toast Notifications
      function showToast(message, type = "info") {
        const container = document.getElementById("toast-container");
        if (!container) return;

        const toast = document.createElement("div");
        toast.className = `toast toast-${type}`;
        
        let icon = "ℹ️";
        if (type === "success") icon = "✅";
        if (type === "error") icon = "❌";
        if (type === "warning") icon = "⚠️";

        toast.innerHTML = `<span class="toast-icon">${icon}</span><span class="toast-message">${message}</span>`;
        container.appendChild(toast);

        setTimeout(() => {
          toast.style.opacity = "0";
          toast.style.transform = "translateY(20px)";
          setTimeout(() => toast.remove(), 300);
        }, 4000);
      }

      // Theme Setup
      async function setupTheme() {
        const themeBtn = document.getElementById("theme-toggle-btn");
        if (!themeBtn) return;

        try {
          const res = await fetch('/api/settings/app-theme');
          if (res.ok) {
            const data = await res.json();
            const currentTheme = data.value || "light";
            document.documentElement.setAttribute("data-theme", currentTheme);
          }
        } catch (err) {
          console.error("Error fetching theme:", err);
        }

        themeBtn.onclick = toggleThemeFromSettings;
      }

      async function toggleThemeFromSettings() {
        const theme = document.documentElement.getAttribute("data-theme") === "dark" ? "light" : "dark";
        document.documentElement.setAttribute("data-theme", theme);
        try {
          await fetch('/api/settings', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ key: 'app-theme', value: theme })
          });
          showToast(`تم تغيير مظهر الواجهة إلى النمط ${theme === 'dark' ? 'المظلم 🌙' : 'المضيء ☀️'}`, "success");
        } catch (err) {
          console.error("Error saving theme:", err);
        }
      }

      // Global AI Providers Config State
      let aiConfigState = {
        active_provider: "openrouter",
        allow_provider_failover: false,
        allow_internal_fallback: true,
        providers: {
          openrouter: {
            name: "🌐 OpenRouter",
            api_key: "",
            active_model: "openai/gpt-4o-mini",
            models: ["openai/gpt-4o-mini", "anthropic/claude-3.5-sonnet", "deepseek/deepseek-r1", "meta-llama/llama-3.3-70b-instruct", "google/gemini-2.5-flash"]
          },
          openai: {
            name: "🤖 OpenAI (ChatGPT)",
            api_key: "",
            active_model: "gpt-4o-mini",
            models: ["gpt-4o", "gpt-4o-mini", "gpt-4-turbo", "o1-mini", "o3-mini"]
          },
          gemini: {
            name: "💎 Google Gemini",
            api_key: "",
            active_model: "gemini-2.5-flash",
            models: ["gemini-2.5-flash", "gemini-2.5-pro", "gemini-2.0-flash", "gemini-1.5-flash", "gemini-1.5-pro"]
          },
          deepseek: {
            name: "🐋 DeepSeek",
            api_key: "",
            active_model: "deepseek-chat",
            models: ["deepseek-chat", "deepseek-reasoner"]
          },
          apiyi: {
            name: "🚀 APIyi",
            api_key: "",
            active_model: "gpt-4o-mini",
            models: ["gpt-4o-mini", "gpt-4o", "claude-3-5-sonnet-20241022", "deepseek-chat", "deepseek-reasoner", "gemini-2.5-flash"]
          },
          custom: {
            name: "⚡ محرك مخصص / Ollama محلي",
            api_key: "",
            endpoint: "http://localhost:11434/v1/chat/completions",
            active_model: "llama3",
            models: ["llama3", "mistral", "qwen2.5"]
          }
        }
      };

      async function loadAiProvidersSettings() {
        try {
          const res = await fetch('/api/settings/ai_providers_config');
          if (res.ok) {
            const data = await res.json();
            if (data.value) {
              const parsed = typeof data.value === 'string' ? JSON.parse(data.value) : data.value;
              if (parsed) {
                if (typeof parsed.allow_internal_fallback !== 'undefined') {
                  aiConfigState.allow_internal_fallback = Boolean(parsed.allow_internal_fallback);
                }
                if (typeof parsed.allow_provider_failover !== 'undefined') {
                  aiConfigState.allow_provider_failover = Boolean(parsed.allow_provider_failover);
                }
                aiConfigState.active_provider = parsed.active_provider || aiConfigState.active_provider;
                if (parsed.providers) {
                  for (const pKey in parsed.providers) {
                    if (aiConfigState.providers[pKey]) {
                      aiConfigState.providers[pKey].api_key = parsed.providers[pKey].api_key || '';
                      aiConfigState.providers[pKey].active_model = parsed.providers[pKey].active_model || aiConfigState.providers[pKey].active_model;
                      if (Array.isArray(parsed.providers[pKey].models) && parsed.providers[pKey].models.length > 0) {
                        aiConfigState.providers[pKey].models = parsed.providers[pKey].models;
                      }
                    } else {
                      aiConfigState.providers[pKey] = parsed.providers[pKey];
                    }
                  }
                }
              }
            }
          }
        } catch (err) {
          console.error("Error loading AI providers settings:", err);
        }

        renderAiProvidersUI();
      }

      function handleGlobalProviderChange(val) {
        if (val) {
          aiConfigState.active_provider = val;
        }
      }

      function handleProviderFailoverToggleChange(val) {
        aiConfigState.allow_provider_failover = (val === 'enabled');
      }

      function handleFallbackToggleChange(val) {
        aiConfigState.allow_internal_fallback = (val === 'enabled');
      }

      function renderAiProvidersUI() {
        const selectGlobal = document.getElementById('ai-global-active-provider');
        if (selectGlobal && aiConfigState.active_provider) {
          selectGlobal.value = aiConfigState.active_provider;
        }

        const selectFailover = document.getElementById('ai-allow-provider-failover-select');
        if (selectFailover) {
          selectFailover.value = (aiConfigState.allow_provider_failover === true) ? 'enabled' : 'disabled';
        }

        const selectFallback = document.getElementById('ai-allow-internal-fallback-select');
        if (selectFallback) {
          selectFallback.value = (aiConfigState.allow_internal_fallback !== false) ? 'enabled' : 'disabled';
        }

        const container = document.getElementById('ai-providers-accordion');
        if (!container) return;

        let html = '';
        for (const pKey in aiConfigState.providers) {
          const provider = aiConfigState.providers[pKey];
          html += `
            <div style="background: var(--bg-input); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1.25rem;">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                <h5 style="font-weight: 700; font-size: 1rem; margin: 0; display: flex; align-items: center; gap: 8px; color: var(--color-text-main);">
                  ${provider.name}
                </h5>
                <span style="font-size: 0.75rem; color: var(--color-text-muted); font-family: monospace;">[${pKey}]</span>
              </div>

              <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 0.75rem;">
                <div>
                  <label style="display: block; font-size: 0.8rem; font-weight: 600; margin-bottom: 4px;">🔑 مفتاح API (API Key):</label>
                  <input type="password" id="api-key-${pKey}" value="${provider.api_key || ''}" class="form-control" style="width: 100%; padding: 8px; border-radius: var(--radius-sm); border: 1px solid var(--border-color); background: var(--bg-card); color: var(--color-text-main);" placeholder="أدخل مفتاح API الخاص بـ ${provider.name}" onchange="updateAiConfigStateKey('${pKey}', this.value)" />
                </div>

                <div>
                  <label style="display: block; font-size: 0.8rem; font-weight: 600; margin-bottom: 4px;">🎯 الموديل الافتراضي للتحليل:</label>
                  <select id="active-model-${pKey}" class="form-control" style="width: 100%; padding: 8px; border-radius: var(--radius-sm); border: 1px solid var(--border-color); background: var(--bg-card); color: var(--color-text-main); font-weight: 600;" onchange="updateAiConfigStateActiveModel('${pKey}', this.value)">
                    ${provider.models.map(m => `<option value="${m}" ${m === provider.active_model ? 'selected' : ''}>${m}</option>`).join('')}
                  </select>
                </div>
              </div>

              <div style="margin-bottom: 0.75rem;">
                <label style="display: block; font-size: 0.8rem; font-weight: 600; margin-bottom: 6px;">📚 الموديلات المتاحة المسجلة:</label>
                <div style="display: flex; flex-wrap: wrap; gap: 6px; align-items: center;">
                  ${provider.models.map(m => `
                    <span style="background: rgba(99, 102, 241, 0.15); color: var(--color-primary); padding: 4px 10px; border-radius: 16px; font-size: 0.8rem; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; border: 1px solid rgba(99, 102, 241, 0.3);">
                      <span>${m}</span>
                      <button type="button" style="background: none; border: none; color: #ef4444; font-weight: 800; cursor: pointer; padding: 0 2px; font-size: 0.9rem;" onclick="removeModelFromProvider('${pKey}', '${m}')" title="حذف هذا الموديل">&times;</button>
                    </span>
                  `).join('')}
                </div>
              </div>

              <div style="display: flex; gap: 8px; align-items: center;">
                <input type="text" id="new-model-input-${pKey}" class="form-control" style="flex: 1; padding: 6px 12px; font-size: 0.82rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color); background: var(--bg-card); color: var(--color-text-main);" placeholder="إضافة موديل جديد لـ ${provider.name} (مثال: gpt-4.5-preview)" onkeypress="if(event.key === 'Enter'){ event.preventDefault(); addCustomModelToProvider('${pKey}'); }" />
                <button type="button" class="btn btn-secondary" style="font-size: 0.8rem; padding: 6px 14px; font-weight: 700;" onclick="addCustomModelToProvider('${pKey}')">+ إضافة موديل</button>
              </div>
            </div>
          `;
        }
        container.innerHTML = html;
      }

      function updateAiConfigStateKey(pKey, val) {
        if (aiConfigState.providers[pKey]) {
          aiConfigState.providers[pKey].api_key = val.trim();
        }
      }

      function updateAiConfigStateActiveModel(pKey, val) {
        if (aiConfigState.providers[pKey]) {
          aiConfigState.providers[pKey].active_model = val.trim();
        }
      }

      function addCustomModelToProvider(pKey) {
        const input = document.getElementById(`new-model-input-${pKey}`);
        if (!input) return;
        const newModel = input.value.trim();
        if (!newModel) return;

        if (!aiConfigState.providers[pKey].models.includes(newModel)) {
          aiConfigState.providers[pKey].models.push(newModel);
          // If first model, set active
          if (!aiConfigState.providers[pKey].active_model) {
            aiConfigState.providers[pKey].active_model = newModel;
          }
          showToast(`تم إدراج الموديل (${newModel}) لقائمة ${aiConfigState.providers[pKey].name} ✨`, "success");
        }
        input.value = "";
        renderAiProvidersUI();
      }

      function removeModelFromProvider(pKey, modelName) {
        if (!aiConfigState.providers[pKey]) return;
        aiConfigState.providers[pKey].models = aiConfigState.providers[pKey].models.filter(m => m !== modelName);
        if (aiConfigState.providers[pKey].active_model === modelName) {
          aiConfigState.providers[pKey].active_model = aiConfigState.providers[pKey].models[0] || '';
        }
        showToast(`تم حذف الموديل (${modelName}) 🗑️`, "info");
        renderAiProvidersUI();
      }

      async function saveAiProvidersSettings() {
        const selectGlobal = document.getElementById('ai-global-active-provider');
        if (selectGlobal) {
          aiConfigState.active_provider = selectGlobal.value;
        }

        try {
          const res = await fetch('/api/settings', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ key: 'ai_providers_config', value: aiConfigState })
          });

          if (res.ok) {
            showToast("تم حفظ إعدادات وموديلات الذكاء الاصطناعي بنجاح! 🤖💾", "success");
          } else {
            showToast("فشل حفظ إعدادات الذكاء الاصطناعي.", "error");
          }
        } catch (err) {
          console.error("Error saving AI settings:", err);
          showToast("خطأ في الاتصال بالسيرفر أثناء حفظ الإعدادات.", "error");
        }
      }

      // Load Settings from server
      async function loadSettings() {
        await loadAiProvidersSettings();
        const radioApi = document.getElementById('radio-source-api');
        const radioDb = document.getElementById('radio-source-db');
        if (radioApi && radioDb) {
          try {
            const res = await fetch('/api/settings/data-source');
            if (res.ok) {
              const data = await res.json();
              const value = data.value || 'database';
              if (value === 'api') {
                radioApi.checked = true;
              } else {
                radioDb.checked = true;
              }
            } else {
              radioDb.checked = true;
            }
          } catch (err) {
            console.error("Error loading settings:", err);
            radioDb.checked = true;
          }
        }

        const radioGlobal = document.getElementById('radio-scope-global');
        const radioSnapshot = document.getElementById('radio-scope-snapshot');
        if (radioGlobal && radioSnapshot) {
          try {
            const resScope = await fetch('/api/settings/analytics-scope');
            if (resScope.ok) {
              const dataScope = await resScope.json();
              const valScope = dataScope.value || 'snapshot';
              if (valScope === 'global') {
                radioGlobal.checked = true;
              } else {
                radioSnapshot.checked = true;
              }
            } else {
              radioSnapshot.checked = true;
            }
          } catch (err) {
            console.error("Error loading analytics scope:", err);
            radioSnapshot.checked = true;
          }
        }
      }

      // Save Data Source Setting
      async function saveDataSourceSetting() {
        const selectedRadio = document.querySelector('input[name="data-source-radio"]:checked');
        if (!selectedRadio) return;

        const value = selectedRadio.value;

        try {
          const res = await fetch('/api/settings', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ key: 'data-source', value: value })
          });

          if (res.ok) {
            showToast("تم حفظ التفضيلات ومصدر البيانات بنجاح! 💾", "success");
          } else {
            showToast("فشل حفظ التفضيلات. يرجى المحاولة مرة أخرى.", "error");
          }
        } catch (err) {
          console.error("Error saving setting:", err);
          showToast("خطأ في الاتصال بالسيرفر.", "error");
        }
      }

      // Save Analytics Scope Setting
      async function saveAnalyticsScopeSetting() {
        const selectedRadio = document.querySelector('input[name="analytics-scope-radio"]:checked');
        if (!selectedRadio) return;

        const value = selectedRadio.value;

        try {
          const res = await fetch('/api/settings', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ key: 'analytics-scope', value: value })
          });

          if (res.ok) {
            showToast("تم حفظ نطاق التحليلات بنجاح! 📊", "success");
          } else {
            showToast("فشل حفظ تفضيلات نطاق التحليلات.", "error");
          }
        } catch (err) {
          console.error("Error saving analytics scope setting:", err);
          showToast("خطأ في الاتصال بالسيرفر.", "error");
        }
      }

      // Clear Browser Cache (JS/CSS)
      async function clearBrowserCache() {
        if (!confirm('هل تريد مسح الكاش المخزن في المتصفح (CSS/JS) وإعادة تحميل أحدث إصدار؟')) return;

        try {
          // Clear Cache Storage API if available
          if ('caches' in window) {
            const keys = await caches.keys();
            await Promise.all(keys.map(k => caches.delete(k)));
          }
        } catch (e) {
          console.error('Cache API error:', e);
        }

        // Reload with a fresh cache-busting parameter
        const t = Date.now();
        window.location.href = window.location.pathname + '?v=' + t;
      }

      // Handle Data Cleaning Operations
      async function clearData(type) {
        let confirmMsg = "هل أنت متأكد من تنفيذ هذه العملية؟";
        if (type === 'fetched') {
          confirmMsg = "هل أنت متأكد من حذف كافة المنتجات المجلوبة مؤقتاً؟ (سيتم الإبقاء على المنتجات المفضلة فقط)";
        } else if (type === 'saved') {
          confirmMsg = "هل أنت متأكد من إلغاء حفظ كل المنتجات المفضلة وتصفير تقييماتها وملاحظاتها؟";
        } else if (type === 'collections') {
          confirmMsg = "هل أنت متأكد من حذف كافة المجموعات وإعادة المنتجات المفضلة للمجموعة العامة؟";
        } else if (type === 'watchlist') {
          confirmMsg = "هل أنت متأكد من حذف قائمة مراقبة المتاجر المنافسة بالكامل؟";
        } else if (type === 'all') {
          confirmMsg = "🚨 تنبيه هام جداً: سيتم حذف كافة المنتجات والمجموعات وقوائم المراقبة وإعادة ضبط الإعدادات. هل أنت متأكد تماماً من رغبتك في تهيئة النظام بالكامل؟ (لا يمكن التراجع عن هذا الإجراء)";
        }

        if (!confirm(confirmMsg)) return;

        try {
          const res = await fetch('/api/products/clear-database-data', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ type: type })
          });

          if (res.ok) {
            showToast("تمت عملية مسح وتصفية البيانات بنجاح! 🧹", "success");
            if (type === 'all') {
              setTimeout(() => window.location.reload(), 1500);
            }
          } else {
            showToast("فشلت عملية تنظيف البيانات. يرجى المحاولة لاحقاً.", "error");
          }
        } catch (err) {
          console.error("Error clearing data:", err);
          showToast("خطأ في الاتصال بالسيرفر أثناء محاولة الحذف.", "error");
        }
      }

      // Delete Data by Selected Date
      async function deleteDataBySelectedDate() {
        const dateInput = document.getElementById('delete-by-date-picker');
        if (!dateInput || !dateInput.value) {
          showToast("يرجى تحديد التاريخ أولاً ⚠️", "warning");
          return;
        }
        const selectedDate = dateInput.value;

        if (!confirm(`⚠️ هل أنت متأكد تماماً من رغبتك في حذف كافة المنتجات ولقطات البيانات غير المحفوظة لتاريخ (${selectedDate})؟`)) {
          return;
        }

        try {
          const res = await fetch('/api/products/delete-by-date', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ date: selectedDate })
          });

          const data = await res.json();
          if (res.ok && data.success) {
            showToast(data.message || `تم حذف بيانات تاريخ ${selectedDate} بنجاح! 🗑️`, "success");
          } else {
            showToast(data.message || "فشلت عملية حذف بيانات التاريخ المحدد.", "error");
          }
        } catch (err) {
          console.error("Error deleting date data:", err);
          showToast("خطأ في الاتصال بالسيرفر أثناء عملية الحذف.", "error");
        }
      }

      // Inline Migration Runner
      async function runInlineMigration(e) {
        if (e) e.preventDefault();
        const btn = e ? e.currentTarget : document.getElementById("run-migration-btn");
        const originalHtml = btn ? btn.innerHTML : "";

        if (btn) {
          btn.disabled = true;
          btn.style.opacity = "0.75";
          btn.innerHTML = `<span style="display:inline-block; width:14px; height:14px; border:2px solid #fff; border-top-color:transparent; border-radius:50%; animation:spin 0.8s linear infinite; margin-left:6px;"></span> جاري التحديث...`;
        }

        try {
          const res = await fetch("/update-db?json=1", {
            headers: {
              "X-Requested-With": "XMLHttpRequest",
              "Accept": "application/json"
            }
          });
          const data = await res.json();

          if (res.ok && data.status === "success") {
            showToast(data.message || "تمت تحديثات قاعدة البيانات بنجاح! 🚀", "success");
            
            const cardContainer = document.getElementById("db-migration-card-container");
            if (cardContainer) {
              cardContainer.innerHTML = `
                <div class="settings-card" style="border-right: 4px solid #10b981;">
                  <div class="settings-card-title" style="color: #10b981; border-bottom: none; padding-bottom: 0; margin-bottom: 0.25rem;">
                    ✅ حالة قاعدة البيانات (Database Status)
                  </div>
                  <p class="settings-card-desc" style="margin-bottom: 0; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                    <span>قاعدة البيانات محدثة بالكامل وتعمل بأحدث هيكل، لا توجد أي هجرات (Migrations) معلقة.</span>
                    <button onclick="runInlineMigration(event)" class="btn btn-secondary" style="font-size: 0.8rem; padding: 5px 12px; cursor: pointer;">إعادة الفحص والتحديث</button>
                  </p>
                </div>
              `;
            }
          } else {
            showToast("❌ " + (data.message || "حدث خطأ أثناء إجراء التحديثات"), "error");
            if (btn) {
              btn.disabled = false;
              btn.style.opacity = "1";
              btn.innerHTML = originalHtml;
            }
          }
        } catch (err) {
          console.error("Migration error:", err);
          showToast("❌ تعذر الاتصال بالسيرفر لتنفيذ التحديث.", "error");
          if (btn) {
            btn.disabled = false;
            btn.style.opacity = "1";
            btn.innerHTML = originalHtml;
          }
        }
      }
    </script>
  </body>
</html>
