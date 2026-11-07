<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('APP_DIR', __DIR__);
define('APP_VERSION', '1.0.0');

$configFile = APP_DIR . '/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    echo '<h1>RLG Bootcamp</h1><p>Missing <code>app/config.php</code>. Copy <code>app/config.sample.php</code> to <code>app/config.php</code> and fill in the database settings.</p>';
    exit;
}
$GLOBALS['config'] = require $configFile;

function config(string $key, $default = null)
{
    $value = $GLOBALS['config'];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

date_default_timezone_set((string) config('timezone', 'Europe/Belgrade'));
mb_internal_encoding('UTF-8');

if (config('debug')) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}
ini_set('log_errors', '1');
@mkdir(APP_ROOT . '/storage/logs', 0755, true);
ini_set('error_log', APP_ROOT . '/storage/logs/php-error.log');

require APP_DIR . '/lib/db.php';
require APP_DIR . '/lib/helpers.php';
require APP_DIR . '/lib/schema.php';
require APP_DIR . '/lib/auth.php';
require APP_DIR . '/lib/mailer.php';
require APP_DIR . '/lib/webpush.php';
require APP_DIR . '/lib/notify.php';
require APP_DIR . '/lib/domain.php';
