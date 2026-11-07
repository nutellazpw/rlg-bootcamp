<?php
declare(strict_types=1);

/**
 * Minimal Web Push (VAPID) without payload.
 * The push only wakes the service worker; the service worker then fetches
 * the pending notifications from /api/push/pending using its subscription endpoint.
 * This avoids payload encryption and needs only the openssl extension.
 */

function b64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function b64url_decode(string $data): string
{
    return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4)) ?: '';
}

/** Returns ['public' => base64url uncompressed point, 'private_pem' => PEM]. Generated once. */
function vapid_keys(): array
{
    $pub = setting('vapid_public');
    $pem = setting('vapid_private_pem');
    if ($pub && $pem) {
        return ['public' => $pub, 'private_pem' => $pem];
    }
    $opts = ['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC];
    $key = @openssl_pkey_new($opts);
    if ($key === false) {
        // Some shared hosts need an explicit openssl.cnf
        foreach (['/etc/ssl/openssl.cnf', '/usr/lib/ssl/openssl.cnf', '/etc/pki/tls/openssl.cnf'] as $cnf) {
            if (is_file($cnf)) {
                $key = @openssl_pkey_new($opts + ['config' => $cnf]);
                if ($key !== false) {
                    break;
                }
            }
        }
    }
    if ($key === false) {
        throw new RuntimeException('Could not generate VAPID keys (openssl EC support missing).');
    }
    openssl_pkey_export($key, $pem);
    $d = openssl_pkey_get_details($key);
    $x = str_pad($d['ec']['x'], 32, "\0", STR_PAD_LEFT);
    $y = str_pad($d['ec']['y'], 32, "\0", STR_PAD_LEFT);
    $pub = b64url_encode("\x04" . $x . $y);
    set_setting('vapid_public', $pub);
    set_setting('vapid_private_pem', $pem);
    return ['public' => $pub, 'private_pem' => $pem];
}

/** Convert an ASN.1 DER ECDSA signature to the raw 64-byte R||S form used by JWT ES256. */
function der_to_raw_signature(string $der): string
{
    $pos = 2; // skip SEQUENCE tag + length (length < 128 for P-256)
    if (ord($der[1]) & 0x80) {
        $pos += ord($der[1]) & 0x7f;
    }
    $out = '';
    for ($i = 0; $i < 2; $i++) {
        $pos++; // INTEGER tag
        $len = ord($der[$pos]);
        $pos++;
        $int = substr($der, $pos, $len);
        $pos += $len;
        $int = ltrim($int, "\0");
        $out .= str_pad($int, 32, "\0", STR_PAD_LEFT);
    }
    return $out;
}

function vapid_jwt(string $audience): string
{
    $keys = vapid_keys();
    $header = b64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
    $claims = b64url_encode(json_encode([
        'aud' => $audience,
        'exp' => time() + 12 * 3600,
        'sub' => (string) config('push_contact', 'mailto:bootcamp@relog.gg'),
    ]));
    $input = $header . '.' . $claims;
    $pkey = openssl_pkey_get_private($keys['private_pem']);
    if (!openssl_sign($input, $der, $pkey, OPENSSL_ALGO_SHA256)) {
        throw new RuntimeException('VAPID signing failed');
    }
    return $input . '.' . b64url_encode(der_to_raw_signature($der));
}

/**
 * Send an empty push to one subscription. Returns HTTP status (0 = network error).
 * 404/410 mean the subscription is gone and should be deleted.
 */
function push_send(array $sub): int
{
    $endpoint = $sub['endpoint'];
    $parts = parse_url($endpoint);
    if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
        return 400;
    }
    $audience = $parts['scheme'] . '://' . $parts['host'];
    $keys = vapid_keys();
    $jwt = vapid_jwt($audience);
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => '',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_HTTPHEADER     => [
            'Authorization: vapid t=' . $jwt . ', k=' . $keys['public'],
            'TTL: 86400',
            'Urgency: high',
            'Content-Length: 0',
        ],
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($status >= 400 || $status === 0) {
        log_line('push.log', "HTTP $status " . substr($endpoint, 0, 60) . '… ' . substr((string) $body, 0, 200));
    }
    return $status;
}

/** Push to every device of the given users. */
function push_to_users(array $userIds): void
{
    $userIds = array_values(array_unique(array_map('intval', $userIds)));
    if (!$userIds || !function_exists('curl_init')) {
        return;
    }
    $in = implode(',', array_fill(0, count($userIds), '?'));
    $subs = all("SELECT * FROM push_subscriptions WHERE user_id IN ($in)", $userIds);
    foreach ($subs as $sub) {
        try {
            $status = push_send($sub);
        } catch (Throwable $ex) {
            log_line('push.log', 'ERR ' . $ex->getMessage());
            return;
        }
        if ($status === 404 || $status === 410) {
            q('DELETE FROM push_subscriptions WHERE id = ?', [$sub['id']]);
        } elseif ($status >= 200 && $status < 300) {
            q('UPDATE push_subscriptions SET last_ok_at = NOW() WHERE id = ?', [$sub['id']]);
        }
    }
}
