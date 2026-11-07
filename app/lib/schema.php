<?php
declare(strict_types=1);

/**
 * Database migrations. Each entry runs once, in order.
 * To change the schema later: append a new entry, never edit old ones.
 */
function migrations(): array
{
    $t = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    return [
        1 => [
            "CREATE TABLE IF NOT EXISTS users (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                role VARCHAR(16) NOT NULL,
                name VARCHAR(120) NOT NULL,
                email VARCHAR(190) NOT NULL,
                phone VARCHAR(40) NULL,
                password_hash VARCHAR(255) NULL,
                team_id INT UNSIGNED NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL,
                last_login_at DATETIME NULL,
                UNIQUE KEY uq_email (email),
                KEY idx_team (team_id)
            ) $t",
            "CREATE TABLE IF NOT EXISTS password_tokens (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                token_hash CHAR(64) NOT NULL,
                purpose VARCHAR(16) NOT NULL,
                expires_at DATETIME NOT NULL,
                used_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                UNIQUE KEY uq_token (token_hash),
                KEY idx_user (user_id)
            ) $t",
            "CREATE TABLE IF NOT EXISTS remember_tokens (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                selector CHAR(24) NOT NULL,
                validator_hash CHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL,
                UNIQUE KEY uq_selector (selector),
                KEY idx_user (user_id)
            ) $t",
            "CREATE TABLE IF NOT EXISTS login_attempts (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                ip VARCHAR(45) NOT NULL,
                email VARCHAR(190) NOT NULL,
                created_at DATETIME NOT NULL,
                KEY idx_ip (ip, created_at)
            ) $t",
            "CREATE TABLE IF NOT EXISTS teams (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(120) NOT NULL,
                game VARCHAR(40) NOT NULL DEFAULT 'CS2',
                contact_name VARCHAR(120) NULL,
                contact_email VARCHAR(190) NULL,
                contact_phone VARCHAR(40) NULL,
                notes TEXT NULL,
                created_at DATETIME NOT NULL
            ) $t",
            "CREATE TABLE IF NOT EXISTS hotels (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(120) NOT NULL,
                city VARCHAR(80) NULL,
                address VARCHAR(255) NULL,
                notes TEXT NULL,
                active TINYINT(1) NOT NULL DEFAULT 1
            ) $t",
            "CREATE TABLE IF NOT EXISTS equipment (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(120) NOT NULL,
                category VARCHAR(20) NOT NULL,
                spec VARCHAR(190) NULL,
                total_qty INT NOT NULL DEFAULT 0,
                price_per_day DECIMAL(10,2) NULL,
                requestable TINYINT(1) NOT NULL DEFAULT 0,
                active TINYINT(1) NOT NULL DEFAULT 1,
                sort INT NOT NULL DEFAULT 0
            ) $t",
            "CREATE TABLE IF NOT EXISTS drinks (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(80) NOT NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                sort INT NOT NULL DEFAULT 0
            ) $t",
            "CREATE TABLE IF NOT EXISTS bootcamps (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                team_id INT UNSIGNED NOT NULL,
                hotel_id INT UNSIGNED NOT NULL,
                room VARCHAR(60) NULL,
                start_date DATE NOT NULL,
                end_date DATE NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'planned',
                price DECIMAL(10,2) NULL,
                currency CHAR(3) NOT NULL DEFAULT 'EUR',
                finance_notes TEXT NULL,
                staff_notes TEXT NULL,
                client_info TEXT NULL,
                created_by INT UNSIGNED NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                KEY idx_team (team_id),
                KEY idx_dates (start_date, end_date)
            ) $t",
            "CREATE TABLE IF NOT EXISTS bootcamp_items (
                bootcamp_id INT UNSIGNED NOT NULL,
                equipment_id INT UNSIGNED NOT NULL,
                qty INT NOT NULL DEFAULT 0,
                PRIMARY KEY (bootcamp_id, equipment_id)
            ) $t",
            "CREATE TABLE IF NOT EXISTS chair_configs (
                bootcamp_id INT UNSIGNED NOT NULL,
                chair_no INT NOT NULL,
                armrests VARCHAR(16) NOT NULL DEFAULT 'keep',
                PRIMARY KEY (bootcamp_id, chair_no)
            ) $t",
            "CREATE TABLE IF NOT EXISTS bootcamp_drinks (
                bootcamp_id INT UNSIGNED NOT NULL,
                drink_id INT UNSIGNED NOT NULL,
                PRIMARY KEY (bootcamp_id, drink_id)
            ) $t",
            "CREATE TABLE IF NOT EXISTS extra_requests (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                bootcamp_id INT UNSIGNED NOT NULL,
                equipment_id INT UNSIGNED NOT NULL,
                qty INT NOT NULL DEFAULT 1,
                note TEXT NULL,
                status VARCHAR(16) NOT NULL DEFAULT 'pending',
                requested_by INT UNSIGNED NULL,
                requested_at DATETIME NOT NULL,
                decided_by INT UNSIGNED NULL,
                decided_at DATETIME NULL,
                bill_from DATE NULL,
                bill_to DATE NULL,
                price_per_day DECIMAL(10,2) NULL,
                staff_note TEXT NULL,
                delivered_at DATETIME NULL,
                KEY idx_bootcamp (bootcamp_id)
            ) $t",
            "CREATE TABLE IF NOT EXISTS tickets (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                bootcamp_id INT UNSIGNED NULL,
                team_id INT UNSIGNED NOT NULL,
                created_by INT UNSIGNED NULL,
                category VARCHAR(20) NOT NULL DEFAULT 'other',
                subject VARCHAR(190) NOT NULL,
                status VARCHAR(16) NOT NULL DEFAULT 'open',
                assigned_to INT UNSIGNED NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                awaiting_staff_since DATETIME NULL,
                last_reminder_at DATETIME NULL,
                KEY idx_status (status),
                KEY idx_team (team_id)
            ) $t",
            "CREATE TABLE IF NOT EXISTS ticket_messages (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                ticket_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NULL,
                body TEXT NOT NULL,
                is_system TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                KEY idx_ticket (ticket_id, id)
            ) $t",
            "CREATE TABLE IF NOT EXISTS ticket_reads (
                ticket_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                last_message_id INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (ticket_id, user_id)
            ) $t",
            "CREATE TABLE IF NOT EXISTS notifications (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                title VARCHAR(190) NOT NULL,
                body VARCHAR(500) NULL,
                url VARCHAR(255) NULL,
                created_at DATETIME NOT NULL,
                read_at DATETIME NULL,
                pushed_at DATETIME NULL,
                KEY idx_user (user_id, id)
            ) $t",
            "CREATE TABLE IF NOT EXISTS push_subscriptions (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                endpoint_hash CHAR(64) NOT NULL,
                endpoint TEXT NOT NULL,
                p256dh VARCHAR(255) NULL,
                auth VARCHAR(255) NULL,
                user_agent VARCHAR(255) NULL,
                seen_upto INT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                last_ok_at DATETIME NULL,
                UNIQUE KEY uq_endpoint (endpoint_hash),
                KEY idx_user (user_id)
            ) $t",
        ],
        2 => [
            "CREATE TABLE IF NOT EXISTS checklist_items (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                bootcamp_id INT UNSIGNED NOT NULL,
                phase VARCHAR(10) NOT NULL,
                item_key VARCHAR(40) NULL,
                label VARCHAR(190) NOT NULL,
                detail VARCHAR(500) NULL,
                sort INT NOT NULL DEFAULT 0,
                done_by INT UNSIGNED NULL,
                done_at DATETIME NULL,
                UNIQUE KEY uq_key (bootcamp_id, phase, item_key),
                KEY idx_bootcamp (bootcamp_id, phase)
            ) $t",
            "CREATE TABLE IF NOT EXISTS bootcamp_reminders (
                bootcamp_id INT UNSIGNED NOT NULL,
                kind VARCHAR(30) NOT NULL,
                sent_at DATETIME NOT NULL,
                PRIMARY KEY (bootcamp_id, kind)
            ) $t",
            "CREATE TABLE IF NOT EXISTS feedback (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                bootcamp_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NULL,
                rating TINYINT NOT NULL,
                comment TEXT NULL,
                created_at DATETIME NOT NULL,
                UNIQUE KEY uq_bootcamp (bootcamp_id)
            ) $t",
            "CREATE TABLE IF NOT EXISTS activity_log (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                bootcamp_id INT UNSIGNED NULL,
                user_id INT UNSIGNED NULL,
                action VARCHAR(500) NOT NULL,
                created_at DATETIME NOT NULL,
                KEY idx_bootcamp (bootcamp_id, id)
            ) $t",
            "CREATE TABLE IF NOT EXISTS bootcamp_photos (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                bootcamp_id INT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NULL,
                file VARCHAR(190) NOT NULL,
                mime VARCHAR(40) NOT NULL,
                caption VARCHAR(255) NULL,
                created_at DATETIME NOT NULL,
                KEY idx_bootcamp (bootcamp_id)
            ) $t",
            "ALTER TABLE bootcamps
                ADD COLUMN drinks_saved_at DATETIME NULL,
                ADD COLUMN chairs_saved_at DATETIME NULL,
                ADD COLUMN checkout_notes TEXT NULL",
        ],
    ];
}

function db_version(): int
{
    q('CREATE TABLE IF NOT EXISTS settings (k VARCHAR(64) NOT NULL PRIMARY KEY, v TEXT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    return (int) (setting('schema_version', '0'));
}

function migrate(): void
{
    $current = db_version();
    foreach (migrations() as $version => $statements) {
        if ($version <= $current) {
            continue;
        }
        foreach ($statements as $sql) {
            db()->exec($sql);
        }
        set_setting('schema_version', (string) $version);
    }
}

function latest_schema_version(): int
{
    return max(array_keys(migrations()));
}

/** Default catalog data on first install. Everything is editable later. */
function seed_defaults(): void
{
    if ((int) scalar('SELECT COUNT(*) FROM equipment') === 0) {
        $items = [
            ['Gaming PC', 'pc', 'Ryzen 7 7800X3D · RTX 4070 Ti · 32GB DDR5', 0, 0],
            ['Monitor 300Hz', 'monitor', '', 0, 1],
            ['Monitor 600Hz', 'monitor', '', 0, 1],
            ['Table', 'table', '', 0, 0],
            ['Chair', 'chair', '', 0, 0],
            ['Webcam', 'peripheral', '', 0, 1],
        ];
        foreach ($items as $i => [$name, $cat, $spec, $qty, $req]) {
            insert('equipment', [
                'name' => $name, 'category' => $cat, 'spec' => $spec ?: null,
                'total_qty' => $qty, 'requestable' => $req, 'active' => 1, 'sort' => $i,
            ]);
        }
    }
    if ((int) scalar('SELECT COUNT(*) FROM drinks') === 0) {
        foreach (['Coca-Cola', 'Coca-Cola Zero', 'Fanta', 'Sprite', 'Red Bull', 'Red Bull Sugarfree', 'Monster Energy', 'Still water', 'Sparkling water'] as $i => $name) {
            insert('drinks', ['name' => $name, 'active' => 1, 'sort' => $i]);
        }
    }
    if ((int) scalar('SELECT COUNT(*) FROM hotels') === 0) {
        foreach (['Hyatt Regency Belgrade', 'Radisson Collection Old Mill Belgrade', 'Crowne Plaza Belgrade'] as $name) {
            insert('hotels', ['name' => $name, 'city' => 'Belgrade', 'active' => 1]);
        }
    }
}
