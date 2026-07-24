<!-- Sidebar Panel -->
<aside class="sidebar">
  <div class="logo-container">
    <div class="logo-icon">⚡</div>
    <div class="logo-text">
      <h1>Overview Insights</h1>
      <p><?= esc($subtitle ?? 'لوحة الإحصائيات والتحليلات') ?></p>
    </div>
  </div>

  <!-- Sidebar Navigation Menu -->
  <nav class="sidebar-nav">
    <a href="<?= base_url('/') ?>" class="sidebar-nav-item <?= (current_url() == base_url() || current_url() == base_url('/')) ? 'active' : '' ?>">
      📊 لوحة التحكم
    </a>
    <a href="<?= base_url('saved-ads') ?>" class="sidebar-nav-item <?= strpos(current_url(), 'saved-ads') !== false ? 'active' : '' ?>">
      ⭐ الإعلانات المحفوظة
    </a>
    <a href="<?= base_url('international-products') ?>" class="sidebar-nav-item <?= strpos(current_url(), 'international-products') !== false ? 'active' : '' ?>">
      🌏 منتجات الصين واليابان
    </a>
    <?php if (auth()->loggedIn() && auth()->user()->inGroup('superadmin', 'admin')): ?>
      <a href="<?= base_url('snapshots') ?>" class="sidebar-nav-item <?= strpos(current_url(), 'snapshots') !== false ? 'active' : '' ?>">
        📸 لقطات البيانات
      </a>
    <?php endif; ?>
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
