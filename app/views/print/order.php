<?php
$setup = $phase === 'setup';
$docType = $setup ? __('Room setup order') : __('Pickup order');
$other = array_values(array_filter($checklist, fn ($c) => $c['item_key'] === null || !preg_match('/^(eq|extra|out_eq|out_extra):/', (string) $c['item_key'])));
$n = 0;
?>
<h1><?= e($docType) ?></h1>

<div class="info-grid">
    <div class="info-room"><span><?= e(__('Room')) ?></span><strong><?= $b['room'] ? e($b['room']) : '____' ?></strong></div>
    <div><span><?= e(__('Team')) ?></span><strong><?= e($b['team_name']) ?></strong> <small><?= e($b['team_game']) ?></small></div>
    <div><span><?= e(__('Hotel')) ?></span><strong><?= e($b['hotel_name']) ?></strong><small><?= e(trim(($b['hotel_address'] ?? '') . ', ' . ($b['hotel_city'] ?? ''), ', ')) ?></small></div>
    <div><span><?= e($setup ? __('Ready by') : __('Collect after')) ?></span><strong><?= $setup && $b['ready_by'] ? e(ldate('D d M Y, H:i', strtotime($b['ready_by']))) : e(fmt_date($setup ? $b['start_date'] : $b['end_date'], 'D d M Y')) ?></strong><small><?= e(__('Bootcamp %s', fmt_range($b['start_date'], $b['end_date']))) ?></small></div>
    <div><span><?= e(__('Team contact')) ?></span>
        <?php if (!$contacts): ?><strong>—</strong><?php endif; ?>
        <?php foreach ($contacts as $c): ?><strong><?= e($c['name']) ?></strong><small><?= e($c['phone'] ?: $c['email']) ?></small><?php endforeach; ?>
    </div>
</div>

<h2><?= e($setup ? __('Equipment to bring & install') : __('Equipment to collect')) ?></h2>
<table class="grid">
    <thead><tr><th class="c">#</th><th><?= e(__('Item')) ?></th><th><?= e(__('Spec / note')) ?></th><th class="c"><?= e(__('Qty')) ?></th><th class="c box-col">✓</th></tr></thead>
    <tbody>
    <?php foreach ($items as $it): ?>
        <tr><td class="c"><?= ++$n ?></td><td><strong><?= e($it['name']) ?></strong></td><td><?= e($it['spec']) ?></td><td class="c qty"><?= (int) $it['qty'] ?></td><td class="c"><span class="box"></span></td></tr>
    <?php endforeach; ?>
    <?php foreach ($extras as $x):
        $from = $x['bill_from'] ?: $b['start_date'];
        $to = $x['bill_to'] ?: $b['end_date']; ?>
        <tr class="extra"><td class="c"><?= ++$n ?></td><td><strong><?= e($x['equipment_name']) ?></strong> <em><?= e(__('extra')) ?></em></td><td><?= e($setup ? __('Bring on %s', fmt_date($from, 'd M')) : __('In room until %s', fmt_date($to, 'd M'))) ?><?= $x['note'] ? ' · ' . e($x['note']) : '' ?></td><td class="c qty"><?= (int) $x['qty'] ?></td><td class="c"><span class="box"></span></td></tr>
    <?php endforeach; ?>
    <?php if (!$items && !$extras): ?><tr><td colspan="5" class="c"><?= e(__('No equipment booked.')) ?></td></tr><?php endif; ?>
    </tbody>
</table>

<?php if ($setup && $chairs): ?>
    <h2><?= e(__('Chairs · armrests')) ?></h2>
    <table class="grid chairs">
        <thead><tr><th class="c"><?= e(__('Chair')) ?></th><th><?= e(__('Armrests')) ?></th><th class="c box-col">✓</th></tr></thead>
        <tbody>
        <?php foreach ($chairs as $no => $opt): ?>
            <tr class="<?= $opt !== 'keep' ? 'hl' : '' ?>"><td class="c"><strong><?= (int) $no ?></strong></td><td><?= e(lbl(ARMREST_OPTIONS, $opt)) ?><?= e($opt === 'remove_both' ? ' — ' . __('take off left and right') : ($opt === 'remove_left' ? ' — ' . __('take off LEFT') : ($opt === 'remove_right' ? ' — ' . __('take off RIGHT') : ''))) ?></td><td class="c"><span class="box"></span></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php if ($b['chairs_note']): ?><p class="pre"><strong><?= e(__('Team note:')) ?></strong> <?= nl2br_e($b['chairs_note']) ?></p><?php endif; ?>
<?php endif; ?>

<?php if ($setup): ?>
    <h2><?= e(__('Fridge')) ?></h2>
    <?php if (!$drinks): ?>
        <p class="note"><?= e(__('The team has not chosen drinks — stock the standard selection.')) ?></p>
    <?php else: ?>
        <div class="drink-list"><?php foreach ($drinks as $d): ?><span><span class="box"></span> <?= e($d['name']) ?></span><?php endforeach; ?></div>
    <?php endif; ?>
    <?php if ($b['drinks_note']): ?><p class="pre"><strong><?= e(__('Team note:')) ?></strong> <?= nl2br_e($b['drinks_note']) ?></p><?php endif; ?>
<?php endif; ?>

<?php if ($other): ?>
    <h2><?= e($setup ? __('Before the team arrives') : __('Check-out')) ?></h2>
    <ul class="checks">
        <?php foreach ($other as $c): ?>
            <li><span class="box<?= $c['done_at'] ? ' ticked' : '' ?>"></span> <strong><?= e($c['label']) ?></strong><?php if ($c['detail'] && $c['item_key'] !== 'drinks'): ?> <small><?= e($c['detail']) ?></small><?php endif; ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php if ($setup && $b['client_info']): ?>
    <h2><?= e(__('Info given to the team')) ?></h2>
    <p class="pre"><?= nl2br_e($b['client_info']) ?></p>
<?php endif; ?>
<?php if ($b['staff_notes']): ?>
    <h2><?= e(__('Internal notes')) ?></h2>
    <p class="pre"><?= nl2br_e($b['staff_notes']) ?></p>
<?php endif; ?>

<?php if (!$setup): ?>
    <h2><?= e(__('Damage / missing items')) ?></h2>
    <?php if ($b['checkout_notes']): ?><p class="pre"><?= nl2br_e($b['checkout_notes']) ?></p><?php endif; ?>
    <div class="lines"><span></span><span></span><span></span><span></span></div>
<?php endif; ?>

<div class="signatures">
    <div><span><?= e($setup ? __('Prepared by') : __('Collected by')) ?></span></div>
    <div><span><?= e($setup ? __('Installed by') : __('Checked by')) ?></span></div>
    <div><span><?= e(__('Date & time')) ?></span></div>
</div>
