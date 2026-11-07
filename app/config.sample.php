<?php
/**
 * RLG Bootcamp — configuration.
 * Copy this file to app/config.php on the server and fill in the values.
 * app/config.php is NOT in git and is never overwritten by deploys.
 */
return [
    'app_name'  => 'RLG Bootcamp',
    // Public URL, no trailing slash. Used in emails and push notifications.
    'base_url'  => 'https://guest.relog.gg',
    'timezone'  => 'Europe/Belgrade',
    'debug'     => false,

    // MySQL database created in cPanel → MySQL Databases
    'db' => [
        'host' => 'localhost',
        'name' => 'cpaneluser_bootcamp',
        'user' => 'cpaneluser_bootcamp',
        'pass' => '',
    ],

    // Outgoing email (invites, password reset). Create a mailbox in cPanel → Email Accounts.
    // Leave 'host' empty to use PHP mail() instead of SMTP.
    'mail' => [
        'host'      => 'mail.relog.gg',
        'port'      => 465,
        'secure'    => 'ssl',          // 'ssl' (465) or 'tls' (587)
        'user'      => 'no-reply@relog.gg',
        'pass'      => '',
        'from'      => 'no-reply@relog.gg',
        'from_name' => 'RLG Bootcamp',
    ],

    // Telegram backup notifications for staff (optional).
    // Create a bot with @BotFather, add it to the support group, put the group chat id here.
    'telegram' => [
        'bot_token' => '',
        'chat_id'   => '',
    ],

    // Contact address used in Web Push (VAPID) — any address you own.
    'push_contact' => 'mailto:bootcamp@relog.gg',

    // Secret for running the cron over HTTP (https://guest.relog.gg/cron?key=...)
    // when cPanel cron with PHP CLI is not an option. Leave empty to disable HTTP cron.
    'cron_key' => '',

    // Ticket reminders: first reminder after N minutes without a staff reply,
    // then every N minutes. No reminders between quiet_from and quiet_to (24h clock).
    'reminders' => [
        'after_minutes' => 60,
        'quiet_from'    => 0,
        'quiet_to'      => 7,
    ],
];
