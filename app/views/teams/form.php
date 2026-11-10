<?php $v = fn (string $k, $d = '') => $t[$k] ?? $d; ?>
<div class="page-head">
    <div>
        <a class="back" href="<?= e($isNew ? url('/teams') : url('/teams/' . (int) $t['id'])) ?>"><?= e(__('← Back')) ?></a>
        <h1><?= e($isNew ? __('New team') : __('Edit team')) ?></h1>
    </div>
</div>
<?php if ($errors): ?><div class="alert alert-error"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div><?php endif; ?>

<form method="post" action="<?= e($isNew ? url('/teams/new') : url('/teams/' . (int) $t['id'] . '/edit')) ?>" class="form" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <section class="card">
        <div class="form-grid">
            <label class="field"><span><?= e(__('Team name')) ?></span><input type="text" name="name" value="<?= e($v('name')) ?>" required maxlength="120"></label>
            <label class="field"><span><?= e(__('Game')) ?></span>
                <select name="game"><?php foreach (GAMES as $g): ?><option value="<?= e($g) ?>"<?= selected($g, $v('game', 'CS2')) ?>><?= e(__($g)) ?></option><?php endforeach; ?></select>
            </label>
            <label class="field"><span><?= e(__('Contact person')) ?> <small><?= e(__('(team manager or main player)')) ?></small></span><input type="text" name="contact_name" value="<?= e($v('contact_name')) ?>" maxlength="120"></label>
            <label class="field"><span><?= e(__('Contact email')) ?></span><input type="email" name="contact_email" value="<?= e($v('contact_email')) ?>"></label>
            <label class="field"><span><?= e(__('Contact phone')) ?></span><input type="tel" name="contact_phone" value="<?= e($v('contact_phone')) ?>" maxlength="40"></label>
        </div>
        <div class="field">
            <span><?= e(__('Team logo')) ?> <small><?= e(__('(PNG with transparent background works best)')) ?></small></span>
            <div class="logo-field">
                <?php if (!$isNew && !empty($t['logo']) && ($lu = team_logo_url(['id' => $t['id'], 'logo' => $t['logo']]))): ?>
                    <img class="team-logo-preview" src="<?= e($lu) ?>" alt="">
                <?php endif; ?>
                <label class="btn btn-sm btn-ghost file-btn"><?= e(__('Choose image')) ?><input type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/gif" onchange="this.closest('.logo-field').querySelector('.file-name').textContent = this.files[0] ? this.files[0].name : ''"></label>
                <span class="file-name small muted"></span>
                <?php if (!$isNew && !empty($t['logo'])): ?><label class="check"><input type="checkbox" name="remove_logo" value="1"><span><?= e(__('Remove logo')) ?></span></label><?php endif; ?>
            </div>
        </div>
        <label class="field"><span><?= e(__('Notes')) ?> <small><?= e(__('(staff only)')) ?></small></span><textarea name="notes" rows="3"><?= e($v('notes')) ?></textarea></label>
        <?php if ($isNew): ?>
            <label class="check"><input type="checkbox" name="invite" value="1"<?= checked(!empty($t['invite']) || !is_post()) ?>><span><?= e(__('Create a client account for the contact person and email the invitation')) ?></span></label>
        <?php endif; ?>
    </section>
    <div class="form-actions">
        <button class="btn" type="submit"><?= e($isNew ? __('Add team') : __('Save')) ?></button>
    </div>
</form>
