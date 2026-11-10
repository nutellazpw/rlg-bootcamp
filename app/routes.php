<?php
// [methods, regex path, "controller@function"]
$GP = ['GET', 'POST'];

return [
    ['GET', '/', 'auth@home'],
    [$GP, '/login', 'auth@login'],
    ['POST', '/logout', 'auth@logout'],
    [$GP, '/forgot-password', 'auth@forgot'],
    [$GP, '/set-password', 'auth@set_password'],
    [$GP, '/setup', 'auth@setup'],

    // Staff
    ['GET', '/dashboard', 'dashboard@index'],
    ['GET', '/calendar', 'dashboard@calendar'],

    ['GET', '/bootcamps', 'bootcamps@index'],
    [$GP, '/bootcamps/new', 'bootcamps@create'],
    ['GET', '/bootcamps/(\d+)', 'bootcamps@show'],
    [$GP, '/bootcamps/(\d+)/edit', 'bootcamps@edit'],
    ['POST', '/bootcamps/(\d+)/quick', 'bootcamps@quick'],
    ['POST', '/bootcamps/(\d+)/delete', 'bootcamps@delete'],
    ['POST', '/bootcamps/(\d+)/drinks', 'bootcamps@drinks'],
    ['POST', '/bootcamps/(\d+)/chairs', 'bootcamps@chairs'],
    ['POST', '/bootcamps/(\d+)/extras', 'bootcamps@add_extra'],
    ['POST', '/extras/(\d+)/decide', 'bootcamps@decide_extra'],
    ['POST', '/extras/(\d+)/delivered', 'bootcamps@deliver_extra'],
    ['POST', '/extras/(\d+)/update', 'bootcamps@update_extra'],
    ['POST', '/extras/(\d+)/claim', 'bootcamps@claim_extra'],
    ['POST', '/api/availability', 'bootcamps@availability'],
    ['POST', '/checklist/(\d+)/toggle', 'ops@checklist_toggle'],
    ['POST', '/checklist/(\d+)/delete', 'ops@checklist_delete'],
    ['POST', '/bootcamps/(\d+)/checklist', 'ops@checklist_add'],
    ['POST', '/bootcamps/(\d+)/checkout-notes', 'ops@checkout_notes'],
    ['POST', '/bootcamps/(\d+)/photos', 'ops@photo_upload'],
    ['GET', '/photos/(\d+)', 'ops@photo_show'],
    ['POST', '/photos/(\d+)/delete', 'ops@photo_delete'],
    ['GET', '/bootcamps/(\d+)/print', 'ops@print'],
    ['GET', '/bootcamps/(\d+)/report', 'ops@report'],
    ['POST', '/my/bootcamp/(\d+)/feedback', 'ops@feedback'],

    ['GET', '/teams', 'teams@index'],
    [$GP, '/teams/new', 'teams@create'],
    ['GET', '/teams/(\d+)', 'teams@show'],
    [$GP, '/teams/(\d+)/edit', 'teams@edit'],
    ['POST', '/teams/(\d+)/accounts', 'teams@add_account'],
    ['GET', '/team-logo/(\d+)', 'teams@logo'],

    ['GET', '/users', 'users@index'],
    ['POST', '/users', 'users@create'],
    ['POST', '/users/(\d+)', 'users@update'],
    ['POST', '/users/(\d+)/invite', 'users@resend_invite'],
    ['POST', '/users/(\d+)/toggle', 'users@toggle'],
    ['POST', '/users/(\d+)/avatar', 'users@avatar'],
    ['GET', '/avatar/(\d+)', 'users@avatar_image'],

    ['GET', '/hotels', 'catalog@hotels'],
    ['POST', '/hotels', 'catalog@hotel_save'],
    ['POST', '/hotels/(\d+)', 'catalog@hotel_save'],
    ['GET', '/inventory', 'catalog@inventory'],
    ['POST', '/inventory', 'catalog@equipment_save'],
    ['POST', '/inventory/(\d+)', 'catalog@equipment_save'],
    ['GET', '/drinks', 'catalog@drinks'],
    ['POST', '/drinks', 'catalog@drink_save'],
    ['POST', '/drinks/(\d+)', 'catalog@drink_save'],
    [$GP, '/settings', 'catalog@settings'],

    // Tickets (staff + clients)
    ['GET', '/tickets', 'tickets@index'],
    [$GP, '/tickets/new', 'tickets@create'],
    ['GET', '/tickets/(\d+)', 'tickets@show'],
    ['POST', '/tickets/(\d+)/reply', 'tickets@reply'],
    ['POST', '/tickets/(\d+)/update', 'tickets@update'],
    ['GET', '/tickets/(\d+)/messages', 'tickets@messages'],
    ['GET', '/attachments/(\d+)', 'tickets@attachment'],

    // Client
    ['GET', '/my', 'client@home'],
    ['GET', '/my/bootcamp/(\d+)', 'client@bootcamp'],
    ['POST', '/my/bootcamp/(\d+)/drinks', 'client@drinks'],
    ['POST', '/my/bootcamp/(\d+)/refill', 'client@refill'],
    ['POST', '/my/bootcamp/(\d+)/chairs', 'client@chairs'],
    ['POST', '/my/bootcamp/(\d+)/extras', 'client@request_extra'],
    ['POST', '/my/extras/(\d+)/cancel', 'client@cancel_extra'],

    // Requests: activities + new bootcamp quotes
    ['POST', '/my/bootcamp/(\d+)/activities', 'requests@activity'],
    [$GP, '/my/new-bootcamp', 'requests@quote_form'],
    ['GET', '/requests', 'requests@index'],
    ['POST', '/activity-requests/(\d+)', 'requests@activity_reply'],
    ['POST', '/quotes/(\d+)', 'requests@quote_update'],
    ['GET', '/activities', 'requests@activities_catalog'],
    ['POST', '/activities', 'requests@activity_save'],
    ['POST', '/activities/(\d+)', 'requests@activity_save'],
    ['GET', '/activity-image/(\d+)', 'requests@activity_image'],

    // Everyone
    [$GP, '/profile', 'account@profile'],
    ['GET', '/notifications', 'account@notifications'],
    ['POST', '/notifications/read-all', 'account@read_all'],
    ['GET', '/n/(\d+)', 'account@open_notification'],
    ['GET', '/api/status', 'account@status'],

    // Web push
    ['GET', '/api/push/key', 'push@key'],
    ['POST', '/api/push/subscribe', 'push@subscribe'],
    ['POST', '/api/push/unsubscribe', 'push@unsubscribe'],
    ['POST', '/api/push/pending', 'push@pending'],
    ['POST', '/api/push/test', 'push@test'],
];
