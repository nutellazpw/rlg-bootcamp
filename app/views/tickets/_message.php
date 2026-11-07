<?php if ($m['is_system']): ?>
    <div class="msg-system" data-id="<?= (int) $m['id'] ?>"><?= e($m['body']) ?> · <?= e(date('d M H:i', strtotime($m['created_at']))) ?></div>
<?php else:
    $mine = (int) $m['user_id'] === (int) $me['id'];
    $fromStaff = in_array($m['user_role'], STAFF_ROLES, true);
    ?>
    <div class="msg<?= $mine ? ' mine' : '' ?><?= $fromStaff ? ' staff' : ' client' ?>" data-id="<?= (int) $m['id'] ?>">
        <div class="msg-meta">
            <strong><?= e($m['user_name'] ?? 'Deleted user') ?></strong>
            <?php if ($fromStaff): ?><span class="tag tag-staff"><?= is_staff($me) ? e(ROLES[$m['user_role']] ?? '') : 'Relog' ?></span><?php endif; ?>
            <span class="muted"><?= e(date('d M H:i', strtotime($m['created_at']))) ?></span>
        </div>
        <div class="msg-body"><?= nl2br_e($m['body']) ?></div>
    </div>
<?php endif; ?>
