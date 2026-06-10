<?php

return [
    'adapter' => env('DB_ADAPTER', 'pgsql'),
    'host'    => env('DB_HOST', '127.0.0.1'),
    'port'    => (int) env('DB_PORT', 5432),
    'name'    => env('DB_NAME', 'yve_agency'),
    'user'    => env('DB_USER', 'postgres'),
    'pass'    => env('DB_PASS', ''),
];
