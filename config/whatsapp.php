<?php

return [
    /*
    |--------------------------------------------------------------------------
    | WhatsApp API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for whatekster WhatsApp client integration
    |
    */

    'api_url' => env('WHATSAPP_API_URL', 'http://localhost:3050/api'),

    'enabled' => env('WHATSAPP_ENABLED', true),

    'timeout' => env('WHATSAPP_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Notification Settings
    |--------------------------------------------------------------------------
    */

    'notifications' => [
        'attendance_success' => env('WHATSAPP_NOTIFY_ATTENDANCE', true),
        'late_attendance' => env('WHATSAPP_NOTIFY_LATE', true),
        'absence' => env('WHATSAPP_NOTIFY_ABSENCE', true),
        'qr_generated' => env('WHATSAPP_NOTIFY_QR', false),
        'daily_report' => env('WHATSAPP_DAILY_REPORT', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Phone Number Format
    |--------------------------------------------------------------------------
    */

    'country_code' => env('WHATSAPP_COUNTRY_CODE', '62'), // Indonesia

    /*
    |--------------------------------------------------------------------------
    | Retry Settings
    |--------------------------------------------------------------------------
    */

    'retry' => [
        'enabled' => env('WHATSAPP_RETRY_ENABLED', false),
        'times' => env('WHATSAPP_RETRY_TIMES', 3),
        'delay' => env('WHATSAPP_RETRY_DELAY', 1000), // milliseconds
    ],
];
