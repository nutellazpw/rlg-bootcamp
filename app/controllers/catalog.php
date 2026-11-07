<?php
declare(strict_types=1);

// ---------- Hotels ----------

function catalog_hotels(): void
{
    require_staff();
    view('catalog/hotels', [
        'title' => 'Hotels',
        'rows' => all("SELECT h.*, (SELECT COUNT(*) FROM bootcamps b WHERE b.hotel_id = h.id AND b.status <> 'cancelled' AND b.end_date >= CURDATE()) AS upcoming FROM hotels h ORDER BY h.active DESC, h.name"),
    ]);
}

function catalog_hotel_save(?int $id = null): void
{
    require_role('admin', 'manager');
    $name = mb_substr(trim((string) input('name')), 0, 120);
    if ($name === '') {
        flash('error', 'Enter the hotel name.');
        redirect('/hotels');
    }
    $data = [
        'name' => $name,
        'city' => mb_substr((string) input('city'), 0, 80) ?: null,
        'address' => mb_substr((string) input('address'), 0, 255) ?: null,
        'notes' => (string) input('notes') ?: null,
        'active' => input('active', $id ? '' : '1') ? 1 : 0,
    ];
    if ($id) {
        one('SELECT id FROM hotels WHERE id = ?', [$id]) ?? not_found();
        update('hotels', $data, 'id = ?', [$id]);
    } else {
        insert('hotels', $data);
    }
    flash('success', 'Hotel saved.');
    redirect('/hotels');
}

// ---------- Inventory ----------

function catalog_inventory(): void
{
    require_staff();
    $today = date('Y-m-d');
    $usageToday = equipment_peak_usage($today, $today);
    $usage30 = equipment_peak_usage($today, date('Y-m-d', strtotime('+30 days')));
    view('catalog/inventory', [
        'title' => 'Inventory',
        'rows' => all('SELECT * FROM equipment ORDER BY active DESC, FIELD(category,"pc","monitor","table","chair","peripheral","other"), sort, name'),
        'usageToday' => $usageToday,
        'usage30' => $usage30,
    ]);
}

function catalog_equipment_save(?int $id = null): void
{
    require_role('admin', 'manager');
    $name = mb_substr(trim((string) input('name')), 0, 120);
    $cat = (string) input('category');
    if ($name === '' || !array_key_exists($cat, EQUIPMENT_CATEGORIES)) {
        flash('error', 'Enter a name and category.');
        redirect('/inventory');
    }
    $data = [
        'name' => $name,
        'category' => $cat,
        'spec' => mb_substr((string) input('spec'), 0, 190) ?: null,
        'total_qty' => max(0, input_int('total_qty')),
        'requestable' => input('requestable') ? 1 : 0,
        'active' => input('active', $id ? '' : '1') ? 1 : 0,
        'sort' => input_int('sort'),
    ];
    if ($id) {
        one('SELECT id FROM equipment WHERE id = ?', [$id]) ?? not_found();
        update('equipment', $data, 'id = ?', [$id]);
    } else {
        insert('equipment', $data);
    }
    flash('success', 'Saved.');
    redirect('/inventory');
}

// ---------- Drinks ----------

function catalog_drinks(): void
{
    require_staff();
    view('catalog/drinks', [
        'title' => 'Drinks',
        'rows' => all('SELECT d.*, (SELECT COUNT(*) FROM bootcamp_drinks bd JOIN bootcamps b ON b.id = bd.bootcamp_id WHERE bd.drink_id = d.id AND b.end_date >= CURDATE() AND b.status <> "cancelled") AS chosen FROM drinks d ORDER BY d.active DESC, d.sort, d.name'),
    ]);
}

function catalog_drink_save(?int $id = null): void
{
    require_role('admin', 'manager');
    $name = mb_substr(trim((string) input('name')), 0, 80);
    if ($name === '') {
        flash('error', 'Enter the drink name.');
        redirect('/drinks');
    }
    $data = ['name' => $name, 'active' => input('active', $id ? '' : '1') ? 1 : 0, 'sort' => input_int('sort')];
    if ($id) {
        one('SELECT id FROM drinks WHERE id = ?', [$id]) ?? not_found();
        update('drinks', $data, 'id = ?', [$id]);
    } else {
        $data['sort'] = (int) scalar('SELECT COALESCE(MAX(sort),0)+1 FROM drinks');
        insert('drinks', $data);
    }
    flash('success', 'Saved.');
    redirect('/drinks');
}

// ---------- Settings (admin) ----------

function catalog_settings(): void
{
    $me = require_role('admin');
    if (is_post()) {
        $action = (string) input('action');
        if ($action === 'test_telegram') {
            $ok = telegram_send('✅ RLG Bootcamp test message from ' . $me['name']);
            $ok ? flash('success', 'Telegram message sent.') : flash('error', 'Telegram failed. Check bot_token and chat_id in app/config.php (see storage/logs/telegram.log).');
        } elseif ($action === 'test_mail') {
            $ok = send_mail($me['email'], 'RLG Bootcamp test email', mail_template('Test email', 'If you can read this, outgoing email works.'));
            $ok ? flash('success', 'Test email sent to ' . $me['email'] . '.') : flash('error', 'Email failed. Check the mail settings in app/config.php (see storage/logs/mail.log).');
        } elseif ($action === 'test_reminders') {
            require_once APP_DIR . '/cron_tasks.php';
            flash('success', 'Cron ran: ' . str_replace("\n", ' · ', trim(run_cron_tasks())));
        }
        redirect('/settings');
    }
    $vapidOk = true;
    try {
        vapid_keys();
    } catch (Throwable $ex) {
        $vapidOk = false;
    }
    view('catalog/settings', [
        'title' => 'Settings',
        'lastCron' => setting('last_cron_run'),
        'telegramOn' => config('telegram.bot_token') && config('telegram.chat_id'),
        'mailHost' => config('mail.host') ?: 'PHP mail()',
        'vapidOk' => $vapidOk,
        'devices' => (int) scalar('SELECT COUNT(*) FROM push_subscriptions'),
    ]);
}
