<?php

declare(strict_types=1);

namespace App\Exceptions;

final class ValidationException extends ApiException
{
    /**
     * @param array<string, mixed> $errors
     */
    public function __construct(array $errors, string $message = 'Validation failed')
    {
        parent::__construct($message, 'VALIDATION_FAILED', 422, $errors);
    }
}

