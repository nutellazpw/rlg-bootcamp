<?php
declare(strict_types=1);

function push_body(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function push_key(): void
{
    require_login();
    try {
        json_out(['key' => vapid_keys()['public']]);
    } catch (Throwable $ex) {
        json_out(['error' => 'Push is not available on this server.'], 500);
    }
}

function push_subscribe(): void
{
    $u = require_login();
    $d = push_body();
    $endpoint = (string) ($d['endpoint'] ?? '');
    if (!preg_match('#^https://#', $endpoint) || strlen($endpoint) > 2000) {
        json_out(['error' => 'Invalid subscription'], 422);
    }
    $hash = hash('sha256', $endpoint);
    q('DELETE FROM push_subscriptions WHERE endpoint_hash = ?', [$hash]);
    insert('push_subscriptions', [
        'user_id' => $u['id'],
        'endpoint_hash' => $hash,
        'endpoint' => $endpoint,
        'p256dh' => mb_substr((string) ($d['keys']['p256dh'] ?? ''), 0, 255) ?: null,
        'auth' => mb_substr((string) ($d['keys']['auth'] ?? ''), 0, 255) ?: null,
        'user_agent' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        'seen_upto' => (int) scalar('SELECT COALESCE(MAX(id),0) FROM notifications WHERE user_id = ?', [$u['id']]),
        'created_at' => now(),
    ]);
    json_out(['ok' => true]);
}

function push_unsubscribe(): void
{
    $u = require_login();
    $d = push_body();
    if (!empty($d['id'])) {
        q('DELETE FROM push_subscriptions WHERE id = ? AND user_id = ?', [(int) $d['id'], $u['id']]);
    } else {
        q('DELETE FROM push_subscriptions WHERE endpoint_hash = ? AND user_id = ?', [hash('sha256', (string) ($d['endpoint'] ?? '')), $u['id']]);
    }
    json_out(['ok' => true]);
}

/**
 * Called by the service worker when a push arrives (no cookies needed).
 * Identifies the device by its subscription endpoint and returns notifications not yet shown.
 */
function push_pending(): void
{
    $d = push_body();
    $endpoint = (string) ($d['endpoint'] ?? '');
    $sub = $endpoint ? one('SELECT * FROM push_subscriptions WHERE endpoint_hash = ?', [hash('sha256', $endpoint)]) : null;
    if (!$sub) {
        json_out(['items' => []]);
    }
    $user = one('SELECT id FROM users WHERE id = ? AND active = 1', [$sub['user_id']]);
    if (!$user) {
        json_out(['items' => []]);
    }
    // Per-device marker of what this device has already shown
    $seen = (int) $sub['seen_upto'];
    $rows = all(
        'SELECT id, title, body, url FROM notifications WHERE user_id = ? AND id > ? AND read_at IS NULL AND created_at > (NOW() - INTERVAL 1 DAY) ORDER BY id DESC LIMIT 4',
        [$user['id'], $seen]
    );
    $max = (int) scalar('SELECT COALESCE(MAX(id),0) FROM notifications WHERE user_id = ?', [$user['id']]);
    q('UPDATE push_subscriptions SET seen_upto = ? WHERE id = ?', [max($seen, $max), $sub['id']]);
    $items = array_map(fn ($n) => [
        'id' => (int) $n['id'],
        'title' => $n['title'],
        'body' => (string) $n['body'],
        'url' => url('/n/' . $n['id']),
    ], array_reverse($rows));
    json_out([
        'items' => $items,
        'unread' => unread_notifications_count((int) $user['id']),
    ]);
}

function push_test(): void
{
    $u = require_login();
    notify([(int) $u['id']], 'Test notification', 'Notifications work on this device. 👍', '/profile');
    json_out(['ok' => true]);
}
