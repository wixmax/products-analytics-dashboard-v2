<?= $this->extend('auth/layout') ?>

<?= $this->section('title') ?>إنشاء حساب جديد<?= $this->endSection() ?>

<?= $this->section('main') ?>
    <div class="auth-header">
        <div class="auth-logo">⚡</div>
        <h2 class="auth-title">Overview Insights</h2>
        <p class="auth-subtitle">أنشئ حساباً جديداً لبدء تحليلاتك</p>
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

    <form action="<?= url_to('register') ?>" method="post">
        <?= csrf_field() ?>

        <!-- Email -->
        <div class="form-group" style="margin-bottom: 1.25rem;">
            <label for="floatingEmailInput">البريد الإلكتروني</label>
            <input type="email" class="form-control" id="floatingEmailInput" name="email" inputmode="email" autocomplete="email" placeholder="name@example.com" value="<?= old('email') ?>" required />
        </div>

        <!-- Username -->
        <div class="form-group" style="margin-bottom: 1.25rem;">
            <label for="floatingUsernameInput">اسم المستخدم</label>
            <input type="text" class="form-control" id="floatingUsernameInput" name="username" inputmode="text" autocomplete="username" placeholder="username" value="<?= old('username') ?>" required />
        </div>

        <!-- Password -->
        <div class="form-group" style="margin-bottom: 1.25rem;">
            <label for="floatingPasswordInput">كلمة المرور</label>
            <input type="password" class="form-control" id="floatingPasswordInput" name="password" inputmode="text" autocomplete="new-password" placeholder="كلمة المرور" required />
        </div>

        <!-- Password Confirm -->
        <div class="form-group" style="margin-bottom: 1.5rem;">
            <label for="floatingPasswordConfirmInput">تأكيد كلمة المرور</label>
            <input type="password" class="form-control" id="floatingPasswordConfirmInput" name="password_confirm" inputmode="text" autocomplete="new-password" placeholder="تأكيد كلمة المرور" required />
        </div>

        <!-- Submit Button -->
        <button type="submit" class="btn btn-primary btn-block">إنشاء الحساب</button>
    </form>

    <!-- Social Register -->
    <div class="divider">أو سجل حساباً عبر</div>
    <a href="<?= base_url('auth/google') ?>" class="btn-oauth">
        <img src="https://www.svgrepo.com/show/355037/google.svg" alt="Google Logo" />
        <span>التسجيل باستخدام Google</span>
    </a>

    <!-- Register Footer -->
    <div class="auth-footer">
        لديك حساب بالفعل؟ <a href="<?= url_to('login') ?>">سجل الدخول</a>
    </div>
<?= $this->endSection() ?>
