<div class="page-head">
    <div>
        <h1>Staff accounts</h1>
        <p class="muted">Click a picture to upload that person's avatar (e.g. the Relog logo in their colour).</p>
        <p class="muted">Administrator sees everything. Bootcamp manager creates and edits bootcamps, teams and inventory and approves extra equipment. Support handles tickets, rooms and deliveries.</p>
    </div>
</div>

<div class="layout-side">
    <div class="col-main">
        <section class="card card-flush">
            <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Name</th><th>Role</th><th>Status</th><th>Push devices</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($rows as $u):
                    $devices = (int) scalar('SELECT COUNT(*) FROM push_subscriptions WHERE user_id = ?', [$u['id']]); ?>
                    <tr class="<?= $u['active'] ? '' : 'row-muted' ?>">
                        <td>
                            <div class="person-cell">
                                <form method="post" action="<?= e(url('/users/' . $u['id'] . '/avatar')) ?>" enctype="multipart/form-data" class="avatar-upload" title="Change picture">
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
                                    <?php foreach (STAFF_ROLES as $r): ?><option value="<?= e($r) ?>"<?= selected($r, $u['role']) ?>><?= e(ROLES[$r]) ?></option><?php endforeach; ?>
                                </select>
                            </form>
                        </td>
                        <td>
                            <?php if (!$u['active']): ?><span class="muted">Disabled</span>
                            <?php elseif ($u['password_hash']): ?><span class="text-ok">Active</span><div class="small muted"><?= $u['last_login_at'] ? 'last login ' . e(time_ago($u['last_login_at'])) : '' ?></div>
                            <?php else: ?><span class="text-warn">Invite pending</span><?php endif; ?>
                        </td>
                        <td><?= $devices ? $devices . ' device' . ($devices > 1 ? 's' : '') : '<span class="text-warn">none</span>' ?></td>
                        <td class="nowrap">
                            <?php if ((int) $u['id'] !== (int) user()['id']): ?>
                                <div class="btn-row">
                                    <?php if ($u['active']): ?><form method="post" action="<?= e(url('/users/' . $u['id'] . '/invite')) ?>"><?= csrf_field() ?><button class="btn btn-xs btn-ghost"><?= $u['password_hash'] ? 'Reset password' : 'Resend invite' ?></button></form><?php endif; ?>
                                    <form method="post" action="<?= e(url('/users/' . $u['id'] . '/toggle')) ?>" data-confirm="<?= $u['active'] ? 'Disable this account?' : 'Enable this account?' ?>"><?= csrf_field() ?><button class="btn btn-xs btn-ghost"><?= $u['active'] ? 'Disable' : 'Enable' ?></button></form>
                                </div>
                            <?php else: ?><span class="muted small">you</span><?php endif; ?>
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
            <h2 class="card-title">Add staff member</h2>
            <form method="post" action="<?= e(url('/users')) ?>" class="form">
                <?= csrf_field() ?>
                <label class="field"><span>Name</span><input type="text" name="name" required></label>
                <label class="field"><span>Email</span><input type="email" name="email" required></label>
                <label class="field"><span>Phone <small>(optional)</small></span><input type="tel" name="phone"></label>
                <label class="field"><span>Role</span>
                    <select name="role">
                        <option value="support">Support</option>
                        <option value="manager">Bootcamp manager</option>
                        <option value="admin">Administrator</option>
                    </select>
                </label>
                <button class="btn btn-block" type="submit">Send invitation</button>
            </form>
        </section>
    </aside>
</div>
