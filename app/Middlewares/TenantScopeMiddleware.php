<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\ApiResponder;
use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;

final class TenantScopeMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $auth = $request->attribute('auth');
        if (!is_array($auth)) {
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

        $isAdmin = (bool) ($auth['is_admin'] ?? false);
        $authTenantId = $auth['tenant_id'] !== null ? (int) $auth['tenant_id'] : null;

        if (!$isAdmin && $authTenantId === null) {
            return $this->forbidden($request, 'Tenant scope is required for non-admin users.');
        }

        $inputTenant = $request->input('tenant_id');
        if ($inputTenant !== null && !$isAdmin) {
            $inputTenantId = (int) $inputTenant;
            if ($inputTenantId !== $authTenantId) {
                return $this->forbidden($request, 'Cross-tenant access is not allowed.');
            }
        }

        $request->setAttribute('tenant_scope', $isAdmin ? null : $authTenantId);
        return $next($request);
    }

    private function forbidden(Request $request, string $message): Response
    {
        if (!str_starts_with($request->path(), '/api/')) {
            return Response::html('<h1>403 Forbidden</h1><p>' . h($message) . '</p>', 403);
        }

        return ApiResponder::error(
            message: $message,
            errorCode: 'FORBIDDEN',
            status: 403,
            request: $request
        );
    }
}

