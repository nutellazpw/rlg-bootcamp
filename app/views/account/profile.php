<?php
function device_label(?string $ua): string
{
    $ua = (string) $ua;
    $os = str_contains($ua, 'iPhone') ? 'iPhone' : (str_contains($ua, 'iPad') ? 'iPad' : (str_contains($ua, 'Android') ? 'Android' : (str_contains($ua, 'Mac OS') ? 'Mac' : (str_contains($ua, 'Windows') ? 'Windows' : (str_contains($ua, 'Linux') ? 'Linux' : __('Device'))))));
    $br = str_contains($ua, 'Edg/') ? 'Edge' : (str_contains($ua, 'Firefox') ? 'Firefox' : (str_contains($ua, 'Chrome') ? 'Chrome' : (str_contains($ua, 'Safari') ? 'Safari' : __('Browser'))));
    return $os . ' · ' . $br;
}
?>
<div class="page-head"><h1><?= e(__('Profile')) ?></h1></div>

<div class="grid-2">
    <section class="card" id="notifications">
        <h2 class="card-title"><?= e(__('Notifications on this device')) ?></h2>
        <div class="push-panel" id="push-panel">
            <p id="push-state" class="push-state"><?= e(__('Checking…')) ?></p>
            <div class="btn-row">
                <button type="button" class="btn btn-sm" id="push-on" hidden><?= e(__('Enable notifications')) ?></button>
                <button type="button" class="btn btn-sm btn-ghost" id="push-test" hidden><?= e(__('Send test')) ?></button>
                <button type="button" class="btn btn-sm btn-ghost" id="push-off" hidden><?= e(__('Turn off on this device')) ?></button>
            </div>
        </div>
        <div class="howto" id="ios-howto" hidden>
            <strong><?= e(__('iPhone / iPad:')) ?></strong> <?= __('notifications work only from the installed app. Open this site in <b>Safari</b> → tap <b>Share</b> → <b>Add to Home Screen</b>, open RLG Bootcamp from the home screen and enable notifications there. (iOS 16.4 or newer.)') ?>
        </div>
        <div class="howto">
            <strong><?= e(__('Android:')) ?></strong> <?= __('open in Chrome → menu ⋮ → <b>Install app</b> / <b>Add to Home screen</b>, then enable notifications.') ?>
        </div>
        <?php if ($devices): ?>
            <h3 class="small-title"><?= e(__('Your devices')) ?></h3>
            <div class="list list-compact">
                <?php foreach ($devices as $d): ?>
                    <div class="list-row">
                        <div class="list-main"><div class="list-title"><?= e(device_label($d['user_agent'])) ?></div><div class="list-sub"><?= e(__('added %s', fmt_date($d['created_at']))) ?><?= $d['last_ok_at'] ? ' · ' . e(__('last delivery %s', time_ago($d['last_ok_at']))) : '' ?></div></div>
                        <div class="list-side"><button type="button" class="btn btn-xs btn-ghost" data-remove-device="<?= (int) $d['id'] ?>"><?= e(__('Remove')) ?></button></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <div>
        <section class="card">
            <h2 class="card-title"><?= e(__('Picture')) ?></h2>
            <?php if (is_staff($u)): ?>
                <div class="logo-field">
                    <?= avatar_html($u, 'lg') ?>
                    <form method="post" action="<?= e(url('/users/' . $u['id'] . '/avatar')) ?>" enctype="multipart/form-data" class="logo-field">
                        <?= csrf_field() ?>
                        <label class="btn btn-sm btn-ghost file-btn"><?= e(__('Upload picture')) ?><input type="file" name="avatar" accept="image/png,image/jpeg,image/webp,image/gif" onchange="this.form.submit()"></label>
                        <?php if ($u['avatar']): ?><button class="btn btn-sm btn-ghost" name="remove" value="1"><?= e(__('Remove')) ?></button><?php endif; ?>
                    </form>
                </div>
            <?php else: ?>
                <div class="logo-field">
                    <?= avatar_html($u, 'lg') ?>
                    <p class="muted small"><?= e(__('Your picture is your team logo. Ask your Relog bootcamp manager to change it.')) ?></p>
                </div>
            <?php endif; ?>
        </section>
        <section class="card">
            <h2 class="card-title"><?= e(__('Your details')) ?></h2>
            <form method="post" action="<?= e(url('/profile')) ?>" class="form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="profile">
                <label class="field"><span><?= e(__('Name')) ?></span><input type="text" name="name" value="<?= e($u['name']) ?>" required></label>
                <label class="field"><span><?= e(__('Phone')) ?></span><input type="tel" name="phone" value="<?= e($u['phone']) ?>"></label>
                <label class="field"><span><?= e(__('Email')) ?></span><input type="email" value="<?= e($u['email']) ?>" disabled></label>
                <?php if (is_staff($u)): ?>
                    <label class="field"><span><?= e(__('Language')) ?></span>
                        <select name="lang">
                            <?php foreach (LANGUAGES as $code => $name): ?><option value="<?= e($code) ?>"<?= selected(user_locale($u), $code) ?>><?= e($name) ?></option><?php endforeach; ?>
                        </select>
                    </label>
                <?php endif; ?>
                <button class="btn btn-sm" type="submit"><?= e(__('Save')) ?></button>
            </form>
        </section>
        <section class="card">
            <h2 class="card-title"><?= e(__('Change password')) ?></h2>
            <form method="post" action="<?= e(url('/profile')) ?>" class="form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="password">
                <input type="email" name="username" value="<?= e($u['email']) ?>" autocomplete="username" hidden>
                <label class="field"><span><?= e(__('Current password')) ?></span><input type="password" name="current" autocomplete="current-password" required></label>
                <label class="field"><span><?= e(__('New password')) ?></span><input type="password" name="password" autocomplete="new-password" minlength="8" required></label>
                <label class="field"><span><?= e(__('Repeat new password')) ?></span><input type="password" name="password2" autocomplete="new-password" minlength="8" required></label>
                <button class="btn btn-sm" type="submit"><?= e(__('Change password')) ?></button>
            </form>
        </section>
    </div>
</div>
