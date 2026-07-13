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
              📁 إدارة مساحة العمل والفريق
            </h2>
            <p style="color: var(--color-text-muted); font-size: 0.85rem">
              تعديل اسم مساحتك المشتركة وإضافة أو إزالة أعضاء فريقك
            </p>
          </div>

          <div class="actions-group">
            <a href="<?= base_url('/') ?>" class="btn btn-secondary"
              >🏠 العودة للوحة التحكم</a
            >
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
