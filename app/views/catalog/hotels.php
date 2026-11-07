<?php $edit = can_manage(); ?>
<div class="page-head"><h1>Hotels</h1></div>

<div class="cards-grid">
    <?php foreach ($rows as $h): ?>
        <section class="card<?= $h['active'] ? '' : ' card-muted' ?>">
            <?php if ($edit): ?>
                <form method="post" action="<?= e(url('/hotels/' . $h['id'])) ?>" class="form">
                    <?= csrf_field() ?>
                    <label class="field"><span>Name</span><input type="text" name="name" value="<?= e($h['name']) ?>" required></label>
                    <div class="form-grid">
                        <label class="field"><span>City</span><input type="text" name="city" value="<?= e($h['city']) ?>"></label>
                        <label class="field"><span>Address</span><input type="text" name="address" value="<?= e($h['address']) ?>"></label>
                    </div>
                    <label class="field"><span>Notes <small>(staff only — contacts at the hotel, access, parking…)</small></span><textarea name="notes" rows="2"><?= e($h['notes']) ?></textarea></label>
                    <div class="form-row-between">
                        <label class="check"><input type="checkbox" name="active" value="1"<?= checked((bool) $h['active']) ?>><span>Active</span></label>
                        <span class="muted small"><?= (int) $h['upcoming'] ?> current/upcoming bootcamps</span>
                        <button class="btn btn-sm" type="submit">Save</button>
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
            <h2 class="card-title">Add hotel</h2>
            <form method="post" action="<?= e(url('/hotels')) ?>" class="form">
                <?= csrf_field() ?>
                <label class="field"><span>Name</span><input type="text" name="name" required></label>
                <div class="form-grid">
                    <label class="field"><span>City</span><input type="text" name="city" value="Belgrade"></label>
                    <label class="field"><span>Address</span><input type="text" name="address"></label>
                </div>
                <label class="field"><span>Notes</span><textarea name="notes" rows="2"></textarea></label>
                <button class="btn btn-sm" type="submit">Add hotel</button>
            </form>
        </section>
    <?php endif; ?>
</div>
