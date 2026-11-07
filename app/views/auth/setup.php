<h1 class="auth-title">First setup</h1>
<p class="muted">Create the administrator account. This page works only once, while there are no users.</p>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<form method="post" action="<?= e(url('/setup')) ?>" class="form">
    <?= csrf_field() ?>
    <label class="field"><span>Your name</span><input type="text" name="name" value="<?= e($name) ?>" required autofocus></label>
    <label class="field"><span>Email</span><input type="email" name="email" value="<?= e($email) ?>" autocomplete="username" required></label>
    <label class="field"><span>Password <small>(min. 8 characters)</small></span><input type="password" name="password" autocomplete="new-password" minlength="8" required></label>
    <button class="btn btn-block" type="submit">Create administrator</button>
</form>
