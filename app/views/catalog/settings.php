<div class="page-head"><h1>Settings</h1></div>

<div class="grid-2">
    <section class="card">
        <h2 class="card-title">System status</h2>
        <dl class="kv">
            <dt>Cron (every minute)</dt>
            <dd><?php if ($lastCron && strtotime($lastCron) > time() - 300): ?><span class="text-ok">Running</span> · last <?= e(time_ago($lastCron)) ?><?php else: ?><span class="text-warn">Not running</span><?= $lastCron ? ' · last ' . e(time_ago($lastCron)) : '' ?> — reminders won't be sent<?php endif; ?></dd>
            <dt>Web push</dt>
            <dd><?= $vapidOk ? '<span class="text-ok">Ready</span>' : '<span class="text-danger">OpenSSL EC keys unavailable</span>' ?> · <?= (int) $devices ?> devices subscribed</dd>
            <dt>Telegram backup</dt>
            <dd><?= $telegramOn ? '<span class="text-ok">Configured</span>' : '<span class="text-warn">Not configured</span> — set telegram.bot_token and chat_id in app/config.php' ?></dd>
            <dt>Email</dt>
            <dd><?= e($mailHost) ?></dd>
        </dl>
        <div class="btn-row">
            <form method="post" action="<?= e(url('/settings')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-ghost" name="action" value="test_mail">Send test email</button></form>
            <form method="post" action="<?= e(url('/settings')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-ghost" name="action" value="test_telegram">Send test Telegram</button></form>
            <form method="post" action="<?= e(url('/settings')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-ghost" name="action" value="test_reminders">Run cron now</button></form>
        </div>
    </section>
</div>
