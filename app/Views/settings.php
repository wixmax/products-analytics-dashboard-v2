<!doctype html>
<html lang="ar" dir="rtl">
  <head>
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
    </style>
  </head>
  <body>
    <div class="app-shell" style="grid-template-columns: 1fr">
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
            <a href="<?= base_url('/') ?>" class="btn btn-secondary"
              >🏠 العودة للوحة التحكم</a
            >
            <a href="<?= base_url('logout') ?>" class="btn btn-error" style="background:rgba(239,68,68,0.12);color:var(--color-error);border-color:rgba(239,68,68,0.3);" title="تسجيل الخروج">🚪 خروج</a>
            <button class="theme-toggle" id="theme-toggle-btn">🌓</button>
          </div>
        </div>

        <div class="settings-container">
          <!-- Card 1: Data Source Setting -->
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

          <!-- Card 2: Database Operations -->
          <div class="settings-card">
            <div class="settings-card-title">
              🧹 تحكم وتنظيف البيانات
            </div>
            <p class="settings-card-desc">
              خيارات لحذف بيانات المنتجات والمجموعات لتهيئة النظام وتخفيف مساحة التخزين.
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

              <!-- Item 5: Clear JS Cache -->
              <div class="action-item">
                <div class="action-item-info">
                  <span class="action-item-title">🧹 تفريغ كاش ملفات JS في المتصفح</span>
                  <span class="action-item-desc">مسح الملفات المخزنة (CSS/JS) من المتصفح وإعادة تحميل آخر إصدار من السيرفر. استخدم هذا بعد تحديث الكود للتأكد من تشغيل الإصدار الجديد.</span>
                </div>
                <button class="btn btn-secondary" onclick="clearBrowserCache()">🧹 تفريغ الكاش</button>
              </div>
            </div>
          </div>

          <!-- Card 3: Danger Zone -->
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

        themeBtn.onclick = async () => {
          const theme = document.documentElement.getAttribute("data-theme") === "dark" ? "light" : "dark";
          document.documentElement.setAttribute("data-theme", theme);
          try {
            await fetch('/api/settings', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ key: 'app-theme', value: theme })
            });
          } catch (err) {
            console.error("Error saving theme:", err);
          }
        };
      }

      // Load Settings from server
      async function loadSettings() {
        try {
          const res = await fetch('/api/settings/data-source');
          if (res.ok) {
            const data = await res.json();
            const value = data.value || 'database';
            if (value === 'api') {
              document.getElementById('radio-source-api').checked = true;
            } else {
              document.getElementById('radio-source-db').checked = true;
            }
          } else {
            document.getElementById('radio-source-db').checked = true;
          }
        } catch (err) {
          console.error("Error loading settings:", err);
          document.getElementById('radio-source-db').checked = true;
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
