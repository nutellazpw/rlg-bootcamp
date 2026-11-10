<div class="page-head">
    <div>
        <a class="back" href="<?= e($staff && $b ? url('/bootcamps/' . $b['id']) : url('/tickets')) ?>"><?= e(__('← Back')) ?></a>
        <h1><?= $staff ? e(__('Message %s', $b['team_name'])) : 'New ticket' ?></h1>
        <?php if ($b): ?><p class="muted"><?= e($b['hotel_name']) ?><?= $b['room'] ? ' · ' . e(__('room %s', $b['room'])) : '' ?> · <?= e(fmt_range($b['start_date'], $b['end_date'])) ?></p><?php endif; ?>
    </div>
</div>
<?php if ($errors): ?><div class="alert alert-error"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div><?php endif; ?>

<form method="post" action="<?= e(url('/tickets/new')) ?>" class="form card" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <?php if ($b): ?><input type="hidden" name="bootcamp" value="<?= (int) $b['id'] ?>"><?php endif; ?>
    <?php if (!$staff): ?>
        <div class="field">
            <span><?= e(__('What do you need?')) ?></span>
            <div class="cat-picker">
                <?php foreach (TICKET_CATEGORIES as $k => $label): if ($k === 'refill') { continue; } ?>
                    <label class="cat-option">
                        <input type="radio" name="category" value="<?= e($k) ?>"<?= checked($category === $k) ?>>
                        <span><i class="cat-dot cat-<?= e($k) ?>"></i><?= e($label) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <p class="hint"><?= e(__('For a drinks refill, use the “Request refill” button on')) ?> <a href="<?= e(url('/my')) ?>"><?= e(__('My bootcamp')) ?></a><?= e(__('. For extra monitors or webcams, use “Request extra equipment”.')) ?></p>
        </div>
    <?php else: ?>
        <input type="hidden" name="category" value="other">
    <?php endif; ?>
    <label class="field"><span><?= e(__('Subject')) ?></span><input type="text" name="subject" value="<?= e($subject) ?>" maxlength="190" required placeholder="<?= e($staff ? __('e.g. Room change tomorrow') : 'e.g. PC 3 has no internet') ?>"></label>
    <label class="field"><span><?= e(__('Message')) ?></span><textarea name="body" rows="6"><?= e($body) ?></textarea></label>
    <div class="field">
        <span><?= e(__('Photos / videos')) ?> <small><?= e(__('(optional, up to %s each)', human_bytes(upload_limit_bytes()))) ?></small></span>
        <label class="btn btn-sm btn-ghost file-btn"><?= e(__('📎 Add photo or video')) ?><input type="file" name="files[]" accept="image/*,video/*" multiple data-attach-input></label>
        <div class="attach-preview" data-attach-preview></div>
    </div>
    <div class="form-actions"><button class="btn" type="submit"><?= e($staff ? __('Send to team') : 'Send ticket') ?></button></div>
</form>
