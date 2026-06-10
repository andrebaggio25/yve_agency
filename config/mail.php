<?php

return [
    'host'       => env('MAIL_HOST', 'smtp.mailtrap.io'),
    'port'       => (int) env('MAIL_PORT', 587),
    'username'   => env('MAIL_USERNAME', ''),
    'password'   => env('MAIL_PASSWORD', ''),
    'encryption' => env('MAIL_ENCRYPTION', 'tls'),
    'from'       => [
        'address' => env('MAIL_FROM_ADDRESS', 'noreply@yveagency.com'),
        'name'    => env('MAIL_FROM_NAME', 'YVE Agency'),
    ],
];
