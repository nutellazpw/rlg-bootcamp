<?php
declare(strict_types=1);

require_once APP_DIR . '/controllers/teams.php'; // flash_invite_result()

function users_index(): void
{
    require_role('admin');
    view('users/index', [
        'title' => 'Staff accounts',
        'rows' => all("SELECT * FROM users WHERE role IN ('admin','manager','support') ORDER BY active DESC, FIELD(role,'admin','manager','support'), name"),
    ]);
}

function users_create(): void
{
    require_role('admin');
    $name = trim((string) input('name'));
    $email = mb_strtolower((string) input('email'));
    $role = (string) input('role');
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($role, STAFF_ROLES, true)) {
        flash('error', 'Enter name, a valid email and a role.');
        redirect('/users');
    }
    if (one('SELECT id FROM users WHERE email = ?', [$email])) {
        flash('error', 'An account with that email already exists.');
        redirect('/users');
    }
    $id = insert('users', [
        'role' => $role, 'name' => mb_substr($name, 0, 120), 'email' => $email,
        'phone' => mb_substr((string) input('phone'), 0, 40) ?: null, 'active' => 1, 'created_at' => now(),
    ]);
    [$sent, $link] = send_invite(one('SELECT * FROM users WHERE id = ?', [$id]));
    flash_invite_result($email, $sent, $link);
    redirect('/users');
}

function users_update(int $id): void
{
    $me = require_role('admin');
    $u = one('SELECT * FROM users WHERE id = ?', [$id]) ?? not_found();
    $role = (string) input('role', $u['role']);
    if (!in_array($role, STAFF_ROLES, true) || $u['role'] === 'client') {
        flash('error', 'Invalid role.');
        redirect('/users');
    }
    if ((int) $u['id'] === (int) $me['id'] && $role !== 'admin') {
        flash('error', 'You cannot remove your own administrator role.');
        redirect('/users');
    }
    update('users', [
        'role' => $role,
        'name' => mb_substr(trim((string) input('name', $u['name'])), 0, 120) ?: $u['name'],
        'phone' => mb_substr((string) input('phone', (string) $u['phone']), 0, 40) ?: null,
    ], 'id = ?', [$id]);
    flash('success', 'Saved.');
    redirect('/users');
}

function users_can_touch(array $me, array $u): bool
{
    if (is_admin($me)) {
        return true;
    }
    return can_manage($me) && $u['role'] === 'client';
}

function users_resend_invite(int $id): void
{
    $me = require_role('admin', 'manager');
    $u = one('SELECT * FROM users WHERE id = ? AND active = 1', [$id]) ?? not_found();
    if (!users_can_touch($me, $u)) {
        forbidden();
    }
    if ($u['password_hash']) {
        $token = create_password_token((int) $u['id'], 'reset');
        $link = abs_url('/set-password?token=' . $token);
        $sent = send_mail($u['email'], 'Reset your RLG Bootcamp password', mail_template('Password reset', 'A Relog staff member sent you a link to set a new password for RLG Bootcamp. The link is valid for 2 hours.', 'Set new password', $link));
        $sent ? flash('success', 'Password reset link sent to ' . $u['email'] . '.')
              : flash('warning', 'Email could not be sent. Send this link yourself (valid 2 hours):<br><input class="copy-input" readonly value="' . e($link) . '" onclick="this.select()">', true);
    } else {
        [$sent, $link] = send_invite($u);
        flash_invite_result($u['email'], $sent, $link);
    }
    back('/');
}

function users_toggle(int $id): void
{
    $me = require_role('admin', 'manager');
    $u = one('SELECT * FROM users WHERE id = ?', [$id]) ?? not_found();
    if (!users_can_touch($me, $u) || (int) $u['id'] === (int) $me['id']) {
        forbidden();
    }
    $active = $u['active'] ? 0 : 1;
    update('users', ['active' => $active], 'id = ?', [$id]);
    if (!$active) {
        q('DELETE FROM remember_tokens WHERE user_id = ?', [$id]);
        q('DELETE FROM push_subscriptions WHERE user_id = ?', [$id]);
    }
    flash('success', $u['name'] . ($active ? ' enabled.' : ' disabled.'));
    back('/');
}
