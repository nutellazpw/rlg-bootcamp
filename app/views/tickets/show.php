<?php
$me = user();
$staff = is_staff($me);
$lastId = $messages ? (int) end($messages)['id'] : 0;
?>
<div class="page-head">
    <div>
        <a class="back" href="<?= e(url('/tickets')) ?>">← <?= e($staff ? __('Tickets') : 'Support') ?></a>
        <h1>#<?= (int) $t['id'] ?> · <?= e($t['subject']) ?></h1>
        <p class="muted">
            <?php if ($staff): ?><a href="<?= e(url('/teams/' . $t['team_id'])) ?>"><?= e($t['team_name']) ?></a> · <?php endif; ?>
            <?= e(lbl(TICKET_CATEGORIES, $t['category'])) ?>
            <?php if ($t['hotel_name']): ?> · <?php if ($staff && $t['bootcamp_id']): ?><a href="<?= e(url('/bootcamps/' . $t['bootcamp_id'])) ?>"><?= e($t['hotel_name']) ?><?= $t['room'] ? ' · ' . e(__('room %s', $t['room'])) : '' ?></a><?php else: ?><?= e($t['hotel_name']) ?><?= $t['room'] ? ' · ' . e(__('room %s', $t['room'])) : '' ?><?php endif; ?><?php endif; ?>
            · <span id="ticket-status"><?= status_badge($t['status'], TICKET_STATUSES) ?></span>
        </p>
    </div>
</div>

<div class="layout-side">
    <div class="col-main">
        <section class="card chat-card">
            <div class="chat" id="chat" data-ticket="<?= (int) $t['id'] ?>" data-last="<?= $lastId ?>">
                <?php foreach ($messages as $m) { partial('tickets/_message', ['m' => $m, 'me' => $me]); } ?>
            </div>
            <div id="bottom"></div>
            <form method="post" action="<?= e(url('/tickets/' . $t['id'] . '/reply')) ?>" class="chat-form" id="chat-form" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <label class="attach-btn" title="<?= e(__('Add photo or video')) ?>" aria-label="<?= e(__('Add photo or video')) ?>">
                    <svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M16.5 6.5v10a4.5 4.5 0 0 1-9 0V5a3 3 0 0 1 6 0v10.5a1.5 1.5 0 0 1-3 0V6.5H9v9a3 3 0 0 0 6 0V5a4.5 4.5 0 0 0-9 0v11.5a6 6 0 0 0 12 0v-10h-1.5Z"/></svg>
                    <input type="file" name="files[]" accept="image/*,video/*" multiple data-attach-input>
                </label>
                <textarea name="body" rows="2" placeholder="<?= e($t['status'] === 'resolved' ? __('Write to reopen the ticket…') : __('Write a message…')) ?>"></textarea>
                <button class="btn" type="submit"><?= e(__('Send')) ?></button>
            </form>
            <div class="attach-preview" data-attach-preview></div>
            <div class="upload-progress" data-upload-progress hidden><i></i><span></span></div>
            <p class="hint chat-hint">📎 <?= e(__('Photos & videos up to %s. Ctrl/⌘ + Enter to send. New messages appear automatically.', human_bytes(upload_limit_bytes()))) ?></p>
        </section>
    </div>
    <?php if ($staff): ?>
    <aside class="col-side">
        <section class="card">
            <h2 class="card-title"><?= e(__('Ticket')) ?></h2>
            <form method="post" action="<?= e(url('/tickets/' . $t['id'] . '/update')) ?>" class="form">
                <?= csrf_field() ?>
                <label class="field"><span><?= e(__('Status')) ?></span>
                    <select name="status">
                        <?php foreach (TICKET_STATUSES as $k => $l): ?><option value="<?= e($k) ?>"<?= selected($k, $t['status']) ?>><?= e(__($l)) ?></option><?php endforeach; ?>
                    </select>
                </label>
                <label class="field"><span><?= e(__('Assigned to')) ?></span>
                    <select name="assigned_to">
                        <option value=""><?= e(__('— nobody —')) ?></option>
                        <?php foreach ($staffUsers as $s): ?><option value="<?= (int) $s['id'] ?>"<?= selected($s['id'], $t['assigned_to']) ?>><?= e($s['name']) ?></option><?php endforeach; ?>
                    </select>
                </label>
                <button class="btn btn-sm btn-block" type="submit"><?= e(__('Update')) ?></button>
            </form>
            <dl class="kv kv-small">
                <dt><?= e(__('Opened')) ?></dt><dd><?= e(__('%s by %s', fmt_dt($t['created_at']), $t['creator_name'] ?? '—')) ?></dd>
                <?php if ($t['awaiting_staff_since']): ?><dt><?= e(__('Waiting for reply')) ?></dt><dd class="text-warn"><?= e(__('since %s', date('H:i', strtotime($t['awaiting_staff_since'])))) ?> (<?= e(time_ago($t['awaiting_staff_since'])) ?>)</dd><?php endif; ?>
            </dl>
        </section>
    </aside>
    <?php endif; ?>
</div>
