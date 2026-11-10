<?php
declare(strict_types=1);

function bootcamps_index(): void
{
    require_staff();
    auto_advance_bootcamps();
    $tab = (string) input('tab', 'current');
    $search = (string) input('q');
    $where = match ($tab) {
        'past' => "b.status = 'completed' OR (b.end_date < CURDATE() AND b.status <> 'cancelled')",
        'cancelled' => "b.status = 'cancelled'",
        'all' => '1=1',
        default => "b.status NOT IN ('cancelled','completed') AND b.end_date >= CURDATE()",
    };
    $params = [];
    $sql = "SELECT b.*, t.name AS team_name, t.game AS team_game, h.name AS hotel_name
            FROM bootcamps b JOIN teams t ON t.id = b.team_id JOIN hotels h ON h.id = b.hotel_id WHERE ($where)";
    if ($search !== '') {
        $sql .= ' AND (t.name LIKE ? OR h.name LIKE ? OR b.room LIKE ?)';
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like);
    }
    $sql .= $tab === 'current' ? ' ORDER BY b.start_date ASC' : ' ORDER BY b.start_date DESC';
    view('bootcamps/index', [
        'title' => __('Bootcamps'),
        'rows' => all($sql . ' LIMIT 300', $params),
        'tab' => $tab,
        'search' => $search,
    ]);
}

function bootcamps_form_data(?array $b): array
{
    return [
        'teams' => all('SELECT id, name, game FROM teams ORDER BY name'),
        'hotels' => all('SELECT id, name, city FROM hotels WHERE active = 1' . ($b ? ' OR id = ' . (int) $b['hotel_id'] : '') . ' ORDER BY name'),
        'equipment' => all('SELECT * FROM equipment WHERE active = 1' . ($b ? ' OR id IN (SELECT equipment_id FROM bootcamp_items WHERE bootcamp_id = ' . (int) $b['id'] . ')' : '') . ' ORDER BY FIELD(category,"pc","monitor","table","chair","peripheral","other"), sort, name'),
        'qty' => $b ? array_column(all('SELECT equipment_id, qty FROM bootcamp_items WHERE bootcamp_id = ?', [$b['id']]), 'qty', 'equipment_id') : [],
    ];
}

/** Validate + save create/edit form. Returns [id|null, errors]. */
function bootcamps_save(?array $existing, array $me): array
{
    $errors = [];
    $teamId = input_int('team_id');
    $hotelId = input_int('hotel_id');
    $start = input_date('start_date');
    $end = input_date('end_date');
    $status = (string) input('status', 'planned');
    if (!one('SELECT id FROM teams WHERE id = ?', [$teamId])) {
        $errors[] = __('Choose a team.');
    }
    if (!one('SELECT id FROM hotels WHERE id = ?', [$hotelId])) {
        $errors[] = __('Choose a hotel.');
    }
    if (!$start || !$end) {
        $errors[] = __('Enter start and end dates.');
    } elseif ($end < $start) {
        $errors[] = __('End date is before start date.');
    }
    if (!array_key_exists($status, BOOTCAMP_STATUSES)) {
        $status = 'planned';
    }
    $readyRaw = str_replace('T', ' ', (string) input('ready_by'));
    $readyTs = $readyRaw !== '' ? strtotime($readyRaw) : false;
    if ($readyTs === false) {
        $errors[] = __('Enter the date and time by which the room must be ready.');
    } elseif ($start && date('Y-m-d', $readyTs) > $start) {
        $errors[] = __('The room must be ready on or before the start date.');
    }
    if ($errors) {
        return [null, $errors];
    }
    $data = [
        'team_id' => $teamId,
        'hotel_id' => $hotelId,
        'room' => mb_substr((string) input('room'), 0, 60) ?: null,
        'start_date' => $start,
        'end_date' => $end,
        'ready_by' => date('Y-m-d H:i:00', $readyTs),
        'show_prices' => input('show_prices') ? 1 : 0,
        'status' => $status,
        'staff_notes' => (string) input('staff_notes') ?: null,
        'client_info' => (string) input('client_info') ?: null,
        'updated_at' => now(),
    ];
    $oldItems = $existing ? array_column(all('SELECT e.name, bi.qty FROM bootcamp_items bi JOIN equipment e ON e.id = bi.equipment_id WHERE bi.bootcamp_id = ? AND bi.qty > 0', [$existing['id']]), 'qty', 'name') : [];
    $db = db();
    $db->beginTransaction();
    if ($existing) {
        $id = (int) $existing['id'];
        update('bootcamps', $data, 'id = ?', [$id]);
    } else {
        $data['created_by'] = $me['id'];
        $data['created_at'] = now();
        $id = insert('bootcamps', $data);
    }
    q('DELETE FROM bootcamp_items WHERE bootcamp_id = ?', [$id]);
    $wanted = [];
    foreach ((array) ($_POST['qty'] ?? []) as $eqId => $qty) {
        $qty = max(0, (int) $qty);
        if ($qty > 0 && ctype_digit((string) $eqId)) {
            insert('bootcamp_items', ['bootcamp_id' => $id, 'equipment_id' => (int) $eqId, 'qty' => $qty]);
            $wanted[(int) $eqId] = $qty;
        }
    }
    // Drop chair configs beyond the new chair count
    q('DELETE FROM chair_configs WHERE bootcamp_id = ? AND chair_no > ?', [$id, bootcamp_chair_count($id)]);
    $db->commit();

    // History
    if (!$existing) {
        log_activity($id, fn () => __('Created the bootcamp'));
    } else {
        $changes = [];
        $names = fn ($table, $v) => (string) scalar("SELECT name FROM $table WHERE id = ?", [$v]);
        if ((int) $existing['team_id'] !== $teamId) {
            $changes[] = __('team → %s', $names('teams', $teamId));
        }
        if ((int) $existing['hotel_id'] !== $hotelId) {
            $changes[] = __('hotel → %s', $names('hotels', $hotelId));
        }
        if ((string) $existing['room'] !== (string) $data['room']) {
            $changes[] = __('room %s → %s', $existing['room'] ?: '—', $data['room'] ?: '—');
        }
        if ((string) $existing['ready_by'] !== $data['ready_by']) {
            $changes[] = __('ready by → %s', ldate('d M H:i', $readyTs));
        }
        if ($existing['start_date'] !== $start || $existing['end_date'] !== $end) {
            $changes[] = __('dates %s → %s', fmt_range($existing['start_date'], $existing['end_date']), fmt_range($start, $end));
        }
        if ($existing['status'] !== $status) {
            $changes[] = __('status → %s', lbl(BOOTCAMP_STATUSES, $status));
        }
        $newItems = array_column(all('SELECT e.name, bi.qty FROM bootcamp_items bi JOIN equipment e ON e.id = bi.equipment_id WHERE bi.bootcamp_id = ? AND bi.qty > 0', [$id]), 'qty', 'name');
        foreach (array_unique(array_merge(array_keys($oldItems), array_keys($newItems))) as $name) {
            $o = (int) ($oldItems[$name] ?? 0);
            $n = (int) ($newItems[$name] ?? 0);
            if ($o !== $n) {
                $changes[] = "$name $o → $n";
            }
        }
        if ((string) $existing['client_info'] !== (string) $data['client_info']) {
            $changes[] = __('info for the team');
        }
        if ((string) $existing['staff_notes'] !== (string) $data['staff_notes']) {
            $changes[] = __('internal notes');
        }
        if ($changes) {
            log_activity($id, fn () => __('Edited: %s', implode('; ', $changes)));
        }
    }

    // Client notifications
    $team = team_user_ids($teamId);
    if (!$existing && $status !== 'cancelled') {
        notify($team, 'Your bootcamp is confirmed', fmt_range($start, $end) . ' · ' . scalar('SELECT name FROM hotels WHERE id = ?', [$hotelId]), '/my/bootcamp/' . $id);
    } elseif ($existing) {
        if (!$existing['room'] && $data['room']) {
            notify($team, 'Your room is assigned', 'Room ' . $data['room'], '/my/bootcamp/' . $id);
        } elseif ($existing['start_date'] !== $start || $existing['end_date'] !== $end || (int) $existing['hotel_id'] !== $hotelId || ($existing['room'] && $existing['room'] !== $data['room'])) {
            notify($team, 'Your bootcamp details changed', fmt_range($start, $end) . ($data['room'] ? ' · room ' . $data['room'] : ''), '/my/bootcamp/' . $id);
        }
    }

    if ($existing && $status === 'ready' && $existing['status'] !== 'ready') {
        notify_room_ready(bootcamp_find($id), $data['room'], $me);
    } elseif ($existing && $status === 'planned' && $existing['room_ready_at']) {
        update('bootcamps', ['room_ready_at' => null], 'id = ?', [$id]);
    }
    // A new deadline means the setup reminders should fire again
    if ($existing && (string) $existing['ready_by'] !== $data['ready_by']) {
        q("DELETE FROM bootcamp_reminders WHERE bootcamp_id = ? AND kind LIKE 'setup_%'", [$id]);
    }

    $warnings = $status === 'cancelled' ? [] : availability_warnings($start, $end, $wanted, $id);
    if ($warnings) {
        flash('warning', '<strong>' . e(__('Saved, but inventory is short:')) . '</strong><br>' . implode('<br>', array_map('e', $warnings)), true);
    }
    return [$id, []];
}

function bootcamps_create(): void
{
    $me = require_role('admin', 'manager');
    $errors = [];
    $b = null;
    if (is_post()) {
        [$id, $errors] = bootcamps_save(null, $me);
        if ($id) {
            if ($qid = input_int('quote_id')) {
                update('quote_requests', ['status' => 'converted', 'bootcamp_id' => $id, 'handled_by' => $me['id'], 'updated_at' => now()], 'id = ?', [$qid]);
                log_activity($id, fn () => __('Created from bootcamp request #%d', $qid));
            }
            flash('success', __('Bootcamp created.'));
            redirect('/bootcamps/' . $id);
        }
        $b = $_POST;
    } elseif ($qid = input_int('quote')) {
        $q = one('SELECT * FROM quote_requests WHERE id = ?', [$qid]) ?? not_found();
        $note = trim(__('From team request #%d: ', $qid) . ($q['people'] ? __('%d people', (int) $q['people']) . ', ' : '') . ($q['pcs'] !== null ? __('%d PCs', (int) $q['pcs']) . ', ' : '') . ($q['hotel_pref'] ? __('hotel: %s', $q['hotel_pref']) . '. ' : '') . ($q['note'] ?? ''));
        $b = ['team_id' => $q['team_id'], 'start_date' => $q['start_date'], 'end_date' => $q['end_date'], 'staff_notes' => $note, 'quote_id' => $qid,
              'ready_by' => $q['start_date'] ? $q['start_date'] . ' 12:00' : ''];
    } elseif (input_int('team')) {
        $b = ['team_id' => input_int('team')];
    }
    view('bootcamps/form', ['title' => __('New bootcamp'), 'b' => $b, 'isNew' => true, 'errors' => $errors] + bootcamps_form_data(null));
}

function bootcamps_edit(int $id): void
{
    $me = require_role('admin', 'manager');
    $existing = bootcamp_find($id) ?? not_found();
    $errors = [];
    $b = $existing;
    if (is_post()) {
        [$saved, $errors] = bootcamps_save($existing, $me);
        if ($saved) {
            flash('success', __('Bootcamp saved.'));
            redirect('/bootcamps/' . $id);
        }
        $b = array_merge($existing, $_POST);
    }
    $data = bootcamps_form_data($existing);
    if (is_post()) {
        $data['qty'] = array_map('intval', (array) ($_POST['qty'] ?? []));
    }
    view('bootcamps/form', ['title' => __('Edit bootcamp'), 'b' => $b, 'isNew' => false, 'errors' => $errors] + $data);
}

function bootcamps_show(int $id): void
{
    require_staff();
    auto_advance_bootcamps();
    $b = bootcamp_find($id) ?? not_found();
    $items = bootcamp_items($id);
    $extras = all(
        'SELECT x.*, COALESCE(e.name, x.custom_name) AS equipment_name, e.price_per_day AS list_price, u.name AS requested_name, d.name AS decided_name, dv.name AS delivery_name
         FROM extra_requests x LEFT JOIN equipment e ON e.id = x.equipment_id
         LEFT JOIN users u ON u.id = x.requested_by LEFT JOIN users d ON d.id = x.decided_by LEFT JOIN users dv ON dv.id = x.delivery_by
         WHERE x.bootcamp_id = ? ORDER BY x.requested_at DESC',
        [$id]
    );
    $tickets = all("SELECT * FROM tickets WHERE bootcamp_id = ? ORDER BY status = 'resolved', updated_at DESC", [$id]);
    $wanted = [];
    foreach ($items as $it) {
        $wanted[(int) $it['id']] = (int) $it['qty'];
    }
    checklist_sync($b);
    view('bootcamps/show', [
        'setupItems' => checklist_items($id, 'setup'),
        'checkoutItems' => checklist_items($id, 'checkout'),
        'photos' => all('SELECT p.*, u.name AS user_name FROM bootcamp_photos p LEFT JOIN users u ON u.id = p.user_id WHERE p.bootcamp_id = ? ORDER BY p.id', [$id]),
        'feedback' => bootcamp_feedback($id),
        'activity' => bootcamp_activity($id),
        'flags' => bootcamp_action_flags([$id])[$id] ?? [],
        'activityRequests' => all('SELECT r.*, a.name AS activity_name, rp.name AS replied_name FROM activity_requests r JOIN activities a ON a.id = r.activity_id LEFT JOIN users rp ON rp.id = r.replied_by WHERE r.bootcamp_id = ? ORDER BY r.status <> \'pending\', r.created_at DESC', [$id]),
        'title' => __('%s bootcamp', $b['team_name']),
        'b' => $b,
        'items' => $items,
        'chairs' => bootcamp_chairs($id),
        'drinkIds' => bootcamp_drink_ids($id),
        'drinks' => all('SELECT * FROM drinks WHERE active = 1 OR id IN (SELECT drink_id FROM bootcamp_drinks WHERE bootcamp_id = ?) ORDER BY sort, name', [$id]),
        'extras' => $extras,
        'tickets' => $tickets,
        'accounts' => all("SELECT id, name, email, phone, password_hash IS NOT NULL AS activated, last_login_at FROM users WHERE team_id = ? AND role = 'client' AND active = 1", [$b['team_id']]),
        'requestable' => all('SELECT * FROM equipment WHERE active = 1 ORDER BY category, sort, name'),
        'warnings' => bootcamp_is_open($b) ? availability_warnings($b['start_date'], $b['end_date'], $wanted, $id) : [],
    ]);
}

/** Quick operational update: support can change status and room. */
function bootcamps_quick(int $id): void
{
    $me = require_staff();
    $b = bootcamp_find($id) ?? not_found();
    $status = (string) input('status', $b['status']);
    if (!array_key_exists($status, BOOTCAMP_STATUSES)) {
        $status = $b['status'];
    }
    $room = mb_substr((string) input('room', (string) $b['room']), 0, 60) ?: null;
    update('bootcamps', ['status' => $status, 'room' => $room, 'updated_at' => now()], 'id = ?', [$id]);
    $changes = [];
    if ((string) $b['room'] !== (string) $room) {
        $changes[] = __('room %s → %s', $b['room'] ?: '—', $room ?: '—');
    }
    if ($b['status'] !== $status) {
        $changes[] = __('status → %s', lbl(BOOTCAMP_STATUSES, $status));
    }
    if ($changes) {
        log_activity($id, fn () => __('Updated %s', implode(', ', $changes)));
    }
    $team = team_user_ids((int) $b['team_id']);
    if (!$b['room'] && $room) {
        notify($team, 'Your room is assigned', $b['hotel_name'] . ' · room ' . $room, '/my/bootcamp/' . $id);
    } elseif ($b['room'] && $room && $room !== $b['room']) {
        notify($team, 'Your room changed', $b['hotel_name'] . ' · room ' . $room, '/my/bootcamp/' . $id);
    }
    if ($status === 'ready' && $b['status'] !== 'ready') {
        notify_room_ready($b, $room, $me);
    } elseif ($status === 'planned' && $b['room_ready_at']) {
        update('bootcamps', ['room_ready_at' => null], 'id = ?', [$id]);
    }
    flash('success', __('Updated.'));
    redirect('/bootcamps/' . $id);
}

function bootcamps_delete(int $id): void
{
    require_role('admin', 'manager');
    $b = bootcamp_find($id) ?? not_found();
    $db = db();
    $db->beginTransaction();
    purge_bootcamp($id);
    $db->commit();
    purge_files_now();
    log_activity(null, fn () => __('Deleted bootcamp #%d (%s, %s)', $id, $b['team_name'], fmt_range($b['start_date'], $b['end_date'])));
    flash('success', __('Bootcamp for %s deleted.', $b['team_name']));
    redirect('/bootcamps');
}

function bootcamps_drinks(int $id): void
{
    require_staff();
    bootcamp_find($id) ?? not_found();
    save_bootcamp_drinks($id, (array) ($_POST['drinks'] ?? []));
    $names = array_column(bootcamp_drinks($id), 'name');
    log_activity($id, fn () => __('Changed drinks: %s', $names ? implode(', ', $names) : __('none')));
    flash('success', __('Drinks saved.'));
    redirect('/bootcamps/' . $id . '#drinks');
}

function save_bootcamp_drinks(int $bootcampId, array $ids): void
{
    q('DELETE FROM bootcamp_drinks WHERE bootcamp_id = ?', [$bootcampId]);
    foreach (array_unique(array_map('intval', $ids)) as $drinkId) {
        if ($drinkId > 0 && one('SELECT id FROM drinks WHERE id = ?', [$drinkId])) {
            insert('bootcamp_drinks', ['bootcamp_id' => $bootcampId, 'drink_id' => $drinkId]);
        }
    }
    update('bootcamps', ['drinks_saved_at' => now()], 'id = ?', [$bootcampId]);
}

function bootcamps_chairs(int $id): void
{
    require_staff();
    bootcamp_find($id) ?? not_found();
    save_chair_configs($id, (array) ($_POST['chair'] ?? []));
    log_activity($id, fn () => __('Changed chairs: %s', chair_summary_text(bootcamp_chairs($id))));
    flash('success', __('Chair setup saved.'));
    redirect('/bootcamps/' . $id . '#chairs');
}

function save_chair_configs(int $bootcampId, array $posted): void
{
    $count = bootcamp_chair_count($bootcampId);
    q('DELETE FROM chair_configs WHERE bootcamp_id = ?', [$bootcampId]);
    for ($i = 1; $i <= $count; $i++) {
        $v = (string) ($posted[$i] ?? 'keep');
        if (!array_key_exists($v, ARMREST_OPTIONS)) {
            $v = 'keep';
        }
        insert('chair_configs', ['bootcamp_id' => $bootcampId, 'chair_no' => $i, 'armrests' => $v]);
    }
    update('bootcamps', ['chairs_saved_at' => now()], 'id = ?', [$bootcampId]);
}

/** Manager adds extra equipment directly (already approved). */
function bootcamps_add_extra(int $id): void
{
    $me = require_role('admin', 'manager');
    $b = bootcamp_find($id) ?? not_found();
    $eq = one('SELECT * FROM equipment WHERE id = ?', [input_int('equipment_id')]);
    $qty = max(1, input_int('qty', 1));
    if (!$eq) {
        flash('error', __('Choose equipment.'));
        redirect('/bootcamps/' . $id . '#extras');
    }
    $from = input_date('bill_from') ?? $b['start_date'];
    $to = input_date('bill_to') ?? $b['end_date'];
    insert('extra_requests', [
        'bootcamp_id' => $id, 'equipment_id' => $eq['id'], 'qty' => $qty,
        'note' => (string) input('note') ?: null, 'status' => 'approved',
        'requested_by' => $me['id'], 'requested_at' => now(),
        'decided_by' => $me['id'], 'decided_at' => now(),
        'bill_from' => $from, 'bill_to' => $to,
        'price_per_day' => $eq['price_per_day'],
    ]);
    log_activity($id, fn () => __('Added extra %s (%s → %s)', $qty . '× ' . $eq['name'], fmt_date($from, 'd M'), fmt_date($to, 'd M')));
    $warnings = availability_warnings($from, $to, [(int) $eq['id'] => $qty]);
    if ($warnings) {
        flash('warning', __('Added, but: %s', implode(' ', $warnings)));
    } else {
        flash('success', __('Extra equipment added.'));
    }
    redirect('/bootcamps/' . $id . '#extras');
}

function bootcamps_decide_extra(int $id): void
{
    $me = require_role('admin', 'manager');
    $x = one('SELECT x.*, COALESCE(e.name, x.custom_name) AS equipment_name, e.price_per_day AS list_price FROM extra_requests x LEFT JOIN equipment e ON e.id = x.equipment_id WHERE x.id = ?', [$id]) ?? not_found();
    $b = bootcamp_find((int) $x['bootcamp_id']) ?? not_found();
    $decision = (string) input('decision');
    $note = (string) input('staff_note') ?: null;
    if ($decision === 'approve') {
        $from = input_date('bill_from') ?? max(date('Y-m-d'), $b['start_date']);
        $to = input_date('bill_to') ?? $b['end_date'];
        if ($to < $from) {
            flash('error', __('End date is before start date.'));
            redirect('/bootcamps/' . $b['id'] . '#extras');
        }
        $qty = max(1, input_int('qty', (int) $x['qty']));
        update('extra_requests', [
            'status' => 'approved', 'qty' => $qty, 'decided_by' => $me['id'], 'decided_at' => now(),
            'bill_from' => $from, 'bill_to' => $to,
            'price_per_day' => input('price_per_day') !== '' ? input_money('price_per_day') : $x['list_price'],
            'staff_note' => $note,
        ], 'id = ?', [$id]);
        log_activity((int) $b['id'], fn () => __('Approved extra %s (%s → %s)', $qty . '× ' . $x['equipment_name'], fmt_date($from, 'd M'), fmt_date($to, 'd M')));
        notify(team_user_ids((int) $b['team_id']), 'Request approved: ' . $qty . '× ' . $x['equipment_name'], $note ?? 'We will bring it to your room.', '/my/bootcamp/' . $b['id']);
        notify_staff(fn () => __('To deliver: %s', $qty . '× ' . $x['equipment_name']), fn () => $b['team_name'] . ' · ' . $b['hotel_name'] . ($b['room'] ? ' · ' . __('room %s', $b['room']) : '') . ' — ' . __('who takes it? Tap “I\'ll deliver”.'), '/bootcamps/' . $b['id'] . '#extras', true, (int) $me['id']);
        $warnings = $x['equipment_id'] ? availability_warnings($from, $to, [(int) $x['equipment_id'] => $qty], null, $id) : [];
        flash($warnings ? 'warning' : 'success', $warnings ? __('Approved, but: %s', implode(' ', $warnings)) : __('Request approved.'));
    } elseif ($decision === 'reject') {
        update('extra_requests', ['status' => 'rejected', 'decided_by' => $me['id'], 'decided_at' => now(), 'staff_note' => $note], 'id = ?', [$id]);
        log_activity((int) $b['id'], fn () => __('Declined extra %s', $x['qty'] . '× ' . $x['equipment_name']));
        notify(team_user_ids((int) $b['team_id']), 'Request declined: ' . $x['equipment_name'], $note ?? 'Contact support for details.', '/my/bootcamp/' . $b['id']);
        flash('success', __('Request declined.'));
    } elseif ($decision === 'cancel') {
        update('extra_requests', ['status' => 'cancelled', 'decided_by' => $me['id'], 'decided_at' => now()], 'id = ?', [$id]);
        flash('success', __('Removed.'));
    }
    redirect('/bootcamps/' . $b['id'] . '#extras');
}

/** Manager edits an approved extra: quantity and the dates it was in the room. */
function bootcamps_update_extra(int $id): void
{
    require_role('admin', 'manager');
    $x = one('SELECT * FROM extra_requests WHERE id = ?', [$id]) ?? not_found();
    $b = bootcamp_find((int) $x['bootcamp_id']) ?? not_found();
    $action = (string) input('action', 'save');
    $eqName = $x['equipment_id'] ? (string) scalar('SELECT name FROM equipment WHERE id = ?', [$x['equipment_id']]) : (string) $x['custom_name'];
    if ($action === 'remove') {
        update('extra_requests', ['status' => 'cancelled', 'decided_at' => now()], 'id = ?', [$id]);
        log_activity((int) $b['id'], fn () => __('Deleted extra %s', $x['qty'] . '× ' . $eqName));
        flash('success', __('Removed from the log.'));
    } elseif ($action === 'taken_out') {
        update('extra_requests', ['bill_to' => max(date('Y-m-d'), (string) ($x['bill_from'] ?: $b['start_date']))], 'id = ?', [$id]);
        log_activity((int) $b['id'], fn () => __('Extra %s taken out of the room today', $eqName));
        flash('success', __('Marked as taken out of the room today.'));
    } else {
        $from = input_date('bill_from') ?? ($x['bill_from'] ?: $b['start_date']);
        $to = input_date('bill_to') ?? ($x['bill_to'] ?: $b['end_date']);
        if ($to < $from) {
            flash('error', __('End date is before start date.'));
        } else {
            $qty = max(1, input_int('qty', (int) $x['qty']));
            $upd = ['qty' => $qty, 'bill_from' => $from, 'bill_to' => $to];
            if (isset($_POST['price_per_day'])) {
                $upd['price_per_day'] = input_money('price_per_day');
            }
            update('extra_requests', $upd, 'id = ?', [$id]);
            log_activity((int) $b['id'], fn () => __('Edited extra %s: %s, %s → %s', $eqName, $qty . '×', fmt_date($from, 'd M'), fmt_date($to, 'd M')));
            flash('success', __('Saved.'));
        }
    }
    redirect('/bootcamps/' . $b['id'] . '#extras');
}

/** Staff member takes (or releases) the delivery of an approved extra. */
function bootcamps_claim_extra(int $id): void
{
    $me = require_staff();
    $x = one("SELECT x.*, COALESCE(e.name, x.custom_name) AS equipment_name FROM extra_requests x LEFT JOIN equipment e ON e.id = x.equipment_id WHERE x.id = ? AND x.status = 'approved'", [$id]) ?? not_found();
    if ((int) $x['delivery_by'] === (int) $me['id']) {
        update('extra_requests', ['delivery_by' => null, 'delivery_claimed_at' => null], 'id = ?', [$id]);
        log_activity((int) $x['bootcamp_id'], fn () => __('Released delivery of %s', $x['qty'] . '× ' . $x['equipment_name']));
        flash('success', __('Released — someone else can take it.'));
    } else {
        update('extra_requests', ['delivery_by' => $me['id'], 'delivery_claimed_at' => now()], 'id = ?', [$id]);
        log_activity((int) $x['bootcamp_id'], fn () => __('Will deliver %s', $x['qty'] . '× ' . $x['equipment_name']));
        $b = bootcamp_find((int) $x['bootcamp_id']);
        notify_staff(fn () => __('%s will deliver %s', $me['name'], $x['qty'] . '× ' . $x['equipment_name']), fn () => $b['team_name'] . ($b['room'] ? ' · ' . __('room %s', $b['room']) : ''), '/bootcamps/' . $b['id'] . '#extras', true, (int) $me['id']);
        flash('success', __('You are delivering this one.'));
    }
    redirect('/bootcamps/' . $x['bootcamp_id'] . '#extras');
}

function bootcamps_deliver_extra(int $id): void
{
    $me = require_staff();
    $x = one('SELECT * FROM extra_requests WHERE id = ?', [$id]) ?? not_found();
    $upd = ['delivered_at' => $x['delivered_at'] ? null : now()];
    if (!$x['delivered_at'] && !$x['delivery_by']) {
        $upd['delivery_by'] = $me['id'];
        $upd['delivery_claimed_at'] = now();
    }
    update('extra_requests', $upd, 'id = ?', [$id]);
    $eqName = $x['equipment_id'] ? scalar('SELECT name FROM equipment WHERE id = ?', [$x['equipment_id']]) : $x['custom_name'];
    log_activity((int) $x['bootcamp_id'], fn () => $x['delivered_at'] ? __('Unmarked delivery of extra %s', $x['qty'] . '× ' . $eqName) : __('Delivered extra %s', $x['qty'] . '× ' . $eqName));
    redirect('/bootcamps/' . $x['bootcamp_id'] . '#extras');
}

/** Live inventory check for the bootcamp form. */
function bootcamps_availability(): void
{
    require_role('admin', 'manager');
    $from = input_date('start_date');
    $to = input_date('end_date');
    if (!$from || !$to || $to < $from) {
        json_out(['warnings' => []]);
    }
    $wanted = [];
    foreach ((array) ($_POST['qty'] ?? []) as $eq => $qty) {
        if (ctype_digit((string) $eq)) {
            $wanted[(int) $eq] = max(0, (int) $qty);
        }
    }
    $exclude = input_int('bootcamp_id') ?: null;
    $usage = equipment_peak_usage($from, $to, $exclude);
    $free = [];
    foreach (all('SELECT id, total_qty FROM equipment') as $eq) {
        $free[(int) $eq['id']] = (int) $eq['total_qty'] - ($usage[(int) $eq['id']] ?? 0);
    }
    json_out(['warnings' => availability_warnings($from, $to, $wanted, $exclude), 'free' => $free]);
}
