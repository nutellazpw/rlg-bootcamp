<div class="page-head">
    <h1>Bootcamps</h1>
    <?php if (can_manage()): ?><a class="btn" href="<?= e(url('/bootcamps/new')) ?>">+ New bootcamp</a><?php endif; ?>
</div>

<div class="toolbar">
    <div class="tabs">
        <?php foreach (['current' => 'Current & upcoming', 'past' => 'Past', 'cancelled' => 'Cancelled', 'all' => 'All'] as $k => $label): ?>
            <a class="tab<?= $tab === $k ? ' active' : '' ?>" href="<?= e(url('/bootcamps', ['tab' => $k] + ($search !== '' ? ['q' => $search] : []))) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </div>
    <form method="get" action="<?= e(url('/bootcamps')) ?>" class="search">
        <input type="hidden" name="tab" value="<?= e($tab) ?>">
        <input type="search" name="q" value="<?= e($search) ?>" placeholder="Search team, hotel, room">
    </form>
</div>

<section class="card card-flush">
    <?php if (!$rows): ?>
        <p class="muted empty-sm">No bootcamps here.</p>
    <?php else: ?>
        <?php partial('bootcamps/_table', ['rows' => $rows]); ?>
    <?php endif; ?>
</section>
