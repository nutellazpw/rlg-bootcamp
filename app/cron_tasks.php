<?php
declare(strict_types=1);

/** Everything that runs every minute from cron. Safe to run more or less often. */
function run_cron_tasks(): string
{
    if (db_version() < latest_schema_version()) {
        migrate();
    }
    $out = [];
    auto_advance_bootcamps();
    $out[] = 'bootcamp statuses updated';

    $n = send_ticket_reminders();
    $out[] = "ticket reminders sent: $n";

    $n = send_setup_deadline_reminders();
    $out[] = "room setup reminders sent: $n";

    $n = send_bootcamp_reminders();
    $out[] = "bootcamp reminders sent: $n";

    // Housekeeping once per hour
    if ((int) date('i') === 0 || setting('last_cleanup', '') < date('Y-m-d H', strtotime('-1 hour'))) {
        q('DELETE FROM notifications WHERE created_at < (NOW() - INTERVAL 120 DAY)');
        q('DELETE FROM password_tokens WHERE expires_at < (NOW() - INTERVAL 30 DAY)');
        q('DELETE FROM remember_tokens WHERE expires_at < NOW()');
        q('DELETE FROM login_attempts WHERE created_at < (NOW() - INTERVAL 1 DAY)');
        set_setting('last_cleanup', date('Y-m-d H'));
        $out[] = 'cleanup done';
    }
    set_setting('last_cron_run', now());
    flush_notification_queue();
    return implode("\n", $out) . "\n";
}
