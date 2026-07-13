<?= $this->extend('install/layout') ?>

<?= $this->section('content') ?>

<div style="text-align: center; margin-bottom: 2rem;">
    <div style="font-size: 72px; color: var(--success); margin-bottom: 1rem; animation: bounce 1s infinite alternate;">🎉</div>
    <h2 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 0.5rem; color: #ffffff;">تهانينا! اكتمل التثبيت بنجاح</h2>
    <p class="step-desc" style="margin-bottom: 1rem;">
        لقد تم تهيئة قاعدة البيانات بالكامل، وإنشاء حساب المدير العام، وضبط إعدادات البيئة بنجاح.
    </p>
</div>

<div class="alert alert-success" style="flex-direction: column; align-items: flex-start;">
    <strong>خطوة أمان هامة:</strong>
    <span>تم إنشاء ملف التثبيت <code>writable/installed.txt</code> لمنع إعادة تشغيل المعالج مرة أخرى. يرجى التأكد من عدم حذفه.</span>
</div>

<style>
    @keyframes bounce {
        from { transform: translateY(0); }
        to { transform: translateY(-10px); }
    }
</style>

<div class="btn-group">
    <a href="<?= base_url('login') ?>" class="btn btn-primary" style="width: 100%;">
        الدخول إلى لوحة التحكم وتسجيل الدخول
        <span>←</span>
    </a>
</div>

<?= $this->endSection() ?>
