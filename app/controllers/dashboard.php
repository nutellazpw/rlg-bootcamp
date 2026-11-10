<?php
declare(strict_types=1);

function dashboard_index(): void
{
    require_staff();
    auto_advance_bootcamps();
    $sel = 'SELECT b.*, t.name AS team_name, t.game AS team_game, h.name AS hotel_name FROM bootcamps b JOIN teams t ON t.id = b.team_id JOIN hotels h ON h.id = b.hotel_id';
    $active = all("$sel WHERE b.status <> 'cancelled' AND CURDATE() BETWEEN b.start_date AND b.end_date ORDER BY h.name, b.room");
    $upcoming = all("$sel WHERE b.status <> 'cancelled' AND b.start_date > CURDATE() AND b.start_date <= (CURDATE() + INTERVAL 30 DAY) ORDER BY b.start_date");
    $tickets = all(
        "SELECT tk.*, t.name AS team_name, a.name AS assignee_name,
                (SELECT body FROM ticket_messages m WHERE m.ticket_id = tk.id ORDER BY m.id DESC LIMIT 1) AS last_body
         FROM tickets tk JOIN teams t ON t.id = tk.team_id LEFT JOIN users a ON a.id = tk.assigned_to
         WHERE tk.status IN ('open','in_progress')
         ORDER BY (tk.awaiting_staff_since IS NULL), tk.awaiting_staff_since, tk.updated_at DESC LIMIT 10"
    );
    $extras = all(
        "SELECT x.*, COALESCE(e.name, x.custom_name) AS equipment_name, t.name AS team_name, b.id AS bootcamp_id, dv.name AS delivery_name
         FROM extra_requests x LEFT JOIN equipment e ON e.id = x.equipment_id JOIN bootcamps b ON b.id = x.bootcamp_id JOIN teams t ON t.id = b.team_id LEFT JOIN users dv ON dv.id = x.delivery_by
         WHERE x.status = 'pending' OR (x.status = 'approved' AND x.delivered_at IS NULL)
         ORDER BY x.status = 'pending' DESC, x.requested_at"
    );
    $missingRoom = all("$sel WHERE b.status IN ('planned','ready') AND (b.room IS NULL OR b.room = '') AND b.start_date <= (CURDATE() + INTERVAL 7 DAY) ORDER BY b.start_date");
    view('dashboard/index', [
        'title' => 'Dashboard',
        'active' => $active,
        'upcoming' => $upcoming,
        'tickets' => $tickets,
        'extras' => $extras,
        'missingRoom' => $missingRoom,
        'warnings' => upcoming_inventory_warnings(),
        'requests' => array_filter([
            'quotes' => (int) scalar("SELECT COUNT(*) FROM quote_requests WHERE status = 'new'"),
            'activities' => (int) scalar("SELECT COUNT(*) FROM activity_requests WHERE status = 'pending'"),
        ]),
    ]);
}

function dashboard_calendar(): void
{
    require_staff();
    $m = (string) input('m', date('Y-m'));
    if (!preg_match('/^\d{4}-\d{2}$/', $m)) {
        $m = date('Y-m');
    }
    $first = strtotime($m . '-01');
    $gridStart = strtotime('monday this week', $first);
    if (date('N', $first) === '1') {
        $gridStart = $first;
    }
    $last = strtotime(date('Y-m-t', $first));
    $gridEnd = strtotime('sunday this week', $last);
    $hotelFilter = input_int('hotel');

    $params = [date('Y-m-d', $gridEnd), date('Y-m-d', $gridStart)];
    $sql = "SELECT b.*, t.name AS team_name, h.name AS hotel_name FROM bootcamps b JOIN teams t ON t.id = b.team_id JOIN hotels h ON h.id = b.hotel_id
            WHERE b.status <> 'cancelled' AND b.start_date <= ? AND b.end_date >= ?";
    if ($hotelFilter) {
        $sql .= ' AND b.hotel_id = ?';
        $params[] = $hotelFilter;
    }
    $bootcamps = all($sql . ' ORDER BY b.start_date, t.name', $params);

    // Stable colour per hotel
    $hotels = all('SELECT id, name FROM hotels ORDER BY name');
    $palette = ['#FF6700', '#3FA7FF', '#2EC27E', '#C061CB', '#F5C211', '#E66100', '#62A0EA', '#8FF0A4'];
    $colors = [];
    foreach ($hotels as $i => $h) {
        $colors[(int) $h['id']] = $palette[$i % count($palette)];
    }

    view('dashboard/calendar', [
        'title' => 'Calendar',
        'month' => $first,
        'gridStart' => $gridStart,
        'gridEnd' => $gridEnd,
        'bootcamps' => $bootcamps,
        'hotels' => $hotels,
        'colors' => $colors,
        'hotelFilter' => $hotelFilter,
    ]);
}
