<?php
declare(strict_types=1);

require __DIR__ . '/app/controllers/public.php';
require __DIR__ . '/app/controllers/admin.php';

function admin_home(): void
{
    if (current_admin()) {
        redirect_route('admin/dashboard');
    }
    redirect_route('admin/login');
}

return [
    /* Public */
    ['GET',  'home',                          'page_home'],
    ['GET',  'register',                      'page_register'],
    ['POST', 'register',                      'submit_register'],
    ['GET',  'portal/{token}',                'page_portal'],
    ['GET',  'complete/{token}',              'page_complete'],
    ['POST', 'complete/{token}',              'submit_complete'],
    ['GET',  'bulk',                          'page_bulk_register'],
    ['POST', 'bulk',                          'submit_bulk_register'],
    ['GET',  'bulk/{token}',                  'page_bulk_portal'],
    ['POST', 'bulk/retry/{token}',            'retry_bulk_payment'],
    ['GET',  'complete-ticket/{token}',       'page_complete_ticket'],
    ['POST', 'complete-ticket/{token}',       'submit_complete_ticket'],
    ['POST', 'pay/start',                     'start_payment_controller'],
    ['GET',  'pay/mock/{reference}',          'page_mock_gateway'],
    ['POST', 'pay/mock/{reference}',          'mock_gateway_action'],
    ['GET',  'payment/callback',              'page_callback'],
    ['GET',  'payment/callback/{reference}',  'page_callback'],
    ['POST', 'payment/webhook',               'payment_webhook'],
    ['GET',  'status',                        'page_status'],
    ['POST', 'status',                        'lookup_status'],
    ['GET',  'id-card/{token}',               'page_id_card'],
    ['GET',  'event.ics',                     'event_ics'],
    ['POST', 'newsletter',                    'newsletter_subscribe'],
    ['GET',  'newsletter/unsubscribe/{token}', 'newsletter_unsubscribe'],

    /* Admin */
    ['GET',  'admin',                         'admin_home'],
    ['GET',  'admin/login',                   'admin_login_page'],
    ['POST', 'admin/login',                   'admin_login_submit'],
    ['POST', 'admin/logout',                  'admin_logout'],
    ['GET',  'admin/dashboard',               'admin_dashboard'],
    ['GET',  'admin/participants',            'admin_participants'],
    ['GET',  'admin/participants/export',     'admin_participants_export'],
    ['GET',  'admin/participants/{id}',       'admin_participant_detail'],
    ['POST', 'admin/participants/{id}/action', 'admin_participant_action'],
    ['GET',  'admin/payments',                'admin_payments'],
    ['GET',  'admin/payments/export',         'admin_payments_export'],
    ['POST', 'admin/payments/{id}/action',    'admin_payment_action'],
    ['GET',  'admin/check-in',                'admin_checkin'],
    ['POST', 'admin/check-in/action',         'admin_checkin_action'],
    ['GET',  'admin/houses',                  'admin_houses'],
    ['POST', 'admin/houses/action',           'admin_house_action'],
    ['GET',  'admin/reports',                 'admin_reports'],
    ['GET',  'admin/reports/export',          'admin_report_export'],
    ['GET',  'admin/users',                   'admin_users'],
    ['POST', 'admin/users/action',            'admin_users_action'],
    ['GET',  'admin/settings',                'admin_settings'],
    ['POST', 'admin/settings/save',           'admin_settings_save'],
    ['GET',  'admin/audit',                   'admin_audit'],
    ['GET',  'admin/newsletter',              'admin_newsletter'],
    ['POST', 'admin/newsletter/action',       'admin_newsletter_action'],
    ['GET',  'admin/bulk',                    'admin_bulk'],
    ['GET',  'admin/database',                'admin_database'],
    ['POST', 'admin/database/query',          'admin_database_query'],
];
