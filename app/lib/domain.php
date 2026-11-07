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
            WHERE x.status = 'approved' AND b.status <> 'cancelled'
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
    return all(
        'SELECT m.*, u.name AS user_name, u.role AS user_role FROM ticket_messages m LEFT JOIN users u ON u.id = m.user_id
         WHERE m.ticket_id = ? AND m.id > ? ORDER BY m.id',
        [$ticketId, $afterId]
    );
}

function ticket_mark_read(int $ticketId, int $userId): void
{
    $last = (int) scalar('SELECT COALESCE(MAX(id),0) FROM ticket_messages WHERE ticket_id = ?', [$ticketId]);
    q('INSERT INTO ticket_reads (ticket_id, user_id, last_message_id) VALUES (?,?,?) ON DUPLICATE KEY UPDATE last_message_id = GREATEST(last_message_id, VALUES(last_message_id))', [$ticketId, $userId, $last]);
}

/**
 * Create a ticket with its first message. Notifies staff (or the team, if staff created it).
 */
function ticket_create(array $author, int $teamId, ?int $bootcampId, string $category, string $subject, string $body): int
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
    insert('ticket_messages', ['ticket_id' => $id, 'user_id' => $author['id'], 'body' => $body, 'created_at' => now()]);
    ticket_mark_read($id, (int) $author['id']);

    $team = (string) scalar('SELECT name FROM teams WHERE id = ?', [$teamId]);
    $room = $bootcampId ? (string) scalar("SELECT CONCAT(h.name, IF(b.room IS NULL OR b.room = '', '', CONCAT(' · room ', b.room))) FROM bootcamps b JOIN hotels h ON h.id = b.hotel_id WHERE b.id = ?", [$bootcampId]) : '';
    if ($isStaff) {
        notify(team_user_ids($teamId), 'New message from Relog support', $subject, '/tickets/' . $id);
    } else {
        notify_staff(
            'New ticket #' . $id . ' · ' . $team,
            '[' . (TICKET_CATEGORIES[$category] ?? $category) . '] ' . $subject . ($room ? ' — ' . $room : ''),
            '/tickets/' . $id,
            true,
            (int) $author['id']
        );
    }
    return $id;
}

function ticket_add_message(array $ticket, array $author, string $body): int
{
    $isStaff = is_staff($author);
    $id = insert('ticket_messages', ['ticket_id' => $ticket['id'], 'user_id' => $author['id'], 'body' => $body, 'created_at' => now()]);
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

    $preview = mb_strimwidth(preg_replace('/\s+/', ' ', $body), 0, 140, '…');
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
