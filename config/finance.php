<?php

return [
    'timezone' => env('FINANCE_TIMEZONE', 'America/Cancun'),

    'local_auto_login' => [
        'enabled' => (bool) env('FINANCE_LOCAL_AUTO_LOGIN', false),
        'email' => env('FINANCE_LOCAL_AUTO_LOGIN_EMAIL', 'administrador.siga@crenfcp.edu.mx'),
        'environments' => array_filter(array_map('trim', explode(',', env('FINANCE_LOCAL_AUTO_LOGIN_ENVIRONMENTS', 'local')))),
    ],

    'siga' => [
        'base_url' => env('SIGA_FINANCE_API_URL', ''),
        'token' => env('SIGA_FINANCE_API_TOKEN', ''),
        'timeout' => (int) env('SIGA_FINANCE_API_TIMEOUT', 10),
    ],
];
