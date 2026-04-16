<?php

declare(strict_types=1);

namespace App\Exceptions;

final class UnauthorizedException extends ApiException
{
    /**
     * @param array<string, mixed> $errors
     */
    public function __construct(string $message = 'Unauthorized', array $errors = [])
    {
        parent::__construct($message, 'UNAUTHORIZED', 401, $errors);
    }
}

