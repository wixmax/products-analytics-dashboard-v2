<!DOCTYPE html>
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
    <title>منتجات اليابان والصين | Overview Insights</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="<?= base_url('index.css') ?>?v=1.6" />
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
    <?php if (session()->has('impersonator_user_id')): ?>
      <div style="background: linear-gradient(90deg, #f59e0b, #d97706); color: white; padding: 10px 20px; text-align: center; font-weight: bold; display: flex; justify-content: center; align-items: center; gap: 15px; z-index: 9999; font-size: 0.9rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); width: 100%;">
        <span>⚠️ أنت تتصفح النظام حالياً بصفتك: <strong><?= esc(auth()->user()->username) ?></strong> (محاكاة حساب)</span>
        <a href="<?= base_url('admin/users/stop-impersonating') ?>" style="background: white; color: #b45309; padding: 4px 12px; border-radius: 4px; text-decoration: none; font-size: 0.8rem; font-weight: 700; transition: all 0.2s;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background='white'">العودة لحساب المسؤول 🚪</a>
      </div>
    <?php endif; ?>
    <div class="app-shell">
      <?= $this->include('partials/sidebar', ['subtitle' => 'منتجات الصين واليابان']) ?>

      <!-- Main Area -->
      <main class="main-content">
        <div class="top-nav">
          <div>
            <h2 style="font-weight: 800; font-size: 1.6rem;">لوحة المنتجات العالمية</h2>
            <p style="color: var(--color-text-muted); font-size: 0.85rem">عرض وتحليل أحدث المنتجات من الأسواق اليابانية والصينية</p>
          </div>
          <div class="actions-group">
            <button class="theme-toggle" id="theme-toggle-btn">🌓</button>
          </div>
        </div>

        <!-- Dashboard Filter Panel -->
        <div class="dashboard-filter-card" id="dashboard-filter-panel" style="padding: 1.25rem; margin-bottom: 1.5rem;">
          <div class="filter-card-header" onclick="toggleFilterPanel()" style="display: flex; justify-content: space-between; align-items: center; cursor: pointer; user-select: none; border-bottom: 1px solid var(--border-color); padding-bottom: 0.75rem; margin-bottom: 0.75rem;">
            <div style="display: flex; align-items: center; gap: 8px;">
              <span style="font-size: 1.1rem;">🔍</span>
              <h3 style="font-size: 0.95rem; font-weight: 700; margin: 0; color: var(--color-text-main);">تصفية واستعلام المنتجات العالمية</h3>
            </div>
            <span id="filter-toggle-icon" style="font-size: 0.9rem; color: var(--color-text-muted); font-weight: bold;">🔼</span>
          </div>

          <div id="filter-panel-body" style="display: block;">
            <div class="dashboard-filter-grid" style="display: grid; grid-template-columns: 1fr 1.5fr 1fr; gap: 1.5rem; align-items: end;">
              
              <!-- Column 1: Quick Search -->
              <div class="form-group" style="margin-bottom: 0;">
                <label>🔍 بحث سريع</label>
                <input type="text" id="search-input" placeholder="ابحث عن منتج..." oninput="searchProducts()" style="width: 100%;">
              </div>

              <!-- Column 2: Filter by Origin -->
              <div class="form-group" style="margin-bottom: 0;">
                <label>🌍 تصفية حسب المنشأ</label>
                <div class="origin-filters" style="display: flex; gap: 8px; width: 100%;">
                  <button class="origin-btn" onclick="filterByOrigin('all')" style="flex: 1; padding: 10px; justify-content: center; font-size: 0.85rem;">
                    <span>🌐 الكل</span>
                    <span class="count" id="kpi-total" style="margin-right: 6px;">0</span>
                  </button>
                  <button class="origin-btn" onclick="selectCountry('Japan')" style="flex: 1; padding: 10px; justify-content: center; font-size: 0.85rem;">
                    <span>🇯🇵 اليابان</span>
                    <span class="count" id="kpi-japan" style="margin-right: 6px;">0</span>
                  </button>
                  <button class="origin-btn" onclick="selectCountry('China')" style="flex: 1; padding: 10px; justify-content: center; font-size: 0.85rem;">
                    <span>🇨🇳 الصين</span>
                    <span class="count" id="kpi-china" style="margin-right: 6px;">0</span>
                  </button>
                </div>
              </div>

              <!-- Column 3: Import JSON -->
              <div class="form-group" style="margin-bottom: 0;">
                <label>📁 استيراد ملفات JSON</label>
                <div class="file-input-wrapper" style="width: 100%;">
                  <button class="btn btn-secondary btn-block" style="padding: 10px; font-size: 0.85rem; width: 100%;">
                    استيراد ملف محلي
                  </button>
                  <input type="file" id="local-file-input" accept=".json" onchange="handleLocalFile(event)" />
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
              localStorage.setItem('intl_filter_panel_collapsed', 'true');
            } else {
              body.style.display = 'block';
              icon.textContent = '🔼';
              header.style.borderBottom = '1px solid var(--border-color)';
              header.style.marginBottom = '0.75rem';
              header.style.paddingBottom = '0.75rem';
              localStorage.removeItem('intl_filter_panel_collapsed');
            }
          }

          // Restore state on load
          document.addEventListener('DOMContentLoaded', () => {
            if (localStorage.getItem('intl_filter_panel_collapsed') === 'true') {
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
