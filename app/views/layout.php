<?php
$me = user();
$staff = is_staff($me);
$path = '/' . trim(substr(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), strlen(base_path())), '/');
$active = function (string $prefix) use ($path): string {
    return ($path === $prefix || str_starts_with($path, rtrim($prefix, '/') . '/')) ? ' active' : '';
};
$unread = $me ? unread_notifications_count((int) $me['id']) : 0;
if ($staff) {
    $openTickets = (int) scalar("SELECT COUNT(*) FROM tickets WHERE status IN ('open','in_progress')");
    $pendingExtras = (int) scalar("SELECT COUNT(*) FROM extra_requests WHERE status = 'pending'");
} else {
    $openTickets = $me ? (int) scalar("SELECT COUNT(*) FROM tickets WHERE team_id = ? AND status IN ('open','in_progress')", [(int) $me['team_id']]) : 0;
    $pendingExtras = 0;
}
$nav = $staff
    ? [
        ['/dashboard', 'Dashboard', 0],
        ['/bootcamps', 'Bootcamps', $pendingExtras],
        ['/calendar', 'Calendar', 0],
        ['/tickets', 'Tickets', $openTickets],
        ['/teams', 'Teams', 0],
        ['/inventory', 'Inventory', 0],
    ]
    : [
        ['/my', 'My bootcamp', 0],
        ['/tickets', 'Support', $openTickets],
    ];
$setup = [];
if ($staff) {
    $setup[] = ['/hotels', 'Hotels'];
    $setup[] = ['/drinks', 'Drinks'];
    if (is_admin($me)) {
        $setup[] = ['/users', 'Staff accounts'];
        $setup[] = ['/settings', 'Settings'];
    }
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e(($title ?? '') ? $title . ' · ' : '') ?>RLG Bootcamp</title>
<meta name="theme-color" content="#0b0b0b">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<meta name="base-path" content="<?= e(base_path()) ?>">
<link rel="manifest" href="<?= e(url('manifest.webmanifest')) ?>">
<link rel="icon" type="image/png" href="<?= e(asset('img/favicon.png')) ?>">
<link rel="apple-touch-icon" href="<?= e(asset('img/icon-192.png')) ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<script>(function(){var t='light';try{t=localStorage.getItem('rlg-theme')||'light'}catch(e){}document.documentElement.setAttribute('data-theme',t);var m=document.querySelector('meta[name=theme-color]');if(m)m.content=t==='light'?'#ffffff':'#0b0b0b';})();</script>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="<?= $staff ? 'is-staff' : 'is-client' ?>">
<header class="topbar">
    <div class="topbar-inner">
        <a class="brand" href="<?= e(url('/')) ?>">
            <img class="logo-on-dark" src="<?= e(asset('img/logo.svg')) ?>" alt="Relog Media" width="150" height="34">
            <img class="logo-on-light" src="<?= e(asset('img/logo-dark.svg')) ?>" alt="Relog Media" width="150" height="34">
            <span class="brand-tag">Bootcamp</span>
        </a>
        <nav class="mainnav" id="mainnav">
            <?php foreach ($nav as [$href, $label, $count]): ?>
                <a class="nav-link<?= $active($href) ?>" href="<?= e(url($href)) ?>"><?= e($label) ?><?php if ($count): ?><span class="nav-count"><?= (int) $count ?></span><?php endif; ?></a>
            <?php endforeach; ?>
            <?php if ($setup): ?>
                <div class="nav-group">
                    <button type="button" class="nav-link nav-group-toggle<?php foreach ($setup as [$h]) { echo $active($h) ? ' active' : ''; } ?>">Setup <svg width="10" height="10" viewBox="0 0 10 10" aria-hidden="true"><path d="M1 3l4 4 4-4" stroke="currentColor" stroke-width="1.6" fill="none"/></svg></button>
                    <div class="nav-sub">
                        <?php foreach ($setup as [$href, $label]): ?>
                            <a class="<?= trim($active($href)) ?>" href="<?= e(url($href)) ?>"><?= e($label) ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            <div class="nav-mobile-extra">
                <a class="nav-link<?= $active('/profile') ?>" href="<?= e(url('/profile')) ?>">Profile &amp; notifications</a>
                <form method="post" action="<?= e(url('/logout')) ?>"><?= csrf_field() ?><button class="nav-link" type="submit">Sign out</button></form>
            </div>
        </nav>
        <div class="topbar-actions">
            <button type="button" class="icon-btn theme-toggle" data-theme-toggle title="Light / dark mode" aria-label="Switch light / dark mode">
                <svg class="theme-icon-moon" width="19" height="19" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M21 14.5A9 9 0 0 1 9.5 3a9 9 0 1 0 11.5 11.5Z"/></svg>
                <svg class="theme-icon-sun" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4.5" fill="currentColor"/><g stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 2v2.5M12 19.5V22M2 12h2.5M19.5 12H22M4.9 4.9l1.8 1.8M17.3 17.3l1.8 1.8M4.9 19.1l1.8-1.8M17.3 6.7l1.8-1.8"/></g></svg>
            </button>
            <a class="icon-btn" href="<?= e(url('/notifications')) ?>" title="Notifications" aria-label="Notifications">
                <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 22a2.5 2.5 0 0 0 2.45-2h-4.9A2.5 2.5 0 0 0 12 22Zm7-6V11a7 7 0 0 0-5.5-6.84V3a1.5 1.5 0 0 0-3 0v1.16A7 7 0 0 0 5 11v5l-2 2v1h18v-1l-2-2Z"/></svg>
                <span class="dot-count" id="notif-count"<?= $unread ? '' : ' hidden' ?>><?= $unread > 99 ? '99+' : (int) $unread ?></span>
            </a>
            <div class="usermenu">
                <button type="button" class="avatar" aria-label="Account menu"><?= e(initials($me['name'])) ?></button>
                <div class="usermenu-panel">
                    <div class="usermenu-head">
                        <strong><?= e($me['name']) ?></strong>
                        <span><?= e(ROLES[$me['role']] ?? '') ?><?php if (!$staff && $me['team_id']): ?> · <?= e((string) scalar('SELECT name FROM teams WHERE id = ?', [$me['team_id']])) ?><?php endif; ?></span>
                    </div>
                    <a href="<?= e(url('/profile')) ?>">Profile &amp; notifications</a>
                    <form method="post" action="<?= e(url('/logout')) ?>"><?= csrf_field() ?><button type="submit">Sign out</button></form>
                </div>
            </div>
            <button type="button" class="hamburger" id="hamburger" aria-label="Menu" aria-controls="mainnav"><span></span><span></span><span></span></button>
        </div>
    </div>
</header>

<main class="page">
    <?php foreach (take_flashes() as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?>" data-autohide><?= $f['message'] ?></div>
    <?php endforeach; ?>
    <?= $content ?>
</main>

<footer class="footer">
    <span>RLG Bootcamp · Relog Media</span>
</footer>

<div class="push-banner" id="push-banner" hidden>
    <div>
        <strong>Turn on notifications</strong>
        <span><?= $staff ? 'Get new tickets and reminders on this device.' : 'Get replies from Relog support on this device.' ?></span>
    </div>
    <div class="push-banner-actions">
        <button type="button" class="btn btn-sm" id="push-enable">Enable</button>
        <button type="button" class="btn btn-sm btn-ghost" id="push-dismiss">Later</button>
    </div>
</div>

<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
