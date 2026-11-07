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
            json_out(['error' => 'Session expired. Reload the page.'], 419);
        }
        flash('error', 'Your session expired. Please try again.');
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
    view('errors/404', ['title' => 'Not found'], user() ? 'layout' : 'layout_auth');
    exit;
}

function forbidden(): void
{
    http_response_code(403);
    view('errors/403', ['title' => 'No access'], user() ? 'layout' : 'layout_auth');
    exit;
}

// ---------- formatting ----------

function fmt_date(?string $d, string $format = 'd M Y'): string
{
    if (!$d) {
        return '—';
    }
    return date($format, strtotime($d));
}

function fmt_dt(?string $d): string
{
    return $d ? date('d M Y, H:i', strtotime($d)) : '—';
}

function fmt_range(string $from, string $to): string
{
    $a = strtotime($from);
    $b = strtotime($to);
    if (date('Y', $a) === date('Y', $b)) {
        if (date('m', $a) === date('m', $b)) {
            return date('d', $a) . '–' . date('d M Y', $b);
        }
        return date('d M', $a) . ' – ' . date('d M Y', $b);
    }
    return date('d M Y', $a) . ' – ' . date('d M Y', $b);
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
        return 'just now';
    }
    if ($s < 3600) {
        return floor($s / 60) . ' min ago';
    }
    if ($s < 86400) {
        return floor($s / 3600) . ' h ago';
    }
    if ($s < 86400 * 7) {
        return floor($s / 86400) . ' d ago';
    }
    return date('d M Y', strtotime($d));
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
