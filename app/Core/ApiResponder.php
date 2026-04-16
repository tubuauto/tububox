<?php

declare(strict_types=1);

namespace App\Core;

final class ApiResponder
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $meta
     */
    public static function success(
        string $message,
        array $data = [],
        int $status = 200,
        array $meta = [],
        ?Request $request = null
    ): Response {
        $baseMeta = [
            'timestamp' => gmdate('c'),
            'request_id' => $request?->requestId(),
        ];

        return Response::json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'meta' => array_merge($baseMeta, $meta),
        ], $status);
    }

    /**
     * @param array<string, mixed> $errors
     * @param array<string, mixed> $meta
     */
    public static function error(
        string $message,
        string $errorCode,
        array $errors = [],
        int $status = 400,
        array $meta = [],
        ?Request $request = null
    ): Response {
        $baseMeta = [
            'timestamp' => gmdate('c'),
            'request_id' => $request?->requestId(),
        ];

        return Response::json([
            'success' => false,
            'message' => $message,
            'error_code' => $errorCode,
            'errors' => $errors,
            'meta' => array_merge($baseMeta, $meta),
        ], $status);
    }
}

