<?php
declare(strict_types=1);

require_once APP_DIR . '/controllers/bootcamps.php'; // save_bootcamp_drinks(), save_chair_configs()

function client_user(): array
{
    $u = require_login();
    if (is_staff($u)) {
        redirect('/dashboard');
    }
    return $u;
}

function client_home(): void
{
    $u = client_user();
    auto_advance_bootcamps();
    $b = client_current_bootcamp($u);
    if (!$b) {
        view('client/none', ['title' => 'My bootcamp']);
        return;
    }
    client_render($u, $b);
}

function client_bootcamp(int $id): void
{
    $u = client_user();
    client_render($u, client_bootcamp_or_404($u, $id));
}

function client_render(array $u, array $b): void
{
    $id = (int) $b['id'];
    view('client/bootcamp', [
        'title' => 'My bootcamp',
        'b' => $b,
        'items' => bootcamp_items($id),
        'chairs' => bootcamp_chairs($id),
        'drinks' => all('SELECT * FROM drinks WHERE active = 1 OR id IN (SELECT drink_id FROM bootcamp_drinks WHERE bootcamp_id = ?) ORDER BY sort, name', [$id]),
        'drinkIds' => bootcamp_drink_ids($id),
        'extras' => all('SELECT x.*, COALESCE(e.name, x.custom_name) AS equipment_name FROM extra_requests x LEFT JOIN equipment e ON e.id = x.equipment_id WHERE x.bootcamp_id = ? AND x.status <> "cancelled" ORDER BY x.requested_at DESC', [$id]),
        'catalog' => all('SELECT * FROM equipment WHERE active = 1 AND requestable = 1 ORDER BY category, sort, name'),
        'tickets' => all("SELECT * FROM tickets WHERE bootcamp_id = ? ORDER BY status = 'resolved', updated_at DESC LIMIT 5", [$id]),
        'others' => all("SELECT b.id, b.start_date, b.end_date, b.status, h.name AS hotel_name FROM bootcamps b JOIN hotels h ON h.id = b.hotel_id WHERE b.team_id = ? AND b.id <> ? AND b.status <> 'cancelled' ORDER BY b.start_date DESC", [(int) $u['team_id'], $id]),
        'open' => bootcamp_is_open($b),
        'feedback' => bootcamp_feedback($id),
        'canRate' => $b['end_date'] <= date('Y-m-d') || $b['status'] === 'completed',
        'teamLogo' => scalar('SELECT logo FROM teams WHERE id = ?', [$b['team_id']]),
        'activities' => all('SELECT * FROM activities WHERE active = 1 ORDER BY sort, name'),
        'activityRequests' => all('SELECT r.*, a.name AS activity_name FROM activity_requests r JOIN activities a ON a.id = r.activity_id WHERE r.bootcamp_id = ? ORDER BY r.created_at DESC', [$id]),
    ]);
}

function client_guard_open(array $b): void
{
    if (!bootcamp_is_open($b)) {
        flash('error', 'This bootcamp is finished and can no longer be changed.');
        redirect('/my/bootcamp/' . $b['id']);
    }
}

function client_drinks(int $id): void
{
    $u = client_user();
    $b = client_bootcamp_or_404($u, $id);
    client_guard_open($b);
    $picked = array_values(array_unique(array_map('intval', (array) ($_POST['drinks'] ?? []))));
    if (count($picked) > DRINKS_MAX) {
        flash('error', 'You can choose up to ' . DRINKS_MAX . ' drinks.');
        redirect('/my/bootcamp/' . $id . '#drinks');
    }
    $before = bootcamp_drink_ids($id);
    save_bootcamp_drinks($id, $picked);
    $after = bootcamp_drink_ids($id);
    $note = mb_substr(trim((string) input('drinks_note')), 0, 1000);
    $noteChanged = $note !== (string) $b['drinks_note'];
    update('bootcamps', ['drinks_note' => $note ?: null], 'id = ?', [$id]);
    sort($before);
    sort($after);
    if ($before !== $after || $noteChanged) {
        $names = array_column(bootcamp_drinks($id), 'name');
        log_activity($id, 'Team chose drinks: ' . ($names ? implode(', ', $names) : 'none') . ($note ? ' — “' . $note . '”' : ''));
        notify_staff($b['team_name'] . ' updated drinks', ($names ? implode(', ', $names) : 'No drinks selected') . ($note ? ' — ' . $note : ''), '/bootcamps/' . $id . '#drinks', false);
    }
    flash('success', 'Drinks saved. We will stock your fridge with these.');
    redirect('/my/bootcamp/' . $id . '#drinks');
}

function client_refill(int $id): void
{
    $u = client_user();
    $b = client_bootcamp_or_404($u, $id);
    client_guard_open($b);
    $note = trim((string) input('note'));
    $picked = array_map('intval', (array) ($_POST['refill'] ?? []));
    $lines = [];
    foreach (all('SELECT id, name FROM drinks ORDER BY sort, name') as $d) {
        if (in_array((int) $d['id'], $picked, true)) {
            $lines[] = '• ' . $d['name'];
        }
    }
    if (!$lines && $note === '') {
        flash('error', 'Choose which drinks we should refill.');
        redirect('/my/bootcamp/' . $id . '#refill');
    }
    $body = 'Please refill the fridge' . ($b['room'] ? ' in room ' . $b['room'] : '') . ':'
        . ($lines ? "\n" . implode("\n", $lines) : '')
        . ($note !== '' ? "\n\n" . $note : '');
    log_activity($id, 'Team requested a drinks refill' . ($lines ? ': ' . implode(', ', array_map(fn ($l) => mb_substr($l, 2), $lines)) : ''));
    $ticketId = ticket_create($u, (int) $b['team_id'], $id, 'refill', 'Drinks refill' . ($b['room'] ? ' · room ' . $b['room'] : ''), $body);
    flash('success', 'Refill requested. We will bring the drinks soon.');
    redirect('/tickets/' . $ticketId);
}

function client_chairs(int $id): void
{
    $u = client_user();
    $b = client_bootcamp_or_404($u, $id);
    client_guard_open($b);
    save_chair_configs($id, (array) ($_POST['chair'] ?? []));
    $chairsNote = mb_substr(trim((string) input('chairs_note')), 0, 1000);
    update('bootcamps', ['chairs_note' => $chairsNote ?: null], 'id = ?', [$id]);
    $changed = (int) scalar("SELECT COUNT(*) FROM chair_configs WHERE bootcamp_id = ? AND armrests <> 'keep'", [$id]);
    log_activity($id, 'Team set chairs: ' . chair_summary_text(bootcamp_chairs($id)) . ($chairsNote ? ' — “' . $chairsNote . '”' : ''));
    notify_staff($b['team_name'] . ' updated chair setup', $changed ? $changed . ' chair(s) with armrests removed' : 'All chairs keep armrests', '/bootcamps/' . $id . '#chairs', false);
    flash('success', 'Chair setup saved.');
    redirect('/my/bootcamp/' . $id . '#chairs');
}

function client_request_extra(int $id): void
{
    $u = client_user();
    $b = client_bootcamp_or_404($u, $id);
    client_guard_open($b);
    $ids = (array) ($_POST['equipment_id'] ?? []);
    $qtys = (array) ($_POST['qty'] ?? []);
    $others = (array) ($_POST['other_name'] ?? []);
    $note = mb_substr(trim((string) input('note')), 0, 500);
    $lines = [];
    foreach ($ids as $i => $eid) {
        $qty = min(20, max(1, (int) ($qtys[$i] ?? 1)));
        if ($eid === 'other') {
            $name = mb_substr(trim((string) ($others[$i] ?? '')), 0, 190);
            if ($name !== '') {
                $lines[] = ['equipment_id' => null, 'custom_name' => $name, 'qty' => $qty, 'label' => $name . ' (other)'];
            }
            continue;
        }
        $eq = one('SELECT * FROM equipment WHERE id = ? AND active = 1 AND requestable = 1', [(int) $eid]);
        if ($eq) {
            $lines[] = ['equipment_id' => (int) $eq['id'], 'custom_name' => null, 'qty' => $qty, 'label' => $eq['name']];
        }
    }
    if (!$lines) {
        flash('error', 'Choose at least one item (for “Other”, describe what you need).');
        redirect('/my/bootcamp/' . $id . '#extras');
    }
    foreach ($lines as $l) {
        insert('extra_requests', [
            'bootcamp_id' => $id, 'equipment_id' => $l['equipment_id'], 'custom_name' => $l['custom_name'], 'qty' => $l['qty'],
            'note' => $note ?: null, 'status' => 'pending', 'requested_by' => $u['id'], 'requested_at' => now(),
        ]);
    }
    $summary = implode(', ', array_map(fn ($l) => $l['qty'] . '× ' . $l['label'], $lines));
    log_activity($id, 'Team requested extra: ' . $summary . ($note ? ' — “' . $note . '”' : ''));
    notify_staff(
        'Extra request: ' . $summary,
        $b['team_name'] . ' · ' . $b['hotel_name'] . ($b['room'] ? ' · room ' . $b['room'] : '') . ($note ? ' — ' . $note : '') . ' (needs manager approval)',
        '/bootcamps/' . $id . '#extras'
    );
    flash('success', count($lines) > 1 ? 'Requests sent. The bootcamp manager will confirm them shortly.' : 'Request sent. The bootcamp manager will confirm it shortly.');
    redirect('/my/bootcamp/' . $id . '#extras');
}

function client_cancel_extra(int $id): void
{
    $u = client_user();
    $x = one('SELECT * FROM extra_requests WHERE id = ?', [$id]) ?? not_found();
    $b = client_bootcamp_or_404($u, (int) $x['bootcamp_id']);
    if ($x['status'] === 'pending') {
        update('extra_requests', ['status' => 'cancelled', 'decided_at' => now()], 'id = ?', [$id]);
        log_activity((int) $b['id'], 'Team cancelled extra request');
        flash('success', 'Request cancelled.');
    }
    redirect('/my/bootcamp/' . $b['id'] . '#extras');
}
