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

/**
 * @param int[]  $userIds
 * @param string|null $telegram  Text for the staff Telegram group (null = don't send)
 */
function notify(array $userIds, string $title, string $body = '', string $url = '/', ?string $telegram = null, ?int $exceptUserId = null): void
{
    $userIds = array_values(array_unique(array_map('intval', $userIds)));
    if ($exceptUserId) {
        $userIds = array_values(array_filter($userIds, fn ($id) => $id !== $exceptUserId));
    }
    foreach ($userIds as $uid) {
        insert('notifications', [
            'user_id' => $uid,
            'title' => mb_substr($title, 0, 190),
            'body' => mb_substr($body, 0, 500),
            'url' => mb_substr($url, 0, 255),
            'created_at' => now(),
        ]);
        $GLOBALS['__push_queue'][$uid] = true;
    }
    if ($telegram !== null) {
        $GLOBALS['__telegram_queue'][] = $telegram . "\n" . abs_url($url);
    }
    static $registered = false;
    if (!$registered) {
        $registered = true;
        register_shutdown_function('flush_notification_queue');
    }
}

function notify_staff(string $title, string $body, string $url, bool $telegram = true, ?int $exceptUserId = null): void
{
    notify(staff_ids(), $title, $body, $url, $telegram ? ('🔔 ' . $title . ($body ? "\n" . $body : '')) : null, $exceptUserId);
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
