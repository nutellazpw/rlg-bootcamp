<div class="page-head">
    <h1><?= e(__('Notifications')) ?></h1>
    <?php if (array_filter($rows, fn ($n) => !$n['read_at'])): ?>
        <form method="post" action="<?= e(url('/notifications/read-all')) ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-sm"><?= e(__('Mark all as read')) ?></button></form>
    <?php endif; ?>
</div>
<section class="card card-flush">
    <?php if (!$rows): ?>
        <p class="muted empty-sm"><?= e(__('No notifications yet.')) ?></p>
    <?php else: ?>
        <div class="list">
            <?php foreach ($rows as $n): ?>
                <a class="list-row<?= $n['read_at'] ? '' : ' unread' ?>" href="<?= e(url('/n/' . $n['id'])) ?>">
                    <div class="list-main">
                        <div class="list-title"><?= e($n['title']) ?></div>
                        <?php if ($n['body']): ?><div class="list-sub"><?= e($n['body']) ?></div><?php endif; ?>
                    </div>
                    <div class="list-side"><span class="small muted"><?= e(time_ago($n['created_at'])) ?></span></div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
