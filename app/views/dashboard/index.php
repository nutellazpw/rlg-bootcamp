<div class="page-head">
    <div>
        <h1>Dashboard</h1>
        <p class="muted"><?= e(date('l, d F Y')) ?></p>
    </div>
    <?php if (can_manage()): ?>
        <a class="btn" href="<?= e(url('/bootcamps/new')) ?>">+ New bootcamp</a>
    <?php endif; ?>
</div>

<div class="stats">
    <div class="stat"><span class="stat-num"><?= count($active) ?></span><span class="stat-label">Bootcamps now</span></div>
    <div class="stat"><span class="stat-num"><?= count($upcoming) ?></span><span class="stat-label">Next 30 days</span></div>
    <div class="stat<?= $tickets ? ' stat-hot' : '' ?>"><span class="stat-num"><?= count($tickets) ?></span><span class="stat-label">Open tickets</span></div>
    <div class="stat<?= array_filter($extras, fn ($x) => $x['status'] === 'pending') ? ' stat-hot' : '' ?>"><span class="stat-num"><?= count(array_filter($extras, fn ($x) => $x['status'] === 'pending')) ?></span><span class="stat-label">Extra requests to approve</span></div>
</div>

<?php if ($warnings): ?>
    <div class="alert alert-warning">
        <strong>Inventory overbooked in the next 60 days</strong>
        <ul class="plain"><?php foreach ($warnings as $w): ?><li><?= e($w) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>
<?php if ($missingRoom): ?>
    <div class="alert alert-info">
        <strong>Room not assigned yet</strong>
        <ul class="plain"><?php foreach ($missingRoom as $b): ?>
            <li><a href="<?= e(url('/bootcamps/' . $b['id'])) ?>"><?= e($b['team_name']) ?></a> — <?= e($b['hotel_name']) ?>, starts <?= e(fmt_date($b['start_date'])) ?></li>
        <?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="grid-2">
    <section class="card">
        <div class="card-head"><h2>Open tickets</h2><a class="link" href="<?= e(url('/tickets')) ?>">All tickets →</a></div>
        <?php if (!$tickets): ?>
            <p class="muted empty-sm">No open tickets. 🎉</p>
        <?php else: ?>
            <div class="list">
                <?php foreach ($tickets as $t): ?>
                    <a class="list-row" href="<?= e(url('/tickets/' . $t['id'])) ?>">
                        <div class="list-main">
                            <div class="list-title">#<?= (int) $t['id'] ?> · <?= e($t['subject']) ?></div>
                            <div class="list-sub"><?= e($t['team_name']) ?> · <?= e(TICKET_CATEGORIES[$t['category']] ?? '') ?><?= $t['assignee_name'] ? ' · ' . e($t['assignee_name']) : '' ?></div>
                        </div>
                        <div class="list-side">
                            <?php if ($t['awaiting_staff_since']): ?>
                                <span class="badge badge-waiting">Waiting <?= e(str_replace(' ago', '', time_ago($t['awaiting_staff_since']))) ?></span>
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
        <div class="card-head"><h2>Extra equipment</h2></div>
        <?php if (!$extras): ?>
            <p class="muted empty-sm">No pending requests.</p>
        <?php else: ?>
            <div class="list">
                <?php foreach ($extras as $x): ?>
                    <a class="list-row" href="<?= e(url('/bootcamps/' . $x['bootcamp_id'] . '#extras')) ?>">
                        <div class="list-main">
                            <div class="list-title"><?= (int) $x['qty'] ?>× <?= e($x['equipment_name']) ?></div>
                            <div class="list-sub"><?= e($x['team_name']) ?> · <?= e(time_ago($x['requested_at'])) ?></div>
                        </div>
                        <div class="list-side">
                            <?php if ($x['status'] === 'pending'): ?>
                                <span class="badge badge-pending">Needs approval</span>
                            <?php else: ?>
                                <span class="badge badge-ready">To deliver</span>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<section class="card">
    <div class="card-head"><h2>Running now</h2><a class="link" href="<?= e(url('/calendar')) ?>">Calendar →</a></div>
    <?php if (!$active): ?>
        <p class="muted empty-sm">No bootcamps today.</p>
    <?php else: ?>
        <?php partial('bootcamps/_table', ['rows' => $active]); ?>
    <?php endif; ?>
</section>

<section class="card">
    <div class="card-head"><h2>Coming up · 30 days</h2><a class="link" href="<?= e(url('/bootcamps')) ?>">All bootcamps →</a></div>
    <?php if (!$upcoming): ?>
        <p class="muted empty-sm">Nothing booked yet.</p>
    <?php else: ?>
        <?php partial('bootcamps/_table', ['rows' => $upcoming]); ?>
    <?php endif; ?>
</section>
