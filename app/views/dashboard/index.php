<div class="page-head">
    <div>
        <h1><?= e(__('Dashboard')) ?></h1>
        <p class="muted"><?= e(mb_ucfirst(ldate('l, d F Y', time()))) ?></p>
    </div>
    <?php if (can_manage()): ?>
        <a class="btn" href="<?= e(url('/bootcamps/new')) ?>"><?= e(__('+ New bootcamp')) ?></a>
    <?php endif; ?>
</div>

<div class="stats">
    <div class="stat"><span class="stat-num"><?= count($active) ?></span><span class="stat-label"><?= e(__('Bootcamps now')) ?></span></div>
    <div class="stat"><span class="stat-num"><?= count($upcoming) ?></span><span class="stat-label"><?= e(__('Next 30 days')) ?></span></div>
    <div class="stat<?= $tickets ? ' stat-hot' : '' ?>"><span class="stat-num"><?= count($tickets) ?></span><span class="stat-label"><?= e(__('Open tickets')) ?></span></div>
    <div class="stat<?= array_filter($extras, fn ($x) => $x['status'] === 'pending') ? ' stat-hot' : '' ?>"><span class="stat-num"><?= count(array_filter($extras, fn ($x) => $x['status'] === 'pending')) ?></span><span class="stat-label"><?= e(__('Extra requests to approve')) ?></span></div>
</div>

<?php if ($requests): $rq = ($requests['quotes'] ?? 0); $ra = ($requests['activities'] ?? 0); ?>
    <a class="card needs-action requests-banner" href="<?= e(url('/requests' . ($rq ? '' : '?tab=activities'))) ?>">
        <strong><?= e(__('Requests waiting:')) ?></strong>
        <?php if ($rq): ?><span class="action-tag"><?= e(__n($rq, '%d bootcamp request', '%d bootcamp requests')) ?></span><?php endif; ?>
        <?php if ($ra): ?><span class="action-tag"><?= e(__n($ra, '%d activity request', '%d activity requests')) ?></span><?php endif; ?>
        <span class="link"><?= e(__('Open →')) ?></span>
    </a>
<?php endif; ?>

<?php if ($warnings): ?>
    <div class="alert alert-warning">
        <strong><?= e(__('Inventory overbooked in the next 60 days')) ?></strong>
        <ul class="plain"><?php foreach ($warnings as $w): ?><li><?= e($w) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>
<?php if ($missingRoom): ?>
    <div class="alert alert-info">
        <strong><?= e(__('Room not assigned yet')) ?></strong>
        <ul class="plain"><?php foreach ($missingRoom as $b): ?>
            <li><a href="<?= e(url('/bootcamps/' . $b['id'])) ?>"><?= e($b['team_name']) ?></a> — <?= e($b['hotel_name']) ?>, <?= e(__('starts %s', fmt_date($b['start_date']))) ?></li>
        <?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="grid-2">
    <section class="card">
        <div class="card-head"><h2><?= e(__('Open tickets')) ?></h2><a class="link" href="<?= e(url('/tickets')) ?>"><?= e(__('All tickets →')) ?></a></div>
        <?php if (!$tickets): ?>
            <p class="muted empty-sm"><?= e(__('No open tickets. 🎉')) ?></p>
        <?php else: ?>
            <div class="list">
                <?php foreach ($tickets as $t): ?>
                    <a class="list-row<?= $t['awaiting_staff_since'] ? ' needs-action' : '' ?>" href="<?= e(url('/tickets/' . $t['id'])) ?>">
                        <div class="list-main">
                            <div class="list-title">#<?= (int) $t['id'] ?> · <?= e($t['subject']) ?></div>
                            <div class="list-sub"><?= e($t['team_name']) ?> · <?= e(lbl(TICKET_CATEGORIES, $t['category'])) ?><?= $t['assignee_name'] ? ' · ' . e($t['assignee_name']) : '' ?></div>
                        </div>
                        <div class="list-side">
                            <?php if ($t['awaiting_staff_since']): ?>
                                <span class="badge badge-waiting"><?= e(__('Waiting %s', waiting_for($t['awaiting_staff_since']))) ?></span>
                            <?php else: ?>
                                <?= status_badge($t['status'], TICKET_STATUSES) ?>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="card">
        <div class="card-head"><h2><?= e(__('Extra equipment')) ?></h2></div>
        <?php if (!$extras): ?>
            <p class="muted empty-sm"><?= e(__('No pending requests.')) ?></p>
        <?php else: ?>
            <div class="list">
                <?php foreach ($extras as $x): ?>
                    <a class="list-row<?= $x['status'] === 'pending' || !$x['delivery_by'] ? ' needs-action' : '' ?>" href="<?= e(url('/bootcamps/' . $x['bootcamp_id'] . '#extras')) ?>">
                        <div class="list-main">
                            <div class="list-title"><?= (int) $x['qty'] ?>× <?= e($x['equipment_name']) ?></div>
                            <div class="list-sub"><?= e($x['team_name']) ?> · <?= e(time_ago($x['requested_at'])) ?></div>
                        </div>
                        <div class="list-side">
                            <?php if ($x['status'] === 'pending'): ?>
                                <span class="action-tag"><?= e(__('Needs approval')) ?></span>
                            <?php elseif (!$x['delivery_by']): ?>
                                <span class="action-tag"><?= e(__('Who delivers?')) ?></span>
                            <?php else: ?>
                                <span class="badge badge-ready">🚚 <?= e($x['delivery_name'] ?? '') ?></span>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<section class="card">
    <div class="card-head"><h2><?= e(__('Running now')) ?></h2><a class="link" href="<?= e(url('/calendar')) ?>"><?= e(__('Calendar →')) ?></a></div>
    <?php if (!$active): ?>
        <p class="muted empty-sm"><?= e(__('No bootcamps today.')) ?></p>
    <?php else: ?>
        <?php partial('bootcamps/_table', ['rows' => $active]); ?>
    <?php endif; ?>
</section>

<section class="card">
    <div class="card-head"><h2><?= e(__('Coming up · 30 days')) ?></h2><a class="link" href="<?= e(url('/bootcamps')) ?>"><?= e(__('All bootcamps →')) ?></a></div>
    <?php if (!$upcoming): ?>
        <p class="muted empty-sm"><?= e(__('Nothing booked yet.')) ?></p>
    <?php else: ?>
        <?php partial('bootcamps/_table', ['rows' => $upcoming]); ?>
    <?php endif; ?>
</section>
