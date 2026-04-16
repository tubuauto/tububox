<?php

declare(strict_types=1);

use App\Core\Router;
use App\Core\View;
use App\Repositories\Database;

require_once __DIR__ . '/autoload.php';
require_once __DIR__ . '/../app/Core/helpers.php';

$envPath = __DIR__ . '/../.env';
if (is_file($envPath)) {
    $envVars = parse_ini_file($envPath, false, INI_SCANNER_TYPED);
    if (is_array($envVars)) {
        foreach ($envVars as $key => $value) {
            $stringValue = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
            $_ENV[$key] = $stringValue;
            $_SERVER[$key] = $stringValue;
            putenv(sprintf('%s=%s', $key, $stringValue));
        }
    }
}

$appConfig = require __DIR__ . '/../config/app.php';
$databaseConfig = require __DIR__ . '/../config/database.php';

date_default_timezone_set((string) $appConfig['timezone']);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

$router = new Router();
$db = new Database($databaseConfig);
$view = new View(__DIR__ . '/../app/Views');

require __DIR__ . '/../routes/web.php';
require __DIR__ . '/../routes/api.php';

return [
    'config' => $appConfig,
    'db' => $db,
    'view' => $view,
    'router' => $router,
];
