<?php
declare(strict_types=1);

// Extra activity requests and new-bootcamp quote requests.

// ---------- Client ----------

function requests_activity(int $bootcampId): void
{
    $u = require_login();
    if (is_staff($u)) {
        forbidden();
    }
    $b = client_bootcamp_or_404($u, $bootcampId);
    $a = one('SELECT * FROM activities WHERE id = ? AND active = 1', [input_int('activity_id')]);
    if (!$a) {
        flash('error', 'Choose an activity.');
        redirect('/my/bootcamp/' . $bootcampId . '#activities');
    }
    $date = input_date('preferred_date');
    $time = mb_substr(trim((string) input('preferred_time')), 0, 30);
    $people = input_int('people') ?: null;
    $note = mb_substr(trim((string) input('note')), 0, 2000);
    insert('activity_requests', [
        'activity_id' => $a['id'], 'team_id' => $b['team_id'], 'bootcamp_id' => $bootcampId, 'requested_by' => $u['id'],
        'preferred_date' => $date, 'preferred_time' => $time ?: null, 'people' => $people, 'note' => $note ?: null,
        'status' => 'pending', 'created_at' => now(),
    ]);
    log_activity($bootcampId, fn () => __('Team requested activity: %s', $a['name']) . ($date || $time ? ' (' . trim(($date ? ldate('D d M', strtotime($date)) : '') . ' ' . $time) . ')' : ''));
    notify_staff(
        fn () => __('Activity request: %s', $a['name']) . ' · ' . $b['team_name'],
        fn () => trim((($date || $time) ? trim(($date ? ldate('D d M', strtotime($date)) : '') . ' ' . $time) . ' · ' : '') . ($people ? __('%d people', $people) . ' · ' : '') . $note, ' ·'),
        '/requests?tab=activities'
    );
    flash('success', 'Request sent — we will get back to you with the details and the offer.');
    redirect('/my/bootcamp/' . $bootcampId . '#activities');
}

function requests_quote_form(): void
{
    $u = require_login();
    if (is_staff($u)) {
        redirect('/requests');
    }
    $errors = [];
    if (is_post()) {
        $start = input_date('start_date');
        $end = input_date('end_date');
        if (!$start || !$end) {
            $errors[] = 'Choose the dates you would like (or approximate dates and tick “flexible”).';
        } elseif ($end < $start) {
            $errors[] = 'End date is before start date.';
        }
        if (!$errors) {
            $id = insert('quote_requests', [
                'team_id' => $u['team_id'], 'requested_by' => $u['id'],
                'start_date' => $start, 'end_date' => $end, 'flexible' => input('flexible') ? 1 : 0,
                'people' => input_int('people') ?: null, 'pcs' => input_int('pcs') ?: null,
                'hotel_pref' => mb_substr(trim((string) input('hotel_pref')), 0, 120) ?: null,
                'note' => mb_substr(trim((string) input('note')), 0, 3000) ?: null,
                'status' => 'new', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $team = (string) scalar('SELECT name FROM teams WHERE id = ?', [$u['team_id']]);
            $flex = (bool) input('flexible');
            $people = input_int('people');
            $pcs = input_int('pcs');
            notify(
                manager_ids(),
                fn () => __('New bootcamp request: %s', $team),
                fn () => fmt_range($start, $end) . ($flex ? ' (' . __('flexible') . ')' : '') . ($people ? ' · ' . __('%d people', $people) : '') . ($pcs ? ' · ' . __('%d PCs', $pcs) : ''),
                '/requests',
                fn () => '📅 ' . __('New bootcamp request from %s: %s', $team, fmt_range($start, $end))
            );
            flash('success', 'Request sent! Our bootcamp manager will contact you with an offer.');
            redirect('/my/new-bootcamp');
        }
    }
    view('client/quote', [
        'title' => 'Request a bootcamp',
        'errors' => $errors,
        'quotes' => all('SELECT * FROM quote_requests WHERE team_id = ? ORDER BY created_at DESC', [(int) $u['team_id']]),
    ]);
}

// ---------- Staff ----------

function requests_index(): void
{
    require_staff();
    $tab = input('tab') === 'activities' ? 'activities' : 'quotes';
    view('requests/index', [
        'title' => __('Requests'),
        'tab' => $tab,
        'quotes' => all(
            "SELECT q.*, t.name AS team_name, u.name AS requested_name, h.name AS handled_name
             FROM quote_requests q JOIN teams t ON t.id = q.team_id LEFT JOIN users u ON u.id = q.requested_by LEFT JOIN users h ON h.id = q.handled_by
             ORDER BY q.status IN ('converted','declined'), q.created_at DESC LIMIT 200"
        ),
        'activityRequests' => all(
            "SELECT r.*, a.name AS activity_name, t.name AS team_name, b.room, ho.name AS hotel_name, u.name AS requested_name, rp.name AS replied_name
             FROM activity_requests r JOIN activities a ON a.id = r.activity_id JOIN teams t ON t.id = r.team_id
             LEFT JOIN bootcamps b ON b.id = r.bootcamp_id LEFT JOIN hotels ho ON ho.id = b.hotel_id
             LEFT JOIN users u ON u.id = r.requested_by LEFT JOIN users rp ON rp.id = r.replied_by
             ORDER BY r.status <> 'pending', r.created_at DESC LIMIT 200"
        ),
        'counts' => [
            'quotes' => (int) scalar("SELECT COUNT(*) FROM quote_requests WHERE status = 'new'"),
            'activities' => (int) scalar("SELECT COUNT(*) FROM activity_requests WHERE status = 'pending'"),
        ],
    ]);
}

function requests_activity_reply(int $id): void
{
    $me = require_staff();
    $r = one('SELECT r.*, a.name AS activity_name FROM activity_requests r JOIN activities a ON a.id = r.activity_id WHERE r.id = ?', [$id]) ?? not_found();
    $status = (string) input('status', $r['status']);
    if (!array_key_exists($status, ACTIVITY_STATUSES)) {
        $status = $r['status'];
    }
    $reply = mb_substr(trim((string) input('staff_reply')), 0, 3000);
    update('activity_requests', ['status' => $status, 'staff_reply' => $reply ?: null, 'replied_by' => $me['id'], 'replied_at' => now()], 'id = ?', [$id]);
    if ($r['bootcamp_id']) {
        log_activity((int) $r['bootcamp_id'], fn () => __('Activity %s → %s', $r['activity_name'], lbl(ACTIVITY_STATUSES, $status)) . ($reply ? ': ' . mb_strimwidth($reply, 0, 120, '…') : ''));
    }
    if ($status !== $r['status'] || $reply !== (string) $r['staff_reply']) {
        notify(
            team_user_ids((int) $r['team_id']),
            $r['activity_name'] . ': ' . ACTIVITY_STATUSES[$status],
            $reply !== '' ? mb_strimwidth($reply, 0, 160, '…') : 'See the details in the app.',
            $r['bootcamp_id'] ? '/my/bootcamp/' . $r['bootcamp_id'] . '#activities' : '/my'
        );
    }
    flash('success', __('Saved and sent to the team.'));
    back('/requests?tab=activities');
}

function requests_quote_update(int $id): void
{
    $me = require_role('admin', 'manager');
    $q = one('SELECT * FROM quote_requests WHERE id = ?', [$id]) ?? not_found();
    $status = (string) input('status', $q['status']);
    if (!array_key_exists($status, QUOTE_STATUSES)) {
        $status = $q['status'];
    }
    $reply = mb_substr(trim((string) input('staff_reply')), 0, 3000);
    update('quote_requests', ['status' => $status, 'staff_reply' => $reply ?: null, 'handled_by' => $me['id'], 'updated_at' => now()], 'id = ?', [$id]);
    if ($status !== $q['status'] || $reply !== (string) $q['staff_reply']) {
        notify(team_user_ids((int) $q['team_id']), 'Your bootcamp request: ' . QUOTE_STATUSES[$status], $reply !== '' ? mb_strimwidth($reply, 0, 160, '…') : 'See the details in the app.', '/my/new-bootcamp');
    }
    flash('success', __('Saved.'));
    redirect('/requests');
}

// ---------- Activities catalog (managers) ----------

function requests_activities_catalog(): void
{
    require_staff();
    view('requests/activities', ['title' => __('Activities'), 'rows' => all('SELECT * FROM activities ORDER BY active DESC, sort, name')]);
}

function requests_activity_save(?int $id = null): void
{
    require_role('admin', 'manager');
    $name = mb_substr(trim((string) input('name')), 0, 120);
    if ($name === '') {
        flash('error', __('Enter a name.'));
        redirect('/activities');
    }
    $data = [
        'name' => $name,
        'description' => trim((string) input('description')) ?: null,
        'active' => input('active', $id ? '' : '1') ? 1 : 0,
        'sort' => input_int('sort'),
    ];
    $img = store_image_upload('image', 'activities');
    if ($img) {
        $data['image'] = $img;
    } elseif ($id && !empty($_POST['remove_image'])) {
        $data['image'] = null;
    }
    if ($id) {
        one('SELECT id FROM activities WHERE id = ?', [$id]) ?? not_found();
        update('activities', $data, 'id = ?', [$id]);
    } else {
        $data['sort'] = (int) scalar('SELECT COALESCE(MAX(sort),0)+1 FROM activities');
        insert('activities', $data);
    }
    flash('success', __('Saved.'));
    redirect('/activities');
}

function requests_activity_image(int $id): void
{
    require_login();
    $a = one('SELECT image FROM activities WHERE id = ?', [$id]);
    if (!$a || !$a['image']) {
        not_found();
    }
    serve_stored_image('activities', $a['image']);
}
