<?php

declare(strict_types=1);

use App\Core\Request;
use App\Core\Response;
use App\Core\ApiResponder;

$app = require __DIR__ . '/../bootstrap/app.php';
$request = Request::capture();

try {
    $response = $app['router']->dispatch($request);
} catch (Throwable $e) {
    $isApi = str_starts_with($request->path(), '/api/');
    if ($isApi) {
        $response = ApiResponder::error(
            message: 'Server Error',
            errorCode: 'INTERNAL_SERVER_ERROR',
            errors: ['exception' => $e->getMessage()],
            status: 500,
            request: $request
        );
    } else {
        $response = Response::html(
            '<h1>500 Server Error</h1><pre>' . htmlspecialchars($e->getMessage(), ENT_QUOTES) . '</pre>',
            500
        );
    }
}

$response->send();
