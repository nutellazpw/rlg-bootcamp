<?php $edit = can_manage(); ?>
<div class="page-head">
    <div>
        <a class="back" href="<?= e(url('/requests', ['tab' => 'activities'])) ?>">← Requests</a>
        <h1>Activities</h1>
        <p class="muted">What teams can request on their bootcamp page. Change the picture and text any time.</p>
    </div>
</div>

<div class="cards-grid">
    <?php foreach ($rows as $a): ?>
        <section class="card activity-edit<?= $a['active'] ? '' : ' card-muted' ?>">
            <img class="activity-img" src="<?= e(activity_image_url($a)) ?>" alt="">
            <?php if ($edit): ?>
                <form method="post" action="<?= e(url('/activities/' . $a['id'])) ?>" class="form" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <label class="field"><span>Name</span><input type="text" name="name" value="<?= e($a['name']) ?>" required></label>
                    <label class="field"><span>Description</span><textarea name="description" rows="4"><?= e($a['description']) ?></textarea></label>
                    <div class="logo-field">
                        <label class="btn btn-xs btn-ghost file-btn">Change picture<input type="file" name="image" accept="image/png,image/jpeg,image/webp,image/gif"></label>
                        <?php if ($a['image']): ?><label class="check"><input type="checkbox" name="remove_image" value="1"><span>Use default picture</span></label><?php endif; ?>
                    </div>
                    <div class="form-row-between">
                        <label class="check"><input type="checkbox" name="active" value="1"<?= checked((bool) $a['active']) ?>><span>Visible to teams</span></label>
                        <label class="field field-inline"><span>Order</span><input type="number" name="sort" value="<?= (int) $a['sort'] ?>" class="input-num"></label>
                        <button class="btn btn-sm" type="submit">Save</button>
                    </div>
                </form>
            <?php else: ?>
                <h2 class="card-title"><?= e($a['name']) ?></h2>
                <p class="small"><?= nl2br_e($a['description']) ?></p>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
    <?php if ($edit): ?>
        <section class="card card-dashed">
            <h2 class="card-title">Add activity</h2>
            <form method="post" action="<?= e(url('/activities')) ?>" class="form" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <label class="field"><span>Name</span><input type="text" name="name" required></label>
                <label class="field"><span>Description</span><textarea name="description" rows="4"></textarea></label>
                <label class="btn btn-xs btn-ghost file-btn">Picture (optional)<input type="file" name="image" accept="image/png,image/jpeg,image/webp,image/gif"></label>
                <div><button class="btn btn-sm" type="submit">Add</button></div>
            </form>
        </section>
    <?php endif; ?>
</div>
