<h1 class="auth-title">Forgot password</h1>
<?php if ($sent): ?>
    <div class="alert alert-success">If an account exists for that email, we have sent a link to reset the password. Check your inbox and spam folder.</div>
    <p class="auth-link"><a href="<?= e(url('/login')) ?>">Back to sign in</a></p>
<?php else: ?>
    <p class="muted">Enter the email of your account and we will send you a reset link.</p>
    <form method="post" action="<?= e(url('/forgot-password')) ?>" class="form">
        <?= csrf_field() ?>
        <label class="field">
            <span>Email</span>
            <input type="email" name="email" required autofocus>
        </label>
        <button class="btn btn-block" type="submit">Send reset link</button>
    </form>
    <p class="auth-link"><a href="<?= e(url('/login')) ?>">Back to sign in</a></p>
<?php endif; ?>
