<div class="page-head"><h1><?= e(__('Settings')) ?></h1></div>

<div class="grid-2">
    <section class="card">
        <h2 class="card-title"><?= e(__('System status')) ?></h2>
        <dl class="kv">
            <dt><?= e(__('Cron (every minute)')) ?></dt>
            <dd><?php if ($lastCron && strtotime($lastCron) > time() - 300): ?><span class="text-ok"><?= e(__('Running')) ?></span> · <?= e(__('last %s', time_ago($lastCron))) ?><?php else: ?><span class="text-warn"><?= e(__('Not running')) ?></span><?= $lastCron ? ' · ' . e(__('last %s', time_ago($lastCron))) : '' ?> — <?= e(__('reminders won\'t be sent')) ?><?php endif; ?></dd>
            <dt><?= e(__('Web push')) ?></dt>
            <dd><?= $vapidOk ? '<span class="text-ok">' . e(__('Ready')) . '</span>' : '<span class="text-danger">' . e(__('OpenSSL EC keys unavailable')) . '</span>' ?> · <?= e(__n((int) $devices, '%d device subscribed', '%d devices subscribed')) ?></dd>
            <dt><?= e(__('Telegram backup')) ?></dt>
            <dd><?= $telegramOn ? '<span class="text-ok">' . e(__('Configured')) . '</span>' : '<span class="text-warn">' . e(__('Not configured')) . '</span> — ' . e(__('set telegram.bot_token and chat_id in app/config.php')) ?></dd>
            <dt><?= e(__('Email')) ?></dt>
            <dd><?= e($mailHost) ?></dd>
        </dl>
        <div class="btn-row">
            <form method="post" action="<?= e(url('/settings')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-ghost" name="action" value="test_mail"><?= e(__('Send test email')) ?></button></form>
            <form method="post" action="<?= e(url('/settings')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-ghost" name="action" value="test_telegram"><?= e(__('Send test Telegram')) ?></button></form>
            <form method="post" action="<?= e(url('/settings')) ?>"><?= csrf_field() ?><button class="btn btn-sm btn-ghost" name="action" value="test_reminders"><?= e(__('Run cron now')) ?></button></form>
        </div>
    </section>
</div>
