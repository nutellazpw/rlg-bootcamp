<?php
declare(strict_types=1);

function tickets_access(array $u, int $id): array
{
    $t = ticket_find($id);
    if (!$t || (!is_staff($u) && (int) $t['team_id'] !== (int) $u['team_id'])) {
        not_found();
    }
    return $t;
}

function tickets_index(): void
{
    $u = require_login();
    $staff = is_staff($u);
    $tab = (string) input('tab', 'active');
    $where = [];
    $params = [];
    if (!$staff) {
        $where[] = 'tk.team_id = ?';
        $params[] = (int) $u['team_id'];
    }
    if ($tab === 'active') {
        $where[] = "tk.status IN ('open','in_progress')";
    } elseif ($tab === 'resolved') {
        $where[] = "tk.status = 'resolved'";
    } elseif ($tab === 'mine' && $staff) {
        $where[] = "tk.assigned_to = ? AND tk.status <> 'resolved'";
        $params[] = (int) $u['id'];
    }
    $cat = (string) input('category');
    if ($cat !== '' && array_key_exists($cat, TICKET_CATEGORIES)) {
        $where[] = 'tk.category = ?';
        $params[] = $cat;
    }
    $params[] = (int) $u['id'];
    $rows = all(
        'SELECT tk.*, t.name AS team_name, a.name AS assignee_name, h.name AS hotel_name, b.room,
                (SELECT MAX(m.id) FROM ticket_messages m WHERE m.ticket_id = tk.id) AS last_msg,
                (SELECT body FROM ticket_messages m WHERE m.ticket_id = tk.id ORDER BY m.id DESC LIMIT 1) AS last_body,
                COALESCE(r.last_message_id, 0) AS read_upto
         FROM tickets tk JOIN teams t ON t.id = tk.team_id
         LEFT JOIN users a ON a.id = tk.assigned_to
         LEFT JOIN bootcamps b ON b.id = tk.bootcamp_id LEFT JOIN hotels h ON h.id = b.hotel_id
         LEFT JOIN ticket_reads r ON r.ticket_id = tk.id AND r.user_id = ?
         ' . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . '
         ORDER BY tk.status = "resolved", (tk.awaiting_staff_since IS NULL), tk.updated_at DESC LIMIT 300',
        array_merge([(int) $u['id']], array_slice($params, 0, -1))
    );
    view('tickets/index', ['title' => $staff ? 'Tickets' : 'Support', 'rows' => $rows, 'tab' => $tab, 'cat' => $cat, 'staff' => $staff]);
}

function tickets_create(): void
{
    $u = require_login();
    $staff = is_staff($u);
    $errors = [];
    $bootcampId = input_int('bootcamp');
    $b = $bootcampId ? bootcamp_find($bootcampId) : null;
    if ($b && !$staff && (int) $b['team_id'] !== (int) $u['team_id']) {
        $b = null;
    }
    if (!$staff && !$b) {
        $b = client_current_bootcamp($u);
    }
    if ($staff && !$b) {
        flash('info', 'Open a bootcamp and use “Message team” to start a conversation with that team.');
        redirect('/bootcamps');
    }
    $subject = (string) input('subject');
    $body = (string) input('body');
    $category = (string) input('category', $staff ? 'other' : 'technical');
    if (is_post()) {
        if ($subject === '' || $body === '') {
            $errors[] = 'Write a subject and a message.';
        } else {
            $teamId = $b ? (int) $b['team_id'] : (int) $u['team_id'];
            $id = ticket_create($u, $teamId, $b ? (int) $b['id'] : null, $category, $subject, $body);
            flash('success', $staff ? 'Message sent to the team.' : 'Ticket sent. Our support team has been notified.');
            redirect('/tickets/' . $id);
        }
    }
    view('tickets/create', [
        'title' => $staff ? 'Message team' : 'New ticket',
        'b' => $b, 'staff' => $staff, 'errors' => $errors,
        'subject' => $subject, 'body' => $body, 'category' => $category,
    ]);
}

function tickets_show(int $id): void
{
    $u = require_login();
    $t = tickets_access($u, $id);
    $messages = ticket_messages($id);
    ticket_mark_read($id, (int) $u['id']);
    // Opening a ticket counts as reading its notifications
    q('UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL AND url = ?', [$u['id'], '/tickets/' . $id]);
    view('tickets/show', [
        'title' => 'Ticket #' . $id,
        't' => $t,
        'messages' => $messages,
        'staffUsers' => is_staff($u) ? all("SELECT id, name FROM users WHERE active = 1 AND role IN ('admin','manager','support') ORDER BY name") : [],
    ]);
}

function tickets_reply(int $id): void
{
    $u = require_login();
    $t = tickets_access($u, $id);
    $body = trim((string) ($_POST['body'] ?? ''));
    if ($body === '') {
        if (wants_json()) {
            json_out(['error' => 'Empty message'], 422);
        }
        redirect('/tickets/' . $id);
    }
    ticket_add_message($t, $u, mb_substr($body, 0, 5000));
    if (wants_json()) {
        tickets_messages($id);
    }
    redirect('/tickets/' . $id . '#bottom');
}

function tickets_update(int $id): void
{
    $u = require_staff();
    $t = tickets_access($u, $id);
    $data = ['updated_at' => now()];
    $status = (string) input('status', $t['status']);
    if (array_key_exists($status, TICKET_STATUSES) && $status !== $t['status']) {
        $data['status'] = $status;
        ticket_system_message($id, $u['name'] . ' marked the ticket as ' . TICKET_STATUSES[$status] . '.');
        if ($status === 'resolved') {
            $data['awaiting_staff_since'] = null;
            $data['last_reminder_at'] = null;
            notify(team_user_ids((int) $t['team_id']), 'Ticket resolved: ' . $t['subject'], 'Reply in the ticket if you still need help.', '/tickets/' . $id);
        }
    }
    if (isset($_POST['assigned_to'])) {
        $assignee = input_int('assigned_to') ?: null;
        if ($assignee !== ($t['assigned_to'] ? (int) $t['assigned_to'] : null)) {
            $name = $assignee ? (string) scalar('SELECT name FROM users WHERE id = ?', [$assignee]) : '';
            $data['assigned_to'] = $assignee;
            ticket_system_message($id, $assignee ? 'Assigned to ' . $name . '.' : 'Unassigned.');
            if ($assignee && $assignee !== (int) $u['id']) {
                notify([$assignee], 'Ticket #' . $id . ' assigned to you', $t['team_name'] . ' — ' . $t['subject'], '/tickets/' . $id);
            }
        }
    }
    update('tickets', $data, 'id = ?', [$id]);
    redirect('/tickets/' . $id);
}

/** JSON: messages newer than ?after=ID, rendered as HTML. Used for live updates. */
function tickets_messages(int $id): void
{
    $u = require_login();
    $t = tickets_access($u, $id);
    $after = input_int('after');
    $msgs = ticket_messages($id, $after);
    ob_start();
    foreach ($msgs as $m) {
        partial('tickets/_message', ['m' => $m, 'me' => $u]);
    }
    $html = ob_get_clean();
    if ($msgs) {
        ticket_mark_read($id, (int) $u['id']);
    }
    $fresh = ticket_find($id);
    json_out([
        'html' => $html,
        'last_id' => $msgs ? (int) end($msgs)['id'] : $after,
        'status' => $fresh['status'],
        'status_label' => TICKET_STATUSES[$fresh['status']] ?? $fresh['status'],
    ]);
}
