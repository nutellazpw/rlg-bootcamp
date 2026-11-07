<?php $v = fn (string $k, $d = '') => $t[$k] ?? $d; ?>
<div class="page-head">
    <div>
        <a class="back" href="<?= e($isNew ? url('/teams') : url('/teams/' . (int) $t['id'])) ?>">← Back</a>
        <h1><?= $isNew ? 'New team' : 'Edit team' ?></h1>
    </div>
</div>
<?php if ($errors): ?><div class="alert alert-error"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div><?php endif; ?>

<form method="post" action="<?= e($isNew ? url('/teams/new') : url('/teams/' . (int) $t['id'] . '/edit')) ?>" class="form">
    <?= csrf_field() ?>
    <section class="card">
        <div class="form-grid">
            <label class="field"><span>Team name</span><input type="text" name="name" value="<?= e($v('name')) ?>" required maxlength="120"></label>
            <label class="field"><span>Game</span>
                <select name="game"><?php foreach (GAMES as $g): ?><option<?= selected($g, $v('game', 'CS2')) ?>><?= e($g) ?></option><?php endforeach; ?></select>
            </label>
            <label class="field"><span>Contact person <small>(team manager or main player)</small></span><input type="text" name="contact_name" value="<?= e($v('contact_name')) ?>" maxlength="120"></label>
            <label class="field"><span>Contact email</span><input type="email" name="contact_email" value="<?= e($v('contact_email')) ?>"></label>
            <label class="field"><span>Contact phone</span><input type="tel" name="contact_phone" value="<?= e($v('contact_phone')) ?>" maxlength="40"></label>
        </div>
        <label class="field"><span>Notes <small>(staff only)</small></span><textarea name="notes" rows="3"><?= e($v('notes')) ?></textarea></label>
        <?php if ($isNew): ?>
            <label class="check"><input type="checkbox" name="invite" value="1"<?= checked(!empty($t['invite']) || !is_post()) ?>><span>Create a client account for the contact person and email the invitation</span></label>
        <?php endif; ?>
    </section>
    <div class="form-actions">
        <button class="btn" type="submit"><?= $isNew ? 'Add team' : 'Save' ?></button>
    </div>
</form>
