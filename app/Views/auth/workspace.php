<!doctype html>
<html lang="ar" dir="rtl">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Workspace | مساحة العمل</title>
    <meta name="description" content="إدارة مساحة العمل المشتركة وأعضاء الفريق." />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
      href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap"
      rel="stylesheet"
    />

    <link rel="stylesheet" href="<?= base_url('index.css') ?>?v=1.6" />
    <style>
      .workspace-container {
        max-width: 900px;
        margin: 2rem auto;
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
        padding: 0 1rem;
      }
      .workspace-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-md);
        padding: 1.75rem;
        box-shadow: var(--shadow-sm);
        transition: var(--transition-all);
      }
      .workspace-card:hover {
        border-color: var(--color-primary);
        box-shadow: var(--shadow-md);
      }
      .workspace-card-title {
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
      .workspace-card-desc {
        color: var(--color-text-muted);
        font-size: 0.85rem;
        line-height: 1.5;
        margin-bottom: 1.25rem;
      }
      .workspace-form-group {
        margin-bottom: 1.25rem;
        display: flex;
        flex-direction: column;
        gap: 6px;
      }
      .workspace-form-group label {
        font-weight: 600;
        font-size: 0.9rem;
        color: var(--color-text-main);
      }
      .workspace-form-group input {
        padding: 0.65rem 0.85rem;
        border-radius: var(--radius-sm);
        border: 1px solid var(--border-color);
        background: var(--bg-input);
        color: var(--color-text-main);
        font-family: inherit;
        font-size: 0.95rem;
        transition: var(--transition-all);
      }
      .workspace-form-group input:focus {
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
      .table-wrapper {
        overflow-x: auto;
        border: 1px solid var(--border-color);
        border-radius: var(--radius-sm);
        margin-top: 1rem;
      }
      .members-table {
        width: 100%;
        border-collapse: collapse;
        text-align: right;
        font-size: 0.9rem;
      }
      .members-table th {
        background: var(--bg-input);
        color: var(--color-text-main);
        font-weight: 700;
        padding: 0.75rem 1rem;
        border-bottom: 1px solid var(--border-color);
      }
      .members-table td {
        padding: 0.75rem 1rem;
        border-bottom: 1px solid var(--border-color);
        color: var(--color-text-main);
      }
      .members-table tr:last-child td {
        border-bottom: none;
      }
      .badge-role {
        background: rgba(99, 102, 241, 0.12);
        color: var(--color-primary);
        font-size: 0.75rem;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 12px;
        text-transform: uppercase;
      }
      .badge-self {
        background: rgba(16, 185, 129, 0.12);
        color: var(--color-success);
        font-size: 0.75rem;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 12px;
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
            <p>مساحة العمل</p>
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
              📁 إدارة مساحة العمل والفريق
            </h2>
            <p style="color: var(--color-text-muted); font-size: 0.85rem">
              تعديل اسم مساحتك المشتركة وإضافة أو إزالة أعضاء فريقك
            </p>
          </div>

          <div class="actions-group">
            <button class="theme-toggle" id="theme-toggle-btn">🌓</button>
          </div>
        </div>

        <div class="workspace-container">
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

          <!-- Card 0: Switch Workspace -->
          <div class="workspace-card" style="border-color: var(--color-primary);">
            <div class="workspace-card-title" style="color: var(--color-primary)">
              🔄 التبديل بين مساحات العمل
            </div>
            <p class="workspace-card-desc">
              اختر مساحة العمل التي ترغب في تصفح بياناتها وتعديلها حالياً.
            </p>

            <div style="display: flex; flex-direction: column; gap: 12px; margin-top: 1rem;">
              <?php foreach ($workspaces as $ws): ?>
                <?php $isActive = (int)$ws['id'] === (int)$tenant->id; ?>
                <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border-radius: var(--radius-sm); border: 1px solid <?= $isActive ? 'var(--color-success)' : 'var(--border-color)' ?>; background: <?= $isActive ? 'rgba(16, 185, 129, 0.05)' : 'var(--bg-input)' ?>; transition: var(--transition-all);">
                  <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="font-size: 1.5rem;"><?= $isActive ? '✅' : '📁' ?></div>
                    <div>
                      <span style="font-weight: 700; font-size: 0.95rem; color: var(--color-text-main);">
                        <?= esc($ws['name']) ?>
                      </span>
                      <span class="badge-role" style="margin-right: 8px; font-size: 0.7rem; background: <?= $ws['role'] === 'owner' ? 'rgba(99, 102, 241, 0.12)' : 'rgba(245, 158, 11, 0.12)' ?>; color: <?= $ws['role'] === 'owner' ? 'var(--color-primary)' : '#f59e0b' ?>;">
                        <?= esc($ws['role'] === 'owner' ? 'مالك' : 'عضو مشارك') ?>
                      </span>
                      <?php if ($isActive): ?>
                        <span class="badge-self" style="margin-right: 4px; font-size: 0.7rem;">المساحة النشطة</span>
                      <?php endif; ?>
                    </div>
                  </div>
                  
                  <?php if (!$isActive): ?>
                    <form action="<?= base_url('workspace/switch') ?>" method="POST" style="margin: 0;">
                      <?= csrf_field() ?>
                      <input type="hidden" name="tenant_id" value="<?= esc($ws['id']) ?>" />
                      <button type="submit" class="btn btn-primary" style="padding: 6px 12px; font-size: 0.8rem;">
                        🔄 انتقال
                      </button>
                    </form>
                  <?php else: ?>
                    <span style="color: var(--color-success); font-size: 0.85rem; font-weight: bold; display: flex; align-items: center; gap: 4px;">
                      🟢 نشطة حالياً
                    </span>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Card 1: Tenant Information -->
          <div class="workspace-card">
            <div class="workspace-card-title">
              📁 معلومات مساحة العمل
            </div>
            <p class="workspace-card-desc">
              اسم مساحة العمل هو الاسم المعروض لفريقك والذي تظهر تحته كل المنتجات المحفوظة والمجموعات.
            </p>

            <form action="<?= base_url('workspace/update') ?>" method="POST">
              <?= csrf_field() ?>
              <div class="workspace-form-group">
                <label for="name">اسم مساحة العمل</label>
                <input type="text" name="name" id="name" value="<?= esc(old('name', $tenant->name)) ?>" required />
              </div>

              <div style="display: flex; justify-content: flex-end; margin-top: 1rem;">
                <button type="submit" class="btn btn-primary">
                  💾 حفظ الاسم الجديد
                </button>
              </div>
            </form>
          </div>

          <!-- Card 2: Teammates List -->
          <div class="workspace-card">
            <div class="workspace-card-title">
              👥 أعضاء الفريق المتواجدين
            </div>
            <p class="workspace-card-desc">
              الأعضاء المضافون في هذه المساحة يتشاركون معك المنتجات المحفوظة، الملاحظات، والتقييمات بشكل فوري.
            </p>

            <div class="table-wrapper">
              <table class="members-table">
                <thead>
                  <tr>
                    <th>اسم المستخدم</th>
                    <th>البريد الإلكتروني</th>
                    <th>الدور / الصلاحية</th>
                    <th>الإجراءات</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($members as $member): ?>
                    <tr>
                      <td style="font-weight:600; color:var(--color-text-main);">
                        <?= esc($member->username) ?>
                        <?php if ((int)$member->id === (int)$user->id): ?>
                          <span class="badge-self">أنت</span>
                        <?php endif; ?>
                      </td>
                      <td><?= esc($member->email) ?></td>
                      <td>
                        <?php foreach ($member->getGroups() as $group): ?>
                          <span class="badge-role"><?= esc($group) ?></span>
                        <?php endforeach; ?>
                      </td>
                      <td>
                        <?php if ((int)$member->id !== (int)$user->id): ?>
                          <form action="<?= base_url('workspace/remove-member/' . $member->id) ?>" method="POST" onsubmit="return confirm('هل أنت متأكد من إزالة هذا العضو من مساحة عملك؟ (سيتم منحه مساحة عمل مستقلة جديدة وسيحتفظ بحسابه)');" style="display:inline;">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-error" style="padding: 4px 10px; font-size: 0.75rem; background:rgba(239,68,68,0.1); border-color:rgba(239,68,68,0.2); color:var(--color-error);">
                              🔴 إزالة العضو
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

          <!-- Card 3: Invite New Team Member -->
          <div class="workspace-card">
            <div class="workspace-card-title">
              ➕ إضافة عضو للفريق
            </div>
            <p class="workspace-card-desc">
              أدخل البريد الإلكتروني للمستخدم المسجل بالفعل في النظام لإضافته إلى مساحة العمل الخاصة بك فوراً.
            </p>

            <form action="<?= base_url('workspace/invite') ?>" method="POST">
              <?= csrf_field() ?>
              <div class="workspace-form-group">
                <label for="email">البريد الإلكتروني للزميل</label>
                <input type="email" name="email" id="email" placeholder="example@domain.com" required />
              </div>

              <div style="display: flex; justify-content: flex-end; margin-top: 1rem;">
                <button type="submit" class="btn btn-primary">
                  ➕ إضافة عضو للفريق
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
