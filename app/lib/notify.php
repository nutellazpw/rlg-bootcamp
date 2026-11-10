<?php
declare(strict_types=1);

/**
 * Notifications: in-app list + Web Push to the user's devices + Telegram backup for staff.
 * Push and Telegram are sent after the response is flushed so pages stay fast.
 */

$GLOBALS['__push_queue'] = [];
$GLOBALS['__telegram_queue'] = [];

function staff_ids(): array
{
    return array_map('intval', array_column(
        all("SELECT id FROM users WHERE active = 1 AND role IN ('admin','manager','support')"),
        'id'
    ));
}

function team_user_ids(int $teamId): array
{
    return array_map('intval', array_column(
        all("SELECT id FROM users WHERE active = 1 AND role = 'client' AND team_id = ?", [$teamId]),
        'id'
    ));
}

/** Message parts may be a plain string or a Closure that builds the text (with __()) in the current language. */
function msg_text($m): string
{
    return $m instanceof Closure ? (string) $m() : (string) $m;
}

/**
 * Store an in-app notification for each user (rendered in that user's language) and queue push.
 *
 * @param int[]                $userIds
 * @param string|Closure       $title
 * @param string|Closure       $body
 * @param string|Closure|null  $telegram  Text for the staff Telegram group, always rendered in Serbian (null = don't send)
 */
function notify(array $userIds, $title, $body = '', string $url = '/', $telegram = null, ?int $exceptUserId = null): void
{
    $userIds = array_values(array_unique(array_map('intval', $userIds)));
    if ($exceptUserId) {
        $userIds = array_values(array_filter($userIds, fn ($id) => $id !== $exceptUserId));
    }
    $langs = [];
    if ($userIds) {
        foreach (all('SELECT id, role, lang FROM users WHERE id IN (' . implode(',', $userIds) . ')') as $r) {
            $langs[(int) $r['id']] = user_locale($r);
        }
    }
    $rendered = [];
    foreach ($userIds as $uid) {
        $lang = $langs[$uid] ?? 'en';
        $rendered[$lang] ??= with_locale($lang, fn () => [msg_text($title), msg_text($body)]);
        [$t, $b] = $rendered[$lang];
        insert('notifications', [
            'user_id' => $uid,
            'title' => mb_substr($t, 0, 190),
            'body' => mb_substr($b, 0, 500),
            'url' => mb_substr($url, 0, 255),
            'created_at' => now(),
        ]);
        $GLOBALS['__push_queue'][$uid] = true;
    }
    if ($telegram !== null) {
        $GLOBALS['__telegram_queue'][] = with_locale('sr', fn () => msg_text($telegram)) . "\n" . abs_url($url);
    }
    static $registered = false;
    if (!$registered) {
        $registered = true;
        register_shutdown_function('flush_notification_queue');
    }
}

/** Notify all staff (+ Telegram). Title/body may be Closures so each person gets their language. */
function notify_staff($title, $body, string $url, bool $telegram = true, ?int $exceptUserId = null): void
{
    $tg = $telegram ? function () use ($title, $body) {
        $b = msg_text($body);
        return '🔔 ' . msg_text($title) . ($b !== '' ? "\n" . $b : '');
    } : null;
    notify(staff_ids(), $title, $body, $url, $tg, $exceptUserId);
}

function flush_notification_queue(): void
{
    $push = array_keys($GLOBALS['__push_queue']);
    $tg = $GLOBALS['__telegram_queue'];
    $GLOBALS['__push_queue'] = [];
    $GLOBALS['__telegram_queue'] = [];
    if (!$push && !$tg) {
        return;
    }
    if (PHP_SAPI !== 'cli') {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } elseif (function_exists('litespeed_finish_request')) {
            litespeed_finish_request();
        }
    }
    ignore_user_abort(true);
    try {
        if ($push) {
            push_to_users($push);
        }
        foreach ($tg as $text) {
            telegram_send($text);
        }
    } catch (Throwable $ex) {
        log_line('notify.log', 'ERR ' . $ex->getMessage());
    }
}

function telegram_send(string $text): bool
{
    $token = (string) config('telegram.bot_token', '');
    $chat = (string) config('telegram.chat_id', '');
    if ($token === '' || $chat === '' || !function_exists('curl_init')) {
        return false;
    }
    $ch = curl_init('https://api.telegram.org/bot' . $token . '/sendMessage');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'chat_id' => $chat,
            'text' => mb_substr($text, 0, 4000),
            'disable_web_page_preview' => 'true',
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
    ]);
    $res = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($status !== 200) {
        log_line('telegram.log', "HTTP $status " . substr((string) $res, 0, 300));
        return false;
    }
    return true;
}

function unread_notifications_count(int $userId): int
{
    return (int) scalar('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL', [$userId]);
}
