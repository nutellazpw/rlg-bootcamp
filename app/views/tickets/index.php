<div class="page-head">
    <div>
        <h1><?= $staff ? 'Tickets' : 'Support' ?></h1>
        <?php if (!$staff): ?><p class="muted">Need something in the room? Open a ticket — our team gets notified right away.</p><?php endif; ?>
    </div>
    <?php if (!$staff): ?><a class="btn" href="<?= e(url('/tickets/new')) ?>">+ New ticket</a><?php endif; ?>
</div>

<div class="toolbar">
    <div class="tabs">
        <?php
        $tabs = ['active' => 'Active', 'resolved' => 'Resolved', 'all' => 'All'];
        if ($staff) {
            $tabs = ['active' => 'Active', 'mine' => 'Assigned to me', 'resolved' => 'Resolved', 'all' => 'All'];
        }
        foreach ($tabs as $k => $label): ?>
            <a class="tab<?= $tab === $k ? ' active' : '' ?>" href="<?= e(url('/tickets', ['tab' => $k] + ($cat ? ['category' => $cat] : []))) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </div>
    <?php if ($staff): ?>
        <form method="get" action="<?= e(url('/tickets')) ?>" class="inline-form">
            <input type="hidden" name="tab" value="<?= e($tab) ?>">
            <select name="category" onchange="this.form.submit()">
                <option value="">All categories</option>
                <?php foreach (TICKET_CATEGORIES as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($k, $cat) ?>><?= e($l) ?></option><?php endforeach; ?>
            </select>
        </form>
    <?php endif; ?>
</div>

<section class="card card-flush">
    <?php if (!$rows): ?>
        <p class="muted empty-sm"><?= $tab === 'active' ? 'No active tickets.' : 'Nothing here.' ?></p>
    <?php else: ?>
        <div class="list list-tickets">
            <?php foreach ($rows as $t): $unread = (int) $t['last_msg'] > (int) $t['read_upto']; ?>
                <a class="list-row<?= $unread ? ' unread' : '' ?>" href="<?= e(url('/tickets/' . $t['id'])) ?>">
                    <span class="cat-dot cat-<?= e($t['category']) ?>" title="<?= e(TICKET_CATEGORIES[$t['category']] ?? '') ?>"></span>
                    <div class="list-main">
                        <div class="list-title">#<?= (int) $t['id'] ?> · <?= e($t['subject']) ?></div>
                        <div class="list-sub">
                            <?php if ($staff): ?><strong><?= e($t['team_name']) ?></strong> · <?php endif; ?>
                            <?= e(TICKET_CATEGORIES[$t['category']] ?? '') ?>
                            <?php if ($t['hotel_name']): ?> · <?= e($t['hotel_name']) ?><?= $t['room'] ? ' ' . e($t['room']) : '' ?><?php endif; ?>
                            <?php if ($staff && $t['assignee_name']): ?> · <?= e($t['assignee_name']) ?><?php endif; ?>
                        </div>
                        <div class="list-preview"><?= e(mb_strimwidth((string) $t['last_body'], 0, 120, '…')) ?></div>
                    </div>
                    <div class="list-side">
                        <?php if ($staff && $t['awaiting_staff_since'] && $t['status'] !== 'resolved'): ?>
                            <span class="badge badge-waiting">Waiting <?= e(str_replace(' ago', '', time_ago($t['awaiting_staff_since']))) ?></span>
                        <?php else: ?>
                            <?= status_badge($t['status'], TICKET_STATUSES) ?>
                        <?php endif; ?>
                        <span class="small muted"><?= e(time_ago($t['updated_at'])) ?></span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
