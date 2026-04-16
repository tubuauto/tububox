<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\ApiResponder;
use App\Core\Request;
use App\Core\Response;
use App\Exceptions\ApiException;
use Throwable;

abstract class BaseApiController
{
    /**
     * @param array<string, mixed> $data
     */
    protected function success(string $message, array $data = [], int $status = 200, ?Request $request = null): Response
    {
        return ApiResponder::success($message, $data, $status, request: $request);
    }

    /**
     * @param array<string, mixed> $errors
     */
    protected function error(
        string $message,
        array $errors = [],
        int $status = 400,
        string $errorCode = 'BAD_REQUEST',
        ?Request $request = null
    ): Response {
        return ApiResponder::error($message, $errorCode, $errors, $status, request: $request);
    }

    protected function handleException(Throwable $e, ?Request $request = null): Response
    {
        if ($e instanceof ApiException) {
            return ApiResponder::error(
                message: $e->getMessage(),
                errorCode: $e->errorCodeKey(),
                errors: $e->errors(),
                status: $e->httpStatus(),
                request: $request
            );
        }

        return ApiResponder::error(
            message: 'Server error',
            errorCode: 'INTERNAL_SERVER_ERROR',
            errors: ['exception' => $e->getMessage()],
            status: 500,
            request: $request
        );
    }
}
