<?php
declare(strict_types=1);

// Photo & video attachments in tickets. Files live in storage/uploads/tickets/{ticket}/ (never web-accessible)
// and are served through /attachments/{id} with an access check and HTTP range support (needed for video).

const ATTACH_TYPES = [
    'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif',
    'image/heic' => 'heic', 'image/heif' => 'heif',
    'video/mp4' => 'mp4', 'video/quicktime' => 'mov', 'video/webm' => 'webm', 'video/3gpp' => '3gp', 'video/x-m4v' => 'm4v',
];
const ATTACH_MAX_IMAGE = 20 * 1024 * 1024;
const ATTACH_MAX_VIDEO = 200 * 1024 * 1024;
const ATTACH_MAX_FILES = 10;

function ini_bytes(string $v): int
{
    $v = trim($v);
    if ($v === '' || $v === '-1') {
        return PHP_INT_MAX;
    }
    $n = (int) $v;
    switch (strtolower(substr($v, -1))) {
        case 'g': $n *= 1024;
        // no break
        case 'm': $n *= 1024;
        // no break
        case 'k': $n *= 1024;
    }
    return $n;
}

/** Largest single upload the server accepts (min of PHP limits and our video limit). */
function upload_limit_bytes(): int
{
    return min(ini_bytes((string) ini_get('upload_max_filesize')), ini_bytes((string) ini_get('post_max_size')), ATTACH_MAX_VIDEO);
}

function human_bytes(int $b): string
{
    if ($b >= 1073741824) {
        return round($b / 1073741824, 1) . ' GB';
    }
    if ($b >= 1048576) {
        return round($b / 1048576) . ' MB';
    }
    return max(1, round($b / 1024)) . ' KB';
}

/** True when the request body was dropped because it exceeded post_max_size. */
function upload_too_large(): bool
{
    $len = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    return is_post() && empty($_POST) && empty($_FILES) && $len > ini_bytes((string) ini_get('post_max_size'));
}

/**
 * Validate uploaded files from $_FILES[$field]. Returns [validFiles, errors].
 * Each valid file: ['tmp' => ..., 'mime' => ..., 'ext' => ..., 'size' => ..., 'name' => ...]
 */
function collect_uploads(string $field = 'files'): array
{
    $f = $_FILES[$field] ?? null;
    if (!$f || !is_array($f['tmp_name'] ?? null)) {
        return [[], []];
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $ok = [];
    $errors = [];
    foreach ($f['tmp_name'] as $i => $tmp) {
        $name = (string) ($f['name'][$i] ?? 'file');
        $err = (int) ($f['error'][$i] ?? UPLOAD_ERR_NO_FILE);
        if ($err === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
            $errors[] = $name . ' is too large (max ' . human_bytes(upload_limit_bytes()) . ').';
            continue;
        }
        if ($err !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)) {
            $errors[] = $name . ' could not be uploaded.';
            continue;
        }
        $mime = (string) $finfo->file($tmp);
        if ($mime === 'application/octet-stream' && preg_match('/\.(mov|mp4|m4v)$/i', $name)) {
            $mime = 'video/quicktime';
        }
        if (!isset(ATTACH_TYPES[$mime])) {
            $errors[] = $name . ': only photos and videos are allowed.';
            continue;
        }
        $size = (int) $f['size'][$i];
        $limit = str_starts_with($mime, 'video/') ? ATTACH_MAX_VIDEO : ATTACH_MAX_IMAGE;
        if ($size > $limit) {
            $errors[] = $name . ' is too large (max ' . human_bytes($limit) . ').';
            continue;
        }
        if (count($ok) >= ATTACH_MAX_FILES) {
            $errors[] = 'Max ' . ATTACH_MAX_FILES . ' files per message.';
            break;
        }
        $ok[] = ['tmp' => $tmp, 'mime' => $mime, 'ext' => ATTACH_TYPES[$mime], 'size' => $size, 'name' => mb_substr($name, 0, 190)];
    }
    return [$ok, $errors];
}

function attachments_dir(int $ticketId): string
{
    $dir = APP_ROOT . '/storage/uploads/tickets/' . $ticketId;
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

/** Move validated uploads into storage and link them to a message. Returns number saved. */
function store_attachments(array $files, int $ticketId, int $messageId, int $userId): int
{
    $n = 0;
    foreach ($files as $f) {
        $name = date('Ymd-His') . '-' . bin2hex(random_bytes(6)) . '.' . $f['ext'];
        if (move_uploaded_file($f['tmp'], attachments_dir($ticketId) . '/' . $name)) {
            insert('ticket_attachments', [
                'ticket_id' => $ticketId, 'message_id' => $messageId, 'user_id' => $userId,
                'file' => $name, 'mime' => $f['mime'], 'size' => $f['size'], 'original_name' => $f['name'],
                'created_at' => now(),
            ]);
            $n++;
        }
    }
    return $n;
}

function attachments_label(array $files): string
{
    $v = count(array_filter($files, fn ($f) => str_starts_with($f['mime'], 'video/')));
    $p = count($files) - $v;
    $parts = [];
    if ($p) {
        $parts[] = $p . ' photo' . ($p > 1 ? 's' : '');
    }
    if ($v) {
        $parts[] = $v . ' video' . ($v > 1 ? 's' : '');
    }
    return $parts ? '📎 ' . implode(' + ', $parts) : '';
}

/** @return array<int, array> message_id => list of attachments */
function attachments_by_message(array $messageIds): array
{
    $messageIds = array_values(array_filter(array_map('intval', $messageIds)));
    if (!$messageIds) {
        return [];
    }
    $in = implode(',', array_fill(0, count($messageIds), '?'));
    $out = [];
    foreach (all("SELECT * FROM ticket_attachments WHERE message_id IN ($in) ORDER BY id", $messageIds) as $a) {
        $out[(int) $a['message_id']][] = $a;
    }
    return $out;
}

/** Stream a file with Range support (Safari/iOS needs it for <video>). */
function send_file_with_range(string $path, string $mime, string $downloadName): void
{
    $size = filesize($path);
    $start = 0;
    $end = $size - 1;
    header('Content-Type: ' . $mime);
    header('Accept-Ranges: bytes');
    header('Cache-Control: private, max-age=86400');
    header('Content-Disposition: inline; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $downloadName) . '"');
    if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
        if ($m[1] === '' && $m[2] !== '') {
            $start = max(0, $size - (int) $m[2]);
        } else {
            $start = (int) $m[1];
            if ($m[2] !== '') {
                $end = min((int) $m[2], $size - 1);
            }
        }
        if ($start > $end || $start >= $size) {
            http_response_code(416);
            header("Content-Range: bytes */$size");
            exit;
        }
        http_response_code(206);
        header("Content-Range: bytes $start-$end/$size");
    }
    $length = $end - $start + 1;
    header('Content-Length: ' . $length);
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    @set_time_limit(0);
    $fh = fopen($path, 'rb');
    fseek($fh, $start);
    $left = $length;
    while ($left > 0 && !feof($fh)) {
        $chunk = fread($fh, (int) min(1024 * 256, $left));
        if ($chunk === false) {
            break;
        }
        echo $chunk;
        $left -= strlen($chunk);
        flush();
    }
    fclose($fh);
    exit;
}
