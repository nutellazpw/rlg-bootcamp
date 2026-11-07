<div class="table-wrap">
<table class="table table-stack">
    <thead>
        <tr>
            <th>Team</th>
            <th>Hotel · Room</th>
            <th>Dates</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $b): ?>
            <tr data-href="<?= e(url('/bootcamps/' . $b['id'])) ?>">
                <td><a href="<?= e(url('/bootcamps/' . $b['id'])) ?>"><strong><?= e($b['team_name']) ?></strong></a><?php if (!empty($b['team_game'])): ?> <span class="tag"><?= e($b['team_game']) ?></span><?php endif; ?></td>
                <td class="stack-muted"><?= e($b['hotel_name']) ?> · <?= $b['room'] ? e($b['room']) : '<span class="text-warn">no room</span>' ?></td>
                <td class="nowrap stack-muted"><?= e(fmt_range($b['start_date'], $b['end_date'])) ?> <span class="muted">(<?= days_between($b['start_date'], $b['end_date']) ?>d)</span>
                    <?php if (!empty($b['ready_by']) && empty($b['room_ready_at']) && in_array($b['status'], ['planned', 'active'], true) && strtotime($b['ready_by']) > time() - 86400): ?>
                        <div class="small <?= strtotime($b['ready_by']) < time() ? 'text-danger' : (strtotime($b['ready_by']) < time() + 86400 ? 'text-warn' : 'muted') ?>">ready by <?= e(date('D d M H:i', strtotime($b['ready_by']))) ?></div>
                    <?php endif; ?></td>
                <td class="stack-corner"><?= status_badge($b['status'], BOOTCAMP_STATUSES) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>
