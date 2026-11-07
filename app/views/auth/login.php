<h1 class="auth-title">Sign in</h1>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<form method="post" action="<?= e(url('/login')) ?>" class="form">
    <?= csrf_field() ?>
    <label class="field">
        <span>Email</span>
        <input type="email" name="email" value="<?= e($email) ?>" autocomplete="username" required autofocus>
    </label>
    <label class="field">
        <span>Password</span>
        <input type="password" name="password" autocomplete="current-password" required>
    </label>
    <label class="check">
        <input type="checkbox" name="remember" value="1" checked>
        <span>Keep me signed in on this device</span>
    </label>
    <button class="btn btn-block" type="submit">Sign in</button>
</form>
<p class="auth-link"><a href="<?= e(url('/forgot-password')) ?>">Forgot password?</a></p>
