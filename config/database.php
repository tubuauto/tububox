<?php

declare(strict_types=1);

return [
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => (int) env('DB_PORT', '5432'),
    'database' => env('DB_DATABASE', 'tububox'),
    'username' => env('DB_USERNAME', 'postgres'),
    'password' => env('DB_PASSWORD', 'postgres'),
    'charset' => 'utf8',
];

