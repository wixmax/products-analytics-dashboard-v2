<?= $this->extend('install/layout') ?>

<?= $this->section('content') ?>

<p class="step-desc">
    تم الاتصال بقاعدة البيانات وحفظ الإعدادات بنجاح. سنقوم الآن بتهيئة الجداول والمخططات الأساسية للنظام.
</p>

<?php if (isset($error)): ?>
    <div class="alert alert-danger">
        <strong>فشلت عملية المزامنة:</strong><br>
        <?= esc($error) ?>
    </div>
    
    <div class="btn-group">
        <a href="<?= base_url('install/database') ?>" class="btn btn-secondary">تعديل الإعدادات</a>
        <a href="<?= base_url('install/migrate') ?>" class="btn btn-primary" onclick="showLoader()">إعادة المحاولة</a>
    </div>
<?php else: ?>
    <div id="ready-state">
        <div class="alert alert-success">
            قاعدة البيانات متصلة بنظام: <strong><?= esc($driver) ?></strong> على المنفذ <strong><?= esc($port) ?></strong>.
        </div>
        
        <div class="btn-group">
            <a href="<?= base_url('install/database') ?>" class="btn btn-secondary">السابق</a>
            <a href="<?= base_url('install/migrate?run=true') ?>" class="btn btn-primary" onclick="showLoader()">البدء بمزامنة وتحديث قاعدة البيانات</a>
        </div>
    </div>
<?php endif; ?>

<div id="loading-state" style="display: none;">
    <div class="spinner"></div>
    <p class="loading-text">جاري إنشاء الجداول وتثبيت قاعدة البيانات... يرجى عدم إغلاق الصفحة.</p>
</div>

<script>
    function showLoader() {
        document.getElementById('ready-state').style.display = 'none';
        if(document.querySelector('.alert-danger')) {
            document.querySelector('.alert-danger').style.display = 'none';
            document.querySelector('.btn-group').style.display = 'none';
        }
        document.getElementById('loading-state').style.display = 'block';
    }
</script>

<?= $this->endSection() ?>
