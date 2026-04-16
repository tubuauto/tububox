<?php

declare(strict_types=1);

namespace App\Exceptions;

final class ForbiddenException extends ApiException
{
    /**
     * @param array<string, mixed> $errors
     */
    public function __construct(string $message = 'Forbidden', array $errors = [])
    {
        parent::__construct($message, 'FORBIDDEN', 403, $errors);
    }
}

