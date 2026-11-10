<?php
declare(strict_types=1);

function account_profile(): void
{
    $u = require_login();
    if (is_post()) {
        $action = (string) input('action');
        if ($action === 'profile') {
            $name = mb_substr(trim((string) input('name')), 0, 120);
            if ($name === '') {
                flash('error', __('Name cannot be empty.'));
            } else {
                $upd = ['name' => $name, 'phone' => mb_substr((string) input('phone'), 0, 40) ?: null];
                if (is_staff($u) && isset(LANGUAGES[(string) input('lang')])) {
                    $upd['lang'] = (string) input('lang');
                }
                update('users', $upd, 'id = ?', [$u['id']]);
                $GLOBALS['__locale_stack'][] = $upd['lang'] ?? locale();
                flash('success', __('Profile saved.'));
            }
        } elseif ($action === 'password') {
            $current = (string) ($_POST['current'] ?? '');
            $p1 = (string) ($_POST['password'] ?? '');
            $p2 = (string) ($_POST['password2'] ?? '');
            if (!$u['password_hash'] || !password_verify($current, $u['password_hash'])) {
                flash('error', __('Current password is wrong.'));
            } elseif (mb_strlen($p1) < 8) {
                flash('error', __('New password must be at least 8 characters.'));
            } elseif ($p1 !== $p2) {
                flash('error', __('Passwords do not match.'));
            } else {
                update('users', ['password_hash' => password_hash($p1, PASSWORD_DEFAULT)], 'id = ?', [$u['id']]);
                q('DELETE FROM remember_tokens WHERE user_id = ?', [$u['id']]);
                issue_remember_token((int) $u['id']);
                flash('success', __('Password changed. Other devices were signed out.'));
            }
        }
        redirect('/profile');
    }
    view('account/profile', [
        'title' => __('Profile'),
        'u' => $u,
        'devices' => all('SELECT id, user_agent, created_at, last_ok_at FROM push_subscriptions WHERE user_id = ? ORDER BY created_at DESC', [$u['id']]),
    ]);
}

function account_notifications(): void
{
    $u = require_login();
    view('account/notifications', [
        'title' => __('Notifications'),
        'rows' => all('SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 100', [$u['id']]),
    ]);
}

function account_read_all(): void
{
    $u = require_login();
    q('UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL', [$u['id']]);
    if (wants_json()) {
        json_out(['ok' => true]);
    }
    redirect('/notifications');
}

function account_open_notification(int $id): void
{
    $u = require_login();
    $n = one('SELECT * FROM notifications WHERE id = ? AND user_id = ?', [$id, $u['id']]) ?? not_found();
    q('UPDATE notifications SET read_at = COALESCE(read_at, NOW()) WHERE id = ?', [$id]);
    $to = (string) ($n['url'] ?: '/');
    redirect(str_starts_with($to, '/') ? $to : '/');
}

/** Lightweight polling endpoint for badges. */
function account_status(): void
{
    $u = require_login();
    json_out([
        'unread' => unread_notifications_count((int) $u['id']),
    ]);
}
