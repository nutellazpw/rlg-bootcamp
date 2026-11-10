<?php $flagsAll = $rows ? bootcamp_action_flags(array_column($rows, 'id')) : []; ?>
<div class="table-wrap">
<table class="table table-stack table-bootcamps">
    <thead>
        <tr>
            <th><?= e(__('Team')) ?></th>
            <th class="c"><?= e(__('Room')) ?></th>
            <th><?= e(__('Hotel')) ?></th>
            <th><?= e(__('Dates')) ?></th>
            <th><?= e(__('Status')) ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($rows as $b): $flags = $flagsAll[(int) $b['id']] ?? []; ?>
            <tr data-href="<?= e(url('/bootcamps/' . $b['id'])) ?>" class="<?= $flags ? 'needs-action' : '' ?>">
                <td>
                    <a href="<?= e(url('/bootcamps/' . $b['id'])) ?>"><strong><?= e($b['team_name']) ?></strong></a><?php if (!empty($b['team_game'])): ?> <span class="tag"><?= e($b['team_game']) ?></span><?php endif; ?>
                    <?php if ($flags): ?><div class="action-tags"><?= action_tags($flags) ?></div><?php endif; ?>
                </td>
                <td class="c room-cell"><?= $b['room'] ? '<span class="room-no">' . e($b['room']) . '</span>' : '<span class="room-no room-none">—</span>' ?></td>
                <td class="stack-muted"><?= e($b['hotel_name']) ?></td>
                <td class="nowrap stack-muted"><?= e(fmt_range($b['start_date'], $b['end_date'])) ?> <span class="muted">(<?= e(__('%d d', days_between($b['start_date'], $b['end_date']))) ?>)</span>
                    <?php if (!empty($b['ready_by']) && empty($b['room_ready_at']) && in_array($b['status'], ['planned', 'active'], true) && strtotime($b['ready_by']) > time() - 86400): ?>
                        <div class="small <?= strtotime($b['ready_by']) < time() ? 'text-danger' : (strtotime($b['ready_by']) < time() + 86400 ? 'text-warn' : 'muted') ?>"><?= e(__('ready by %s', ldate('D d M H:i', strtotime($b['ready_by'])))) ?></div>
                    <?php endif; ?></td>
                <td class="stack-corner"><?= status_badge($b['status'], BOOTCAMP_STATUSES) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>
