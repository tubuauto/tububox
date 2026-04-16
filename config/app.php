<?php

declare(strict_types=1);

return [
    'name' => env('APP_NAME', 'tububox'),
    'env' => env('APP_ENV', 'local'),
    'debug' => filter_var(env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOL),
    'url' => env('APP_URL', 'http://127.0.0.1:8080'),
    'timezone' => env('APP_TIMEZONE', 'UTC'),
    'secret' => env('APP_SECRET', 'change-me'),
];

