<form method="post" action="<?= e(url('/activity-requests/' . $r['id'])) ?>" class="form reply-form">
    <?= csrf_field() ?>
    <label class="field"><span>Reply to the team <small>(offer, price, time, questions…)</small></span><textarea name="staff_reply" rows="2" placeholder="e.g. Photographer available Tue 14:00, 2h session — 250 EUR. Confirm?"><?= e($r['staff_reply']) ?></textarea></label>
    <div class="btn-row">
        <select name="status"><?php foreach (ACTIVITY_STATUSES as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($k, $r['status'] === 'pending' ? 'answered' : $r['status']) ?>><?= e($l) ?></option><?php endforeach; ?></select>
        <button class="btn btn-sm" type="submit">Save &amp; notify team</button>
        <?php if (!empty($r['replied_name'])): ?><span class="small muted">last reply: <?= e($r['replied_name']) ?>, <?= e(time_ago($r['replied_at'])) ?></span><?php endif; ?>
    </div>
</form>
