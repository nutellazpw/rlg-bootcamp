<?php
declare(strict_types=1);

const BOOTCAMP_STATUSES = [
    'planned'   => 'Planned',
    'ready'     => 'Room ready',
    'active'    => 'In progress',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled',
];

const EQUIPMENT_CATEGORIES = [
    'pc'         => 'PCs',
    'monitor'    => 'Monitors',
    'table'      => 'Tables',
    'chair'      => 'Chairs',
    'peripheral' => 'Peripherals',
    'other'      => 'Other',
];

const ARMREST_OPTIONS = [
    'keep'         => 'Keep both',
    'remove_both'  => 'Remove both',
    'remove_left'  => 'Remove left',
    'remove_right' => 'Remove right',
];

const TICKET_CATEGORIES = [
    'technical' => 'Technical issue',
    'refill'    => 'Drinks refill',
    'room'      => 'Room / cleaning',
    'equipment' => 'Equipment',
    'other'     => 'Other',
];

const TICKET_STATUSES = [
    'open'        => 'Open',
    'in_progress' => 'In progress',
    'resolved'    => 'Resolved',
];

const EXTRA_STATUSES = [
    'pending'   => 'Pending',
    'approved'  => 'Approved',
    'rejected'  => 'Rejected',
    'cancelled' => 'Cancelled',
];

const GAMES = ['CS2', 'Dota 2', 'Valorant', 'League of Legends', 'Rainbow Six Siege', 'Other'];

function status_badge(string $status, array $labels): string
{
    return '<span class="badge badge-' . e($status) . '">' . e($labels[$status] ?? $status) . '</span>';
}

function bootcamp_find(int $id): ?array
{
    return one(
        'SELECT b.*, t.name AS team_name, t.game AS team_game, h.name AS hotel_name, h.city AS hotel_city, h.address AS hotel_address
         FROM bootcamps b JOIN teams t ON t.id = b.team_id JOIN hotels h ON h.id = b.hotel_id WHERE b.id = ?',
        [$id]
    );
}

function bootcamp_items(int $bootcampId): array
{
    return all(
        'SELECT e.*, bi.qty FROM bootcamp_items bi JOIN equipment e ON e.id = bi.equipment_id
         WHERE bi.bootcamp_id = ? AND bi.qty > 0 ORDER BY FIELD(e.category,"pc","monitor","table","chair","peripheral","other"), e.sort, e.name',
        [$bootcampId]
    );
}

function bootcamp_chair_count(int $bootcampId): int
{
    return (int) scalar(
        "SELECT COALESCE(SUM(bi.qty),0) FROM bootcamp_items bi JOIN equipment e ON e.id = bi.equipment_id
         WHERE bi.bootcamp_id = ? AND e.category = 'chair'",
        [$bootcampId]
    );
}

function bootcamp_chairs(int $bootcampId): array
{
    $count = bootcamp_chair_count($bootcampId);
    $saved = [];
    foreach (all('SELECT chair_no, armrests FROM chair_configs WHERE bootcamp_id = ?', [$bootcampId]) as $r) {
        $saved[(int) $r['chair_no']] = $r['armrests'];
    }
    $chairs = [];
    for ($i = 1; $i <= $count; $i++) {
        $chairs[$i] = $saved[$i] ?? 'keep';
    }
    return $chairs;
}

function bootcamp_drink_ids(int $bootcampId): array
{
    return array_map('intval', array_column(all('SELECT drink_id FROM bootcamp_drinks WHERE bootcamp_id = ?', [$bootcampId]), 'drink_id'));
}

function bootcamp_drinks(int $bootcampId): array
{
    return all('SELECT d.* FROM bootcamp_drinks bd JOIN drinks d ON d.id = bd.drink_id WHERE bd.bootcamp_id = ? ORDER BY d.sort, d.name', [$bootcampId]);
}

function bootcamp_is_open(array $b): bool
{
    return !in_array($b['status'], ['completed', 'cancelled'], true);
}

/** Client may only see bootcamps of their own team. */
function client_bootcamp_or_404(array $u, int $id): array
{
    $b = bootcamp_find($id);
    if (!$b || (int) $b['team_id'] !== (int) $u['team_id'] || $b['status'] === 'cancelled') {
        not_found();
    }
    return $b;
}

/** The bootcamp a client sees by default: current one, else next upcoming, else latest. */
function client_current_bootcamp(array $u): ?array
{
    $row = one(
        "SELECT id FROM bootcamps WHERE team_id = ? AND status <> 'cancelled'
         ORDER BY (CURDATE() BETWEEN start_date AND end_date) DESC, (end_date >= CURDATE()) DESC,
                  CASE WHEN end_date >= CURDATE() THEN start_date END ASC, end_date DESC LIMIT 1",
        [(int) $u['team_id']]
    );
    return $row ? bootcamp_find((int) $row['id']) : null;
}

/**
 * Peak daily usage of each equipment item between two dates (inclusive),
 * counting bootcamp bookings and approved extra requests.
 * @return array<int,int> equipment_id => peak qty
 */
function equipment_peak_usage(string $from, string $to, ?int $excludeBootcampId = null, ?int $excludeExtraId = null): array
{
    $params = [$to, $from];
    $sql = "SELECT b.id, b.start_date, b.end_date, bi.equipment_id, bi.qty
            FROM bootcamps b JOIN bootcamp_items bi ON bi.bootcamp_id = b.id
            WHERE b.status <> 'cancelled' AND b.start_date <= ? AND b.end_date >= ? AND bi.qty > 0";
    if ($excludeBootcampId) {
        $sql .= ' AND b.id <> ?';
        $params[] = $excludeBootcampId;
    }
    $rows = all($sql, $params);

    $params = [$to, $from];
    $sql = "SELECT x.id, COALESCE(x.bill_from, b.start_date) AS start_date, COALESCE(x.bill_to, b.end_date) AS end_date, x.equipment_id, x.qty
            FROM extra_requests x JOIN bootcamps b ON b.id = x.bootcamp_id
            WHERE x.status = 'approved' AND x.equipment_id IS NOT NULL AND b.status <> 'cancelled'
              AND COALESCE(x.bill_from, b.start_date) <= ? AND COALESCE(x.bill_to, b.end_date) >= ?";
    if ($excludeExtraId) {
        $sql .= ' AND x.id <> ?';
        $params[] = $excludeExtraId;
    }
    $rows = array_merge($rows, all($sql, $params));

    $perDay = [];
    $fromTs = strtotime($from);
    $toTs = strtotime($to);
    foreach ($rows as $r) {
        $s = max($fromTs, strtotime($r['start_date']));
        $e = min($toTs, strtotime($r['end_date']));
        for ($d = $s; $d <= $e; $d = strtotime('+1 day', $d)) {
            $perDay[(int) $r['equipment_id']][$d] = ($perDay[(int) $r['equipment_id']][$d] ?? 0) + (int) $r['qty'];
        }
    }
    $peak = [];
    foreach ($perDay as $eq => $days) {
        $peak[$eq] = max($days);
    }
    return $peak;
}

/**
 * Warnings when the requested quantities would exceed the inventory.
 * @param array<int,int> $wanted equipment_id => qty
 */
function availability_warnings(string $from, string $to, array $wanted, ?int $excludeBootcampId = null, ?int $excludeExtraId = null): array
{
    $wanted = array_filter($wanted, fn ($q) => $q > 0);
    if (!$wanted) {
        return [];
    }
    $usage = equipment_peak_usage($from, $to, $excludeBootcampId, $excludeExtraId);
    $in = implode(',', array_fill(0, count($wanted), '?'));
    $equipment = all("SELECT id, name, total_qty FROM equipment WHERE id IN ($in)", array_keys($wanted));
    $warnings = [];
    foreach ($equipment as $eq) {
        $used = $usage[(int) $eq['id']] ?? 0;
        $need = $wanted[(int) $eq['id']];
        $free = (int) $eq['total_qty'] - $used;
        if ($need > $free) {
            $warnings[] = sprintf('%s: need %d, only %d free in that period (%d in stock, %d already booked).', $eq['name'], $need, max(0, $free), $eq['total_qty'], $used);
        }
    }
    return $warnings;
}

/** Equipment overbooked anywhere in the next N days — for the dashboard. */
function upcoming_inventory_warnings(int $days = 60): array
{
    $from = date('Y-m-d');
    $to = date('Y-m-d', strtotime("+$days days"));
    $usage = equipment_peak_usage($from, $to);
    $out = [];
    foreach (all('SELECT id, name, total_qty FROM equipment WHERE active = 1') as $eq) {
        $used = $usage[(int) $eq['id']] ?? 0;
        if ($used > (int) $eq['total_qty']) {
            $out[] = sprintf('%s: up to %d booked, only %d in stock.', $eq['name'], $used, $eq['total_qty']);
        }
    }
    return $out;
}

/** Room marked ready: tell staff and managers (not the team). */
function notify_room_ready(array $b, ?string $room, array $actor): void
{
    update('bootcamps', ['room_ready_at' => now()], 'id = ?', [$b['id']]);
    log_activity((int) $b['id'], 'Room marked ready', $actor);
    $ready = ($b['ready_by'] ?? null) ? ' (deadline ' . date('d M H:i', strtotime($b['ready_by'])) . ')' : '';
    notify_staff(
        'Room ready: ' . $b['team_name'],
        $b['hotel_name'] . ($room ? ' · room ' . $room : '') . ' · by ' . $actor['name'] . $ready,
        '/bootcamps/' . $b['id'],
        true,
        (int) $actor['id']
    );
}

/** Move statuses forward by date: planned/ready → active on start day, active → completed after end day. */
function auto_advance_bootcamps(): void
{
    q("UPDATE bootcamps SET status = 'active', updated_at = NOW() WHERE status IN ('planned','ready') AND start_date <= CURDATE() AND end_date >= CURDATE()");
    q("UPDATE bootcamps SET status = 'completed', updated_at = NOW() WHERE status IN ('planned','ready','active') AND end_date < CURDATE()");
}

function ticket_find(int $id): ?array
{
    return one(
        'SELECT tk.*, t.name AS team_name, b.room, b.start_date, b.end_date, h.name AS hotel_name,
                a.name AS assignee_name, c.name AS creator_name
         FROM tickets tk JOIN teams t ON t.id = tk.team_id
         LEFT JOIN bootcamps b ON b.id = tk.bootcamp_id LEFT JOIN hotels h ON h.id = b.hotel_id
         LEFT JOIN users a ON a.id = tk.assigned_to LEFT JOIN users c ON c.id = tk.created_by
         WHERE tk.id = ?',
        [$id]
    );
}

function ticket_messages(int $ticketId, int $afterId = 0): array
{
    $rows = all(
        'SELECT m.*, u.name AS user_name, u.role AS user_role FROM ticket_messages m LEFT JOIN users u ON u.id = m.user_id
         WHERE m.ticket_id = ? AND m.id > ? ORDER BY m.id',
        [$ticketId, $afterId]
    );
    $att = attachments_by_message(array_column($rows, 'id'));
    foreach ($rows as &$r) {
        $r['attachments'] = $att[(int) $r['id']] ?? [];
    }
    return $rows;
}

function ticket_mark_read(int $ticketId, int $userId): void
{
    $last = (int) scalar('SELECT COALESCE(MAX(id),0) FROM ticket_messages WHERE ticket_id = ?', [$ticketId]);
    q('INSERT INTO ticket_reads (ticket_id, user_id, last_message_id) VALUES (?,?,?) ON DUPLICATE KEY UPDATE last_message_id = GREATEST(last_message_id, VALUES(last_message_id))', [$ticketId, $userId, $last]);
}

/**
 * Create a ticket with its first message. Notifies staff (or the team, if staff created it).
 */
function ticket_create(array $author, int $teamId, ?int $bootcampId, string $category, string $subject, string $body, array $files = []): int
{
    $isStaff = is_staff($author);
    $id = insert('tickets', [
        'bootcamp_id' => $bootcampId,
        'team_id' => $teamId,
        'created_by' => $author['id'],
        'category' => array_key_exists($category, TICKET_CATEGORIES) ? $category : 'other',
        'subject' => mb_substr($subject, 0, 190),
        'status' => 'open',
        'created_at' => now(),
        'updated_at' => now(),
        'awaiting_staff_since' => $isStaff ? null : now(),
    ]);
    $msgId = insert('ticket_messages', ['ticket_id' => $id, 'user_id' => $author['id'], 'body' => $body, 'created_at' => now()]);
    if ($files) {
        store_attachments($files, $id, $msgId, (int) $author['id']);
    }
    ticket_mark_read($id, (int) $author['id']);
    $label = attachments_label($files);

    $team = (string) scalar('SELECT name FROM teams WHERE id = ?', [$teamId]);
    $room = $bootcampId ? (string) scalar("SELECT CONCAT(h.name, IF(b.room IS NULL OR b.room = '', '', CONCAT(' · room ', b.room))) FROM bootcamps b JOIN hotels h ON h.id = b.hotel_id WHERE b.id = ?", [$bootcampId]) : '';
    if ($isStaff) {
        notify(team_user_ids($teamId), 'New message from Relog support', $subject, '/tickets/' . $id);
    } else {
        notify_staff(
            'New ticket #' . $id . ' · ' . $team,
            '[' . (TICKET_CATEGORIES[$category] ?? $category) . '] ' . $subject . ($room ? ' — ' . $room : '') . ($label ? ' · ' . $label : ''),
            '/tickets/' . $id,
            true,
            (int) $author['id']
        );
    }
    return $id;
}

function ticket_add_message(array $ticket, array $author, string $body, array $files = []): int
{
    $isStaff = is_staff($author);
    $id = insert('ticket_messages', ['ticket_id' => $ticket['id'], 'user_id' => $author['id'], 'body' => $body, 'created_at' => now()]);
    if ($files) {
        store_attachments($files, (int) $ticket['id'], $id, (int) $author['id']);
    }
    $data = ['updated_at' => now()];
    if ($isStaff) {
        $data['awaiting_staff_since'] = null;
        $data['last_reminder_at'] = null;
        if ($ticket['status'] === 'open') {
            $data['status'] = 'in_progress';
        }
        if (!$ticket['assigned_to']) {
            $data['assigned_to'] = $author['id'];
        }
    } else {
        if (!$ticket['awaiting_staff_since']) {
            $data['awaiting_staff_since'] = now();
        }
        if ($ticket['status'] === 'resolved') {
            $data['status'] = 'open';
        }
    }
    update('tickets', $data, 'id = ?', [$ticket['id']]);
    ticket_mark_read((int) $ticket['id'], (int) $author['id']);

    $preview = trim(mb_strimwidth(preg_replace('/\s+/', ' ', $body), 0, 140, '…') . ' ' . attachments_label($files));
    if ($isStaff) {
        notify(team_user_ids((int) $ticket['team_id']), 'Reply from Relog support', $preview, '/tickets/' . $ticket['id']);
    } else {
        notify_staff('Ticket #' . $ticket['id'] . ' · ' . $ticket['team_name'], $preview, '/tickets/' . $ticket['id'], true, (int) $author['id']);
    }
    return $id;
}

function ticket_system_message(int $ticketId, string $text): void
{
    insert('ticket_messages', ['ticket_id' => $ticketId, 'user_id' => null, 'body' => $text, 'is_system' => 1, 'created_at' => now()]);
}

/** Hourly reminders for tickets waiting for staff. Called by cron. Returns number of reminders sent. */
function send_ticket_reminders(): int
{
    $hour = (int) date('G');
    $qf = (int) config('reminders.quiet_from', 0);
    $qt = (int) config('reminders.quiet_to', 7);
    $quiet = $qf < $qt ? ($hour >= $qf && $hour < $qt) : ($hour >= $qf || $hour < $qt);
    if ($quiet) {
        return 0;
    }
    $mins = max(5, (int) config('reminders.after_minutes', 60));
    $tickets = all(
        "SELECT tk.*, t.name AS team_name FROM tickets tk JOIN teams t ON t.id = tk.team_id
         WHERE tk.status IN ('open','in_progress') AND tk.awaiting_staff_since IS NOT NULL
           AND tk.awaiting_staff_since <= (NOW() - INTERVAL $mins MINUTE)
           AND (tk.last_reminder_at IS NULL OR tk.last_reminder_at <= (NOW() - INTERVAL $mins MINUTE))"
    );
    foreach ($tickets as $tk) {
        $waiting = (int) floor((time() - strtotime($tk['awaiting_staff_since'])) / 60);
        $label = $waiting >= 120 ? floor($waiting / 60) . ' h' : $waiting . ' min';
        notify_staff(
            'Reminder: ticket #' . $tk['id'] . ' waiting ' . $label,
            $tk['team_name'] . ' — ' . $tk['subject'],
            '/tickets/' . $tk['id']
        );
        update('tickets', ['last_reminder_at' => now()], 'id = ?', [$tk['id']]);
    }
    return count($tickets);
}

// ---------------------------------------------------------------------------
// Activity log (history of changes)
// ---------------------------------------------------------------------------

function log_activity(?int $bootcampId, string $action, ?array $actor = null): void
{
    $actor = $actor ?? (function_exists('user') && session_status() === PHP_SESSION_ACTIVE ? user() : null);
    insert('activity_log', [
        'bootcamp_id' => $bootcampId,
        'user_id' => $actor['id'] ?? null,
        'action' => mb_substr($action, 0, 500),
        'created_at' => now(),
    ]);
}

function bootcamp_activity(int $bootcampId, int $limit = 200): array
{
    return all(
        'SELECT a.*, u.name AS user_name, u.role AS user_role FROM activity_log a LEFT JOIN users u ON u.id = a.user_id
         WHERE a.bootcamp_id = ? ORDER BY a.id DESC LIMIT ' . (int) $limit,
        [$bootcampId]
    );
}

function chair_summary_text(array $chairs): string
{
    $groups = [];
    foreach ($chairs as $no => $opt) {
        if ($opt !== 'keep') {
            $groups[$opt][] = $no;
        }
    }
    if (!$groups) {
        return 'All chairs keep both armrests';
    }
    $parts = [];
    foreach ($groups as $opt => $nos) {
        $parts[] = ARMREST_OPTIONS[$opt] . ': #' . implode(', #', $nos);
    }
    return implode(' · ', $parts);
}

// ---------------------------------------------------------------------------
// Checklists (staff only): setup before arrival, pickup after the bootcamp
// ---------------------------------------------------------------------------

const CHECKLIST_PHASES = ['setup' => 'Room setup', 'checkout' => 'Pickup & check-out'];

/** Items generated from the booking. Keyed so they can be kept in sync when the booking changes. */
function checklist_wanted(array $b): array
{
    $id = (int) $b['id'];
    $items = bootcamp_items($id);
    $chairs = bootcamp_chairs($id);
    $drinks = array_column(bootcamp_drinks($id), 'name');
    $extras = all("SELECT x.*, COALESCE(e.name, x.custom_name) AS equipment_name FROM extra_requests x LEFT JOIN equipment e ON e.id = x.equipment_id WHERE x.bootcamp_id = ? AND x.status = 'approved' ORDER BY x.id", [$id]);

    $setup = [];
    $checkout = [];
    $sort = 0;
    foreach ($items as $it) {
        $detail = $it['spec'] ?: null;
        if ($it['category'] === 'chair') {
            $detail = chair_summary_text($chairs) . (!empty($b['chairs_note']) ? ' — note: ' . $b['chairs_note'] : '');
        }
        $setup['eq:' . $it['id']] = [(int) $it['qty'] . '× ' . $it['name'], $detail, $sort];
        $checkout['out_eq:' . $it['id']] = ['Collect ' . (int) $it['qty'] . '× ' . $it['name'], null, $sort];
        $sort++;
    }
    foreach ($extras as $x) {
        $from = $x['bill_from'] ?: $b['start_date'];
        $to = $x['bill_to'] ?: $b['end_date'];
        $setup['extra:' . $x['id']] = [(int) $x['qty'] . '× ' . $x['equipment_name'] . ' (extra)', 'In room ' . fmt_date($from, 'd M') . ' → ' . fmt_date($to, 'd M'), 100 + $sort];
        $checkout['out_extra:' . $x['id']] = ['Collect ' . (int) $x['qty'] . '× ' . $x['equipment_name'] . ' (extra)', null, 100 + $sort];
        $sort++;
    }
    $setup['net'] = ['Internet / network tested on every PC', null, 200];
    $setup['drinks'] = ['Fridge stocked', ($drinks ? implode(', ', $drinks) : 'Team has not chosen drinks yet') . (!empty($b['drinks_note']) ? ' — note: ' . $b['drinks_note'] : ''), 201];
    $setup['clean'] = ['Room clean, cables tidy', null, 202];
    $checkout['damage'] = ['Room and equipment checked for damage', 'Write notes and add photos below if anything is damaged', 300];
    $checkout['fridge'] = ['Fridge emptied', null, 301];
    $checkout['keys'] = ['Room handed back to the hotel', null, 302];
    return ['setup' => $setup, 'checkout' => $checkout];
}

function checklist_sync(array $b): void
{
    $id = (int) $b['id'];
    foreach (checklist_wanted($b) as $phase => $wanted) {
        $existing = [];
        foreach (all('SELECT id, item_key FROM checklist_items WHERE bootcamp_id = ? AND phase = ? AND item_key IS NOT NULL', [$id, $phase]) as $r) {
            $existing[$r['item_key']] = (int) $r['id'];
        }
        foreach ($wanted as $key => [$label, $detail, $sort]) {
            if (isset($existing[$key])) {
                update('checklist_items', ['label' => mb_substr($label, 0, 190), 'detail' => $detail ? mb_substr($detail, 0, 500) : null, 'sort' => $sort], 'id = ?', [$existing[$key]]);
                unset($existing[$key]);
            } else {
                insert('checklist_items', ['bootcamp_id' => $id, 'phase' => $phase, 'item_key' => $key, 'label' => mb_substr($label, 0, 190), 'detail' => $detail ? mb_substr($detail, 0, 500) : null, 'sort' => $sort]);
            }
        }
        foreach ($existing as $rowId) {
            q('DELETE FROM checklist_items WHERE id = ?', [$rowId]);
        }
    }
}

function checklist_items(int $bootcampId, string $phase): array
{
    return all(
        'SELECT c.*, u.name AS done_name FROM checklist_items c LEFT JOIN users u ON u.id = c.done_by
         WHERE c.bootcamp_id = ? AND c.phase = ? ORDER BY c.sort, c.id',
        [$bootcampId, $phase]
    );
}

function checklist_progress(int $bootcampId, string $phase): array
{
    $row = one('SELECT COUNT(*) AS total, SUM(done_at IS NOT NULL) AS done FROM checklist_items WHERE bootcamp_id = ? AND phase = ?', [$bootcampId, $phase]);
    return [(int) ($row['done'] ?? 0), (int) ($row['total'] ?? 0)];
}

// ---------------------------------------------------------------------------
// Pre-arrival reminders + feedback requests (run by cron)
// ---------------------------------------------------------------------------

function manager_ids(): array
{
    return array_map('intval', array_column(all("SELECT id FROM users WHERE active = 1 AND role IN ('admin','manager')"), 'id'));
}

/** Records a reminder; returns false if it was already sent. */
function reminder_once(int $bootcampId, string $kind): bool
{
    return q('INSERT IGNORE INTO bootcamp_reminders (bootcamp_id, kind, sent_at) VALUES (?, ?, NOW())', [$bootcampId, $kind])->rowCount() > 0;
}

function send_bootcamp_reminders(): int
{
    $hour = (int) date('G');
    if ($hour < 9 || $hour >= 21) {
        return 0; // daytime only
    }
    $sent = 0;
    $rows = all(
        "SELECT b.*, t.name AS team_name, h.name AS hotel_name, DATEDIFF(b.start_date, CURDATE()) AS days_left
         FROM bootcamps b JOIN teams t ON t.id = b.team_id JOIN hotels h ON h.id = b.hotel_id
         WHERE b.status IN ('planned','ready','active') AND b.start_date BETWEEN CURDATE() AND (CURDATE() + INTERVAL 3 DAY)"
    );
    foreach ($rows as $b) {
        $id = (int) $b['id'];
        $days = (int) $b['days_left'];
        $when = $days === 0 ? 'today' : ($days === 1 ? 'tomorrow' : "in $days days");

        if (!$b['room']) {
            $kind = $days <= 1 ? 'room_1d' : 'room_3d';
            if (reminder_once($id, $kind)) {
                notify(manager_ids(), 'No room yet: ' . $b['team_name'], 'Bootcamp at ' . $b['hotel_name'] . ' starts ' . $when . '.', '/bootcamps/' . $id, '🏨 No room assigned yet: ' . $b['team_name'] . ' at ' . $b['hotel_name'] . ', starts ' . $when);
                $sent++;
            }
        }

        $needDrinks = !$b['drinks_saved_at'];
        $needChairs = !$b['chairs_saved_at'] && bootcamp_chair_count($id) > 0;
        if (($needDrinks || $needChairs) && $days >= 1 && reminder_once($id, 'prefs')) {
            $what = implode(' and ', array_filter([$needDrinks ? 'drinks for your fridge' : null, $needChairs ? 'the armrests on your chairs' : null]));
            notify(team_user_ids((int) $b['team_id']), 'Your bootcamp starts ' . $when, 'Please choose ' . $what . ' so we can prepare the room.', '/my/bootcamp/' . $id);
            foreach (all("SELECT * FROM users WHERE team_id = ? AND role = 'client' AND active = 1 AND password_hash IS NOT NULL", [(int) $b['team_id']]) as $u) {
                send_mail($u['email'], 'Your bootcamp starts ' . $when, mail_template(
                    'Get your room ready',
                    'Your bootcamp at <strong>' . e($b['hotel_name']) . '</strong> starts ' . e($when) . ' (' . e(fmt_range($b['start_date'], $b['end_date'])) . ').<br><br>Please choose ' . e($what) . ' in the app so we can prepare everything before you arrive.',
                    'Open RLG Bootcamp',
                    abs_url('/my/bootcamp/' . $id)
                ));
            }
            notify(manager_ids(), $b['team_name'] . ' has not chosen ' . ($needDrinks && $needChairs ? 'drinks & chairs' : ($needDrinks ? 'drinks' : 'chairs')), 'Reminder sent to the team. Starts ' . $when . '.', '/bootcamps/' . $id);
            $sent++;
        }

    }

    // Feedback request the day after the bootcamp ends
    $ended = all(
        "SELECT b.*, t.name AS team_name, h.name AS hotel_name FROM bootcamps b JOIN teams t ON t.id = b.team_id JOIN hotels h ON h.id = b.hotel_id
         WHERE b.status <> 'cancelled' AND b.end_date < CURDATE() AND b.end_date >= (CURDATE() - INTERVAL 7 DAY)
           AND NOT EXISTS (SELECT 1 FROM feedback f WHERE f.bootcamp_id = b.id)"
    );
    foreach ($ended as $b) {
        if (!reminder_once((int) $b['id'], 'feedback')) {
            continue;
        }
        notify(team_user_ids((int) $b['team_id']), 'How was your bootcamp?', 'Tell us in 30 seconds — it helps us a lot.', '/my/bootcamp/' . $b['id'] . '#feedback');
        foreach (all("SELECT * FROM users WHERE team_id = ? AND role = 'client' AND active = 1 AND password_hash IS NOT NULL", [(int) $b['team_id']]) as $u) {
            send_mail($u['email'], 'How was your bootcamp?', mail_template(
                'Thanks for staying with us',
                'We hope the bootcamp at <strong>' . e($b['hotel_name']) . '</strong> went well. How did we do? Rate your bootcamp and tell us what we can do better — it takes 30 seconds.',
                'Rate your bootcamp',
                abs_url('/my/bootcamp/' . $b['id'] . '#feedback')
            ));
        }
        $sent++;
    }
    return $sent;
}

/**
 * Room-ready deadline reminders for staff: 24h before, 3h before, and when overdue.
 * Respects the quiet hours from config (reminders.quiet_from / quiet_to).
 */
function send_setup_deadline_reminders(): int
{
    $hour = (int) date('G');
    $qf = (int) config('reminders.quiet_from', 0);
    $qt = (int) config('reminders.quiet_to', 7);
    if ($qf < $qt ? ($hour >= $qf && $hour < $qt) : ($hour >= $qf || $hour < $qt)) {
        return 0;
    }
    $rows = all(
        "SELECT b.*, t.name AS team_name, h.name AS hotel_name FROM bootcamps b JOIN teams t ON t.id = b.team_id JOIN hotels h ON h.id = b.hotel_id
         WHERE b.ready_by IS NOT NULL AND b.room_ready_at IS NULL AND b.status IN ('planned','ready','active')
           AND b.ready_by <= (NOW() + INTERVAL 24 HOUR) AND b.ready_by >= (NOW() - INTERVAL 24 HOUR)"
    );
    $sent = 0;
    foreach ($rows as $b) {
        $id = (int) $b['id'];
        if ($b['status'] === 'ready') {
            continue;
        }
        $mins = (int) floor((strtotime($b['ready_by']) - time()) / 60);
        [$done, $total] = checklist_progress($id, 'setup');
        $where = $b['hotel_name'] . ($b['room'] ? ' · room ' . $b['room'] : ' · no room yet') . " · checklist $done/$total";
        $deadline = date('D H:i', strtotime($b['ready_by']));
        if ($mins <= 0) {
            if (reminder_once($id, 'setup_late')) {
                notify_staff('⚠ Room NOT ready: ' . $b['team_name'], 'Deadline was ' . $deadline . ' · ' . $where, '/bootcamps/' . $id . '#checklist');
                $sent++;
            }
        } elseif ($mins <= 180) {
            if (reminder_once($id, 'setup_3h')) {
                $left = $mins >= 60 ? floor($mins / 60) . ' h ' . ($mins % 60) . ' min' : $mins . ' min';
                notify_staff('Room setup due in ' . $left . ': ' . $b['team_name'], 'Ready by ' . $deadline . ' · ' . $where, '/bootcamps/' . $id . '#checklist');
                $sent++;
            }
        } elseif (reminder_once($id, 'setup_24h')) {
            notify_staff('Room setup due ' . $deadline . ': ' . $b['team_name'], $where, '/bootcamps/' . $id . '#checklist');
            $sent++;
        }
    }
    return $sent;
}

function bootcamp_feedback(int $bootcampId): ?array
{
    return one('SELECT f.*, u.name AS user_name FROM feedback f LEFT JOIN users u ON u.id = f.user_id WHERE f.bootcamp_id = ?', [$bootcampId]);
}

function stars(int $rating): string
{
    return str_repeat('★', max(0, min(5, $rating))) . str_repeat('☆', 5 - max(0, min(5, $rating)));
}

// ---------------------------------------------------------------------------
// Team logos, activities, quote requests
// ---------------------------------------------------------------------------

const ACTIVITY_STATUSES = [
    'pending'   => 'Pending',
    'answered'  => 'Offer sent',
    'confirmed' => 'Confirmed',
    'declined'  => 'Declined',
    'cancelled' => 'Cancelled',
];

const QUOTE_STATUSES = [
    'new'         => 'New',
    'in_progress' => 'In progress',
    'converted'   => 'Booked',
    'declined'    => 'Declined',
];

const DRINKS_MAX = 5;

function team_logo_url(?array $team): ?string
{
    if (!$team || empty($team['logo'])) {
        return null;
    }
    $id = (int) ($team['team_id'] ?? $team['id']);
    return url('/team-logo/' . $id) . '?v=' . substr(md5((string) $team['logo']), 0, 8);
}

function activity_image_url(array $a): string
{
    if (!empty($a['image'])) {
        return url('/activity-image/' . (int) $a['id']) . '?v=' . substr(md5((string) $a['image']), 0, 8);
    }
    $slug = (string) ($a['slug'] ?? '');
    if ($slug !== '' && is_file(APP_ROOT . '/assets/img/activities/' . $slug . '.svg')) {
        return asset('img/activities/' . $slug . '.svg');
    }
    return asset('img/activities/media-day.svg');
}

/** Save an uploaded image (logo / activity picture). Returns stored file name or null. */
function store_image_upload(string $field, string $dir): ?string
{
    $f = $_FILES[$field] ?? null;
    if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
        return null;
    }
    if ($f['size'] > 10 * 1024 * 1024) {
        flash('error', 'Image is too large (max 10 MB).');
        return null;
    }
    $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'][$mime] ?? null;
    if (!$ext) {
        flash('error', 'Use a JPG, PNG, WEBP or GIF image.');
        return null;
    }
    $path = APP_ROOT . '/storage/uploads/' . $dir;
    if (!is_dir($path)) {
        @mkdir($path, 0755, true);
    }
    $name = date('Ymd-His') . '-' . bin2hex(random_bytes(5)) . '.' . $ext;
    return move_uploaded_file($f['tmp_name'], $path . '/' . $name) ? $name : null;
}

function serve_stored_image(string $dir, string $file): void
{
    $path = APP_ROOT . '/storage/uploads/' . $dir . '/' . basename($file);
    if (!is_file($path)) {
        not_found();
    }
    $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($path);
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: private, max-age=604800');
    readfile($path);
    exit;
}

/**
 * What each bootcamp is waiting for from staff. Returns [bootcamp_id => [label => count]].
 * Used to highlight rows and sections that need action.
 */
function bootcamp_action_flags(array $bootcampIds = []): array
{
    $where = $bootcampIds ? ' AND b.id IN (' . implode(',', array_map('intval', $bootcampIds)) . ')' : '';
    $flags = [];
    $add = function (array $rows, string $label) use (&$flags) {
        foreach ($rows as $r) {
            $flags[(int) $r['id']][$label] = (int) $r['n'];
        }
    };
    $add(all("SELECT b.id, COUNT(*) n FROM extra_requests x JOIN bootcamps b ON b.id = x.bootcamp_id WHERE x.status = 'pending'$where GROUP BY b.id"), 'Extra to approve');
    $add(all("SELECT b.id, COUNT(*) n FROM extra_requests x JOIN bootcamps b ON b.id = x.bootcamp_id WHERE x.status = 'approved' AND x.delivered_at IS NULL AND x.delivery_by IS NULL$where GROUP BY b.id"), 'Delivery unassigned');
    $add(all("SELECT b.id, COUNT(*) n FROM tickets tk JOIN bootcamps b ON b.id = tk.bootcamp_id WHERE tk.status IN ('open','in_progress') AND tk.awaiting_staff_since IS NOT NULL$where GROUP BY b.id"), 'Ticket waiting');
    $add(all("SELECT b.id, COUNT(*) n FROM activity_requests a JOIN bootcamps b ON b.id = a.bootcamp_id WHERE a.status = 'pending'$where GROUP BY b.id"), 'Activity request');
    $add(all("SELECT b.id, 1 n FROM bootcamps b WHERE b.status IN ('planned','ready') AND (b.room IS NULL OR b.room = '') AND b.start_date <= (CURDATE() + INTERVAL 7 DAY) AND b.end_date >= CURDATE()$where"), 'No room');
    $add(all("SELECT b.id, 1 n FROM bootcamps b WHERE b.ready_by IS NOT NULL AND b.room_ready_at IS NULL AND b.status IN ('planned','active') AND b.ready_by < (NOW() + INTERVAL 24 HOUR) AND b.end_date >= CURDATE()$where"), 'Setup due');
    return $flags;
}

function action_tags(array $flags): string
{
    $out = '';
    foreach ($flags as $label => $n) {
        $out .= '<span class="action-tag">' . e($label) . ($n > 1 ? ' ×' . $n : '') . '</span>';
    }
    return $out;
}
