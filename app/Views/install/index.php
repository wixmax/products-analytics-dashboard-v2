<?= $this->extend('install/layout') ?>

<?= $this->section('content') ?>

<p class="step-desc">
    مرحباً بك في معالج تثبيت لوحة تحكم تحليلات المنتجات. سنقوم الآن بفحص توافق خادمك مع متطلبات تشغيل النظام قبل البدء بالإعداد.
</p>

<ul class="req-list">
    <!-- PHP Version -->
    <li class="req-item">
        <span class="req-name">اصدار PHP (>= 8.1)</span>
        <span class="req-status <?= $php_ok ? 'status-ok' : 'status-fail' ?>">
            <?= $php_version ?> <?= $php_ok ? '✓' : '✗' ?>
        </span>
    </li>
    
    <!-- Writeable Folder -->
    <li class="req-item">
        <span class="req-name">صلاحية الكتابة لمجلد writable/</span>
        <span class="req-status <?= $writable_ok ? 'status-ok' : 'status-fail' ?>">
            <?= $writable_ok ? 'مكتوب' : 'غير مكتوب' ?> <?= $writable_ok ? '✓' : '✗' ?>
        </span>
    </li>

    <!-- Extensions Check -->
    <?php foreach ($extensions as $ext => $loaded): ?>
    <li class="req-item">
        <span class="req-name">إضافة PHP: <code><?= $ext ?></code></span>
        <span class="req-status <?= $loaded ? 'status-ok' : 'status-fail' ?>">
            <?= $loaded ? 'مفعّلة' : 'غير متوفرة' ?> <?= $loaded ? '✓' : '✗' ?>
        </span>
    </li>
    <?php endforeach; ?>
</ul>

<?php if (!$requirements_met): ?>
    <div class="alert alert-danger">
        <strong>عذراً!</strong> بعض متطلبات التشغيل الأساسية لم يتم استيفاؤها. يرجى تهيئة السيرفر وحل المشاكل قبل المتابعة.
    </div>
<?php endif; ?>

<div class="btn-group">
    <?php if ($requirements_met): ?>
        <a href="<?= base_url('install/database') ?>" class="btn btn-primary">
            المتابعة لإعداد قاعدة البيانات
            <span>←</span>
        </a>
    <?php else: ?>
        <a href="<?= base_url('install') ?>" class="btn btn-secondary">إعادة الفحص</a>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
