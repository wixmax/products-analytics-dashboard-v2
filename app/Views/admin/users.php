<!doctype html>
<html lang="ar" dir="rtl">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>User Management | إدارة الأعضاء</title>
    <meta name="description" content="لوحة تحكم المسؤول لإدارة الأعضاء والصلاحيات." />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap"
      rel="stylesheet"
    />

    <link rel="stylesheet" href="<?= base_url('index.css') ?>?v=1.6" />
    <style>
      .admin-container {
        max-width: 1100px;
        margin: 2rem auto;
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
        padding: 0 1rem;
      }
      .admin-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-md);
        padding: 1.75rem;
        box-shadow: var(--shadow-sm);
        transition: var(--transition-all);
      }
      .admin-card:hover {
        border-color: var(--color-primary);
        box-shadow: var(--shadow-md);
      }
      .admin-card-title {
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
      .admin-card-desc {
        color: var(--color-text-muted);
        font-size: 0.85rem;
        line-height: 1.5;
        margin-bottom: 1.25rem;
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
      .table-wrapper {
        overflow-x: auto;
        border: 1px solid var(--border-color);
        border-radius: var(--radius-sm);
        margin-top: 1rem;
      }
      .admin-table {
        width: 100%;
        border-collapse: collapse;
        text-align: right;
        font-size: 0.9rem;
      }
      .admin-table th {
        background: var(--bg-input);
        color: var(--color-text-main);
        font-weight: 700;
        padding: 0.85rem 1rem;
        border-bottom: 1px solid var(--border-color);
      }
      .admin-table td {
        padding: 0.85rem 1rem;
        border-bottom: 1px solid var(--border-color);
        color: var(--color-text-main);
        vertical-align: middle;
      }
      .admin-table tr:last-child td {
        border-bottom: none;
      }
      .badge-role {
        font-size: 0.75rem;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 12px;
        text-transform: uppercase;
        display: inline-block;
      }
      .badge-role-superadmin {
        background: rgba(239, 68, 68, 0.12);
        color: var(--color-error);
      }
      .badge-role-admin {
        background: rgba(245, 158, 11, 0.12);
        color: var(--color-warning);
      }
      .badge-role-developer {
        background: rgba(16, 185, 129, 0.12);
        color: var(--color-success);
      }
      .badge-role-user {
        background: rgba(99, 102, 241, 0.12);
        color: var(--color-primary);
      }
      .badge-role-beta {
        background: rgba(139, 92, 246, 0.12);
        color: #8b5cf6;
      }
      .status-active {
        color: var(--color-success);
        font-weight: 700;
      }
      .status-inactive {
        color: var(--color-text-muted);
        text-decoration: line-through;
      }
      .role-select {
        padding: 4px 8px;
        border-radius: var(--radius-sm);
        border: 1px solid var(--border-color);
        background: var(--bg-input);
        color: var(--color-text-main);
        font-family: inherit;
        font-size: 0.8rem;
      }
      /* Modal Styles */
      .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.6);
        backdrop-filter: blur(4px);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10000;
        animation: fadeIn 0.2s ease-out;
      }
      .modal-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-md);
        padding: 1.75rem;
        width: 90%;
        max-width: 450px;
        box-shadow: var(--shadow-lg);
        animation: slideUp 0.3s cubic-bezier(0.16, 1, 0.3, 1);
      }
      @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
      }
      @keyframes slideUp {
        from { transform: translateY(20px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
      }
    </style>
  </head>
  <body>
    <?php if (session()->has('impersonator_user_id')): ?>
      <div style="background: linear-gradient(90deg, #f59e0b, #d97706); color: white; padding: 10px 20px; text-align: center; font-weight: bold; display: flex; justify-content: center; align-items: center; gap: 15px; z-index: 9999; font-size: 0.9rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <span>⚠️ أنت تتصفح النظام حالياً بصفتك: <strong><?= esc(auth()->user()->username) ?></strong> (محاكاة حساب)</span>
        <a href="<?= base_url('admin/users/stop-impersonating') ?>" style="background: white; color: #b45309; padding: 4px 12px; border-radius: 4px; text-decoration: none; font-size: 0.8rem; font-weight: 700; transition: all 0.2s;" onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background='white'">العودة لحساب المسؤول 🚪</a>
      </div>
    <?php endif; ?>
    <div class="app-shell">
      <!-- Sidebar Panel -->
      <aside class="sidebar">
        <div class="logo-container">
          <div class="logo-icon">⚡</div>
          <div class="logo-text">
            <h1>Overview Insights</h1>
            <p>إدارة الأعضاء</p>
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
              🛡️ لوحة إدارة المستخدمين والأعضاء
            </h2>
            <p style="color: var(--color-text-muted); font-size: 0.85rem">
              إدارة صلاحيات الأعضاء وتعديل أدوارهم أو تفعيل وتعطيل حساباتهم بالكامل
            </p>
          </div>

          <div class="actions-group">
            <button class="theme-toggle" id="theme-toggle-btn">🌓</button>
          </div>
        </div>

        <div class="admin-container">
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

          <!-- Card: Users Database -->
          <div class="admin-card">
            <div class="admin-card-title">
              👥 قاعدة بيانات الأعضاء
            </div>
            <p class="admin-card-desc">
              تعديل الأدوار والصلاحيات للمستخدمين المسجلين وتنشيط أو إيقاف حساباتهم للوصول للمشروع.
            </p>

            <div class="table-wrapper">
              <table class="admin-table">
                <thead>
                  <tr>
                    <th>اسم العضو</th>
                    <th>البريد الإلكتروني</th>
                    <th>مساحة العمل (Workspace)</th>
                    <th>تاريخ التسجيل</th>
                    <th>الدور الحالي</th>
                    <th>الحالة</th>
                    <th>تحديث الصلاحية</th>
                    <th>التحكم بالحالة</th>
                    <th>تغيير كلمة المرور</th>
                    <th>محاكاة الحساب</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($users as $u): ?>
                    <tr>
                      <td style="font-weight:700; color:var(--color-text-main);">
                        <?= esc($u['username']) ?>
                      </td>
                      <td style="font-family:sans-serif;"><?= esc($u['email']) ?></td>
                      <td style="font-weight:500; color:var(--color-primary);">
                        <?= esc($u['tenant_name'] ?: 'بدون مساحة عمل') ?>
                      </td>
                      <td style="font-size:0.8rem; color:var(--color-text-muted);">
                        <?= esc(date('Y-m-d H:i', strtotime($u['created_at']))) ?>
                      </td>
                      <td>
                        <?php foreach ($u['groups'] as $group): ?>
                          <span class="badge-role badge-role-<?= esc($group) ?>"><?= esc($group) ?></span>
                        <?php endforeach; ?>
                      </td>
                      <td class="<?= $u['active'] ? 'status-active' : 'status-inactive' ?>">
                        <?= $u['active'] ? '🟢 نشط' : '🔴 معطل' ?>
                      </td>
                      <td>
                        <form action="<?= base_url('admin/users/update-role') ?>" method="POST" style="display:flex; gap:6px; align-items:center;">
                          <?= csrf_field() ?>
                          <input type="hidden" name="user_id" value="<?= $u['id'] ?>" />
                          <select name="role" class="role-select">
                            <option value="user" <?= in_array('user', $u['groups'], true) ? 'selected' : '' ?>>User</option>
                            <option value="admin" <?= in_array('admin', $u['groups'], true) ? 'selected' : '' ?>>Admin</option>
                            <option value="superadmin" <?= in_array('superadmin', $u['groups'], true) ? 'selected' : '' ?>>Superadmin</option>
                            <option value="developer" <?= in_array('developer', $u['groups'], true) ? 'selected' : '' ?>>Developer</option>
                            <option value="beta" <?= in_array('beta', $u['groups'], true) ? 'selected' : '' ?>>Beta</option>
                          </select>
                          <button type="submit" class="btn btn-primary" style="padding: 4px 8px; font-size: 0.75rem;">💾</button>
                        </form>
                      </td>
                      <td>
                        <?php if ((int)$u['id'] !== (int)auth()->id()): ?>
                          <form action="<?= base_url('admin/users/toggle-status') ?>" method="POST" style="display:inline;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>" />
                            <button type="submit" class="btn <?= $u['active'] ? 'btn-secondary' : 'btn-success' ?>" style="padding: 4px 10px; font-size: 0.75rem; width: 100px;">
                              <?= $u['active'] ? '🔴 تعطيل' : '🟢 تفعيل' ?>
                            </button>
                          </form>
                        <?php else: ?>
                          <span style="color:var(--color-text-muted); font-size:0.8rem;">(حسابك الحالي)</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <button type="button" class="btn btn-secondary" style="padding: 4px 10px; font-size: 0.75rem; background: rgba(99, 102, 241, 0.1); border-color: rgba(99, 102, 241, 0.2); color: var(--color-primary); width: 120px;" onclick="openPasswordModal(<?= $u['id'] ?>, '<?= esc($u['username'], 'js') ?>')">
                          🔑 تغيير كلمة المرور
                        </button>
                      </td>
                      <td>
                        <?php if ((int)$u['id'] !== (int)auth()->id()): ?>
                          <form action="<?= base_url('admin/users/impersonate') ?>" method="POST" style="display:inline;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>" />
                            <button type="submit" class="btn btn-secondary" style="padding: 4px 10px; font-size: 0.75rem; background: rgba(245, 158, 11, 0.1); border-color: rgba(245, 158, 11, 0.2); color: var(--color-warning); width: 100px;">
                              🔑 دخول كعضو
                            </button>
                          </form>
                        <?php else: ?>
                          <span style="color:var(--color-text-muted); font-size:0.8rem;">-</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
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

      function openPasswordModal(userId, username) {
        document.getElementById('modal-user-id').value = userId;
        document.getElementById('modal-username').innerText = username;
        document.getElementById('new_password').value = '';
        document.getElementById('new_password_confirm').value = '';
        document.getElementById('password-modal').style.display = 'flex';
      }

      function closePasswordModal() {
        document.getElementById('password-modal').style.display = 'none';
      }

      function validatePasswordForm() {
        const pass = document.getElementById('new_password').value;
        const confirm = document.getElementById('new_password_confirm').value;
        if (pass !== confirm) {
          alert('كلمتا المرور غير متطابقتين!');
          return false;
        }
        return true;
      }
    </script>

    <!-- Password Reset Modal -->
    <div class="modal-overlay" id="password-modal" style="display: none;">
      <div class="modal-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">
          <h3 style="font-weight: 700; font-size: 1.1rem; color: var(--color-text-main);">🔑 تغيير كلمة المرور للمستخدم</h3>
          <button style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--color-text-main)" onclick="closePasswordModal()">×</button>
        </div>
        <form action="<?= base_url('admin/users/change-password') ?>" method="POST" id="password-form" onsubmit="return validatePasswordForm()">
          <?= csrf_field() ?>
          <input type="hidden" name="user_id" id="modal-user-id" value="" />
          
          <div style="margin-bottom: 1rem;">
            <span style="font-size: 0.85rem; color: var(--color-text-muted);">اسم المستخدم:</span>
            <strong id="modal-username" style="font-size: 0.9rem; color: var(--color-text-main); margin-right: 5px;"></strong>
          </div>

          <div style="display: flex; flex-direction: column; gap: 6px; margin-bottom: 1rem;">
            <label for="new_password" style="font-size: 0.85rem; font-weight: 600; color: var(--color-text-main);">كلمة المرور الجديدة</label>
            <input type="password" name="password" id="new_password" required minlength="8" placeholder="8 رموز على الأقل" style="padding: 0.6rem 0.8rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color); background: var(--bg-input); color: var(--color-text-main); font-family: inherit; font-size: 0.9rem;" />
          </div>

          <div style="display: flex; flex-direction: column; gap: 6px; margin-bottom: 1.5rem;">
            <label for="new_password_confirm" style="font-size: 0.85rem; font-weight: 600; color: var(--color-text-main);">تأكيد كلمة المرور الجديدة</label>
            <input type="password" name="password_confirm" id="new_password_confirm" required minlength="8" placeholder="تأكيد كلمة المرور" style="padding: 0.6rem 0.8rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color); background: var(--bg-input); color: var(--color-text-main); font-family: inherit; font-size: 0.9rem;" />
          </div>

          <div style="display: flex; justify-content: flex-end; gap: 8px;">
            <button type="button" class="btn btn-secondary" onclick="closePasswordModal()">إلغاء</button>
            <button type="submit" class="btn btn-primary">حفظ كلمة المرور 💾</button>
          </div>
        </form>
      </div>
    </div>
  </body>
</html>
