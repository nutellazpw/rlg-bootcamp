<?php
$docType = 'Bootcamp report';
$approved = array_values(array_filter($extras, fn ($x) => $x['status'] === 'approved'));
$other = array_values(array_filter($extras, fn ($x) => $x['status'] !== 'approved'));
$days = days_between($b['start_date'], $b['end_date']);
[$setupDone, $setupTotal] = $setup;
?>
<h1>Bootcamp report</h1>

<div class="info-grid">
    <div class="info-room"><span>Room</span><strong><?= $b['room'] ? e($b['room']) : '—' ?></strong></div>
    <div><span>Team</span><strong><?= e($b['team_name']) ?></strong> <small><?= e($b['team_game']) ?></small></div>
    <div><span>Hotel</span><strong><?= e($b['hotel_name']) ?></strong></div>
    <div><span>Dates</span><strong><?= e(fmt_range($b['start_date'], $b['end_date'])) ?></strong><small><?= $days ?> days · <?= e(BOOTCAMP_STATUSES[$b['status']] ?? $b['status']) ?></small></div>
    <div><span>Feedback</span><?php if ($feedback): ?><strong class="stars"><?= stars((int) $feedback['rating']) ?></strong><?php else: ?><strong>—</strong><small>not given yet</small><?php endif; ?></div>
</div>

<h2>Extra equipment</h2>
<?php if (!$approved): ?>
    <p class="note">No extra equipment during this bootcamp.</p>
<?php else: ?>
    <table class="grid">
        <thead><tr><th>Item</th><th class="c">Qty</th><th>In room</th><th class="c">Days</th><th class="c">Unit-days</th><th>Delivered</th><th>Requested</th></tr></thead>
        <tbody>
        <?php $totalUnitDays = 0;
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
                <td><?= $x['delivered_at'] ? e(fmt_date($x['delivered_at'], 'd M H:i')) : '—' ?></td>
                <td><small><?= e(fmt_date($x['requested_at'], 'd M')) ?></small></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p class="note">Unit-days = quantity × days in the room (e.g. 2 monitors for 3 days = 6).</p>
<?php endif; ?>
<?php if ($other): ?>
    <p class="note">Not delivered: <?php foreach ($other as $i => $x): ?><?= $i ? ', ' : '' ?><?= (int) $x['qty'] ?>× <?= e($x['equipment_name']) ?> (<?= e(EXTRA_STATUSES[$x['status']] ?? $x['status']) ?>)<?php endforeach; ?></p>
<?php endif; ?>

<h2>Booked equipment</h2>
<p><?php foreach ($items as $i => $it): ?><?= $i ? ' · ' : '' ?><strong><?= (int) $it['qty'] ?>×</strong> <?= e($it['name']) ?><?php endforeach; ?><?= $items ? '' : '—' ?></p>
<p><small>Chairs: <?= e(chair_summary_text($chairs)) ?> · Drinks: <?= e($drinks ? implode(', ', array_column($drinks, 'name')) : 'not chosen') ?> · Refill requests: <?= (int) $refills ?></small></p>

<h2>Check-out</h2>
<p><small>Room setup checklist: <?= $setupDone ?>/<?= $setupTotal ?> done</small></p>
<ul class="checks">
    <?php foreach ($checkout as $c): ?>
        <li><span class="box<?= $c['done_at'] ? ' ticked' : '' ?>"></span> <?= e($c['label']) ?><?= $c['done_at'] ? ' <small>' . e(($c['done_name'] ?? '') . ', ' . date('d M H:i', strtotime($c['done_at']))) . '</small>' : '' ?></li>
    <?php endforeach; ?>
</ul>
<?php if ($b['checkout_notes']): ?>
    <h3>Damage / notes</h3>
    <p class="pre"><?= nl2br_e($b['checkout_notes']) ?></p>
<?php endif; ?>
<?php if ($photos): ?>
    <div class="photo-grid"><?php foreach ($photos as $p): ?><figure><img src="<?= e(url('/photos/' . $p['id'])) ?>" alt=""><?php if ($p['caption']): ?><figcaption><?= e($p['caption']) ?></figcaption><?php endif; ?></figure><?php endforeach; ?></div>
<?php endif; ?>

<h2>Tickets (<?= count($tickets) ?>)</h2>
<?php if (!$tickets): ?>
    <p class="note">No tickets.</p>
<?php else: ?>
    <table class="grid">
        <thead><tr><th class="c">#</th><th>Subject</th><th>Category</th><th>Opened</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($tickets as $t): ?>
            <tr><td class="c"><?= (int) $t['id'] ?></td><td><?= e($t['subject']) ?></td><td><?= e(TICKET_CATEGORIES[$t['category']] ?? $t['category']) ?></td><td class="nowrap"><?= e(fmt_date($t['created_at'], 'd M H:i')) ?></td><td><?= e(TICKET_STATUSES[$t['status']] ?? $t['status']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php if ($feedback): ?>
    <h2>Team feedback</h2>
    <p><strong class="stars"><?= stars((int) $feedback['rating']) ?></strong> <small><?= e($feedback['user_name'] ?? '') ?>, <?= e(fmt_dt($feedback['created_at'])) ?></small></p>
    <?php if ($feedback['comment']): ?><p class="pre">“<?= nl2br_e($feedback['comment']) ?>”</p><?php endif; ?>
<?php endif; ?>
