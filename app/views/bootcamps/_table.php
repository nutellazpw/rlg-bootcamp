<div class="table-wrap">
<table class="table table-stack">
    <thead>
        <tr>
            <th>Team</th>
            <th>Hotel · Room</th>
            <th>Dates</th>
            <th>Status</th>
            <?php if (can_finance()): ?><th class="num">Price</th><?php endif; ?>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $b): ?>
            <tr data-href="<?= e(url('/bootcamps/' . $b['id'])) ?>">
                <td><a href="<?= e(url('/bootcamps/' . $b['id'])) ?>"><strong><?= e($b['team_name']) ?></strong></a><?php if (!empty($b['team_game'])): ?> <span class="tag"><?= e($b['team_game']) ?></span><?php endif; ?></td>
                <td class="stack-muted"><?= e($b['hotel_name']) ?> · <?= $b['room'] ? e($b['room']) : '<span class="text-warn">no room</span>' ?></td>
                <td class="nowrap stack-muted"><?= e(fmt_range($b['start_date'], $b['end_date'])) ?> <span class="muted">(<?= days_between($b['start_date'], $b['end_date']) ?>d)</span></td>
                <td class="stack-corner"><?= status_badge($b['status'], BOOTCAMP_STATUSES) ?></td>
                <?php if (can_finance()): ?><td class="num nowrap stack-muted"><?= e(money($b['price'], $b['currency'])) ?></td><?php endif; ?>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>
