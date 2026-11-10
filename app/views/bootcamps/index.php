<div class="page-head">
    <h1><?= e(__('Bootcamps')) ?></h1>
    <?php if (can_manage()): ?><a class="btn" href="<?= e(url('/bootcamps/new')) ?>"><?= e(__('+ New bootcamp')) ?></a><?php endif; ?>
</div>

<div class="toolbar">
    <div class="tabs">
        <?php foreach (['current' => __('Current & upcoming'), 'past' => __('Past'), 'cancelled' => __('Cancelled'), 'all' => __('All')] as $k => $label): ?>
            <a class="tab<?= $tab === $k ? ' active' : '' ?>" href="<?= e(url('/bootcamps', ['tab' => $k] + ($search !== '' ? ['q' => $search] : []))) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </div>
    <form method="get" action="<?= e(url('/bootcamps')) ?>" class="search">
        <input type="hidden" name="tab" value="<?= e($tab) ?>">
        <input type="search" name="q" value="<?= e($search) ?>" placeholder="<?= e(__('Search team, hotel, room')) ?>">
    </form>
</div>

<section class="card card-flush">
    <?php if (!$rows): ?>
        <p class="muted empty-sm"><?= e(__('No bootcamps here.')) ?></p>
    <?php else: ?>
        <?php partial('bootcamps/_table', ['rows' => $rows]); ?>
    <?php endif; ?>
</section>
