<?php
$done = count(array_filter($items, fn ($i) => $i['done_at']));
$total = count($items);
$pct = $total ? (int) round($done / $total * 100) : 0;
?>
<div class="checklist" data-checklist="<?= e($phase) ?>">
    <div class="checklist-progress">
        <div class="progress progress-wide"><i style="width:<?= $pct ?>%"></i></div>
        <span class="small"><strong data-progress-text><?= $done ?>/<?= $total ?></strong> done</span>
    </div>
    <ul class="check-list">
        <?php foreach ($items as $it): ?>
            <li class="check-item<?= $it['done_at'] ? ' done' : '' ?>">
                <form method="post" action="<?= e(url('/checklist/' . $it['id'] . '/toggle')) ?>" class="check-toggle-form">
                    <?= csrf_field() ?>
                    <button type="submit" class="check-box" aria-pressed="<?= $it['done_at'] ? 'true' : 'false' ?>" aria-label="Toggle <?= e($it['label']) ?>"></button>
                </form>
                <div class="check-text">
                    <span class="check-label"><?= e($it['label']) ?></span>
                    <?php if ($it['detail']): ?><span class="check-detail"><?= e($it['detail']) ?></span><?php endif; ?>
                    <span class="check-by"><?= $it['done_at'] ? e(($it['done_name'] ?? '—') . ' · ' . date('d M H:i', strtotime($it['done_at']))) : '' ?></span>
                </div>
                <?php if ($it['item_key'] === null): ?>
                    <form method="post" action="<?= e(url('/checklist/' . $it['id'] . '/delete')) ?>" data-confirm="Remove this item?"><?= csrf_field() ?><button class="btn-icon" title="Remove" aria-label="Remove">×</button></form>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
    <form method="post" action="<?= e(url('/bootcamps/' . $b['id'] . '/checklist')) ?>" class="check-add">
        <?= csrf_field() ?>
        <input type="hidden" name="phase" value="<?= e($phase) ?>">
        <input type="text" name="label" maxlength="190" placeholder="Add an item…" required>
        <button class="btn btn-xs btn-ghost" type="submit">Add</button>
    </form>
</div>
