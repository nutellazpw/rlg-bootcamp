<?php if ($invalid): ?>
    <h1 class="auth-title">Link expired</h1>
    <p class="muted">This link is invalid, already used or expired. Ask for a new password reset link, or contact your Relog bootcamp manager for a new invitation.</p>
    <a class="btn btn-block" href="<?= e(url('/forgot-password')) ?>">Request a new link</a>
<?php else: ?>
    <h1 class="auth-title"><?= $row['purpose'] === 'invite' ? 'Welcome' : 'New password' ?></h1>
    <p class="muted">Set a password for <strong><?= e($row['email']) ?></strong>.</p>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="<?= e(url('/set-password')) ?>" class="form">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <input type="email" name="username" value="<?= e($row['email']) ?>" autocomplete="username" hidden>
        <label class="field">
            <span>Password <small>(min. 8 characters)</small></span>
            <input type="password" name="password" autocomplete="new-password" minlength="8" required autofocus>
        </label>
        <label class="field">
            <span>Repeat password</span>
            <input type="password" name="password2" autocomplete="new-password" minlength="8" required>
        </label>
        <button class="btn btn-block" type="submit">Save password</button>
    </form>
<?php endif; ?>
