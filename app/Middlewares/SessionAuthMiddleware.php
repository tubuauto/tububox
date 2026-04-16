<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\ApiResponder;
use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;

final class SessionAuthMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        $user = $this->authService->user();
        if ($user === null) {
            if (str_starts_with($request->path(), '/api/')) {
                return ApiResponder::error(
                    message: 'Unauthorized',
                    errorCode: 'UNAUTHORIZED',
                    status: 401,
                    request: $request
                );
            }

            return Response::redirect('/login');
        }

        $user['auth_type'] = 'session';
        $request->setAttribute('auth', $user);
        return $next($request);
    }
}
