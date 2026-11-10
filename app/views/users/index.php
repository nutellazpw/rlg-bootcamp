<div class="page-head">
    <div>
        <h1><?= e(__('Staff accounts')) ?></h1>
        <p class="muted"><?= e(__('Click a picture to upload that person\'s avatar (e.g. the Relog logo in their colour).')) ?></p>
        <p class="muted"><?= e(__('Administrator sees everything. Bootcamp manager creates and edits bootcamps, teams and inventory and approves extra equipment. Support handles tickets, rooms and deliveries.')) ?></p>
    </div>
</div>

<div class="layout-side">
    <div class="col-main">
        <section class="card card-flush">
            <div class="table-wrap">
            <table class="table">
                <thead><tr><th><?= e(__('Name')) ?></th><th><?= e(__('Role')) ?></th><th><?= e(__('Status')) ?></th><th><?= e(__('Push devices')) ?></th><th></th></tr></thead>
                <tbody>
                <?php foreach ($rows as $u):
                    $devices = (int) scalar('SELECT COUNT(*) FROM push_subscriptions WHERE user_id = ?', [$u['id']]); ?>
                    <tr class="<?= $u['active'] ? '' : 'row-muted' ?>">
                        <td>
                            <div class="person-cell">
                                <form method="post" action="<?= e(url('/users/' . $u['id'] . '/avatar')) ?>" enctype="multipart/form-data" class="avatar-upload" title="<?= e(__('Change picture')) ?>">
                                    <?= csrf_field() ?>
                                    <label><?= avatar_html($u) ?><input type="file" name="avatar" accept="image/png,image/jpeg,image/webp,image/gif" onchange="this.form.submit()"><span class="avatar-edit">✎</span></label>
                                </form>
                                <div><strong><?= e($u['name']) ?></strong><div class="small muted"><?= e($u['email']) ?><?= $u['phone'] ? ' · ' . e($u['phone']) : '' ?></div></div>
                            </div>
                        </td>
                        <td>
                            <form method="post" action="<?= e(url('/users/' . $u['id'])) ?>" class="inline-form">
                                <?= csrf_field() ?>
                                <select name="role" onchange="this.form.submit()">
                                    <?php foreach (STAFF_ROLES as $r): ?><option value="<?= e($r) ?>"<?= selected($r, $u['role']) ?>><?= e(lbl(ROLES, $r)) ?></option><?php endforeach; ?>
                                </select>
                            </form>
                        </td>
                        <td>
                            <?php if (!$u['active']): ?><span class="muted"><?= e(__('Disabled')) ?></span>
                            <?php elseif ($u['password_hash']): ?><span class="text-ok"><?= e(__('Active')) ?></span><div class="small muted"><?= $u['last_login_at'] ? e(__('last login %s', time_ago($u['last_login_at']))) : '' ?></div>
                            <?php else: ?><span class="text-warn"><?= e(__('Invite pending')) ?></span><?php endif; ?>
                        </td>
                        <td><?= $devices ? e(__n($devices, '%d device', '%d devices')) : '<span class="text-warn">' . e(__('none')) . '</span>' ?></td>
                        <td class="nowrap">
                            <?php if ((int) $u['id'] !== (int) user()['id']): ?>
                                <div class="btn-row">
                                    <?php if ($u['active']): ?><form method="post" action="<?= e(url('/users/' . $u['id'] . '/invite')) ?>"><?= csrf_field() ?><button class="btn btn-xs btn-ghost"><?= e($u['password_hash'] ? __('Reset password') : __('Resend invite')) ?></button></form><?php endif; ?>
                                    <form method="post" action="<?= e(url('/users/' . $u['id'] . '/toggle')) ?>" data-confirm="<?= e($u['active'] ? __('Disable this account?') : __('Enable this account?')) ?>"><?= csrf_field() ?><button class="btn btn-xs btn-ghost"><?= e($u['active'] ? __('Disable') : __('Enable')) ?></button></form>
                                </div>
                            <?php else: ?><span class="muted small"><?= e(__('you')) ?></span><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </section>
    </div>
    <aside class="col-side">
        <section class="card">
            <h2 class="card-title"><?= e(__('Add staff member')) ?></h2>
            <form method="post" action="<?= e(url('/users')) ?>" class="form">
                <?= csrf_field() ?>
                <label class="field"><span><?= e(__('Name')) ?></span><input type="text" name="name" required></label>
                <label class="field"><span><?= e(__('Email')) ?></span><input type="email" name="email" required></label>
                <label class="field"><span><?= e(__('Phone')) ?> <small><?= e(__('(optional)')) ?></small></span><input type="tel" name="phone"></label>
                <label class="field"><span><?= e(__('Role')) ?></span>
                    <select name="role">
                        <option value="support"><?= e(__('Support')) ?></option>
                        <option value="manager"><?= e(__('Bootcamp manager')) ?></option>
                        <option value="admin"><?= e(__('Administrator')) ?></option>
                    </select>
                </label>
                <button class="btn btn-block" type="submit"><?= e(__('Send invitation')) ?></button>
            </form>
        </section>
    </aside>
</div>
