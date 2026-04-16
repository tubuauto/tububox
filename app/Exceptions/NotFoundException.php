<?php

declare(strict_types=1);

namespace App\Exceptions;

final class NotFoundException extends ApiException
{
    /**
     * @param array<string, mixed> $errors
     */
    public function __construct(string $message = 'Not found', array $errors = [])
    {
        parent::__construct($message, 'NOT_FOUND', 404, $errors);
    }
}

