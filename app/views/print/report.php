<?php
$docType = __('Bootcamp report');
$fin = can_finance();
$approved = array_values(array_filter($extras, fn ($x) => $x['status'] === 'approved'));
$other = array_values(array_filter($extras, fn ($x) => $x['status'] !== 'approved'));
$days = days_between($b['start_date'], $b['end_date']);
[$setupDone, $setupTotal] = $setup;
?>
<h1><?= e(__('Bootcamp report')) ?></h1>

<div class="info-grid">
    <div class="info-room"><span><?= e(__('Room')) ?></span><strong><?= $b['room'] ? e($b['room']) : '—' ?></strong></div>
    <div><span><?= e(__('Team')) ?></span><strong><?= e($b['team_name']) ?></strong> <small><?= e($b['team_game']) ?></small></div>
    <div><span><?= e(__('Hotel')) ?></span><strong><?= e($b['hotel_name']) ?></strong></div>
    <div><span><?= e(__('Dates')) ?></span><strong><?= e(fmt_range($b['start_date'], $b['end_date'])) ?></strong><small><?= e(__n($days, '%d day', '%d days')) ?> · <?= e(lbl(BOOTCAMP_STATUSES, $b['status'])) ?></small></div>
    <div><span><?= e(__('Feedback')) ?></span><?php if ($feedback): ?><strong class="stars"><?= stars((int) $feedback['rating']) ?></strong><?php else: ?><strong>—</strong><small><?= e(__('not given yet')) ?></small><?php endif; ?></div>
</div>

<h2><?= e(__('Extra equipment')) ?></h2>
<?php if (!$approved): ?>
    <p class="note"><?= e(__('No extra equipment during this bootcamp.')) ?></p>
<?php else: ?>
    <table class="grid">
        <thead><tr><th><?= e(__('Item')) ?></th><th class="c"><?= e(__('Qty')) ?></th><th><?= e(__('In room')) ?></th><th class="c"><?= e(__('Days')) ?></th><th class="c"><?= e(__('Unit-days')) ?></th><?php if ($fin): ?><th class="c"><?= e(__('Price/day')) ?></th><th class="c"><?= e(__('Total')) ?></th><?php endif; ?><th><?= e(__('Delivered')) ?></th><th><?= e(__('Requested')) ?></th></tr></thead>
        <tbody>
        <?php $totalUnitDays = 0; $grand = 0.0;
        foreach ($approved as $x):
            $from = $x['bill_from'] ?: $b['start_date'];
            $to = $x['bill_to'] ?: $b['end_date'];
            $d = days_between($from, $to);
            $totalUnitDays += $d * (int) $x['qty']; ?>
            <tr>
                <td><strong><?= e($x['equipment_name']) ?></strong><?= $x['note'] ? '<br><small>' . e($x['note']) . '</small>' : '' ?></td>
                <td class="c"><?= (int) $x['qty'] ?></td>
                <td class="nowrap"><?= e(fmt_date($from, 'd M')) ?> → <?= e(fmt_date($to, 'd M')) ?></td>
                <td class="c"><?= $d ?></td>
                <td class="c"><strong><?= $d * (int) $x['qty'] ?></strong></td>
                <?php if ($fin): $line = $x['price_per_day'] !== null ? (float) $x['price_per_day'] * $d * (int) $x['qty'] : null; $grand += (float) $line; ?>
                    <td class="c nowrap"><?= $x['price_per_day'] !== null ? e(money($x['price_per_day'])) : '—' ?></td>
                    <td class="c nowrap"><strong><?= $line !== null ? e(money((string) $line)) : '—' ?></strong></td>
                <?php endif; ?>
                <td><?= $x['delivered_at'] ? e(fmt_date($x['delivered_at'], 'd M H:i')) : '—' ?></td>
                <td><small><?= e(fmt_date($x['requested_at'], 'd M')) ?></small></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php if ($fin && $grand > 0): ?><p class="grand"><?= e(__('Extra equipment total:')) ?> <strong><?= e(money((string) $grand)) ?></strong></p><?php endif; ?>
    <p class="note"><?= e(__('Unit-days = quantity × days in the room (e.g. 2 monitors for 3 days = 6).')) ?></p>
<?php endif; ?>
<?php if ($other): ?>
    <p class="note"><?= e(__('Not delivered:')) ?> <?php foreach ($other as $i => $x): ?><?= $i ? ', ' : '' ?><?= (int) $x['qty'] ?>× <?= e($x['equipment_name']) ?> (<?= e(lbl(EXTRA_STATUSES, $x['status'])) ?>)<?php endforeach; ?></p>
<?php endif; ?>

<h2><?= e(__('Booked equipment')) ?></h2>
<p><?php foreach ($items as $i => $it): ?><?= $i ? ' · ' : '' ?><strong><?= (int) $it['qty'] ?>×</strong> <?= e($it['name']) ?><?php endforeach; ?><?= $items ? '' : '—' ?></p>
<p><small><?= e(__('Chairs: %s', chair_summary_text($chairs))) ?> · <?= e(__('Drinks: %s', $drinks ? implode(', ', array_column($drinks, 'name')) : __('not chosen'))) ?> · <?= e(__('Refill requests: %d', (int) $refills)) ?></small></p>

<h2><?= e(__('Check-out')) ?></h2>
<p><small><?= e(__('Room setup checklist: %d/%d done', $setupDone, $setupTotal)) ?></small></p>
<ul class="checks">
    <?php foreach ($checkout as $c): ?>
        <li><span class="box<?= $c['done_at'] ? ' ticked' : '' ?>"></span> <?= e($c['label']) ?><?= $c['done_at'] ? ' <small>' . e(($c['done_name'] ?? '') . ', ' . ldate('d M H:i', strtotime($c['done_at']))) . '</small>' : '' ?></li>
    <?php endforeach; ?>
</ul>
<?php if ($b['checkout_notes']): ?>
    <h3><?= e(__('Damage / notes')) ?></h3>
    <p class="pre"><?= nl2br_e($b['checkout_notes']) ?></p>
<?php endif; ?>
<?php if ($photos): ?>
    <div class="photo-grid"><?php foreach ($photos as $p): ?><figure><img src="<?= e(url('/photos/' . $p['id'])) ?>" alt=""><?php if ($p['caption']): ?><figcaption><?= e($p['caption']) ?></figcaption><?php endif; ?></figure><?php endforeach; ?></div>
<?php endif; ?>

<h2><?= e(__('Tickets')) ?> (<?= count($tickets) ?>)</h2>
<?php if (!$tickets): ?>
    <p class="note"><?= e(__('No tickets.')) ?></p>
<?php else: ?>
    <table class="grid">
        <thead><tr><th class="c">#</th><th><?= e(__('Subject')) ?></th><th><?= e(__('Category')) ?></th><th><?= e(__('Opened')) ?></th><th><?= e(__('Status')) ?></th></tr></thead>
        <tbody>
        <?php foreach ($tickets as $t): ?>
            <tr><td class="c"><?= (int) $t['id'] ?></td><td><?= e($t['subject']) ?></td><td><?= e(lbl(TICKET_CATEGORIES, $t['category'])) ?></td><td class="nowrap"><?= e(fmt_date($t['created_at'], 'd M H:i')) ?></td><td><?= e(lbl(TICKET_STATUSES, $t['status'])) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php if ($feedback): ?>
    <h2><?= e(__('Team feedback')) ?></h2>
    <p><strong class="stars"><?= stars((int) $feedback['rating']) ?></strong> <small><?= e($feedback['user_name'] ?? '') ?>, <?= e(fmt_dt($feedback['created_at'])) ?></small></p>
    <?php if ($feedback['comment']): ?><p class="pre">“<?= nl2br_e($feedback['comment']) ?>”</p><?php endif; ?>
<?php endif; ?>
