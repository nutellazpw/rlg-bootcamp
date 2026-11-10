<?php
declare(strict_types=1);

const ROLES = [
    'admin'   => 'Administrator',
    'manager' => 'Bootcamp manager',
    'support' => 'Support',
    'client'  => 'Client',
];
const STAFF_ROLES = ['admin', 'manager', 'support'];
const REMEMBER_COOKIE = 'rlg_remember';
const REMEMBER_DAYS = 60;

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('rlg_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => base_path() . '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function user(): ?array
{
    static $cache = [];
    static $triedRemember = false;
    $uid = $_SESSION['uid'] ?? null;
    if (!$uid && !$triedRemember) {
        $triedRemember = true;
        $uid = restore_remembered_login();
    }
    if (!$uid) {
        return null;
    }
    if (!array_key_exists($uid, $cache)) {
        $cache[$uid] = one('SELECT * FROM users WHERE id = ? AND active = 1', [$uid]);
        if (!$cache[$uid]) {
            unset($_SESSION['uid']);
        }
    }
    return $cache[$uid];
}

function login_user(array $u, bool $remember): void
{
    session_regenerate_id(true);
    $_SESSION['uid'] = (int) $u['id'];
    update('users', ['last_login_at' => now()], 'id = ?', [$u['id']]);
    if ($remember) {
        issue_remember_token((int) $u['id']);
    }
}

function logout_user(): void
{
    $cookie = $_COOKIE[REMEMBER_COOKIE] ?? '';
    if ($cookie && str_contains($cookie, ':')) {
        [$selector] = explode(':', $cookie, 2);
        q('DELETE FROM remember_tokens WHERE selector = ?', [$selector]);
    }
    set_remember_cookie('', time() - 3600);
    $_SESSION = [];
    session_regenerate_id(true);
}

function set_remember_cookie(string $value, int $expires): void
{
    setcookie(REMEMBER_COOKIE, $value, [
        'expires'  => $expires,
        'path'     => base_path() . '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function issue_remember_token(int $userId): void
{
    $selector = bin2hex(random_bytes(12));
    $validator = bin2hex(random_bytes(32));
    $expires = time() + REMEMBER_DAYS * 86400;
    insert('remember_tokens', [
        'user_id' => $userId,
        'selector' => $selector,
        'validator_hash' => hash('sha256', $validator),
        'expires_at' => date('Y-m-d H:i:s', $expires),
        'created_at' => now(),
    ]);
    set_remember_cookie($selector . ':' . $validator, $expires);
}

function restore_remembered_login(): ?int
{
    $cookie = $_COOKIE[REMEMBER_COOKIE] ?? '';
    if (!$cookie || !str_contains($cookie, ':')) {
        return null;
    }
    [$selector, $validator] = explode(':', $cookie, 2);
    $row = one('SELECT * FROM remember_tokens WHERE selector = ? AND expires_at > NOW()', [$selector]);
    if (!$row || !hash_equals($row['validator_hash'], hash('sha256', $validator))) {
        set_remember_cookie('', time() - 3600);
        return null;
    }
    // Rotate token on use
    q('DELETE FROM remember_tokens WHERE id = ?', [$row['id']]);
    $u = one('SELECT id FROM users WHERE id = ? AND active = 1', [$row['user_id']]);
    if (!$u) {
        return null;
    }
    session_regenerate_id(true);
    $_SESSION['uid'] = (int) $u['id'];
    issue_remember_token((int) $u['id']);
    return (int) $u['id'];
}

function too_many_attempts(string $email): bool
{
    q('DELETE FROM login_attempts WHERE created_at < (NOW() - INTERVAL 1 DAY)');
    $byIp = (int) scalar('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND created_at > (NOW() - INTERVAL 15 MINUTE)', [client_ip()]);
    $byEmail = (int) scalar('SELECT COUNT(*) FROM login_attempts WHERE email = ? AND created_at > (NOW() - INTERVAL 15 MINUTE)', [$email]);
    return $byIp >= 20 || $byEmail >= 8;
}

function record_failed_login(string $email): void
{
    insert('login_attempts', ['ip' => client_ip(), 'email' => mb_substr($email, 0, 190), 'created_at' => now()]);
}

function require_login(): array
{
    $u = user();
    if (!$u) {
        if (wants_json()) {
            json_out(['error' => 'Not logged in'], 401);
        }
        $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? '/';
        redirect('/login');
    }
    return $u;
}

function require_role(string ...$roles): array
{
    $u = require_login();
    if (!in_array($u['role'], $roles, true)) {
        forbidden();
    }
    return $u;
}

function require_staff(): array
{
    return require_role(...STAFF_ROLES);
}

function is_staff(?array $u = null): bool
{
    $u = $u ?? user();
    return $u && in_array($u['role'], STAFF_ROLES, true);
}

function is_client(?array $u = null): bool
{
    $u = $u ?? user();
    return $u && $u['role'] === 'client';
}

/** Admin + bootcamp manager: prices, finance notes, approvals. Support never sees finances. */
function can_finance(?array $u = null): bool
{
    $u = $u ?? user();
    return $u && in_array($u['role'], ['admin', 'manager'], true);
}

function can_manage(?array $u = null): bool
{
    return can_finance($u);
}

function is_admin(?array $u = null): bool
{
    $u = $u ?? user();
    return $u && $u['role'] === 'admin';
}

/** Create a single-use token for invite / password reset. Returns the raw token. */
function create_password_token(int $userId, string $purpose): string
{
    $token = random_token(32);
    $hours = $purpose === 'invite' ? 24 * 7 : 2;
    q('UPDATE password_tokens SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL', [$userId]);
    insert('password_tokens', [
        'user_id' => $userId,
        'token_hash' => hash('sha256', $token),
        'purpose' => $purpose,
        'expires_at' => date('Y-m-d H:i:s', time() + $hours * 3600),
        'created_at' => now(),
    ]);
    return $token;
}

function find_password_token(string $token): ?array
{
    if ($token === '') {
        return null;
    }
    return one(
        'SELECT t.*, u.email, u.name FROM password_tokens t JOIN users u ON u.id = t.user_id
         WHERE t.token_hash = ? AND t.used_at IS NULL AND t.expires_at > NOW() AND u.active = 1',
        [hash('sha256', $token)]
    );
}

/** Send invite email; returns [sent(bool), link]. */
function send_invite(array $u): array
{
    return with_locale(user_locale($u), fn () => send_invite_mail($u));
}

function send_invite_mail(array $u): array
{
    $token = create_password_token((int) $u['id'], 'invite');
    $link = abs_url('/set-password?token=' . $token);
    $isClient = $u['role'] === 'client';
    $intro = $isClient
        ? 'Relog Media has created your team account for RLG Bootcamp. Here you can see your bootcamp details, choose drinks for your room, set up chairs, request extra equipment and contact our support team.'
        : __('You have been added to RLG Bootcamp as %s.', lbl(ROLES, $u['role']));
    $sent = send_mail(
        $u['email'],
        __('Your RLG Bootcamp account'),
        mail_template(
            __('Welcome, %s', $u['name']),
            e($intro) . '<br><br>' . e(__('Click the button below to set your password. The link is valid for 7 days.')),
            __('Set password'),
            $link
        )
    );
    return [$sent, $link];
}
