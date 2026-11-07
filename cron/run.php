<?php
/**
 * cPanel → Cron Jobs → every minute:
 *   /usr/local/bin/php /home/CPANELUSER/guest.relog.gg/cron/run.php >/dev/null 2>&1
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
require dirname(__DIR__) . '/app/bootstrap.php';
require APP_DIR . '/cron_tasks.php';

$lock = fopen(APP_ROOT . '/storage/cron.lock', 'c');
if ($lock && !flock($lock, LOCK_EX | LOCK_NB)) {
    exit(0); // previous run still going
}
echo run_cron_tasks();
