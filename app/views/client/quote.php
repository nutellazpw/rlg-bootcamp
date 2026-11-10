<div class="page-head">
    <div>
        <h1>Request a bootcamp</h1>
        <p class="muted">Planning your next bootcamp? Tell us what you need and our bootcamp manager will send you an offer.</p>
    </div>
</div>

<?php if ($errors): ?><div class="alert alert-error"><?php foreach ($errors as $er): ?><div><?= e($er) ?></div><?php endforeach; ?></div><?php endif; ?>

<div class="layout-side">
    <div class="col-main">
        <form method="post" action="<?= e(url('/my/new-bootcamp')) ?>" class="form card">
            <?= csrf_field() ?>
            <div class="form-grid">
                <label class="field"><span>From</span><input type="date" name="start_date" value="<?= e(input('start_date')) ?>" min="<?= e(date('Y-m-d')) ?>" required></label>
                <label class="field"><span>To</span><input type="date" name="end_date" value="<?= e(input('end_date')) ?>" min="<?= e(date('Y-m-d')) ?>" required></label>
            </div>
            <label class="check"><input type="checkbox" name="flexible" value="1"<?= checked((bool) input('flexible')) ?>><span>Our dates are flexible</span></label>
            <div class="form-grid">
                <label class="field"><span>Number of people</span><input type="number" name="people" min="1" max="99" value="<?= e(input('people')) ?>" placeholder="players + staff"></label>
                <label class="field"><span>Number of PCs</span><input type="number" name="pcs" min="0" max="99" value="<?= e(input('pcs')) ?>"></label>
            </div>
            <label class="field"><span>Preferred hotel <small>(optional)</small></span><input type="text" name="hotel_pref" maxlength="120" value="<?= e(input('hotel_pref')) ?>" placeholder="Any / Hyatt / Radisson…"></label>
            <label class="field"><span>What else do you need?</span><textarea name="note" rows="5" maxlength="3000" placeholder="Monitors (Hz), extra equipment, activities, budget, anything we should know"><?= e(input('note')) ?></textarea></label>
            <div><button class="btn" type="submit">Send request</button></div>
        </form>
    </div>
    <aside class="col-side">
        <section class="card">
            <h2 class="card-title">Your requests</h2>
            <?php if (!$quotes): ?>
                <p class="muted small">No requests yet.</p>
            <?php else: ?>
                <div class="list list-compact">
                    <?php foreach ($quotes as $q): ?>
                        <div class="list-row">
                            <div class="list-main">
                                <div class="list-title"><?= e(fmt_range($q['start_date'], $q['end_date'])) ?><?= $q['flexible'] ? ' <span class="tag">flexible</span>' : '' ?></div>
                                <div class="list-sub">sent <?= e(fmt_date($q['created_at'])) ?><?= $q['people'] ? ' · ' . (int) $q['people'] . ' people' : '' ?></div>
                                <?php if ($q['staff_reply']): ?><div class="staff-reply"><strong>Relog:</strong> <?= nl2br_e($q['staff_reply']) ?></div><?php endif; ?>
                            </div>
                            <div class="list-side"><?= status_badge($q['status'] === 'converted' ? 'confirmed' : $q['status'], ['new' => 'Sent', 'in_progress' => 'In progress', 'confirmed' => 'Booked', 'declined' => 'Declined']) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </aside>
</div>
