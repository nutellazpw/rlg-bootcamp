<?php
declare(strict_types=1);

function teams_index(): void
{
    require_staff();
    $search = (string) input('q');
    $params = [];
    $sql = "SELECT t.*,
              (SELECT COUNT(*) FROM users u WHERE u.team_id = t.id AND u.role = 'client' AND u.active = 1) AS accounts,
              (SELECT MIN(b.start_date) FROM bootcamps b WHERE b.team_id = t.id AND b.status NOT IN ('cancelled','completed') AND b.end_date >= CURDATE()) AS next_start,
              (SELECT COUNT(*) FROM bootcamps b WHERE b.team_id = t.id AND b.status <> 'cancelled') AS bootcamps
            FROM teams t";
    if ($search !== '') {
        $sql .= ' WHERE t.name LIKE ? OR t.contact_name LIKE ?';
        $params = ['%' . $search . '%', '%' . $search . '%'];
    }
    view('teams/index', ['title' => 'Teams', 'rows' => all($sql . ' ORDER BY t.name', $params), 'search' => $search]);
}

function teams_save(?array $existing): array
{
    $name = mb_substr((string) input('name'), 0, 120);
    if ($name === '') {
        return [null, ['Enter the team name.']];
    }
    $email = mb_strtolower((string) input('contact_email'));
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [null, ['Contact email is not valid.']];
    }
    $data = [
        'name' => $name,
        'game' => in_array(input('game'), GAMES, true) ? input('game') : 'Other',
        'contact_name' => mb_substr((string) input('contact_name'), 0, 120) ?: null,
        'contact_email' => $email ?: null,
        'contact_phone' => mb_substr((string) input('contact_phone'), 0, 40) ?: null,
        'notes' => (string) input('notes') ?: null,
    ];
    $logo = store_image_upload('logo', 'teams');
    if ($logo) {
        $data['logo'] = $logo;
    } elseif ($existing && !empty($_POST['remove_logo'])) {
        $data['logo'] = null;
    }
    if ($existing) {
        update('teams', $data, 'id = ?', [$existing['id']]);
        return [(int) $existing['id'], []];
    }
    $data['created_at'] = now();
    return [insert('teams', $data), []];
}

function teams_create(): void
{
    require_role('admin', 'manager');
    $errors = [];
    $t = ['game' => 'CS2'];
    if (is_post()) {
        [$id, $errors] = teams_save(null);
        if ($id) {
            // Optionally create the client account right away
            if (!empty($_POST['invite']) && input('contact_email') !== '') {
                teams_create_account($id, (string) input('contact_name') ?: (string) input('name'), (string) input('contact_email'), (string) input('contact_phone'));
            } else {
                flash('success', 'Team added.');
            }
            redirect('/teams/' . $id);
        }
        $t = $_POST;
    }
    view('teams/form', ['title' => 'New team', 't' => $t, 'isNew' => true, 'errors' => $errors]);
}

function teams_edit(int $id): void
{
    require_role('admin', 'manager');
    $existing = one('SELECT * FROM teams WHERE id = ?', [$id]) ?? not_found();
    $errors = [];
    $t = $existing;
    if (is_post()) {
        [$ok, $errors] = teams_save($existing);
        if ($ok) {
            flash('success', 'Team saved.');
            redirect('/teams/' . $id);
        }
        $t = array_merge($existing, $_POST);
    }
    view('teams/form', ['title' => 'Edit team', 't' => $t, 'isNew' => false, 'errors' => $errors]);
}

function teams_show(int $id): void
{
    require_staff();
    $t = one('SELECT * FROM teams WHERE id = ?', [$id]) ?? not_found();
    view('teams/show', [
        'title' => $t['name'],
        't' => $t,
        'accounts' => all("SELECT * FROM users WHERE team_id = ? AND role = 'client' ORDER BY active DESC, name", [$id]),
        'counts' => [
            'tickets' => (int) scalar('SELECT COUNT(*) FROM tickets WHERE team_id = ?', [$id]),
        ],
        'bootcamps' => all('SELECT b.*, h.name AS hotel_name, ? AS team_name FROM bootcamps b JOIN hotels h ON h.id = b.hotel_id WHERE b.team_id = ? ORDER BY b.start_date DESC', [$t['name'], $id]),
    ]);
}

function teams_delete(int $id): void
{
    require_role('admin', 'manager');
    $t = one('SELECT * FROM teams WHERE id = ?', [$id]) ?? not_found();
    if (trim((string) input('confirm_name')) !== trim($t['name'])) {
        flash('error', 'Type the team name exactly to confirm the deletion.');
        redirect('/teams/' . $id . '#delete-team');
    }
    $n = (int) scalar('SELECT COUNT(*) FROM bootcamps WHERE team_id = ?', [$id]);
    $db = db();
    $db->beginTransaction();
    purge_team($id);
    $db->commit();
    purge_files_now();
    log_activity(null, 'Deleted team ' . $t['name'] . ' (with ' . $n . ' bootcamp' . ($n === 1 ? '' : 's') . ')');
    flash('success', 'Team ' . $t['name'] . ' deleted.');
    redirect('/teams');
}

function teams_add_account(int $id): void
{
    require_role('admin', 'manager');
    one('SELECT id FROM teams WHERE id = ?', [$id]) ?? not_found();
    teams_create_account($id, (string) input('name'), (string) input('email'), (string) input('phone'));
    redirect('/teams/' . $id);
}

function teams_create_account(int $teamId, string $name, string $email, string $phone): void
{
    $email = mb_strtolower(trim($email));
    $name = trim($name);
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('error', 'Enter a name and a valid email for the account.');
        return;
    }
    $existing = one('SELECT * FROM users WHERE email = ?', [$email]);
    if ($existing) {
        flash('error', 'An account with ' . $email . ' already exists' . ($existing['role'] === 'client' && (int) $existing['team_id'] !== $teamId ? ' (in another team)' : '') . '.');
        return;
    }
    $uid = insert('users', [
        'role' => 'client', 'name' => mb_substr($name, 0, 120), 'email' => $email,
        'phone' => mb_substr($phone, 0, 40) ?: null, 'team_id' => $teamId, 'active' => 1, 'created_at' => now(),
    ]);
    $u = one('SELECT * FROM users WHERE id = ?', [$uid]);
    [$sent, $link] = send_invite($u);
    flash_invite_result($email, $sent, $link);
}

function flash_invite_result(string $email, bool $sent, string $link): void
{
    if ($sent) {
        flash('success', 'Invitation sent to ' . $email . '.');
    } else {
        flash('warning', 'Account created, but the email could not be sent. Send this link to ' . e($email) . ' yourself (valid 7 days):<br><input class="copy-input" readonly value="' . e($link) . '" onclick="this.select()">', true);
    }
}

/** Team logo: visible to staff and to that team's accounts. */
function teams_logo(int $id): void
{
    $u = require_login();
    if (!is_staff($u) && (int) $u['team_id'] !== $id) {
        not_found();
    }
    $t = one('SELECT logo FROM teams WHERE id = ?', [$id]);
    if (!$t || !$t['logo']) {
        not_found();
    }
    serve_stored_image('teams', $t['logo']);
}
