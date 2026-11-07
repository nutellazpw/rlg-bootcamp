<?php $edit = can_manage(); ?>
<div class="page-head">
    <div>
        <h1>Drinks</h1>
        <p class="muted">Teams choose from this list what they want in the room fridge. Drinks are included in the bootcamp price.</p>
    </div>
</div>
<div class="layout-side">
    <div class="col-main">
        <section class="card card-flush">
            <div class="table-wrap">
            <table class="table table-edit">
                <thead><tr><th>Drink</th><th class="num">Order</th><th class="num" title="Chosen by current/upcoming bootcamps">Chosen by</th><?php if ($edit): ?><th>Active</th><th></th><?php endif; ?></tr></thead>
                <tbody>
                <?php foreach ($rows as $d): $fid = 'dr' . (int) $d['id']; ?>
                    <tr class="<?= $d['active'] ? '' : 'row-muted' ?>">
                        <?php if ($edit): ?>
                            <td><form id="<?= $fid ?>" method="post" action="<?= e(url('/drinks/' . $d['id'])) ?>"><?= csrf_field() ?></form><input form="<?= $fid ?>" type="text" name="name" value="<?= e($d['name']) ?>" required></td>
                            <td class="num"><input form="<?= $fid ?>" type="number" name="sort" value="<?= (int) $d['sort'] ?>" class="input-num"></td>
                        <?php else: ?>
                            <td><?= e($d['name']) ?></td><td class="num"><?= (int) $d['sort'] ?></td>
                        <?php endif; ?>
                        <td class="num"><?= (int) $d['chosen'] ?></td>
                        <?php if ($edit): ?>
                            <td><label class="switch"><input form="<?= $fid ?>" type="checkbox" name="active" value="1"<?= checked((bool) $d['active']) ?>><span></span></label></td>
                            <td><button form="<?= $fid ?>" class="btn btn-xs" type="submit">Save</button></td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </section>
    </div>
    <?php if ($edit): ?>
    <aside class="col-side">
        <section class="card">
            <h2 class="card-title">Add drink</h2>
            <form method="post" action="<?= e(url('/drinks')) ?>" class="form">
                <?= csrf_field() ?>
                <label class="field"><span>Name</span><input type="text" name="name" required placeholder="e.g. Pepsi Max"></label>
                <button class="btn btn-block" type="submit">Add</button>
            </form>
        </section>
    </aside>
    <?php endif; ?>
</div>
