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

        <div class="settings-container">
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
                <a href="<?= base_url('update-db') ?>" class="btn" style="background: linear-gradient(135deg, #f59e0b, #d97706); color: white; border: none; font-weight: 700; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; padding: 10px 22px; border-radius: 8px; box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);">
                  🔄 تشغيل التحديث الآن (/update-db)
                </a>
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
                <a href="<?= base_url('update-db') ?>" class="btn btn-secondary" style="font-size: 0.8rem; padding: 5px 12px; text-decoration: none;">إعادة الفحص (/update-db)</a>
              </p>
            </div>
          <?php endif; ?>

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
          <!-- Card 1: Data Source Setting (Admin Only) -->
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

    <script>
      // Load current settings on mount
      document.addEventListener("DOMContentLoaded", async () => {
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

      // Load Settings from server
      async function loadSettings() {
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
    </script>
  </body>
</html>
