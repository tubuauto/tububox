<?php

declare(strict_types=1);

namespace App\Exceptions;

final class ConflictException extends ApiException
{
    /**
     * @param array<string, mixed> $errors
     */
    public function __construct(string $message = 'Conflict', array $errors = [])
    {
        parent::__construct($message, 'CONFLICT', 409, $errors);
    }
}

