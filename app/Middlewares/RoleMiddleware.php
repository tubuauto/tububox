<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\ApiResponder;
use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;

final class RoleMiddleware implements MiddlewareInterface
{
    /**
     * @param array<int, string> $allowedRoles
     */
    public function __construct(private readonly array $allowedRoles)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        $auth = $request->attribute('auth');
        if (!is_array($auth)) {
            if (!str_starts_with($request->path(), '/api/')) {
                return Response::redirect('/login');
            }

            return ApiResponder::error(
                message: 'Unauthorized',
                errorCode: 'UNAUTHORIZED',
                status: 401,
                request: $request
            );
        }

        $role = (string) ($auth['role'] ?? '');
        if (!in_array($role, $this->allowedRoles, true)) {
            if (!str_starts_with($request->path(), '/api/')) {
                return Response::html('<h1>403 Forbidden</h1>', 403);
            }

            return ApiResponder::error(
                message: 'Forbidden',
                errorCode: 'FORBIDDEN',
                status: 403,
                request: $request
            );
        }

        return $next($request);
    }
}
