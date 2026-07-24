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
    <?php if (session()->has('impersonator_user_id')): ?>
      <div style="background: linear-gradient(90deg, #f59e0b, #d97706); color: white; padding: 10px 20px; text-align: center; font-weight: bold; display: flex; justify-content: center; align-items: center; gap: 15px; z-index: 9999; font-size: 0.9rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1); width: 100%;">
        <span>⚠️ أنت تتصفح النظام حالياً بصفتك: <strong><?= esc(auth()->user()->username) ?></strong> (محاكاة حساب)</span>
        <a href="<?= base_url('admin/users/stop-impersonating') ?>" style="background: white; color: #b45309; padding: 4px 12px; border-radius: 4px; text-decoration: none; font-size: 0.8rem; font-weight: 700; transition: all 0.2s;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background='white'">العودة لحساب المسؤول 🚪</a>
      </div>
    <?php endif; ?>
    <div class="app-shell">
      <?= $this->include('partials/sidebar', ['subtitle' => 'الملف الشخصي']) ?>

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

        const localTheme = localStorage.getItem("app-theme");
        if (localTheme) {
          document.documentElement.setAttribute("data-theme", localTheme);
        }

        try {
          const res = await fetch('/api/settings/app-theme');
          if (res.ok) {
            const data = await res.json();
            if (data.value) {
              document.documentElement.setAttribute("data-theme", data.value);
              localStorage.setItem("app-theme", data.value);
            }
          }
        } catch (err) {
          console.error("Error fetching theme:", err);
        }

        themeBtn.onclick = async () => {
          const theme = document.documentElement.getAttribute("data-theme") === "dark" ? "light" : "dark";
          document.documentElement.setAttribute("data-theme", theme);
          localStorage.setItem("app-theme", theme);
          try {
            await fetch('/api/settings', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ key: 'app-theme', value: theme })
            });
          } catch (err) {
            console.error("Error saving theme setting:", err);
          }
        };
      }
    </script>
  </body>
</html>
