<?php
declare(strict_types=1);

/*
 * Simple translations. Code and views are written in English; staff see Serbian (Latin) by default,
 * teams always see English. Strings are looked up in app/lang/sr.php by their English text.
 *
 *   __('Delete team')                    → "Obriši tim"
 *   __('Team %s deleted.', $name)        → sprintf on the translated template
 *   __n(3, '%d ticket', '%d tickets')    → Serbian dictionary entry is [one, few (2–4), many]
 */

const LANGUAGES = ['sr' => 'Srpski', 'en' => 'English'];

/** Language of the current request (the signed-in user), unless overridden with with_locale(). */
function locale(): string
{
    if (!empty($GLOBALS['__locale_stack'])) {
        return end($GLOBALS['__locale_stack']);
    }
    $u = function_exists('user') && session_status() === PHP_SESSION_ACTIVE ? user() : null;
    return user_locale($u);
}

function user_locale(?array $u): string
{
    if (!$u || ($u['role'] ?? '') === 'client') {
        return 'en';
    }
    $l = (string) ($u['lang'] ?? '');
    if (isset(LANGUAGES[$l])) {
        return $l;
    }
    $default = (string) config('staff_language', 'sr');
    return isset(LANGUAGES[$default]) ? $default : 'sr';
}

/** Run $fn with a forced language (e.g. rendering a notification for a specific recipient). */
function with_locale(string $lang, callable $fn)
{
    $GLOBALS['__locale_stack'][] = $lang;
    try {
        return $fn();
    } finally {
        array_pop($GLOBALS['__locale_stack']);
    }
}

function lang_dict(string $lang): array
{
    static $cache = [];
    if (!isset($cache[$lang])) {
        $f = APP_DIR . '/lang/' . $lang . '.php';
        $cache[$lang] = $lang !== 'en' && is_file($f) ? (require $f) : [];
    }
    return $cache[$lang];
}

function __(string $s, ...$args): string
{
    $l = locale();
    $t = $l === 'en' ? $s : (lang_dict($l)[$s] ?? $s);
    if (is_array($t)) {
        $t = $t[0];
    }
    return $args ? vsprintf($t, $args) : $t;
}

/** Plural: English one/many; Serbian dictionary entry under $one is [one, few, many]. */
function __n(int $n, string $one, string $many, ...$args): string
{
    $l = locale();
    $entry = $l === 'en' ? null : (lang_dict($l)[$one] ?? null);
    if (is_array($entry)) {
        $m10 = $n % 10;
        $m100 = $n % 100;
        $t = ($m10 === 1 && $m100 !== 11) ? $entry[0] : (($m10 >= 2 && $m10 <= 4 && ($m100 < 12 || $m100 > 14)) ? $entry[1] : $entry[2]);
    } else {
        $t = is_string($entry) ? $entry : ($n === 1 ? $one : $many);
    }
    return vsprintf($t, array_merge([$n], $args));
}

/** Translate a label from one of the status/category constants. */
function lbl(array $labels, ?string $key): string
{
    return __($labels[$key] ?? (string) $key);
}

/** date() with month/day names in the current language. */
function ldate(string $format, int $ts): string
{
    $out = date($format, $ts);
    if (locale() !== 'sr') {
        return $out;
    }
    return strtr($out, [
        'January' => 'januar', 'February' => 'februar', 'March' => 'mart', 'April' => 'april', 'May' => 'maj', 'June' => 'jun',
        'July' => 'jul', 'August' => 'avgust', 'September' => 'septembar', 'October' => 'oktobar', 'November' => 'novembar', 'December' => 'decembar',
        'Jan' => 'jan', 'Feb' => 'feb', 'Mar' => 'mar', 'Apr' => 'apr', 'Jun' => 'jun', 'Jul' => 'jul', 'Aug' => 'avg',
        'Sep' => 'sep', 'Oct' => 'okt', 'Nov' => 'nov', 'Dec' => 'dec',
        'Monday' => 'ponedeljak', 'Tuesday' => 'utorak', 'Wednesday' => 'sreda', 'Thursday' => 'četvrtak', 'Friday' => 'petak', 'Saturday' => 'subota', 'Sunday' => 'nedelja',
        'Mon' => 'pon', 'Tue' => 'uto', 'Wed' => 'sre', 'Thu' => 'čet', 'Fri' => 'pet', 'Sat' => 'sub', 'Sun' => 'ned',
    ]);
}

/** Strings app.js needs, in the current language. */
function js_strings(): array
{
    $keys = require APP_DIR . '/lang/js_keys.php';
    $out = [];
    foreach ($keys as $k) {
        $out[$k] = __($k);
    }
    return $out;
}

if (!function_exists('mb_ucfirst')) { // built in since PHP 8.4
    function mb_ucfirst(string $s): string
    {
        return mb_strtoupper(mb_substr($s, 0, 1)) . mb_substr($s, 1);
    }
}

/** "2 h", "35 min" since a timestamp (for "Waiting …" badges). */
function waiting_for(string $since): string
{
    $m = max(0, (int) floor((time() - strtotime($since)) / 60));
    if ($m < 60) {
        return $m . ' min';
    }
    if ($m < 1440) {
        return floor($m / 60) . ' h';
    }
    return __('%d d', (int) floor($m / 1440));
}
