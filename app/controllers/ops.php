<?php
declare(strict_types=1);

// Checklists, check-out notes & photos, print pages, feedback.

function ops_checklist_toggle(int $id): void
{
    $u = require_staff();
    $item = one('SELECT * FROM checklist_items WHERE id = ?', [$id]) ?? not_found();
    $done = !$item['done_at'];
    update('checklist_items', ['done_at' => $done ? now() : null, 'done_by' => $done ? $u['id'] : null], 'id = ?', [$id]);
    log_activity((int) $item['bootcamp_id'], ($done ? '✓ ' : '✗ unchecked ') . (CHECKLIST_PHASES[$item['phase']] ?? '') . ': ' . $item['label']);
    [$doneCount, $total] = checklist_progress((int) $item['bootcamp_id'], $item['phase']);
    if (wants_json()) {
        json_out([
            'done' => $done,
            'by' => $done ? $u['name'] . ' · ' . date('d M H:i') : '',
            'progress' => [$doneCount, $total],
        ]);
    }
    redirect('/bootcamps/' . $item['bootcamp_id'] . '#checklist');
}

function ops_checklist_add(int $bootcampId): void
{
    require_staff();
    bootcamp_find($bootcampId) ?? not_found();
    $phase = array_key_exists((string) input('phase'), CHECKLIST_PHASES) ? (string) input('phase') : 'setup';
    $label = mb_substr(trim((string) input('label')), 0, 190);
    if ($label !== '') {
        insert('checklist_items', ['bootcamp_id' => $bootcampId, 'phase' => $phase, 'item_key' => null, 'label' => $label, 'sort' => 250]);
        log_activity($bootcampId, 'Added checklist item: ' . $label);
    }
    redirect('/bootcamps/' . $bootcampId . '#checklist-' . $phase);
}

function ops_checklist_delete(int $id): void
{
    require_staff();
    $item = one('SELECT * FROM checklist_items WHERE id = ? AND item_key IS NULL', [$id]) ?? not_found();
    q('DELETE FROM checklist_items WHERE id = ?', [$id]);
    log_activity((int) $item['bootcamp_id'], 'Removed checklist item: ' . $item['label']);
    redirect('/bootcamps/' . $item['bootcamp_id'] . '#checklist-' . $item['phase']);
}

function ops_checkout_notes(int $bootcampId): void
{
    require_staff();
    $b = bootcamp_find($bootcampId) ?? not_found();
    $notes = trim((string) input('checkout_notes'));
    if ($notes !== (string) $b['checkout_notes']) {
        update('bootcamps', ['checkout_notes' => $notes ?: null], 'id = ?', [$bootcampId]);
        log_activity($bootcampId, 'Updated check-out / damage notes');
    }
    flash('success', 'Notes saved.');
    redirect('/bootcamps/' . $bootcampId . '#checklist-checkout');
}

function photos_dir(int $bootcampId): string
{
    $dir = APP_ROOT . '/storage/uploads/bootcamp_' . $bootcampId;
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

function ops_photo_upload(int $bootcampId): void
{
    require_staff();
    bootcamp_find($bootcampId) ?? not_found();
    $files = $_FILES['photos'] ?? null;
    $ok = 0;
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/heic' => 'heic', 'image/heif' => 'heif'];
    if ($files && is_array($files['tmp_name'])) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        foreach ($files['tmp_name'] as $i => $tmp) {
            if (($files['error'][$i] ?? 1) !== UPLOAD_ERR_OK || !is_uploaded_file($tmp) || $files['size'][$i] > 15 * 1024 * 1024) {
                continue;
            }
            $mime = (string) $finfo->file($tmp);
            if (!isset($allowed[$mime])) {
                continue;
            }
            $name = date('Ymd-His') . '-' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
            if (move_uploaded_file($tmp, photos_dir($bootcampId) . '/' . $name)) {
                insert('bootcamp_photos', [
                    'bootcamp_id' => $bootcampId, 'user_id' => user()['id'], 'file' => $name, 'mime' => $mime,
                    'caption' => mb_substr(trim((string) input('caption')), 0, 255) ?: null, 'created_at' => now(),
                ]);
                $ok++;
            }
        }
    }
    if ($ok) {
        log_activity($bootcampId, 'Added ' . $ok . ' photo' . ($ok > 1 ? 's' : ''));
        flash('success', $ok . ' photo' . ($ok > 1 ? 's' : '') . ' uploaded.');
    } else {
        flash('error', 'No photo uploaded. Use JPG, PNG, WEBP or HEIC up to 15 MB.');
    }
    redirect('/bootcamps/' . $bootcampId . '#checklist-checkout');
}

function ops_photo_show(int $id): void
{
    require_staff();
    $p = one('SELECT * FROM bootcamp_photos WHERE id = ?', [$id]) ?? not_found();
    $path = photos_dir((int) $p['bootcamp_id']) . '/' . basename($p['file']);
    if (!is_file($path)) {
        not_found();
    }
    header('Content-Type: ' . $p['mime']);
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: private, max-age=86400');
    header('Content-Disposition: inline; filename="' . basename($p['file']) . '"');
    readfile($path);
    exit;
}

function ops_photo_delete(int $id): void
{
    require_staff();
    $p = one('SELECT * FROM bootcamp_photos WHERE id = ?', [$id]) ?? not_found();
    @unlink(photos_dir((int) $p['bootcamp_id']) . '/' . basename($p['file']));
    q('DELETE FROM bootcamp_photos WHERE id = ?', [$id]);
    log_activity((int) $p['bootcamp_id'], 'Deleted a photo');
    redirect('/bootcamps/' . $p['bootcamp_id'] . '#checklist-checkout');
}

/** Printable room setup order (phase=setup) or pickup order (phase=checkout). */
function ops_print(int $id): void
{
    require_staff();
    $b = bootcamp_find($id) ?? not_found();
    checklist_sync($b);
    $phase = input('phase') === 'checkout' ? 'checkout' : 'setup';
    view('print/order', [
        'title' => ($phase === 'setup' ? 'Room setup order' : 'Pickup order') . ' · ' . $b['team_name'],
        'b' => $b,
        'phase' => $phase,
        'items' => bootcamp_items($id),
        'chairs' => bootcamp_chairs($id),
        'drinks' => bootcamp_drinks($id),
        'extras' => all("SELECT x.*, COALESCE(e.name, x.custom_name) AS equipment_name, e.spec FROM extra_requests x LEFT JOIN equipment e ON e.id = x.equipment_id WHERE x.bootcamp_id = ? AND x.status = 'approved' ORDER BY x.bill_from, x.id", [$id]),
        'checklist' => checklist_items($id, $phase),
        'contacts' => all("SELECT name, email, phone FROM users WHERE team_id = ? AND role = 'client' AND active = 1", [$b['team_id']]),
    ], 'layout_print');
}

/** End-of-bootcamp report. */
function ops_report(int $id): void
{
    require_staff();
    $b = bootcamp_find($id) ?? not_found();
    checklist_sync($b);
    view('print/report', [
        'title' => 'Bootcamp report · ' . $b['team_name'],
        'b' => $b,
        'items' => bootcamp_items($id),
        'chairs' => bootcamp_chairs($id),
        'drinks' => bootcamp_drinks($id),
        'extras' => all("SELECT x.*, COALESCE(e.name, x.custom_name) AS equipment_name FROM extra_requests x LEFT JOIN equipment e ON e.id = x.equipment_id WHERE x.bootcamp_id = ? ORDER BY x.status = 'approved' DESC, x.bill_from, x.id", [$id]),
        'tickets' => all('SELECT tk.*, (SELECT COUNT(*) FROM ticket_messages m WHERE m.ticket_id = tk.id AND m.is_system = 0) AS msgs FROM tickets tk WHERE tk.bootcamp_id = ? ORDER BY tk.created_at', [$id]),
        'setup' => checklist_progress($id, 'setup'),
        'checkout' => checklist_items($id, 'checkout'),
        'photos' => all('SELECT * FROM bootcamp_photos WHERE bootcamp_id = ? ORDER BY id', [$id]),
        'feedback' => bootcamp_feedback($id),
        'refills' => (int) scalar("SELECT COUNT(*) FROM tickets WHERE bootcamp_id = ? AND category = 'refill'", [$id]),
    ], 'layout_print');
}

function ops_feedback(int $bootcampId): void
{
    $u = require_login();
    if (is_staff($u)) {
        forbidden();
    }
    $b = client_bootcamp_or_404($u, $bootcampId);
    if ($b['end_date'] > date('Y-m-d') && $b['status'] !== 'completed') {
        flash('error', 'You can rate the bootcamp on its last day or after.');
        redirect('/my/bootcamp/' . $bootcampId);
    }
    $rating = input_int('rating');
    if ($rating < 1 || $rating > 5) {
        flash('error', 'Choose a rating from 1 to 5 stars.');
        redirect('/my/bootcamp/' . $bootcampId . '#feedback');
    }
    if (bootcamp_feedback($bootcampId)) {
        flash('info', 'Thanks — your feedback was already sent.');
        redirect('/my/bootcamp/' . $bootcampId . '#feedback');
    }
    $comment = mb_substr(trim((string) input('comment')), 0, 3000);
    insert('feedback', ['bootcamp_id' => $bootcampId, 'user_id' => $u['id'], 'rating' => $rating, 'comment' => $comment ?: null, 'created_at' => now()]);
    log_activity($bootcampId, 'Team left feedback: ' . stars($rating));
    notify(
        manager_ids(),
        'Feedback from ' . $b['team_name'] . ': ' . stars($rating),
        $comment !== '' ? mb_strimwidth($comment, 0, 160, '…') : 'No comment',
        '/bootcamps/' . $bootcampId . '#feedback',
        '⭐ Feedback from ' . $b['team_name'] . ': ' . stars($rating) . ($comment !== '' ? "\n" . mb_strimwidth($comment, 0, 500, '…') : '')
    );
    flash('success', 'Thank you for your feedback!');
    redirect('/my/bootcamp/' . $bootcampId . '#feedback');
}
