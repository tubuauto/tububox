<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\ApiResponder;
use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\ApiKeyRepository;

final class ApiKeyAuthMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly ApiKeyRepository $apiKeys)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        $key = (string) $request->header('x-api-key', '');
        $secret = (string) $request->header('x-api-secret', '');

        if ($key === '' || $secret === '') {
            return ApiResponder::error(
                message: 'Missing API credentials',
                errorCode: 'UNAUTHORIZED',
                status: 401,
                request: $request
            );
        }

        $apiKey = $this->apiKeys->findActive($key, $secret);
        if ($apiKey === null) {
            return ApiResponder::error(
                message: 'Invalid API credentials',
                errorCode: 'UNAUTHORIZED',
                status: 401,
                request: $request
            );
        }

        $request->setAttribute('auth', [
            'id' => null,
            'tenant_id' => (int) $apiKey['tenant_id'],
            'role' => 'merchant',
            'is_admin' => false,
            'api_key_id' => (int) $apiKey['id'],
            'auth_type' => 'api_key',
        ]);

        return $next($request);
    }
}
