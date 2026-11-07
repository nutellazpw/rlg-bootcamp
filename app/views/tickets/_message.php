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
        <?php if (trim((string) $m['body']) !== ''): ?><div class="msg-body"><?= nl2br_e($m['body']) ?></div><?php endif; ?>
        <?php if (!empty($m['attachments'])): ?>
            <div class="msg-media">
                <?php foreach ($m['attachments'] as $a): $src = url('/attachments/' . $a['id']); ?>
                    <?php if (str_starts_with($a['mime'], 'video/')): ?>
                        <video controls playsinline preload="metadata" class="media-video">
                            <source src="<?= e($src) ?>" type="<?= e($a['mime'] === 'video/quicktime' ? 'video/mp4' : $a['mime']) ?>">
                            <source src="<?= e($src) ?>" type="<?= e($a['mime']) ?>">
                        </video>
                    <?php elseif (in_array($a['mime'], ['image/heic', 'image/heif'], true)): ?>
                        <a class="media-file" href="<?= e($src) ?>" target="_blank">📷 <?= e($a['original_name'] ?: 'Photo') ?> <span>(<?= e(human_bytes((int) $a['size'])) ?>)</span></a>
                    <?php else: ?>
                        <a class="media-img" href="<?= e($src) ?>" target="_blank"><img src="<?= e($src) ?>" alt="<?= e($a['original_name'] ?? 'Photo') ?>" loading="lazy"></a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
