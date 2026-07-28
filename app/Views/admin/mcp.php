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
    <title>MCP Control Panel | لوحة تحكم الـ MCP</title>
    <meta name="description" content="لوحة التحكم الشاملة لسيرفر بروتوكول الذكاء الاصطناعي MCP ومفاتيح الأعضاء." />

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
        max-width: 1200px;
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
      .metrics-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 1rem;
      }
      .metric-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-md);
        padding: 1.25rem;
        display: flex;
        flex-direction: column;
        gap: 6px;
        position: relative;
        overflow: hidden;
      }
      .metric-card::before {
        content: '';
        position: absolute;
        top: 0;
        right: 0;
        left: 0;
        height: 3px;
        background: var(--color-primary);
      }
      .metric-label {
        font-size: 0.8rem;
        color: var(--color-text-muted);
        font-weight: 600;
      }
      .metric-value {
        font-size: 1.6rem;
        font-weight: 800;
        color: var(--color-text-main);
        display: flex;
        align-items: center;
        gap: 8px;
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
      .tools-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
        gap: 1rem;
        margin-top: 1rem;
      }
      .tool-card {
        background: var(--bg-input);
        border: 1px solid var(--border-color);
        border-radius: var(--radius-sm);
        padding: 1.25rem;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        gap: 10px;
        transition: var(--transition-all);
      }
      .tool-card.disabled {
        opacity: 0.6;
        filter: grayscale(0.4);
      }
      .tool-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 8px;
      }
      .tool-name {
        font-family: 'JetBrains Mono', monospace;
        font-weight: 700;
        font-size: 0.95rem;
        color: var(--color-primary);
        display: flex;
        align-items: center;
        gap: 6px;
      }
      .tool-badge {
        font-size: 0.7rem;
        font-weight: 700;
        padding: 2px 6px;
        border-radius: 8px;
        background: rgba(99, 102, 241, 0.15);
        color: var(--color-primary);
      }
      .tool-desc {
        font-size: 0.82rem;
        color: var(--color-text-muted);
        line-height: 1.4;
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
      .token-code {
        font-family: 'JetBrains Mono', monospace;
        font-size: 0.8rem;
        background: var(--bg-card);
        padding: 4px 8px;
        border-radius: 4px;
        border: 1px solid var(--border-color);
        display: inline-block;
        max-width: 180px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
      }
      /* Toggle switch styling */
      .switch {
        position: relative;
        display: inline-block;
        width: 44px;
        height: 24px;
      }
      .switch input {
        opacity: 0;
        width: 0;
        height: 0;
      }
      .slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #4b5563;
        transition: .3s;
        border-radius: 24px;
      }
      .slider:before {
        position: absolute;
        content: "";
        height: 18px;
        width: 18px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        transition: .3s;
        border-radius: 50%;
      }
      input:checked + .slider {
        background-color: var(--color-success);
      }
      input:checked + .slider:before {
        transform: translateX(20px);
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
      <?= $this->include('partials/sidebar', ['subtitle' => 'إدارة MCP']) ?>

      <!-- Main Area -->
      <main class="main-content">
        <!-- Top Navigation -->
        <div class="top-nav">
          <div>
            <h2 style="font-weight: 800; font-size: 1.6rem; letter-spacing: -0.01em;">
              🔌 لوحة التحكم بسيرفر بروتوكول الذكاء الاصطناعي (MCP Control Panel)
            </h2>
            <p style="color: var(--color-text-muted); font-size: 0.85rem">
              التحكم الشامل بسيرفر الـ MCP، تمكين/تعطيل الأدوات، وإدارة مفاتيح API للأعضاء.
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

          <!-- Metrics Row -->
          <div class="metrics-grid">
            <div class="metric-card">
              <span class="metric-label">حالة سيرفر MCP العام:</span>
              <div class="metric-value">
                <?php if ($globalEnabled): ?>
                  <span style="color: var(--color-success);">🟢 نشط ومفعل</span>
                <?php else: ?>
                  <span style="color: var(--color-error);">🔴 متوقف مؤقتاً</span>
                <?php endif; ?>
              </div>
              <form action="<?= base_url('admin/mcp/toggle-global') ?>" method="POST" style="margin-top: 8px;">
                <?= csrf_field() ?>
                <input type="hidden" name="status" value="<?= $globalEnabled ? '0' : '1' ?>" />
                <button type="submit" class="btn <?= $globalEnabled ? 'btn-secondary' : 'btn-success' ?>" style="width: 100%; font-size: 0.75rem; padding: 4px;">
                  <?= $globalEnabled ? '🔴 إيقاف السيرفر مؤقتاً' : '🟢 تفعيل السيرفر' ?>
                </button>
              </form>
            </div>

            <div class="metric-card">
              <span class="metric-label">مفاتيح الأعضاء النشطة:</span>
              <div class="metric-value">
                🔑 <?= $usersWithTokenCount ?> <span style="font-size:0.9rem; color:var(--color-text-muted); font-weight:normal;">/ <?= $totalUsers ?> عضو</span>
              </div>
            </div>

            <div class="metric-card">
              <span class="metric-label">الأدوات المتاحة للذكاء الاصطناعي:</span>
              <div class="metric-value">
                🎛️ <?= $enabledToolsCount ?> <span style="font-size:0.9rem; color:var(--color-text-muted); font-weight:normal;">/ <?= count($tools) ?> أداة</span>
              </div>
            </div>

            <div class="metric-card">
              <span class="metric-label">رابط السيرفر المباشر (Base Endpoint):</span>
              <div style="font-family: 'JetBrains Mono', monospace; font-size: 0.75rem; word-break: break-all; margin-top: 4px; color: var(--color-primary);">
                <?= esc($mcpEndpointUrl) ?>
              </div>
              <button type="button" class="btn btn-secondary" onclick="navigator.clipboard.writeText('<?= esc($mcpEndpointUrl, 'js') ?>'); alert('تم نسخ رابط MCP Endpoint بنجاح!');" style="margin-top: auto; font-size: 0.75rem; padding: 4px;">
                📋 نسخ الرابط
              </button>
            </div>
          </div>

          <!-- Section 1: MCP Tools Control Matrix -->
          <div class="admin-card">
            <div class="admin-card-title">
              🎛️ أدوات وقدرات الـ MCP (MCP Tools Matrix)
            </div>
            <p class="admin-card-desc">
              يمكنك التحكم بكل أداة يستدعيها الذكاء الاصطناعي secara منفردة. تعطيل أداة يخفيها مباشرة من قائمة الاستعلامات المتاحة للسيرفر.
            </p>

            <div class="tools-grid">
              <?php foreach ($tools as $tool): ?>
                <div class="tool-card <?= $tool['enabled'] ? '' : 'disabled' ?>">
                  <div class="tool-header">
                    <div>
                      <div class="tool-name">
                        ⚡ <?= esc($tool['name']) ?>
                      </div>
                      <span class="tool-badge"><?= esc($tool['badge']) ?></span>
                    </div>

                    <form action="<?= base_url('admin/mcp/toggle-tool') ?>" method="POST" id="form-tool-<?= esc($tool['name']) ?>">
                      <?= csrf_field() ?>
                      <input type="hidden" name="tool_name" value="<?= esc($tool['name']) ?>" />
                      <input type="hidden" name="status" value="<?= $tool['enabled'] ? '0' : '1' ?>" />
                      <label class="switch">
                        <input type="checkbox" <?= $tool['enabled'] ? 'checked' : '' ?> onchange="document.getElementById('form-tool-<?= esc($tool['name']) ?>').submit();" />
                        <span class="slider"></span>
                      </label>
                    </form>
                  </div>

                  <div style="font-weight: 700; font-size: 0.9rem; color: var(--color-text-main);">
                    <?= esc($tool['title']) ?>
                  </div>

                  <div class="tool-desc">
                    <?= esc($tool['description']) ?>
                  </div>

                  <div style="font-size: 0.75rem; color: var(--color-text-muted); display: flex; justify-content: space-between; align-items: center; margin-top: 4px; border-top: 1px dashed var(--border-color); padding-top: 6px;">
                    <span>الحالة: <strong><?= $tool['enabled'] ? '🟢 مفعلة' : '🔴 معطلة' ?></strong></span>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Section 2: Member API Tokens Table -->
          <div class="admin-card">
            <div class="admin-card-title">
              👥 مفاتيح API الخاصة بالأعضاء (Member API Tokens)
            </div>
            <p class="admin-card-desc">
              عرض مفاتيح الأعضاء، توليد مفاتيح جديدة للأعضاء، أو إلغاء التوكن الخاص بأي عضو لمنع وصول الذكاء الاصطناعي لحسابه.
            </p>

            <div class="table-wrapper">
              <table class="admin-table">
                <thead>
                  <tr>
                    <th>اسم العضو</th>
                    <th>البريد الإلكتروني</th>
                    <th>مساحة العمل (Workspace)</th>
                    <th>مفتاح الـ API الحالي</th>
                    <th>حالة المفتاح</th>
                    <th>رابط MCP المخصص للمستخدم</th>
                    <th>التحكم بالمفتاح</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($users as $u): ?>
                    <?php $userMcpUrl = base_url('api/mcp?token=' . ($u['api_token'] ?? '')); ?>
                    <tr>
                      <td style="font-weight:700; color:var(--color-text-main);">
                        <?= esc($u['username']) ?>
                      </td>
                      <td style="font-family:sans-serif; font-size: 0.85rem; color: var(--color-text-muted);"><?= esc($u['email']) ?></td>
                      <td style="font-weight:600; color:var(--color-primary);">
                        <?= esc($u['tenant_name'] ?: 'بدون مساحة') ?>
                      </td>
                      <td>
                        <?php if (!empty($u['api_token'])): ?>
                          <span class="token-code" title="<?= esc($u['api_token']) ?>"><?= esc($u['api_token']) ?></span>
                        <?php else: ?>
                          <span style="color:var(--color-text-muted); font-size:0.8rem; font-style:italic;">لا يوجد مفتاح</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <?php if (!empty($u['api_token'])): ?>
                          <span style="color:var(--color-success); font-weight:700; font-size:0.8rem;">🟢 مفتاح نشط</span>
                        <?php else: ?>
                          <span style="color:var(--color-text-muted); font-size:0.8rem;">⚪ غير منشأ</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <?php if (!empty($u['api_token'])): ?>
                          <button type="button" class="btn btn-secondary" style="padding: 4px 8px; font-size: 0.75rem;" onclick="navigator.clipboard.writeText('<?= esc($userMcpUrl, 'js') ?>'); alert('تم نسخ رابط Perplexity المخصص للعضو <?= esc($u['username'], 'js') ?> بنجاح! 📋');">
                            📋 نسخ الرابط
                          </button>
                        <?php else: ?>
                          <span style="color:var(--color-text-muted); font-size:0.75rem;">-</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <div style="display:flex; gap:6px;">
                          <form action="<?= base_url('admin/mcp/generate-token/' . $u['id']) ?>" method="POST">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-primary" style="padding: 4px 8px; font-size: 0.75rem;">
                              <?= !empty($u['api_token']) ? '🔄 إعادة توليد' : '✨ توليد مفتاح' ?>
                            </button>
                          </form>

                          <?php if (!empty($u['api_token'])): ?>
                            <form action="<?= base_url('admin/mcp/revoke-token/' . $u['id']) ?>" method="POST" onsubmit="return confirm('هل أنت تأكد من إلغاء مفتاح الـ API للعضو <?= esc($u['username'], 'js') ?>؟');">
                              <?= csrf_field() ?>
                              <button type="submit" class="btn btn-error" style="padding: 4px 8px; font-size: 0.75rem; background: rgba(239, 68, 68, 0.1); color: var(--color-error); border: 1px solid rgba(239, 68, 68, 0.2);">
                                🚫 إلغاء
                              </button>
                            </form>
                          <?php endif; ?>
                        </div>
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
            console.error("Error saving theme:", err);
          }
        };
      }
    </script>
  </body>
</html>
