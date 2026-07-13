<!doctype html>
<html lang="ar" dir="rtl">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Profile | الملف الشخصي</title>
    <meta name="description" content="تعديل بيانات الحساب وتحديث كلمة المرور." />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap"
      rel="stylesheet"
    />

    <link rel="stylesheet" href="<?= base_url('index.css') ?>?v=1.6" />
    <style>
      .profile-container {
        max-width: 800px;
        margin: 2rem auto;
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
        padding: 0 1rem;
      }
      .profile-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-md);
        padding: 1.75rem;
        box-shadow: var(--shadow-sm);
        transition: var(--transition-all);
      }
      .profile-card:hover {
        border-color: var(--color-primary);
        box-shadow: var(--shadow-md);
      }
      .profile-card-title {
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
      .profile-card-desc {
        color: var(--color-text-muted);
        font-size: 0.85rem;
        line-height: 1.5;
        margin-bottom: 1.25rem;
      }
      .profile-form-group {
        margin-bottom: 1.25rem;
        display: flex;
        flex-direction: column;
        gap: 6px;
      }
      .profile-form-group label {
        font-weight: 600;
        font-size: 0.9rem;
        color: var(--color-text-main);
      }
      .profile-form-group input {
        padding: 0.65rem 0.85rem;
        border-radius: var(--radius-sm);
        border: 1px solid var(--border-color);
        background: var(--bg-input);
        color: var(--color-text-main);
        font-family: inherit;
        font-size: 0.95rem;
        transition: var(--transition-all);
      }
      .profile-form-group input:focus {
        border-color: var(--color-primary);
        outline: none;
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
      }
      .alert {
        padding: 0.75rem 1rem;
        border-radius: var(--radius-sm);
        margin-bottom: 1.5rem;
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        gap: 8px;
      }
      .alert-success {
        background: rgba(16, 185, 129, 0.12);
        color: var(--color-success);
        border: 1px solid rgba(16, 185, 129, 0.2);
      }
      .alert-danger {
        background: rgba(239, 68, 68, 0.12);
        color: var(--color-error);
        border: 1px solid rgba(239, 68, 68, 0.2);
      }
      .user-avatar-circle {
        width: 70px;
        height: 70px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--color-primary), #6366f1);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
        font-weight: 800;
        margin-bottom: 1rem;
        box-shadow: var(--shadow-sm);
      }
      .profile-header-card {
        display: flex;
        align-items: center;
        gap: 1.25rem;
      }
      .profile-header-info {
        display: flex;
        flex-direction: column;
        gap: 4px;
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
            <p>الملف الشخصي</p>
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
              👤 الملف الشخصي وإعدادات الحساب
            </h2>
            <p style="color: var(--color-text-muted); font-size: 0.85rem">
              إدارة معلومات حسابك الشخصي وكلمات المرور الخاصة بك
            </p>
          </div>

          <div class="actions-group">
            <button class="theme-toggle" id="theme-toggle-btn">🌓</button>
          </div>
        </div>

        <div class="profile-container">
          <!-- Session Messages -->
          <?php if (session()->has('message')): ?>
            <div class="alert alert-success">
              <span>✅</span>
              <span><?= session('message') ?></span>
            </div>
          <?php endif; ?>

          <?php if (session()->has('error')): ?>
            <div class="alert alert-danger">
              <span>❌</span>
              <span><?= session('error') ?></span>
            </div>
          <?php endif; ?>

          <?php if (session()->has('errors')): ?>
            <div class="alert alert-danger" style="flex-direction: column; align-items: flex-start; gap: 4px;">
              <?php foreach (session('errors') as $error): ?>
                <div>❌ <?= esc($error) ?></div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <!-- Card 1: User Overview -->
          <div class="profile-card">
            <div class="profile-header-card">
              <div class="user-avatar-circle">
                <?= strtoupper(substr(esc($user->username ?? 'U'), 0, 1)) ?>
              </div>
              <div class="profile-header-info">
                <h3 style="font-weight:700; font-size:1.25rem; color:var(--color-text-main);"><?= esc($user->username) ?></h3>
                <span style="font-size:0.85rem; color:var(--color-text-muted);"><?= esc($user->email) ?></span>
                <div style="margin-top: 4px;">
                  <?php foreach ($user->getGroups() as $group): ?>
                    <span style="background:rgba(99,102,241,0.15); color:var(--color-primary); font-size:0.75rem; font-weight:700; padding: 2px 8px; border-radius:12px; text-transform:uppercase;">
                      🛡️ <?= esc($group) ?>
                    </span>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
          </div>

          <!-- Card 2: Update Profile Information -->
          <div class="profile-card">
            <div class="profile-card-title">
              📝 تعديل البيانات الأساسية
            </div>
            <p class="profile-card-desc">
              تحديث اسم المستخدم الخاص بك لتغيير طريقة عرض اسمك في لوحة التحكم.
            </p>

            <form action="<?= base_url('profile/update') ?>" method="POST">
              <?= csrf_field() ?>
              <div class="profile-form-group">
                <label for="username">اسم المستخدم</label>
                <input type="text" name="username" id="username" value="<?= esc(old('username', $user->username)) ?>" required />
              </div>

              <div style="display: flex; justify-content: flex-end; margin-top: 1rem;">
                <button type="submit" class="btn btn-primary">
                  💾 حفظ التغييرات
                </button>
              </div>
            </form>
          </div>

          <!-- Card 3: Change Password -->
          <div class="profile-card">
            <div class="profile-card-title">
              🔒 تغيير كلمة المرور
            </div>
            <p class="profile-card-desc">
              تأكد من اختيار كلمة مرور قوية وغير مكررة لحماية حسابك من الاختراق.
            </p>

            <form action="<?= base_url('profile/change-password') ?>" method="POST">
              <?= csrf_field() ?>
              <div class="profile-form-group">
                <label for="current_password">كلمة المرور الحالية</label>
                <input type="password" name="current_password" id="current_password" required />
              </div>

              <div class="profile-form-group">
                <label for="new_password">كلمة المرور الجديدة</label>
                <input type="password" name="new_password" id="new_password" required minlength="8" />
              </div>

              <div class="profile-form-group">
                <label for="confirm_password">تأكيد كلمة المرور الجديدة</label>
                <input type="password" name="confirm_password" id="confirm_password" required />
              </div>

              <div style="display: flex; justify-content: flex-end; margin-top: 1rem;">
                <button type="submit" class="btn btn-primary">
                  🔒 تحديث كلمة المرور
                </button>
              </div>
            </form>
          </div>
        </div>
      </main>
    </div>

    <script>
      document.addEventListener("DOMContentLoaded", async () => {
        await setupTheme();
      });

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
    </script>
  </body>
</html>
