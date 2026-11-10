<div class="page-head">
    <div>
        <a class="back" href="<?= e(url('/teams')) ?>"><?= e(__('← Teams')) ?></a>
        <h1><?= e($t['name']) ?> <span class="tag"><?= e($t['game']) ?></span></h1>
        <p class="muted"><?= e($t['contact_name'] ?? '') ?><?= $t['contact_email'] ? ' · ' . e($t['contact_email']) : '' ?><?= $t['contact_phone'] ? ' · ' . e($t['contact_phone']) : '' ?></p>
    </div>
    <?php if (can_manage()): ?>
        <div class="head-actions">
            <a class="btn btn-ghost" href="<?= e(url('/teams/' . $t['id'] . '/edit')) ?>"><?= e(__('Edit')) ?></a>
            <a class="btn" href="<?= e(url('/bootcamps/new', ['team' => $t['id']])) ?>"><?= e(__('+ Bootcamp')) ?></a>
        </div>
    <?php endif; ?>
</div>

<div class="layout-side">
    <div class="col-main">
        <section class="card card-flush">
            <div class="card-head card-pad"><h2 class="card-title"><?= e(__('Bootcamps')) ?></h2></div>
            <?php if (!$bootcamps): ?>
                <p class="muted empty-sm"><?= e(__('No bootcamps yet.')) ?></p>
            <?php else: ?>
                <?php partial('bootcamps/_table', ['rows' => $bootcamps]); ?>
            <?php endif; ?>
        </section>
        <?php if ($t['notes']): ?>
            <section class="card"><h2 class="card-title"><?= e(__('Notes')) ?></h2><p><?= nl2br_e($t['notes']) ?></p></section>
        <?php endif; ?>
    </div>
    <aside class="col-side">
        <section class="card">
            <h2 class="card-title"><?= e(__('Client accounts')) ?></h2>
            <p class="hint"><?= e(__('Usually one account per team — the team manager, or the main player if they have no manager.')) ?></p>
            <?php foreach ($accounts as $a): ?>
                <div class="person<?= $a['active'] ? '' : ' inactive' ?>">
                    <?= avatar_html($a + ['team_logo' => $t['logo']], 'sm') ?>
                    <div class="person-main">
                        <strong><?= e($a['name']) ?></strong>
                        <div class="small muted"><?= e($a['email']) ?><?= $a['phone'] ? ' · ' . e($a['phone']) : '' ?></div>
                        <div class="small">
                            <?php if (!$a['active']): ?><span class="muted"><?= e(__('Disabled')) ?></span>
                            <?php elseif ($a['password_hash']): ?><span class="text-ok"><?= e(__('Active')) ?></span><?= $a['last_login_at'] ? ' <span class="muted">· ' . e(__('last login %s', time_ago($a['last_login_at']))) . '</span>' : '' ?>
                            <?php else: ?><span class="text-warn"><?= e(__('Invite not accepted')) ?></span><?php endif; ?>
                        </div>
                        <?php if (can_manage()): ?>
                            <div class="btn-row">
                                <?php if ($a['active']): ?>
                                    <form method="post" action="<?= e(url('/users/' . $a['id'] . '/invite')) ?>"><?= csrf_field() ?><button class="btn btn-xs btn-ghost"><?= e($a['password_hash'] ? __('Send password reset') : __('Resend invite')) ?></button></form>
                                <?php endif; ?>
                                <form method="post" action="<?= e(url('/users/' . $a['id'] . '/toggle')) ?>" data-confirm="<?= e($a['active'] ? __('Disable this account? They will not be able to sign in.') : __('Enable this account again?')) ?>"><?= csrf_field() ?><button class="btn btn-xs btn-ghost"><?= e($a['active'] ? __('Disable') : __('Enable')) ?></button></form>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (can_manage()): ?>
                <details class="details"<?= $accounts ? '' : ' open' ?>>
                    <summary><?= e(__('Invite client account')) ?></summary>
                    <form method="post" action="<?= e(url('/teams/' . $t['id'] . '/accounts')) ?>" class="form">
                        <?= csrf_field() ?>
                        <label class="field"><span><?= e(__('Name')) ?></span><input type="text" name="name" value="<?= $accounts ? '' : e($t['contact_name']) ?>" required></label>
                        <label class="field"><span><?= e(__('Email')) ?></span><input type="email" name="email" value="<?= $accounts ? '' : e($t['contact_email']) ?>" required></label>
                        <label class="field"><span><?= e(__('Phone')) ?> <small><?= e(__('(optional)')) ?></small></span><input type="tel" name="phone" value="<?= $accounts ? '' : e($t['contact_phone']) ?>"></label>
                        <button class="btn btn-sm" type="submit"><?= e(__('Send invitation')) ?></button>
                    </form>
                </details>
            <?php endif; ?>
        </section>
        <?php if (can_manage()): ?>
            <section class="card card-danger" id="delete-team">
                <h2 class="card-title text-danger"><?= e(__('Delete team')) ?></h2>
                <p class="small muted"><?= __('Permanently deletes <strong>%s</strong>, its %s, %s, requests, photos and %s. This cannot be undone.', e($t['name']), e(__n(count($bootcamps), '%d bootcamp', '%d bootcamps')), e(__n((int) $counts['tickets'], '%d ticket', '%d tickets')), e(__n(count($accounts), '%d client account', '%d client accounts'))) ?></p>
                <form method="post" action="<?= e(url('/teams/' . $t['id'] . '/delete')) ?>" class="form" data-confirm="<?= e(__('Delete %s and everything that belongs to it? This cannot be undone.', $t['name'])) ?>">
                    <?= csrf_field() ?>
                    <label class="field"><span><?= e(__('Type the team name to confirm')) ?></span><input type="text" name="confirm_name" autocomplete="off" required placeholder="<?= e($t['name']) ?>"></label>
                    <button class="btn btn-sm btn-danger" type="submit"><?= e(__('Delete team permanently')) ?></button>
                </form>
            </section>
        <?php endif; ?>
    </aside>
</div>
