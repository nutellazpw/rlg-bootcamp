<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

// Path relative to the app root
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$base = base_path();
if ($base !== '' && str_starts_with($path, $base)) {
    $path = substr($path, strlen($base)) ?: '/';
}
$path = '/' . trim($path, '/');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

header('X-Content-Type-Options: nosniff');

// Cron over HTTP (optional) — does not need a session
if ($path === '/run-cron' || $path === '/cron') {
    $key = trim((string) config('cron_key', ''));
    header('Content-Type: text/plain; charset=utf-8');
    if ($key === '') {
        http_response_code(403);
        exit("RLG cron: cron_key is empty in app/config.php\n");
    }
    if (!hash_equals($key, trim((string) ($_GET['key'] ?? '')))) {
        http_response_code(403);
        exit("RLG cron: wrong key\n");
    }
    require APP_DIR . '/cron_tasks.php';
    header('Content-Type: text/plain');
    echo run_cron_tasks();
    exit;
}

// First run / pending migrations
try {
    $dbVersion = db_version();
} catch (PDOException $ex) {
    http_response_code(500);
    error_log('DB connection failed: ' . $ex->getMessage());
    exit('<h1>RLG Bootcamp</h1><p>Database connection failed. Check the <code>db</code> settings in <code>app/config.php</code>.</p>');
}
if ($dbVersion < latest_schema_version()) {
    migrate();
}

start_session();

$routes = require APP_DIR . '/routes.php';

foreach ($routes as [$m, $pattern, $handler]) {
    if (!in_array($method, (array) $m, true)) {
        continue;
    }
    if (preg_match('#^' . $pattern . '$#', $path, $matches)) {
        array_shift($matches);
        [$file, $fn] = explode('@', $handler);
        require_once APP_DIR . '/controllers/' . $file . '.php';
        if (upload_too_large()) {
            $msg = 'The file is too large. Max ' . human_bytes(upload_limit_bytes()) . ' per upload.';
            if (wants_json()) {
                json_out(['error' => $msg], 413);
            }
            flash('error', $msg);
            back();
        }
        if ($method === 'POST' && !in_array($handler, ['push@pending'], true)) {
            csrf_check();
        }
        $fn = $file . '_' . $fn;
        $fn(...array_map(fn ($v) => ctype_digit($v) ? (int) $v : $v, $matches));
        exit;
    }
}

not_found();
