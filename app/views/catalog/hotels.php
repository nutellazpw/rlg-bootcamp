<?php $edit = can_manage(); ?>
<div class="page-head"><h1><?= e(__('Hotels')) ?></h1></div>

<div class="cards-grid">
    <?php foreach ($rows as $h): ?>
        <section class="card<?= $h['active'] ? '' : ' card-muted' ?>">
            <?php if ($edit): ?>
                <form method="post" action="<?= e(url('/hotels/' . $h['id'])) ?>" class="form">
                    <?= csrf_field() ?>
                    <label class="field"><span><?= e(__('Name')) ?></span><input type="text" name="name" value="<?= e($h['name']) ?>" required></label>
                    <div class="form-grid">
                        <label class="field"><span><?= e(__('City')) ?></span><input type="text" name="city" value="<?= e($h['city']) ?>"></label>
                        <label class="field"><span><?= e(__('Address')) ?></span><input type="text" name="address" value="<?= e($h['address']) ?>"></label>
                    </div>
                    <label class="field"><span><?= e(__('Notes')) ?> <small><?= e(__('(staff only — contacts at the hotel, access, parking…)')) ?></small></span><textarea name="notes" rows="2"><?= e($h['notes']) ?></textarea></label>
                    <div class="form-row-between">
                        <label class="check"><input type="checkbox" name="active" value="1"<?= checked((bool) $h['active']) ?>><span><?= e(__('Active')) ?></span></label>
                        <span class="muted small"><?= e(__('%d current/upcoming bootcamps', (int) $h['upcoming'])) ?></span>
                        <button class="btn btn-sm" type="submit"><?= e(__('Save')) ?></button>
                    </div>
                </form>
            <?php else: ?>
                <h2 class="card-title"><?= e($h['name']) ?></h2>
                <p class="muted"><?= e(trim(($h['address'] ?? '') . ', ' . ($h['city'] ?? ''), ', ')) ?></p>
                <?php if ($h['notes']): ?><p class="small"><?= nl2br_e($h['notes']) ?></p><?php endif; ?>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>

    <?php if ($edit): ?>
        <section class="card card-dashed">
            <h2 class="card-title"><?= e(__('Add hotel')) ?></h2>
            <form method="post" action="<?= e(url('/hotels')) ?>" class="form">
                <?= csrf_field() ?>
                <label class="field"><span><?= e(__('Name')) ?></span><input type="text" name="name" required></label>
                <div class="form-grid">
                    <label class="field"><span><?= e(__('City')) ?></span><input type="text" name="city" value="Belgrade"></label>
                    <label class="field"><span><?= e(__('Address')) ?></span><input type="text" name="address"></label>
                </div>
                <label class="field"><span><?= e(__('Notes')) ?></span><textarea name="notes" rows="2"></textarea></label>
                <button class="btn btn-sm" type="submit"><?= e(__('Add hotel')) ?></button>
            </form>
        </section>
    <?php endif; ?>
</div>
