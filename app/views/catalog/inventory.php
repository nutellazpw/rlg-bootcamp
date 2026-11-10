<?php $edit = can_manage(); $fin = can_finance(); ?>
<div class="page-head">
    <div>
        <h1><?= e(__('Inventory')) ?></h1>
        <p class="muted"><?= e(__('Total quantities you own. Bookings and approved extras are checked against these numbers.')) ?></p>
    </div>
</div>

<section class="card card-flush">
    <div class="table-wrap">
    <table class="table table-edit">
        <thead>
            <tr>
                <th><?= e(__('Item')) ?></th>
                <th><?= e(__('Category')) ?></th>
                <th class="num"><?= e(__('In stock')) ?></th>
                <th class="num" title="<?= e(__('Booked today')) ?>"><?= e(__('Today')) ?></th>
                <th class="num" title="<?= e(__('Highest daily usage in the next 30 days')) ?>"><?= e(__('Peak 30d')) ?></th>
                <?php if ($fin): ?><th class="num"><?= e(__('Price / day')) ?></th><?php endif; ?>
                <th title="<?= e(__('Clients can request it as extra equipment')) ?>"><?= e(__('Client can request')) ?></th>
                <?php if ($edit): ?><th><?= e(__('Active')) ?></th><th></th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r):
            $today = $usageToday[(int) $r['id']] ?? 0;
            $peak = $usage30[(int) $r['id']] ?? 0;
            $over = $peak > (int) $r['total_qty'];
            $fid = 'eq' . (int) $r['id'];
            ?>
            <tr class="<?= $r['active'] ? '' : 'row-muted' ?>">
                <?php if ($edit): ?>
                    <td>
                        <form id="<?= $fid ?>" method="post" action="<?= e(url('/inventory/' . $r['id'])) ?>"><?= csrf_field() ?><input type="hidden" name="sort" value="<?= (int) $r['sort'] ?>"></form>
                        <input form="<?= $fid ?>" type="text" name="name" value="<?= e($r['name']) ?>" required>
                        <input form="<?= $fid ?>" type="text" name="spec" value="<?= e($r['spec']) ?>" placeholder="<?= e(__('Spec / model')) ?>" class="input-sub">
                    </td>
                    <td><select form="<?= $fid ?>" name="category"><?php foreach (EQUIPMENT_CATEGORIES as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($k, $r['category']) ?>><?= e(__($l)) ?></option><?php endforeach; ?></select></td>
                    <td class="num"><input form="<?= $fid ?>" type="number" name="total_qty" value="<?= (int) $r['total_qty'] ?>" min="0" class="input-num"></td>
                <?php else: ?>
                    <td><strong><?= e($r['name']) ?></strong><?php if ($r['spec']): ?><div class="small muted"><?= e($r['spec']) ?></div><?php endif; ?></td>
                    <td><?= e(lbl(EQUIPMENT_CATEGORIES, $r['category'])) ?></td>
                    <td class="num"><?= (int) $r['total_qty'] ?></td>
                <?php endif; ?>
                <td class="num"><?= $today ?></td>
                <td class="num<?= $over ? ' text-danger' : '' ?>"><?= $peak ?><?= $over ? ' ⚠' : '' ?></td>
                <?php if ($fin): ?><td class="num"><?php if ($edit): ?><input form="<?= $fid ?>" type="text" name="price_per_day" value="<?= e($r['price_per_day']) ?>" inputmode="decimal" class="input-num" placeholder="—"><?php else: ?><?= $r['price_per_day'] !== null ? e(money($r['price_per_day'])) : '—' ?><?php endif; ?></td><?php endif; ?>
                <td><?php if ($edit): ?><label class="switch"><input form="<?= $fid ?>" type="checkbox" name="requestable" value="1"<?= checked((bool) $r['requestable']) ?>><span></span></label><?php else: ?><?= $r['requestable'] ? e(__('Yes')) : '—' ?><?php endif; ?></td>
                <?php if ($edit): ?>
                    <td><label class="switch"><input form="<?= $fid ?>" type="checkbox" name="active" value="1"<?= checked((bool) $r['active']) ?>><span></span></label></td>
                    <td><button form="<?= $fid ?>" class="btn btn-xs" type="submit"><?= e(__('Save')) ?></button></td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</section>

<?php if ($edit): ?>
<section class="card">
    <h2 class="card-title"><?= e(__('Add item')) ?></h2>
    <form method="post" action="<?= e(url('/inventory')) ?>" class="form">
        <?= csrf_field() ?>
        <div class="form-grid form-grid-4">
            <label class="field"><span><?= e(__('Name')) ?></span><input type="text" name="name" placeholder="<?= e(__('e.g. Monitor 540Hz')) ?>" required></label>
            <label class="field"><span><?= e(__('Category')) ?></span><select name="category"><?php foreach (EQUIPMENT_CATEGORIES as $k => $l): ?><option value="<?= e($k) ?>"><?= e(__($l)) ?></option><?php endforeach; ?></select></label>
            <label class="field"><span><?= e(__('Spec / model')) ?></span><input type="text" name="spec" placeholder="<?= e(__('e.g. ZOWIE XL2586X+')) ?>"></label>
            <label class="field"><span><?= e(__('In stock')) ?></span><input type="number" name="total_qty" value="0" min="0"></label>
        </div>
        <div class="form-grid form-grid-4">
            <label class="field"><span><?= e(__('Price / day')) ?> <small><?= e(__('(EUR, for extras)')) ?></small></span><input type="text" name="price_per_day" inputmode="decimal"></label>
            <label class="check check-field"><input type="checkbox" name="requestable" value="1"><span><?= e(__('Clients can request it as extra')) ?></span></label>
        </div>
        <button class="btn btn-sm" type="submit"><?= e(__('Add item')) ?></button>
    </form>
</section>
<?php endif; ?>
