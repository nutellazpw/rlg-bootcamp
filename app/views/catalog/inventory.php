<?php $edit = can_manage(); $fin = can_finance(); ?>
<div class="page-head">
    <div>
        <h1>Inventory</h1>
        <p class="muted">Total quantities you own. Bookings and approved extras are checked against these numbers.</p>
    </div>
</div>

<section class="card card-flush">
    <div class="table-wrap">
    <table class="table table-edit">
        <thead>
            <tr>
                <th>Item</th>
                <th>Category</th>
                <th class="num">In stock</th>
                <th class="num" title="Booked today">Today</th>
                <th class="num" title="Highest daily usage in the next 30 days">Peak 30d</th>
                <?php if ($fin): ?><th class="num">Price / day</th><?php endif; ?>
                <th title="Clients can request it as extra equipment">Client can request</th>
                <?php if ($edit): ?><th>Active</th><th></th><?php endif; ?>
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
                        <input form="<?= $fid ?>" type="text" name="spec" value="<?= e($r['spec']) ?>" placeholder="Spec / model" class="input-sub">
                    </td>
                    <td><select form="<?= $fid ?>" name="category"><?php foreach (EQUIPMENT_CATEGORIES as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($k, $r['category']) ?>><?= e($l) ?></option><?php endforeach; ?></select></td>
                    <td class="num"><input form="<?= $fid ?>" type="number" name="total_qty" value="<?= (int) $r['total_qty'] ?>" min="0" class="input-num"></td>
                <?php else: ?>
                    <td><strong><?= e($r['name']) ?></strong><?php if ($r['spec']): ?><div class="small muted"><?= e($r['spec']) ?></div><?php endif; ?></td>
                    <td><?= e(EQUIPMENT_CATEGORIES[$r['category']] ?? $r['category']) ?></td>
                    <td class="num"><?= (int) $r['total_qty'] ?></td>
                <?php endif; ?>
                <td class="num"><?= $today ?></td>
                <td class="num<?= $over ? ' text-danger' : '' ?>"><?= $peak ?><?= $over ? ' ⚠' : '' ?></td>
                <?php if ($fin): ?>
                    <td class="num"><?php if ($edit): ?><input form="<?= $fid ?>" type="text" name="price_per_day" value="<?= e($r['price_per_day']) ?>" inputmode="decimal" class="input-num" placeholder="—"><?php else: ?><?= e(money($r['price_per_day'])) ?><?php endif; ?></td>
                <?php endif; ?>
                <td><?php if ($edit): ?><label class="switch"><input form="<?= $fid ?>" type="checkbox" name="requestable" value="1"<?= checked((bool) $r['requestable']) ?>><span></span></label><?php else: ?><?= $r['requestable'] ? 'Yes' : '—' ?><?php endif; ?></td>
                <?php if ($edit): ?>
                    <td><label class="switch"><input form="<?= $fid ?>" type="checkbox" name="active" value="1"<?= checked((bool) $r['active']) ?>><span></span></label></td>
                    <td><button form="<?= $fid ?>" class="btn btn-xs" type="submit">Save</button></td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</section>

<?php if ($edit): ?>
<section class="card">
    <h2 class="card-title">Add item</h2>
    <form method="post" action="<?= e(url('/inventory')) ?>" class="form">
        <?= csrf_field() ?>
        <div class="form-grid form-grid-4">
            <label class="field"><span>Name</span><input type="text" name="name" placeholder="e.g. Monitor 540Hz" required></label>
            <label class="field"><span>Category</span><select name="category"><?php foreach (EQUIPMENT_CATEGORIES as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select></label>
            <label class="field"><span>Spec / model</span><input type="text" name="spec" placeholder="e.g. ZOWIE XL2586X+"></label>
            <label class="field"><span>In stock</span><input type="number" name="total_qty" value="0" min="0"></label>
        </div>
        <div class="form-grid form-grid-4">
            <label class="field"><span>Price / day <small>(for extras)</small></span><input type="text" name="price_per_day" inputmode="decimal"></label>
            <label class="check check-field"><input type="checkbox" name="requestable" value="1"><span>Clients can request it as extra</span></label>
        </div>
        <button class="btn btn-sm" type="submit">Add item</button>
    </form>
</section>
<?php endif; ?>
