<?= $this->extend('install/layout') ?>

<?= $this->section('content') ?>

<p class="step-desc">
    يرجى إدخال بيانات الاتصال بقاعدة البيانات الخاصة بك. سيقوم المعالج باختبار الاتصال وحفظ الإعدادات تلقائياً في ملف <code>.env</code>.
</p>

<?php if (session()->getFlashdata('error')): ?>
    <div class="alert alert-danger">
        <?= session()->getFlashdata('error') ?>
    </div>
<?php endif; ?>

<form action="<?= base_url('install/database') ?>" method="POST">
    <?= csrf_field() ?>

    <div class="form-row">
        <!-- DB Driver -->
        <div class="form-group">
            <label for="driver">نوع قاعدة البيانات (Driver)</label>
            <select name="driver" id="driver" class="input-control" required>
                <option value="Postgre" <?= old('driver', 'Postgre') === 'Postgre' ? 'selected' : '' ?>>PostgreSQL</option>
                <option value="MySQLi" <?= old('driver') === 'MySQLi' ? 'selected' : '' ?>>MySQL / MariaDB</option>
            </select>
        </div>

        <!-- DB Host -->
        <div class="form-group">
            <label for="hostname">مضيف قاعدة البيانات (Host)</label>
            <input type="text" name="hostname" id="hostname" class="input-control" value="<?= old('hostname', 'localhost') ?>" required>
        </div>
    </div>

    <div class="form-row">
        <!-- DB Port -->
        <div class="form-group">
            <label for="port">المنفذ (Port)</label>
            <input type="text" name="port" id="port" class="input-control" value="<?= old('port', '5432') ?>" placeholder="PostgreSQL: 5432, MySQL: 3306" required>
        </div>

        <!-- DB Name -->
        <div class="form-group">
            <label for="database">اسم قاعدة البيانات (Database Name)</label>
            <input type="text" name="database" id="database" class="input-control" value="<?= old('database') ?>" placeholder="e.g. products_dashboard" required dir="ltr">
        </div>
    </div>

    <div class="form-row">
        <!-- DB Username -->
        <div class="form-group">
            <label for="username">اسم المستخدم (Username)</label>
            <input type="text" name="username" id="username" class="input-control" value="<?= old('username') ?>" placeholder="e.g. postgres" required dir="ltr">
        </div>

        <!-- DB Password -->
        <div class="form-group">
            <label for="password">كلمة المرور (Password)</label>
            <input type="password" name="password" id="password" class="input-control" placeholder="أدخل كلمة المرور" dir="ltr">
        </div>
    </div>

    <div class="btn-group">
        <a href="<?= base_url('install') ?>" class="btn btn-secondary">السابق</a>
        <button type="submit" class="btn btn-primary">اختيار وحفظ البيانات</button>
    </div>
</form>

<script>
    // Automatically change port based on selected driver
    document.getElementById('driver').addEventListener('change', function() {
        const portInput = document.getElementById('port');
        if (this.value === 'Postgre') {
            portInput.value = '5432';
        } else if (this.value === 'MySQLi') {
            portInput.value = '3306';
        }
    });
</script>

<?= $this->endSection() ?>
