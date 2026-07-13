<?= $this->extend('auth/layout') ?>

<?= $this->section('title') ?>تسجيل الدخول<?= $this->endSection() ?>

<?= $this->section('main') ?>
    <div class="auth-header">
        <div class="auth-logo">⚡</div>
        <h2 class="auth-title">Overview Insights</h2>
        <p class="auth-subtitle">سجل الدخول للوصول إلى لوحة التحليلات</p>
    </div>

    <?php if (session('error') !== null) : ?>
        <div class="alert alert-danger" role="alert"><?= session('error') ?></div>
    <?php elseif (session('errors') !== null) : ?>
        <div class="alert alert-danger" role="alert">
            <?php if (is_array(session('errors'))) : ?>
                <?php foreach (session('errors') as $error) : ?>
                    <?= $error ?><br>
                <?php endforeach ?>
            <?php else : ?>
                <?= session('errors') ?>
            <?php endif ?>
        </div>
    <?php endif ?>

    <?php if (session('message') !== null) : ?>
        <div class="alert alert-success" role="alert"><?= session('message') ?></div>
    <?php endif ?>

    <form action="<?= url_to('login') ?>" method="post">
        <?= csrf_field() ?>

        <!-- Username / Email -->
        <div class="form-group" style="margin-bottom: 1.25rem;">
            <label for="floatingLoginInput">اسم المستخدم أو البريد الإلكتروني</label>
            <input type="text" class="form-control" id="floatingLoginInput" name="login" autocomplete="username" placeholder="اسم المستخدم أو البريد الإلكتروني" value="<?= old('login') ?>" required />
        </div>

        <!-- Password -->
        <div class="form-group" style="margin-bottom: 1.25rem;">
            <label for="floatingPasswordInput">كلمة المرور</label>
            <input type="password" class="form-control" id="floatingPasswordInput" name="password" inputmode="text" autocomplete="current-password" placeholder="كلمة المرور" required />
        </div>

        <!-- Remember me -->
        <?php if (setting('Auth.sessionConfig')['allowRemembering']): ?>
            <div style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center;">
                <label class="form-check">
                    <input type="checkbox" name="remember" class="form-check-input" <?php if (old('remember')): ?>checked<?php endif ?>>
                    <span>تذكرني على هذا الجهاز</span>
                </label>
            </div>
        <?php endif; ?>

        <!-- Submit Button -->
        <button type="submit" class="btn btn-primary btn-block">تسجيل الدخول</button>
    </form>

    <!-- Social Logins -->
    <div class="divider">أو سجل الدخول عبر</div>
    <a href="<?= base_url('auth/google') ?>" class="btn-oauth">
        <img src="https://cdn.jsdelivr.net/gh/glincker/thesvg@main/public/icons/google/default.svg" alt="Google Logo" />
        <span>متابعة باستخدام Google</span>
    </a>

    <!-- Register Footer -->
    <?php if (setting('Auth.allowRegistration')) : ?>
        <div class="auth-footer">
            ليس لديك حساب؟ <a href="<?= url_to('register') ?>">أنشئ حساباً جديداً</a>
        </div>
    <?php endif ?>
<?= $this->endSection() ?>
