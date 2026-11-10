<?php
declare(strict_types=1);

function auth_home(): void
{
    if ((int) scalar('SELECT COUNT(*) FROM users') === 0) {
        redirect('/setup');
    }
    $u = user();
    if (!$u) {
        redirect('/login');
    }
    redirect(is_staff($u) ? '/dashboard' : '/my');
}

function auth_login(): void
{
    if (user()) {
        redirect('/');
    }
    $email = '';
    $error = null;
    if (is_post()) {
        $email = mb_strtolower((string) input('email'));
        $password = (string) ($_POST['password'] ?? '');
        if (too_many_attempts($email)) {
            $error = 'Too many attempts. Wait 15 minutes and try again.';
        } else {
            $u = one('SELECT * FROM users WHERE email = ? AND active = 1', [$email]);
            if ($u && $u['password_hash'] && password_verify($password, $u['password_hash'])) {
                if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
                    update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = ?', [$u['id']]);
                }
                login_user($u, !empty($_POST['remember']));
                $to = $_SESSION['after_login'] ?? '/';
                unset($_SESSION['after_login']);
                if (!is_string($to) || !str_starts_with($to, '/') || str_starts_with($to, '//')) {
                    $to = '/';
                }
                header('Location: ' . $to);
                exit;
            }
            record_failed_login($email);
            $error = 'Wrong email or password.';
        }
    }
    view('auth/login', ['title' => 'Sign in', 'email' => $email, 'error' => $error], 'layout_auth');
}

function auth_logout(): void
{
    logout_user();
    redirect('/login');
}

function auth_forgot(): void
{
    $sent = false;
    if (is_post()) {
        $email = mb_strtolower((string) input('email'));
        if (!too_many_attempts($email)) {
            record_failed_login($email); // counts toward rate limit
            $u = one('SELECT * FROM users WHERE email = ? AND active = 1', [$email]);
            if ($u) {
                $token = create_password_token((int) $u['id'], 'reset');
                $link = abs_url('/set-password?token=' . $token);
                with_locale(user_locale($u), fn () => send_mail($u['email'], __('Reset your RLG Bootcamp password'), mail_template(
                    __('Password reset'),
                    e(__('Someone asked to reset the password for your RLG Bootcamp account. If it was you, click the button below. The link is valid for 2 hours.')) . '<br><br>' . e(__('If you did not ask for this, ignore this email.')),
                    __('Reset password'),
                    $link
                )));
            }
        }
        $sent = true;
    }
    view('auth/forgot', ['title' => 'Forgot password', 'sent' => $sent], 'layout_auth');
}

function auth_set_password(): void
{
    $token = (string) input('token');
    $row = find_password_token($token);
    $error = null;
    if (!$row) {
        view('auth/set_password', ['title' => 'Set password', 'invalid' => true, 'token' => '', 'row' => null, 'error' => null], 'layout_auth');
        return;
    }
    if (is_post()) {
        $p1 = (string) ($_POST['password'] ?? '');
        $p2 = (string) ($_POST['password2'] ?? '');
        if (mb_strlen($p1) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($p1 !== $p2) {
            $error = 'Passwords do not match.';
        } else {
            update('users', ['password_hash' => password_hash($p1, PASSWORD_DEFAULT)], 'id = ?', [$row['user_id']]);
            update('password_tokens', ['used_at' => now()], 'id = ?', [$row['id']]);
            q('DELETE FROM remember_tokens WHERE user_id = ?', [$row['user_id']]);
            $u = one('SELECT * FROM users WHERE id = ?', [$row['user_id']]);
            login_user($u, true);
            flash('success', 'Password saved. Welcome to RLG Bootcamp!');
            redirect('/');
        }
    }
    view('auth/set_password', ['title' => 'Set password', 'invalid' => false, 'token' => $token, 'row' => $row, 'error' => $error], 'layout_auth');
}

/** First run: create the administrator account. Disabled once any user exists. */
function auth_setup(): void
{
    if ((int) scalar('SELECT COUNT(*) FROM users') > 0) {
        redirect('/login');
    }
    $error = null;
    $name = (string) input('name');
    $email = mb_strtolower((string) input('email'));
    if (is_post()) {
        $p = (string) ($_POST['password'] ?? '');
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter your name and a valid email.';
        } elseif (mb_strlen($p) < 8) {
            $error = 'Password must be at least 8 characters.';
        } else {
            $id = insert('users', [
                'role' => 'admin', 'name' => $name, 'email' => $email,
                'password_hash' => password_hash($p, PASSWORD_DEFAULT), 'active' => 1, 'created_at' => now(),
            ]);
            seed_defaults();
            vapid_keys();
            login_user(one('SELECT * FROM users WHERE id = ?', [$id]), true);
            flash('success', 'Administrator account created. Start by setting inventory quantities, then add a team.');
            redirect('/dashboard');
        }
    }
    view('auth/setup', ['title' => 'Setup', 'error' => $error, 'name' => $name, 'email' => $email], 'layout_auth');
}
