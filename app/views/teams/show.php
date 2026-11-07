<div class="page-head">
    <div>
        <a class="back" href="<?= e(url('/teams')) ?>">← Teams</a>
        <h1><?= e($t['name']) ?> <span class="tag"><?= e($t['game']) ?></span></h1>
        <p class="muted"><?= e($t['contact_name'] ?? '') ?><?= $t['contact_email'] ? ' · ' . e($t['contact_email']) : '' ?><?= $t['contact_phone'] ? ' · ' . e($t['contact_phone']) : '' ?></p>
    </div>
    <?php if (can_manage()): ?>
        <div class="head-actions">
            <a class="btn btn-ghost" href="<?= e(url('/teams/' . $t['id'] . '/edit')) ?>">Edit</a>
            <a class="btn" href="<?= e(url('/bootcamps/new', ['team' => $t['id']])) ?>">+ Bootcamp</a>
        </div>
    <?php endif; ?>
</div>

<div class="layout-side">
    <div class="col-main">
        <section class="card card-flush">
            <div class="card-head card-pad"><h2 class="card-title">Bootcamps</h2></div>
            <?php if (!$bootcamps): ?>
                <p class="muted empty-sm">No bootcamps yet.</p>
            <?php else: ?>
                <?php partial('bootcamps/_table', ['rows' => $bootcamps]); ?>
            <?php endif; ?>
        </section>
        <?php if ($t['notes']): ?>
            <section class="card"><h2 class="card-title">Notes</h2><p><?= nl2br_e($t['notes']) ?></p></section>
        <?php endif; ?>
    </div>
    <aside class="col-side">
        <section class="card">
            <h2 class="card-title">Client accounts</h2>
            <p class="hint">Usually one account per team — the team manager, or the main player if they have no manager.</p>
            <?php foreach ($accounts as $a): ?>
                <div class="person<?= $a['active'] ? '' : ' inactive' ?>">
                    <span class="avatar avatar-sm"><?= e(initials($a['name'])) ?></span>
                    <div class="person-main">
                        <strong><?= e($a['name']) ?></strong>
                        <div class="small muted"><?= e($a['email']) ?><?= $a['phone'] ? ' · ' . e($a['phone']) : '' ?></div>
                        <div class="small">
                            <?php if (!$a['active']): ?><span class="muted">Disabled</span>
                            <?php elseif ($a['password_hash']): ?><span class="text-ok">Active</span><?= $a['last_login_at'] ? ' <span class="muted">· last login ' . e(time_ago($a['last_login_at'])) . '</span>' : '' ?>
                            <?php else: ?><span class="text-warn">Invite not accepted</span><?php endif; ?>
                        </div>
                        <?php if (can_manage()): ?>
                            <div class="btn-row">
                                <?php if ($a['active']): ?>
                                    <form method="post" action="<?= e(url('/users/' . $a['id'] . '/invite')) ?>"><?= csrf_field() ?><button class="btn btn-xs btn-ghost"><?= $a['password_hash'] ? 'Send password reset' : 'Resend invite' ?></button></form>
                                <?php endif; ?>
                                <form method="post" action="<?= e(url('/users/' . $a['id'] . '/toggle')) ?>" data-confirm="<?= $a['active'] ? 'Disable this account? They will not be able to sign in.' : 'Enable this account again?' ?>"><?= csrf_field() ?><button class="btn btn-xs btn-ghost"><?= $a['active'] ? 'Disable' : 'Enable' ?></button></form>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (can_manage()): ?>
                <details class="details"<?= $accounts ? '' : ' open' ?>>
                    <summary>Invite client account</summary>
                    <form method="post" action="<?= e(url('/teams/' . $t['id'] . '/accounts')) ?>" class="form">
                        <?= csrf_field() ?>
                        <label class="field"><span>Name</span><input type="text" name="name" value="<?= $accounts ? '' : e($t['contact_name']) ?>" required></label>
                        <label class="field"><span>Email</span><input type="email" name="email" value="<?= $accounts ? '' : e($t['contact_email']) ?>" required></label>
                        <label class="field"><span>Phone <small>(optional)</small></span><input type="tel" name="phone" value="<?= $accounts ? '' : e($t['contact_phone']) ?>"></label>
                        <button class="btn btn-sm" type="submit">Send invitation</button>
                    </form>
                </details>
            <?php endif; ?>
        </section>
    </aside>
</div>
