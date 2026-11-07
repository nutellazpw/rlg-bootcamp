<?php
$v = fn (string $k, $d = '') => $b[$k] ?? $d;
$byCat = [];
foreach ($equipment as $eq) {
    $byCat[$eq['category']][] = $eq;
}
$action = $isNew ? url('/bootcamps/new') : url('/bootcamps/' . (int) $b['id'] . '/edit');
?>
<div class="page-head">
    <div>
        <a class="back" href="<?= e($isNew ? url('/bootcamps') : url('/bootcamps/' . (int) $b['id'])) ?>">← Back</a>
        <h1><?= $isNew ? 'New bootcamp' : 'Edit bootcamp' ?></h1>
    </div>
</div>

<?php if ($errors): ?>
    <div class="alert alert-error"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<?php if (!$teams): ?>
    <div class="alert alert-info">There are no teams yet. <a href="<?= e(url('/teams/new')) ?>">Add a team first →</a></div>
<?php endif; ?>

<form method="post" action="<?= e($action) ?>" class="form" id="bootcamp-form" data-bootcamp-id="<?= $isNew ? '' : (int) $b['id'] ?>">
    <?= csrf_field() ?>

    <section class="card">
        <h2 class="card-title">Team &amp; place</h2>
        <div class="form-grid">
            <label class="field">
                <span>Team</span>
                <select name="team_id" required>
                    <option value="">Choose team…</option>
                    <?php foreach ($teams as $t): ?>
                        <option value="<?= (int) $t['id'] ?>"<?= selected($t['id'], $v('team_id')) ?>><?= e($t['name']) ?> (<?= e($t['game']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field">
                <span>Hotel</span>
                <select name="hotel_id" required>
                    <option value="">Choose hotel…</option>
                    <?php foreach ($hotels as $h): ?>
                        <option value="<?= (int) $h['id'] ?>"<?= selected($h['id'], $v('hotel_id')) ?>><?= e($h['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field">
                <span>Room <small>(can be added later)</small></span>
                <input type="text" name="room" value="<?= e($v('room')) ?>" maxlength="60" placeholder="e.g. 512">
            </label>
            <label class="field">
                <span>Status</span>
                <select name="status">
                    <?php foreach (BOOTCAMP_STATUSES as $k => $label): ?>
                        <option value="<?= e($k) ?>"<?= selected($k, $v('status', 'planned')) ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="field">
                <span>Start date</span>
                <input type="date" name="start_date" value="<?= e($v('start_date')) ?>" required>
            </label>
            <label class="field">
                <span>End date</span>
                <input type="date" name="end_date" value="<?= e($v('end_date')) ?>" required>
            </label>
        </div>
    </section>

    <section class="card">
        <div class="card-head">
            <h2 class="card-title">Equipment in the room</h2>
            <a class="link" href="<?= e(url('/inventory')) ?>" target="_blank">Inventory →</a>
        </div>
        <?php if (!$equipment): ?>
            <p class="muted">No equipment in inventory yet. <a href="<?= e(url('/inventory')) ?>">Add equipment →</a></p>
        <?php endif; ?>
        <div class="equip-groups">
            <?php foreach (EQUIPMENT_CATEGORIES as $cat => $catLabel): if (empty($byCat[$cat])) { continue; } ?>
                <div class="equip-group">
                    <h3><?= e($catLabel) ?></h3>
                    <?php foreach ($byCat[$cat] as $eq): ?>
                        <label class="qty-row">
                            <span class="qty-name"><?= e($eq['name']) ?><?php if ($eq['spec']): ?><small><?= e($eq['spec']) ?></small><?php endif; ?></span>
                            <span class="qty-free" data-free-for="<?= (int) $eq['id'] ?>" title="Free in the selected period"></span>
                            <input type="number" name="qty[<?= (int) $eq['id'] ?>]" value="<?= (int) ($qty[$eq['id']] ?? 0) ?>" min="0" max="999" inputmode="numeric">
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="alert alert-warning" id="availability-warnings" hidden></div>
        <p class="hint">Chair count decides how many chairs the team can configure (armrests). Monitors are separate items per refresh rate — add new types in Inventory.</p>
    </section>


    <section class="card">
        <h2 class="card-title">Notes</h2>
        <label class="field">
            <span>Info for the team <small>(visible to the client: Wi-Fi, check-in, contacts, schedule…)</small></span>
            <textarea name="client_info" rows="4"><?= e($v('client_info')) ?></textarea>
        </label>
        <label class="field">
            <span>Internal notes <small>(staff only)</small></span>
            <textarea name="staff_notes" rows="3"><?= e($v('staff_notes')) ?></textarea>
        </label>
    </section>

    <div class="form-actions">
        <button class="btn" type="submit"><?= $isNew ? 'Create bootcamp' : 'Save changes' ?></button>
        <a class="btn btn-ghost" href="<?= e($isNew ? url('/bootcamps') : url('/bootcamps/' . (int) $b['id'])) ?>">Cancel</a>
    </div>
</form>
