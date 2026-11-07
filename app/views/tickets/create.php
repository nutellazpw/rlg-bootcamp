<div class="page-head">
    <div>
        <a class="back" href="<?= e($staff && $b ? url('/bootcamps/' . $b['id']) : url('/tickets')) ?>">← Back</a>
        <h1><?= $staff ? 'Message ' . e($b['team_name']) : 'New ticket' ?></h1>
        <?php if ($b): ?><p class="muted"><?= e($b['hotel_name']) ?><?= $b['room'] ? ' · room ' . e($b['room']) : '' ?> · <?= e(fmt_range($b['start_date'], $b['end_date'])) ?></p><?php endif; ?>
    </div>
</div>
<?php if ($errors): ?><div class="alert alert-error"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div><?php endif; ?>

<form method="post" action="<?= e(url('/tickets/new')) ?>" class="form card">
    <?= csrf_field() ?>
    <?php if ($b): ?><input type="hidden" name="bootcamp" value="<?= (int) $b['id'] ?>"><?php endif; ?>
    <?php if (!$staff): ?>
        <div class="field">
            <span>What do you need?</span>
            <div class="cat-picker">
                <?php foreach (TICKET_CATEGORIES as $k => $label): if ($k === 'refill') { continue; } ?>
                    <label class="cat-option">
                        <input type="radio" name="category" value="<?= e($k) ?>"<?= checked($category === $k) ?>>
                        <span><i class="cat-dot cat-<?= e($k) ?>"></i><?= e($label) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <p class="hint">For a drinks refill, use the “Request refill” button on <a href="<?= e(url('/my')) ?>">My bootcamp</a>. For extra monitors or webcams, use “Request extra equipment”.</p>
        </div>
    <?php else: ?>
        <input type="hidden" name="category" value="other">
    <?php endif; ?>
    <label class="field"><span>Subject</span><input type="text" name="subject" value="<?= e($subject) ?>" maxlength="190" required placeholder="<?= $staff ? 'e.g. Room change tomorrow' : 'e.g. PC 3 has no internet' ?>"></label>
    <label class="field"><span>Message</span><textarea name="body" rows="6" required><?= e($body) ?></textarea></label>
    <div class="form-actions"><button class="btn" type="submit"><?= $staff ? 'Send to team' : 'Send ticket' ?></button></div>
</form>
