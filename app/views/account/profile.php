<?php
function device_label(?string $ua): string
{
    $ua = (string) $ua;
    $os = str_contains($ua, 'iPhone') ? 'iPhone' : (str_contains($ua, 'iPad') ? 'iPad' : (str_contains($ua, 'Android') ? 'Android' : (str_contains($ua, 'Mac OS') ? 'Mac' : (str_contains($ua, 'Windows') ? 'Windows' : (str_contains($ua, 'Linux') ? 'Linux' : 'Device')))));
    $br = str_contains($ua, 'Edg/') ? 'Edge' : (str_contains($ua, 'Firefox') ? 'Firefox' : (str_contains($ua, 'Chrome') ? 'Chrome' : (str_contains($ua, 'Safari') ? 'Safari' : 'Browser')));
    return $os . ' · ' . $br;
}
?>
<div class="page-head"><h1>Profile</h1></div>

<div class="grid-2">
    <section class="card" id="notifications">
        <h2 class="card-title">Notifications on this device</h2>
        <div class="push-panel" id="push-panel">
            <p id="push-state" class="push-state">Checking…</p>
            <div class="btn-row">
                <button type="button" class="btn btn-sm" id="push-on" hidden>Enable notifications</button>
                <button type="button" class="btn btn-sm btn-ghost" id="push-test" hidden>Send test</button>
                <button type="button" class="btn btn-sm btn-ghost" id="push-off" hidden>Turn off on this device</button>
            </div>
        </div>
        <div class="howto" id="ios-howto" hidden>
            <strong>iPhone / iPad:</strong> notifications work only from the installed app.
            Open this site in <b>Safari</b> → tap <b>Share</b> → <b>Add to Home Screen</b>, open RLG Bootcamp from the home screen and enable notifications there. (iOS 16.4 or newer.)
        </div>
        <div class="howto">
            <strong>Android:</strong> open in Chrome → menu ⋮ → <b>Install app</b> / <b>Add to Home screen</b>, then enable notifications.
        </div>
        <?php if ($devices): ?>
            <h3 class="small-title">Your devices</h3>
            <div class="list list-compact">
                <?php foreach ($devices as $d): ?>
                    <div class="list-row">
                        <div class="list-main"><div class="list-title"><?= e(device_label($d['user_agent'])) ?></div><div class="list-sub">added <?= e(fmt_date($d['created_at'])) ?><?= $d['last_ok_at'] ? ' · last delivery ' . e(time_ago($d['last_ok_at'])) : '' ?></div></div>
                        <div class="list-side"><button type="button" class="btn btn-xs btn-ghost" data-remove-device="<?= (int) $d['id'] ?>">Remove</button></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <div>
        <section class="card">
            <h2 class="card-title">Your details</h2>
            <form method="post" action="<?= e(url('/profile')) ?>" class="form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="profile">
                <label class="field"><span>Name</span><input type="text" name="name" value="<?= e($u['name']) ?>" required></label>
                <label class="field"><span>Phone</span><input type="tel" name="phone" value="<?= e($u['phone']) ?>"></label>
                <label class="field"><span>Email</span><input type="email" value="<?= e($u['email']) ?>" disabled></label>
                <button class="btn btn-sm" type="submit">Save</button>
            </form>
        </section>
        <section class="card">
            <h2 class="card-title">Change password</h2>
            <form method="post" action="<?= e(url('/profile')) ?>" class="form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="password">
                <input type="email" name="username" value="<?= e($u['email']) ?>" autocomplete="username" hidden>
                <label class="field"><span>Current password</span><input type="password" name="current" autocomplete="current-password" required></label>
                <label class="field"><span>New password</span><input type="password" name="password" autocomplete="new-password" minlength="8" required></label>
                <label class="field"><span>Repeat new password</span><input type="password" name="password2" autocomplete="new-password" minlength="8" required></label>
                <button class="btn btn-sm" type="submit">Change password</button>
            </form>
        </section>
    </div>
</div>
