<?php
declare(strict_types=1);

function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function base_path(): string
{
    static $base = null;
    if ($base === null) {
        $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
        $base = rtrim($dir, '/');
    }
    return $base;
}

function url(string $path = '/', array $query = []): string
{
    $u = base_path() . '/' . ltrim($path, '/');
    if ($query) {
        $u .= '?' . http_build_query($query);
    }
    return $u;
}

function abs_url(string $path = '/'): string
{
    return rtrim((string) config('base_url', ''), '/') . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $file = APP_ROOT . '/assets/' . ltrim($path, '/');
    $v = is_file($file) ? filemtime($file) : APP_VERSION;
    return url('assets/' . ltrim($path, '/')) . '?v=' . $v;
}

function redirect(string $path, array $query = []): void
{
    header('Location: ' . (preg_match('#^https?://#', $path) ? $path : url($path, $query)));
    exit;
}

function back(string $fallback = '/'): void
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($ref && parse_url($ref, PHP_URL_HOST) === $host) {
        header('Location: ' . $ref);
        exit;
    }
    redirect($fallback);
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function input(string $key, $default = '')
{
    $v = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $v;
}

function input_int(string $key, int $default = 0): int
{
    $v = input($key, '');
    return is_numeric($v) ? (int) $v : $default;
}

function input_date(string $key): ?string
{
    $v = (string) input($key, '');
    $d = DateTime::createFromFormat('Y-m-d', $v);
    return ($d && $d->format('Y-m-d') === $v) ? $v : null;
}

function input_money(string $key): ?string
{
    $v = str_replace([' ', ','], ['', '.'], (string) input($key, ''));
    if ($v === '') {
        return null;
    }
    return is_numeric($v) ? number_format((float) $v, 2, '.', '') : null;
}

/** Flash message. Plain text is escaped; pass $html = true only for markup you built yourself. */
function flash(string $type, string $message, bool $html = false): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $html ? $message : e($message)];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        if (wants_json()) {
            json_out(['error' => __('Session expired. Reload the page.')], 419);
        }
        flash('error', __('Your session expired. Please try again.'));
        back();
    }
}

function wants_json(): bool
{
    return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
        || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
}

function json_out($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Render a view inside the layout. */
function view(string $template, array $data = [], string $layout = 'layout'): void
{
    $data['__template'] = $template;
    extract($data, EXTR_SKIP);
    ob_start();
    require APP_DIR . '/views/' . $template . '.php';
    $content = ob_get_clean();
    if ($layout === '') {
        echo $content;
        return;
    }
    require APP_DIR . '/views/' . $layout . '.php';
}

function partial(string $template, array $data = []): void
{
    extract($data, EXTR_SKIP);
    require APP_DIR . '/views/' . $template . '.php';
}

function not_found(): void
{
    http_response_code(404);
    view('errors/404', ['title' => __('Not found')], user() ? 'layout' : 'layout_auth');
    exit;
}

function forbidden(): void
{
    http_response_code(403);
    view('errors/403', ['title' => __('No access')], user() ? 'layout' : 'layout_auth');
    exit;
}

// ---------- formatting ----------

function fmt_date(?string $d, string $format = 'd M Y'): string
{
    if (!$d) {
        return '—';
    }
    return ldate($format, strtotime($d));
}

function fmt_dt(?string $d): string
{
    return $d ? ldate('d M Y, H:i', strtotime($d)) : '—';
}

function fmt_range(string $from, string $to): string
{
    $a = strtotime($from);
    $b = strtotime($to);
    if (date('Y', $a) === date('Y', $b)) {
        if (date('m', $a) === date('m', $b)) {
            return date('d', $a) . '–' . ldate('d M Y', $b);
        }
        return ldate('d M', $a) . ' – ' . ldate('d M Y', $b);
    }
    return ldate('d M Y', $a) . ' – ' . ldate('d M Y', $b);
}

function days_between(string $from, string $to): int
{
    return (int) ((strtotime($to) - strtotime($from)) / 86400) + 1;
}

function money(?string $amount, string $currency = 'EUR'): string
{
    if ($amount === null || $amount === '') {
        return '—';
    }
    return number_format((float) $amount, 2, ',', '.') . ' ' . $currency;
}

function time_ago(?string $d): string
{
    if (!$d) {
        return '';
    }
    $s = time() - strtotime($d);
    if ($s < 60) {
        return __('just now');
    }
    if ($s < 3600) {
        return __('%d min ago', (int) floor($s / 60));
    }
    if ($s < 86400) {
        return __('%d h ago', (int) floor($s / 3600));
    }
    if ($s < 86400 * 7) {
        return __('%d d ago', (int) floor($s / 86400));
    }
    return ldate('d M Y', strtotime($d));
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $i = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $i .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $i ?: '?';
}

function nl2br_e(?string $text): string
{
    return nl2br(e($text));
}

function selected($a, $b): string
{
    return (string) $a === (string) $b ? ' selected' : '';
}

function checked(bool $cond): string
{
    return $cond ? ' checked' : '';
}

function client_ip(): string
{
    return $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function random_token(int $bytes = 32): string
{
    return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
}

function log_line(string $file, string $message): void
{
    @file_put_contents(APP_ROOT . '/storage/logs/' . $file, '[' . now() . '] ' . $message . "\n", FILE_APPEND);
}

/** Small line icons for section titles. */
function icon(string $name, string $class = 'ico'): string
{
    $p = [
        'info'      => '<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7.5v.5"/>',
        'setup'     => '<rect x="3" y="4" width="18" height="12" rx="1.5"/><path d="M8 20h8M12 16v4"/>',
        'drinks'    => '<path d="M8 3h8M9 3v3l-2 3v11a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V9l-2-3V3"/><path d="M7 13h10"/>',
        'chair'     => '<path d="M7 3h10v8H7z"/><path d="M5 11h14v3H5zM7 14v7M17 14v7M4 9v5M20 9v5"/>',
        'extras'    => '<rect x="3" y="7" width="18" height="13" rx="1.5"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M12 11v6M9 14h6"/>',
        'activity'  => '<path d="M12 3l2.6 5.4 5.9.8-4.3 4.1 1 5.9L12 16.4 6.8 19.2l1-5.9L3.5 9.2l5.9-.8z"/>',
        'support'   => '<path d="M4 5h16v11H9l-5 4z"/><path d="M8 9h8M8 12h5"/>',
        'feedback'  => '<path d="M12 21s-7-4.4-9-9a5 5 0 0 1 9-3 5 5 0 0 1 9 3c-2 4.6-9 9-9 9z"/>',
        'calendar'  => '<rect x="3" y="5" width="18" height="16" rx="1.5"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        'checklist' => '<path d="M9 6h11M9 12h11M9 18h11"/><path d="M3.5 6l1.5 1.5L7.5 5M3.5 12l1.5 1.5L7.5 11M3.5 18l1.5 1.5L7.5 17"/>',
        'truck'     => '<path d="M3 6h11v10H3zM14 10h4l3 3v3h-7z"/><circle cx="7" cy="18" r="2"/><circle cx="17" cy="18" r="2"/>',
        'history'   => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5M12 7v5l3 2"/>',
        'team'      => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><circle cx="17" cy="9" r="2.5"/><path d="M16 14.2A5 5 0 0 1 21.5 19"/>',
        'room'      => '<path d="M3 21h18M5 21V4h10v17M15 9h4v12"/><circle cx="12" cy="13" r=".8"/>',
    ][$name] ?? '';
    return '<svg class="' . e($class) . '" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
}
