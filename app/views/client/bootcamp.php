<?php
$today = date('Y-m-d');
$total = days_between($b['start_date'], $b['end_date']);
if ($today < $b['start_date']) {
    $daysTo = days_between($today, $b['start_date']) - 1;
    $when = $daysTo === 1 ? 'Starts tomorrow' : 'Starts in ' . $daysTo . ' days';
    $progress = 0;
} elseif ($today > $b['end_date']) {
    $when = 'Finished';
    $progress = 100;
} else {
    $day = days_between($b['start_date'], $today);
    $when = 'Day ' . $day . ' of ' . $total;
    $progress = (int) round($day / $total * 100);
}
$chairChanged = count(array_filter($chairs, fn ($c) => $c !== 'keep'));
$logo = team_logo_url(['id' => $b['team_id'], 'logo' => $teamLogo]);
$selectedDrinks = array_values(array_filter($drinks, fn ($d) => in_array((int) $d['id'], $drinkIds, true)));
$drinksDone = !empty($b['drinks_saved_at']);
$chairsDone = !empty($b['chairs_saved_at']);
$showPrices = !empty($b['show_prices']);
?>
<section class="hero<?= $logo ? ' hero-has-logo' : '' ?>">
    <?php if ($logo): ?><div class="hero-bg" style="background-image:url('<?= e($logo) ?>')" aria-hidden="true"></div><?php endif; ?>
    <div class="hero-main">
        <div class="hero-team">
            <?php if ($logo): ?><img class="hero-logo" src="<?= e($logo) ?>" alt=""><?php endif; ?>
            <div>
                <span class="hero-kicker">Bootcamp · <?= e($b['team_game']) ?></span>
                <h1 class="hero-title"><?= e($b['team_name']) ?></h1>
            </div>
        </div>
        <p class="hero-sub"><strong><?= e($b['hotel_name']) ?></strong><?= $b['hotel_address'] ? ' · ' . e($b['hotel_address']) : '' ?><?= $b['hotel_city'] ? ', ' . e($b['hotel_city']) : '' ?></p>
        <div class="hero-meta">
            <div><span class="label">Dates</span><strong><?= e(fmt_range($b['start_date'], $b['end_date'])) ?></strong></div>
            <div><span class="label">Status</span><?= status_badge($b['status'], BOOTCAMP_STATUSES) ?></div>
            <div><span class="label"><?= e($when) ?></span><div class="progress"><i style="width:<?= $progress ?>%"></i></div></div>
        </div>
    </div>
    <div class="hero-room">
        <span class="label">Room</span>
        <strong><?= $b['room'] ? e($b['room']) : '—' ?></strong>
        <?php if (!$b['room']): ?><small>Assigned before arrival</small><?php endif; ?>
    </div>
</section>

<?php if ($canRate): ?>
    <section class="card card-accent" id="feedback">
        <?php if ($feedback): ?>
            <h2 class="card-title">Thanks for your feedback!</h2>
            <div class="stars-big"><?= stars((int) $feedback['rating']) ?></div>
            <?php if ($feedback['comment']): ?><p class="muted">“<?= nl2br_e($feedback['comment']) ?>”</p><?php endif; ?>
        <?php else: ?>
            <h2 class="card-title">How was your bootcamp?</h2>
            <p class="muted small">Rate your stay and tell us what we can do better.</p>
            <form method="post" action="<?= e(url('/my/bootcamp/' . $b['id'] . '/feedback')) ?>" class="form">
                <?= csrf_field() ?>
                <div class="rating" role="radiogroup" aria-label="Rating">
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <input type="radio" name="rating" id="r<?= $i ?>" value="<?= $i ?>" required><label for="r<?= $i ?>" title="<?= $i ?> / 5">★</label>
                    <?php endfor; ?>
                </div>
                <label class="field"><span>Comment <small>(optional)</small></span><textarea name="comment" rows="3" placeholder="What was great? What should we improve?"></textarea></label>
                <div><button class="btn btn-sm" type="submit">Send feedback</button></div>
            </form>
        <?php endif; ?>
    </section>
<?php endif; ?>

<nav class="subnav">
    <a href="#room">Room</a><a href="#drinks">Drinks</a><a href="#chairs">Chairs</a><a href="#extras">Extras</a><a href="#activities">Activities</a><a href="#support">Support</a>
</nav>

<?php if ($b['client_info']): ?>
    <section class="card card-accent">
        <h2 class="card-title">Info from Relog</h2>
        <p><?= nl2br_e($b['client_info']) ?></p>
    </section>
<?php endif; ?>

<section class="card" id="room">
    <h2 class="card-title">Your setup</h2>
    <?php if (!$items): ?>
        <p class="muted">Equipment details will appear here.</p>
    <?php else: ?>
        <div class="equip-chips">
            <?php foreach ($items as $it): ?>
                <div class="equip-chip"><span class="equip-qty"><?= (int) $it['qty'] ?>×</span><span><?= e($it['name']) ?><?php if ($it['spec']): ?><small><?= e($it['spec']) ?></small><?php endif; ?></span></div>
            <?php endforeach; ?>
            <?php foreach ($extras as $x): if ($x['status'] !== 'approved') { continue; } ?>
                <div class="equip-chip equip-extra"><span class="equip-qty">+<?= (int) $x['qty'] ?></span><span><?= e($x['equipment_name']) ?><small>extra</small></span></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<div class="grid-2">
    <section class="card" id="drinks">
        <details class="fold"<?= $drinksDone ? '' : ' open' ?>>
            <summary>
                <span class="fold-title"><h2 class="card-title">Drinks in your fridge</h2>
                    <?php if ($drinksDone): ?><span class="fold-done">✓ Done</span><?php else: ?><span class="action-tag">To do</span><?php endif; ?></span>
                <span class="fold-summary">
                    <?php if ($selectedDrinks): ?><?= e(implode(', ', array_column($selectedDrinks, 'name'))) ?><?php else: ?>Choose up to <?= DRINKS_MAX ?> drinks<?php endif; ?>
                    <?php if ($b['drinks_note']): ?><em>“<?= e(mb_strimwidth($b['drinks_note'], 0, 80, '…')) ?>”</em><?php endif; ?>
                </span>
                <span class="fold-toggle" aria-hidden="true"></span>
            </summary>
            <p class="muted small">Choose up to <?= DRINKS_MAX ?> drinks for the room. Drinks are included — we keep the fridge stocked.</p>
            <form method="post" action="<?= e(url('/my/bootcamp/' . $b['id'] . '/drinks')) ?>" class="form" data-max-drinks="<?= DRINKS_MAX ?>">
                <?= csrf_field() ?>
                <?php partial('client/_drinks', ['drinks' => $drinks, 'drinkIds' => $drinkIds]); ?>
                <p class="small muted drink-counter"><span data-drink-count><?= count($drinkIds) ?></span> / <?= DRINKS_MAX ?> selected</p>
                <label class="field"><span>Anything else about drinks? <small>(optional)</small></span><textarea name="drinks_note" rows="2" maxlength="1000" placeholder="e.g. no sugar, extra water, energy drinks only for the coach"><?= e($b['drinks_note']) ?></textarea></label>
                <?php if ($open): ?><div><button class="btn btn-sm" type="submit">Save drinks</button></div><?php endif; ?>
            </form>
        </details>
        <?php if ($open && $drinkIds): ?>
            <details class="refill-box" id="refill">
                <summary class="btn btn-sm btn-outline">Request refill</summary>
                <form method="post" action="<?= e(url('/my/bootcamp/' . $b['id'] . '/refill')) ?>" class="refill-form">
                    <?= csrf_field() ?>
                    <p class="small muted">What should we refill?</p>
                    <div class="drink-grid refill-grid">
                        <?php foreach ($selectedDrinks as $d): ?>
                            <label class="drink"><input type="checkbox" name="refill[]" value="<?= (int) $d['id'] ?>"><span><?= e($d['name']) ?></span></label>
                        <?php endforeach; ?>
                    </div>
                    <input type="text" name="note" maxlength="300" placeholder="Anything else? (optional)">
                    <button class="btn btn-sm" type="submit">Send refill request</button>
                </form>
            </details>
        <?php endif; ?>
    </section>

    <section class="card" id="chairs">
        <?php if (!$chairs): ?>
            <h2 class="card-title">Chairs · armrests</h2>
            <p class="muted">No chairs in your booking.</p>
        <?php else: ?>
            <details class="fold"<?= $chairsDone ? '' : ' open' ?>>
                <summary>
                    <span class="fold-title"><h2 class="card-title">Chairs · armrests</h2>
                        <?php if ($chairsDone): ?><span class="fold-done">✓ Done</span><?php else: ?><span class="action-tag">To do</span><?php endif; ?></span>
                    <span class="fold-summary"><?= count($chairs) ?> chairs · <?= e(chair_summary_text($chairs)) ?>
                        <?php if ($b['chairs_note']): ?><em>“<?= e(mb_strimwidth($b['chairs_note'], 0, 80, '…')) ?>”</em><?php endif; ?></span>
                    <span class="fold-toggle" aria-hidden="true"></span>
                </summary>
                <p class="muted small">Choose which armrests to remove on each chair. We will set them up before you arrive.</p>
                <form method="post" action="<?= e(url('/my/bootcamp/' . $b['id'] . '/chairs')) ?>" class="form">
                    <?= csrf_field() ?>
                    <?php partial('client/_chairs', ['chairs' => $chairs]); ?>
                    <label class="field"><span>Anything else about the room setup? <small>(optional)</small></span><textarea name="chairs_note" rows="2" maxlength="1000" placeholder="e.g. desk height, seating order, left-handed player on PC 3"><?= e($b['chairs_note']) ?></textarea></label>
                    <?php if ($open): ?><div><button class="btn btn-sm" type="submit">Save chairs</button></div><?php endif; ?>
                </form>
            </details>
        <?php endif; ?>
    </section>
</div>

<section class="card" id="extras">
    <h2 class="card-title">Extra equipment</h2>
    <?php if ($extras): ?>
        <div class="list list-compact">
            <?php foreach ($extras as $x): ?>
                <div class="list-row">
                    <div class="list-main">
                        <div class="list-title"><?= (int) $x['qty'] ?>× <?= e($x['equipment_name']) ?><?= $x['equipment_id'] ? '' : ' <span class="tag">other</span>' ?></div>
                        <div class="list-sub"><?= e(fmt_dt($x['requested_at'])) ?><?php if ($x['note']): ?> · “<?= e($x['note']) ?>”<?php endif; ?><?php if ($x['staff_note']): ?> · <strong><?= e($x['staff_note']) ?></strong><?php endif; ?><?php if ($x['status'] === 'approved' && $x['bill_from']): ?> · from <?= e(fmt_date($x['bill_from'], 'd M')) ?><?php endif; ?><?php if ($showPrices && $x['status'] === 'approved' && $x['price_per_day'] !== null): ?> · <?= e(money($x['price_per_day'])) ?>/day<?php endif; ?></div>
                    </div>
                    <div class="list-side">
                        <?php if ($x['status'] === 'approved' && $x['delivered_at']): ?><span class="badge badge-completed">Delivered</span>
                        <?php else: ?><?= status_badge($x['status'], EXTRA_STATUSES) ?><?php endif; ?>
                        <?php if ($x['status'] === 'pending'): ?>
                            <form method="post" action="<?= e(url('/my/extras/' . $x['id'] . '/cancel')) ?>"><?= csrf_field() ?><button class="btn btn-xs btn-ghost">Cancel</button></form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if (!$extras): ?><p class="muted small">Need another monitor, a webcam or something else in the room? Send us a request.</p><?php endif; ?>
    <?php if ($open): ?>
        <details class="request-box">
        <summary class="btn btn-sm btn-outline">+ Request extra equipment</summary>
        <form method="post" action="<?= e(url('/my/bootcamp/' . $b['id'] . '/extras')) ?>" class="form extra-form" data-extra-form>
            <?= csrf_field() ?>
            <div class="extra-rows" data-extra-rows>
                <div class="extra-row" data-extra-row>
                    <label class="field"><span>Item</span>
                        <select name="equipment_id[]" data-extra-select>
                            <?php foreach ($catalog as $eq): ?>
                                <option value="<?= (int) $eq['id'] ?>"><?= e($eq['name']) ?><?= $showPrices && $eq['price_per_day'] !== null ? ' — ' . e(money($eq['price_per_day'])) . '/day' : '' ?></option>
                            <?php endforeach; ?>
                            <option value="other">Other (not on the list)…</option>
                        </select>
                    </label>
                    <label class="field"><span>Qty</span><input type="number" name="qty[]" value="1" min="1" max="20"></label>
                    <label class="field extra-other" data-extra-other hidden><span>What do you need?</span><input type="text" name="other_name[]" maxlength="190" placeholder="e.g. second keyboard, HDMI splitter, ring light"></label>
                    <button type="button" class="btn-icon extra-remove" data-extra-remove title="Remove" aria-label="Remove" hidden>×</button>
                </div>
            </div>
            <button type="button" class="btn btn-xs btn-ghost extra-add" data-extra-add>+ Add another item</button>
            <label class="field"><span>Note <small>(optional)</small></span><input type="text" name="note" maxlength="500" placeholder="e.g. for the coach, from tomorrow"></label>
            <div><button class="btn btn-sm" type="submit">Send request</button></div>
            <p class="hint">The bootcamp manager confirms each request<?= $showPrices ? '. Extra equipment is charged per day.' : '.' ?></p>
        </form>
        </details>
    <?php endif; ?>
</section>

<section class="card" id="activities">
    <h2 class="card-title">Extra activities</h2>
    <p class="muted small">Want more out of your bootcamp? Send us a request and we will get back to you with the details and the offer.</p>
    <?php if ($activityRequests): ?>
        <div class="list list-compact activity-requests">
            <?php foreach ($activityRequests as $r): ?>
                <div class="list-row">
                    <div class="list-main">
                        <div class="list-title"><?= e($r['activity_name']) ?></div>
                        <div class="list-sub"><?= $r['preferred_date'] ? e(fmt_date($r['preferred_date'], 'D d M')) . ($r['preferred_time'] ? ' ' . e($r['preferred_time']) : '') . ' · ' : '' ?><?= $r['people'] ? (int) $r['people'] . ' people · ' : '' ?>sent <?= e(time_ago($r['created_at'])) ?></div>
                        <?php if ($r['staff_reply']): ?><div class="staff-reply"><strong>Relog:</strong> <?= nl2br_e($r['staff_reply']) ?></div><?php endif; ?>
                    </div>
                    <div class="list-side"><?= status_badge($r['status'], ACTIVITY_STATUSES) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <div class="activity-grid">
        <?php foreach ($activities as $a): ?>
            <article class="activity">
                <img class="activity-img" src="<?= e(activity_image_url($a)) ?>" alt="" loading="lazy">
                <div class="activity-body">
                    <h3><?= e($a['name']) ?></h3>
                    <p><?= nl2br_e($a['description']) ?></p>
                    <?php if ($open): ?>
                        <details class="activity-form">
                            <summary class="btn btn-sm btn-outline">Request</summary>
                            <form method="post" action="<?= e(url('/my/bootcamp/' . $b['id'] . '/activities')) ?>" class="form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="activity_id" value="<?= (int) $a['id'] ?>">
                                <div class="form-grid">
                                    <label class="field"><span>Preferred date</span><input type="date" name="preferred_date" min="<?= e(date('Y-m-d')) ?>"></label>
                                    <label class="field"><span>Time</span><input type="text" name="preferred_time" maxlength="30" placeholder="e.g. 14:00 or afternoon"></label>
                                </div>
                                <label class="field"><span>Number of people</span><input type="number" name="people" min="1" max="99"></label>
                                <label class="field"><span>Details</span><textarea name="note" rows="3" maxlength="2000" placeholder="Tell us what you need"></textarea></label>
                                <div><button class="btn btn-sm" type="submit">Send request</button></div>
                            </form>
                        </details>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="card" id="support">
    <div class="card-head">
        <h2 class="card-title">Support</h2>
        <a class="btn btn-sm" href="<?= e(url('/tickets/new', ['bootcamp' => $b['id']])) ?>">+ New ticket</a>
    </div>
    <?php if (!$tickets): ?>
        <p class="muted small">Something not working or missing in the room? Open a ticket — our team is notified immediately.</p>
    <?php else: ?>
        <div class="list">
            <?php foreach ($tickets as $t): ?>
                <a class="list-row" href="<?= e(url('/tickets/' . $t['id'])) ?>">
                    <span class="cat-dot cat-<?= e($t['category']) ?>"></span>
                    <div class="list-main"><div class="list-title"><?= e($t['subject']) ?></div><div class="list-sub"><?= e(time_ago($t['updated_at'])) ?></div></div>
                    <div class="list-side"><?= status_badge($t['status'], TICKET_STATUSES) ?></div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php if ($others): ?>
    <section class="card">
        <h2 class="card-title">Other bootcamps</h2>
        <div class="list">
            <?php foreach ($others as $o): ?>
                <a class="list-row" href="<?= e(url('/my/bootcamp/' . $o['id'])) ?>">
                    <div class="list-main"><div class="list-title"><?= e($o['hotel_name']) ?></div><div class="list-sub"><?= e(fmt_range($o['start_date'], $o['end_date'])) ?></div></div>
                    <div class="list-side"><?= status_badge($o['status'], BOOTCAMP_STATUSES) ?></div>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>
