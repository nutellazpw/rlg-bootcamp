<?php
$manage = can_manage();
$chairSummary = [];
foreach ($chairs as $no => $opt) {
    if ($opt !== 'keep') {
        $chairSummary[$opt][] = $no;
    }
}
$selectedDrinks = array_values(array_filter($drinks, fn ($d) => in_array((int) $d['id'], $drinkIds, true)));
?>
<div class="page-head">
    <div>
        <a class="back" href="<?= e(url('/bootcamps')) ?>">← Bootcamps</a>
        <h1><?= e($b['team_name']) ?> <span class="tag"><?= e($b['team_game']) ?></span></h1>
        <p class="muted">
            <?= e($b['hotel_name']) ?> · <?= $b['room'] ? 'Room ' . e($b['room']) : '<span class="text-warn">no room yet</span>' ?>
            · <?= e(fmt_range($b['start_date'], $b['end_date'])) ?> (<?= days_between($b['start_date'], $b['end_date']) ?> days)
            · <?= status_badge($b['status'], BOOTCAMP_STATUSES) ?>
        </p>
    </div>
    <div class="head-actions">
        <?php if ($manage): ?>
            <a class="btn btn-ghost" href="<?= e(url('/bootcamps/' . $b['id'] . '/edit')) ?>">Edit</a>
        <?php endif; ?>
        <a class="btn btn-ghost" href="<?= e(url('/tickets/new', ['bootcamp' => $b['id']])) ?>">Message team</a>
    </div>
</div>

<?php if ($warnings): ?>
    <div class="alert alert-warning"><strong>Inventory is short for this bootcamp</strong><ul class="plain"><?php foreach ($warnings as $w): ?><li><?= e($w) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="layout-side">
    <div class="col-main">

        <section class="card">
            <form method="post" action="<?= e(url('/bootcamps/' . $b['id'] . '/quick')) ?>" class="quick-form">
                <?= csrf_field() ?>
                <label class="field"><span>Room</span><input type="text" name="room" value="<?= e($b['room']) ?>" maxlength="60" placeholder="Room number"></label>
                <label class="field"><span>Status</span>
                    <select name="status">
                        <?php foreach (BOOTCAMP_STATUSES as $k => $label): ?><option value="<?= e($k) ?>"<?= selected($k, $b['status']) ?>><?= e($label) ?></option><?php endforeach; ?>
                    </select>
                </label>
                <button class="btn btn-sm" type="submit">Update</button>
            </form>
            <p class="hint">The team gets a notification when the room is assigned or the status changes to “Room ready”.</p>
        </section>

        <section class="card" id="equipment">
            <h2 class="card-title">Equipment</h2>
            <?php if (!$items): ?>
                <p class="muted">No equipment booked.</p>
            <?php else: ?>
                <div class="equip-chips">
                    <?php foreach ($items as $it): ?>
                        <div class="equip-chip">
                            <span class="equip-qty"><?= (int) $it['qty'] ?>×</span>
                            <span><?= e($it['name']) ?><?php if ($it['spec']): ?><small><?= e($it['spec']) ?></small><?php endif; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="card" id="chairs">
            <div class="card-head">
                <h2 class="card-title">Chairs · armrests</h2>
                <span class="muted"><?= count($chairs) ?> chairs</span>
            </div>
            <?php if (!$chairs): ?>
                <p class="muted">No chairs in this booking.</p>
            <?php else: ?>
                <div class="setup-summary">
                    <?php if (!$chairSummary): ?>
                        <span class="badge badge-ready">All chairs keep both armrests</span>
                    <?php else: foreach ($chairSummary as $opt => $nos): ?>
                        <div><strong><?= e(ARMREST_OPTIONS[$opt]) ?>:</strong> chair <?= e(implode(', ', $nos)) ?></div>
                    <?php endforeach; endif; ?>
                </div>
                <details class="details">
                    <summary>Change chair setup</summary>
                    <form method="post" action="<?= e(url('/bootcamps/' . $b['id'] . '/chairs')) ?>">
                        <?= csrf_field() ?>
                        <?php partial('client/_chairs', ['chairs' => $chairs]); ?>
                        <button class="btn btn-sm" type="submit">Save chairs</button>
                    </form>
                </details>
            <?php endif; ?>
        </section>

        <section class="card" id="drinks">
            <h2 class="card-title">Drinks in the fridge</h2>
            <?php if (!$selectedDrinks): ?>
                <p class="muted">The team hasn't chosen drinks yet.</p>
            <?php else: ?>
                <div class="pill-list"><?php foreach ($selectedDrinks as $d): ?><span class="pill"><?= e($d['name']) ?></span><?php endforeach; ?></div>
            <?php endif; ?>
            <details class="details">
                <summary>Change drinks</summary>
                <form method="post" action="<?= e(url('/bootcamps/' . $b['id'] . '/drinks')) ?>">
                    <?= csrf_field() ?>
                    <?php partial('client/_drinks', ['drinks' => $drinks, 'drinkIds' => $drinkIds]); ?>
                    <button class="btn btn-sm" type="submit">Save drinks</button>
                </form>
            </details>
        </section>

        <?php
        $approved = array_values(array_filter($extras, fn ($x) => $x['status'] === 'approved'));
        $pending = array_values(array_filter($extras, fn ($x) => $x['status'] === 'pending'));
        $closed = array_values(array_filter($extras, fn ($x) => in_array($x['status'], ['rejected', 'cancelled'], true)));
        ?>
        <section class="card" id="extras">
            <div class="card-head">
                <h2 class="card-title">Extra equipment</h2>
                <?php if ($approved): ?><span class="muted small"><?= count($approved) ?> in log</span><?php endif; ?>
            </div>

            <?php if (!$extras): ?>
                <p class="muted">No extra equipment.</p>
            <?php endif; ?>

            <?php foreach ($pending as $x): ?>
                <div class="extra extra-pending">
                    <div class="extra-head">
                        <div><strong><?= (int) $x['qty'] ?>× <?= e($x['equipment_name']) ?></strong> <?= status_badge('pending', EXTRA_STATUSES) ?></div>
                        <span class="muted small"><?= e($x['requested_name'] ?? '') ?> · <?= e(fmt_dt($x['requested_at'])) ?></span>
                    </div>
                    <?php if ($x['note']): ?><p class="extra-note">“<?= e($x['note']) ?>”</p><?php endif; ?>
                    <?php if ($manage): ?>
                        <form method="post" action="<?= e(url('/extras/' . $x['id'] . '/decide')) ?>" class="decide-form">
                            <?= csrf_field() ?>
                            <div class="form-grid form-grid-3">
                                <label class="field"><span>Qty</span><input type="number" name="qty" value="<?= (int) $x['qty'] ?>" min="1"></label>
                                <label class="field"><span>In room from</span><input type="date" name="bill_from" value="<?= e(max(date('Y-m-d'), $b['start_date'])) ?>" min="<?= e($b['start_date']) ?>" max="<?= e($b['end_date']) ?>"></label>
                                <label class="field"><span>Until</span><input type="date" name="bill_to" value="<?= e($b['end_date']) ?>" min="<?= e($b['start_date']) ?>" max="<?= e($b['end_date']) ?>"></label>
                            </div>
                            <label class="field"><span>Note to team <small>(optional)</small></span><input type="text" name="staff_note" maxlength="255"></label>
                            <div class="btn-row">
                                <button class="btn btn-sm" name="decision" value="approve">Approve</button>
                                <button class="btn btn-sm btn-danger" name="decision" value="reject">Decline</button>
                            </div>
                        </form>
                    <?php else: ?>
                        <p class="small muted">Waiting for the bootcamp manager to approve.</p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <?php if ($approved): ?>
                <div class="table-wrap extras-log">
                    <table class="table table-stack">
                        <thead><tr><th>Item</th><th class="num">Qty</th><th>In room</th><th class="num">Days</th><th>Delivered</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($approved as $x):
                            $from = $x['bill_from'] ?: $b['start_date'];
                            $to = $x['bill_to'] ?: $b['end_date'];
                            ?>
                            <tr>
                                <td><strong><?= e($x['equipment_name']) ?></strong><?php if ($x['note']): ?><div class="small muted">“<?= e($x['note']) ?>”</div><?php endif; ?>
                                    <div class="small muted"><?= $x['requested_name'] ? 'Requested by ' . e($x['requested_name']) : '' ?><?= $x['decided_name'] ? ' · approved by ' . e($x['decided_name']) : '' ?></div></td>
                                <td class="num stack-muted"><?= (int) $x['qty'] ?>×</td>
                                <td class="nowrap stack-muted"><?= e(fmt_date($from, 'd M')) ?> → <?= e(fmt_date($to, 'd M')) ?></td>
                                <td class="num stack-muted"><?= days_between($from, $to) ?> d</td>
                                <td class="stack-corner"><?= $x['delivered_at'] ? '<span class="badge badge-completed">Delivered</span>' : '<span class="badge badge-pending">Not yet</span>' ?></td>
                                <td class="nowrap">
                                    <div class="btn-row">
                                        <form method="post" action="<?= e(url('/extras/' . $x['id'] . '/delivered')) ?>"><?= csrf_field() ?><button class="btn btn-xs btn-ghost"><?= $x['delivered_at'] ? 'Undo delivered' : 'Mark delivered' ?></button></form>
                                        <?php if ($manage): ?>
                                            <details class="details-inline">
                                                <summary class="btn btn-xs btn-ghost">Edit</summary>
                                                <form method="post" action="<?= e(url('/extras/' . $x['id'] . '/update')) ?>" class="mini-form">
                                                    <?= csrf_field() ?>
                                                    <label class="field"><span>Qty</span><input type="number" name="qty" value="<?= (int) $x['qty'] ?>" min="1"></label>
                                                    <label class="field"><span>From</span><input type="date" name="bill_from" value="<?= e($from) ?>"></label>
                                                    <label class="field"><span>Until</span><input type="date" name="bill_to" value="<?= e($to) ?>"></label>
                                                    <div class="btn-row">
                                                        <button class="btn btn-xs" name="action" value="save">Save</button>
                                                        <?php if ($to > date('Y-m-d') && date('Y-m-d') >= $from): ?><button class="btn btn-xs btn-ghost" name="action" value="taken_out">Taken out today</button><?php endif; ?>
                                                        <button class="btn btn-xs btn-danger" name="action" value="remove" formnovalidate>Delete</button>
                                                    </div>
                                                </form>
                                            </details>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if ($closed): ?>
                <p class="small muted closed-extras">Declined / cancelled:
                    <?php foreach ($closed as $i => $x): ?><?= $i ? ', ' : '' ?><?= (int) $x['qty'] ?>× <?= e($x['equipment_name']) ?> (<?= e(EXTRA_STATUSES[$x['status']]) ?>)<?php endforeach; ?>
                </p>
            <?php endif; ?>

            <?php if ($manage && bootcamp_is_open($b)): ?>
                <details class="details">
                    <summary>Add extra equipment</summary>
                    <form method="post" action="<?= e(url('/bootcamps/' . $b['id'] . '/extras')) ?>">
                        <?= csrf_field() ?>
                        <div class="form-grid form-grid-4">
                            <label class="field"><span>Item</span>
                                <select name="equipment_id" required>
                                    <?php foreach ($requestable as $eq): ?><option value="<?= (int) $eq['id'] ?>"><?= e($eq['name']) ?></option><?php endforeach; ?>
                                </select>
                            </label>
                            <label class="field"><span>Qty</span><input type="number" name="qty" value="1" min="1"></label>
                            <label class="field"><span>In room from</span><input type="date" name="bill_from" value="<?= e(max(date('Y-m-d'), $b['start_date'])) ?>"></label>
                            <label class="field"><span>Until</span><input type="date" name="bill_to" value="<?= e($b['end_date']) ?>"></label>
                        </div>
                        <label class="field"><span>Note</span><input type="text" name="note" maxlength="255"></label>
                        <button class="btn btn-sm" type="submit">Add</button>
                    </form>
                </details>
            <?php endif; ?>
        </section>
    </div>

    <aside class="col-side">

        <section class="card">
            <div class="card-head"><h2 class="card-title">Team accounts</h2><a class="link" href="<?= e(url('/teams/' . $b['team_id'])) ?>">Team →</a></div>
            <?php if (!$accounts): ?>
                <p class="muted small">No client account yet. <a href="<?= e(url('/teams/' . $b['team_id'])) ?>">Invite the team manager →</a></p>
            <?php else: foreach ($accounts as $a): ?>
                <div class="person">
                    <span class="avatar avatar-sm"><?= e(initials($a['name'])) ?></span>
                    <div>
                        <strong><?= e($a['name']) ?></strong>
                        <div class="small muted"><?= e($a['email']) ?><?= $a['phone'] ? ' · ' . e($a['phone']) : '' ?></div>
                        <div class="small"><?= $a['activated'] ? '<span class="text-ok">Active</span>' : '<span class="text-warn">Invite not accepted</span>' ?></div>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </section>

        <section class="card">
            <h2 class="card-title">Info for the team</h2>
            <?= $b['client_info'] ? '<p class="small">' . nl2br_e($b['client_info']) . '</p>' : '<p class="muted small">Nothing yet.</p>' ?>
            <?php if ($b['staff_notes']): ?>
                <h3 class="small-title">Internal notes</h3>
                <p class="small"><?= nl2br_e($b['staff_notes']) ?></p>
            <?php endif; ?>
        </section>

        <section class="card">
            <div class="card-head"><h2 class="card-title">Tickets</h2></div>
            <?php if (!$tickets): ?>
                <p class="muted small">No tickets.</p>
            <?php else: ?>
                <div class="list">
                    <?php foreach ($tickets as $t): ?>
                        <a class="list-row" href="<?= e(url('/tickets/' . $t['id'])) ?>">
                            <div class="list-main"><div class="list-title">#<?= (int) $t['id'] ?> <?= e($t['subject']) ?></div><div class="list-sub"><?= e(time_ago($t['updated_at'])) ?></div></div>
                            <div class="list-side"><?= status_badge($t['status'], TICKET_STATUSES) ?></div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <?php if ($manage): ?>
            <form method="post" action="<?= e(url('/bootcamps/' . $b['id'] . '/delete')) ?>" data-confirm="Delete this bootcamp permanently? Usually it's better to set the status to Cancelled.">
                <?= csrf_field() ?>
                <button class="btn btn-ghost btn-sm btn-block text-danger" type="submit">Delete bootcamp</button>
            </form>
        <?php endif; ?>
    </aside>
</div>
