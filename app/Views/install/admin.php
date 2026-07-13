<?= $this->extend('install/layout') ?>

<?= $this->section('content') ?>

<p class="step-desc">
    تمت مزامنة الجداول بنجاح. يرجى الآن تعيين بيانات حساب المدير العام (Superadmin) الذي ستستخدمه لتسجيل الدخول وإدارة لوحة التحكم.
</p>

<?php if (session()->getFlashdata('errors')): ?>
    <div class="alert alert-danger">
        <ul style="padding-right: 1.25rem;">
            <?php foreach (session()->getFlashdata('errors') as $error): ?>
                <li><?= esc($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form action="<?= base_url('install/admin') ?>" method="POST">
    <?= csrf_field() ?>

    <div class="form-row">
        <!-- Admin Username -->
        <div class="form-group">
            <label for="username">اسم المستخدم (Username)</label>
            <input type="text" name="username" id="username" class="input-control" value="<?= old('username', 'admin') ?>" required dir="ltr">
        </div>

        <!-- Admin Email -->
        <div class="form-group">
            <label for="email">البريد الإلكتروني (Email)</label>
            <input type="email" name="email" id="email" class="input-control" value="<?= old('email') ?>" placeholder="e.g. admin@yourdomain.com" required dir="ltr">
        </div>
    </div>

    <div class="form-row">
        <!-- Admin Password -->
        <div class="form-group">
            <label for="password">كلمة المرور (Password)</label>
            <input type="password" name="password" id="password" class="input-control" placeholder="أدخل كلمة مرور قوية" required dir="ltr">
        </div>

        <!-- Confirm Password -->
        <div class="form-group">
            <label for="password_confirm">تأكيد كلمة المرور</label>
            <input type="password" name="password_confirm" id="password_confirm" class="input-control" placeholder="أعد إدخال كلمة المرور" required dir="ltr">
        </div>
    </div>

    <div class="btn-group">
        <button type="submit" class="btn btn-primary">إنشاء الحساب وإنهاء التثبيت</button>
    </div>
</form>

<?= $this->endSection() ?>
