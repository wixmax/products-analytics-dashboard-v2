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
    <div class="app-shell">
      <!-- Sidebar Panel -->
      <aside class="sidebar">
        <div class="logo-container">
          <div class="logo-icon">⚡</div>
          <div class="logo-text">
            <h1>Overview Insights</h1>
            <p>منتجات الصين واليابان</p>
          </div>
        </div>

        <!-- Sidebar Navigation Menu -->
        <nav class="sidebar-nav">
          <a href="<?= base_url('/') ?>" class="sidebar-nav-item <?= current_url() == base_url() || current_url() == base_url('/') ? 'active' : '' ?>">
            📊 لوحة التحكم
          </a>
          <a href="<?= base_url('saved-ads') ?>" class="sidebar-nav-item <?= strpos(current_url(), 'saved-ads') !== false ? 'active' : '' ?>">
            ⭐ الإعلانات المحفوظة
          </a>
          <a href="<?= base_url('international-products') ?>" class="sidebar-nav-item <?= strpos(current_url(), 'international-products') !== false ? 'active' : '' ?>">
            🌏 منتجات الصين واليابان
          </a>
          <a href="<?= base_url('snapshots') ?>" class="sidebar-nav-item <?= strpos(current_url(), 'snapshots') !== false ? 'active' : '' ?>">
            📸 لقطات البيانات
          </a>
          <a href="<?= base_url('settings') ?>" class="sidebar-nav-item <?= strpos(current_url(), 'settings') !== false ? 'active' : '' ?>">
            ⚙️ إعدادات النظام
          </a>
          <a href="<?= base_url('workspace') ?>" class="sidebar-nav-item <?= strpos(current_url(), 'workspace') !== false ? 'active' : '' ?>">
            📁 مساحة العمل
          </a>
          <a href="<?= base_url('profile') ?>" class="sidebar-nav-item <?= strpos(current_url(), 'profile') !== false ? 'active' : '' ?>">
            👤 الملف الشخصي
          </a>
          <?php if (auth()->loggedIn() && auth()->user()->inGroup('superadmin', 'admin')): ?>
            <a href="<?= base_url('admin/users') ?>" class="sidebar-nav-item <?= strpos(current_url(), 'admin/users') !== false ? 'active' : '' ?>">
              🛡️ إدارة الأعضاء
            </a>
          <?php endif; ?>
        </nav>

        <!-- Sidebar Footer (User Profile Card) -->
        <?php if (auth()->loggedIn()): ?>
          <?php
            $db = \Config\Database::connect();
            $activeTenant = !empty(auth()->user()->tenant_id) ? $db->table('tenants')->where('id', auth()->user()->tenant_id)->get()->getRow() : null;
          ?>
          <div class="sidebar-user-card" style="margin-top: auto;">
            <div style="display: flex; align-items: center; gap: 10px; min-width: 0;">
              <div class="user-avatar-small" style="width: 38px; height: 38px; font-size: 1.1rem; flex-shrink: 0; background: var(--color-primary); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold;">
                <?= strtoupper(substr(esc(auth()->user()->username ?? 'U'), 0, 1)) ?>
              </div>
              <div style="display: flex; flex-direction: column; min-width: 0;">
                <span class="user-name-small" style="font-weight: 700; font-size: 0.85rem; color: var(--color-text-main); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                  <?= esc(auth()->user()->username) ?>
                </span>
                <?php if ($activeTenant): ?>
                  <span style="font-size: 0.7rem; color: var(--color-text-muted); display: flex; align-items: center; gap: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= esc($activeTenant->name) ?>">
                    📁 <?= esc($activeTenant->name) ?>
                  </span>
                <?php endif; ?>
              </div>
            </div>
            <div style="display: flex; gap: 6px;">
              <a href="<?= base_url('profile') ?>" class="btn btn-secondary" style="padding: 6px; font-size: 0.9rem; border-radius: 50%; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;" title="الملف الشخصي">👤</a>
              <a href="<?= base_url('logout') ?>" class="btn btn-error" style="padding: 6px; font-size: 0.9rem; border-radius: 50%; width: 32px; height: 32px; background: rgba(239, 68, 68, 0.1); border-color: rgba(239, 68, 68, 0.2); color: var(--color-error); display: flex; align-items: center; justify-content: center;" title="تسجيل الخروج">🚪</a>
            </div>
          </div>
        <?php endif; ?>
      </aside>

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
