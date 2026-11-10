<?php $manage = can_manage(); ?>
<div class="page-head">
    <div>
        <h1><?= e(__('Requests')) ?></h1>
        <p class="muted"><?= e(__('New bootcamp requests from teams and extra activity requests.')) ?></p>
    </div>
    <?php if ($manage): ?><a class="btn btn-ghost" href="<?= e(url('/activities')) ?>"><?= e(__('Edit activities')) ?></a><?php endif; ?>
</div>

<div class="toolbar">
    <div class="tabs">
        <a class="tab<?= $tab === 'quotes' ? ' active' : '' ?>" href="<?= e(url('/requests')) ?>"><?= e(__('Bootcamp requests')) ?><?php if ($counts['quotes']): ?> <span class="nav-count"><?= $counts['quotes'] ?></span><?php endif; ?></a>
        <a class="tab<?= $tab === 'activities' ? ' active' : '' ?>" href="<?= e(url('/requests', ['tab' => 'activities'])) ?>"><?= e(__('Activities')) ?><?php if ($counts['activities']): ?> <span class="nav-count"><?= $counts['activities'] ?></span><?php endif; ?></a>
    </div>
</div>

<?php if ($tab === 'quotes'): ?>
    <?php if (!$quotes): ?><section class="card"><p class="muted"><?= e(__('No bootcamp requests yet.')) ?></p></section><?php endif; ?>
    <div class="request-list">
        <?php foreach ($quotes as $q): $needs = $q['status'] === 'new'; ?>
            <section class="card request<?= $needs ? ' needs-action' : '' ?>">
                <div class="card-head">
                    <h2 class="card-title"><a href="<?= e(url('/teams/' . $q['team_id'])) ?>"><?= e($q['team_name']) ?></a> · <?= e(fmt_range($q['start_date'], $q['end_date'])) ?><?= $q['flexible'] ? ' <span class="tag">' . e(__('flexible')) . '</span>' : '' ?></h2>
                    <?= $needs ? '<span class="action-tag">' . e(__('New')) . '</span>' : status_badge($q['status'] === 'converted' ? 'completed' : $q['status'], ['in_progress' => 'In progress', 'completed' => 'Booked', 'declined' => 'Declined']) ?>
                </div>
                <dl class="kv">
                    <dt><?= e(__('People')) ?></dt><dd><?= $q['people'] ? (int) $q['people'] : '—' ?></dd>
                    <dt><?= e(__('PCs')) ?></dt><dd><?= $q['pcs'] !== null ? (int) $q['pcs'] : '—' ?></dd>
                    <dt><?= e(__('Hotel')) ?></dt><dd><?= e($q['hotel_pref'] ?: __('Any')) ?></dd>
                    <dt><?= e(__('Sent')) ?></dt><dd><?= e(__('%s by %s', fmt_dt($q['created_at']), $q['requested_name'] ?? '—')) ?></dd>
                </dl>
                <?php if ($q['note']): ?><p class="pre-note"><?= nl2br_e($q['note']) ?></p><?php endif; ?>
                <?php if ($manage): ?>
                    <form method="post" action="<?= e(url('/quotes/' . $q['id'])) ?>" class="form reply-form">
                        <?= csrf_field() ?>
                        <label class="field"><span><?= e(__('Reply to the team')) ?> <small><?= e(__('(offer, questions…)')) ?></small></span><textarea name="staff_reply" rows="2"><?= e($q['staff_reply']) ?></textarea></label>
                        <div class="btn-row">
                            <select name="status"><?php foreach (QUOTE_STATUSES as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($k, $q['status']) ?>><?= e(__($l)) ?></option><?php endforeach; ?></select>
                            <button class="btn btn-sm" type="submit"><?= e(__('Save & notify team')) ?></button>
                            <?php if ($q['bootcamp_id']): ?>
                                <a class="btn btn-sm btn-ghost" href="<?= e(url('/bootcamps/' . $q['bootcamp_id'])) ?>"><?= e(__('Open bootcamp →')) ?></a>
                            <?php elseif ($q['status'] !== 'declined'): ?>
                                <a class="btn btn-sm btn-outline" href="<?= e(url('/bootcamps/new', ['quote' => $q['id']])) ?>"><?= e(__('Create bootcamp from this')) ?></a>
                            <?php endif; ?>
                        </div>
                    </form>
                <?php elseif ($q['staff_reply']): ?>
                    <div class="staff-reply"><strong><?= e($q['handled_name'] ?? 'Relog') ?>:</strong> <?= nl2br_e($q['staff_reply']) ?></div>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <?php if (!$activityRequests): ?><section class="card"><p class="muted"><?= e(__('No activity requests yet.')) ?></p></section><?php endif; ?>
    <div class="request-list">
        <?php foreach ($activityRequests as $r): $needs = $r['status'] === 'pending'; ?>
            <section class="card request<?= $needs ? ' needs-action' : '' ?>">
                <div class="card-head">
                    <h2 class="card-title"><?= e($r['activity_name']) ?> · <a href="<?= e(url($r['bootcamp_id'] ? '/bootcamps/' . $r['bootcamp_id'] : '/teams/' . $r['team_id'])) ?>"><?= e($r['team_name']) ?></a></h2>
                    <?= $needs ? '<span class="action-tag">' . e(__('Needs reply')) . '</span>' : status_badge($r['status'], ACTIVITY_STATUSES) ?>
                </div>
                <dl class="kv">
                    <dt><?= e(__('When')) ?></dt><dd><?= $r['preferred_date'] ? e(fmt_date($r['preferred_date'], 'D d M Y')) : '—' ?><?= $r['preferred_time'] ? ' · ' . e($r['preferred_time']) : '' ?></dd>
                    <dt><?= e(__('People')) ?></dt><dd><?= $r['people'] ? (int) $r['people'] : '—' ?></dd>
                    <?php if ($r['hotel_name']): ?><dt><?= e(__('Where')) ?></dt><dd><?= e($r['hotel_name']) ?><?= $r['room'] ? ' · ' . e(__('room %s', $r['room'])) : '' ?></dd><?php endif; ?>
                    <dt><?= e(__('Sent')) ?></dt><dd><?= e(__('%s by %s', fmt_dt($r['created_at']), $r['requested_name'] ?? '—')) ?></dd>
                </dl>
                <?php if ($r['note']): ?><p class="pre-note"><?= nl2br_e($r['note']) ?></p><?php endif; ?>
                <?php partial('requests/_activity_reply', ['r' => $r]); ?>
            </section>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
