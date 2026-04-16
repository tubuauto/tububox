<?php

declare(strict_types=1);

namespace App\Exceptions;

final class BadRequestException extends ApiException
{
    /**
     * @param array<string, mixed> $errors
     */
    public function __construct(string $message = 'Bad request', array $errors = [])
    {
        parent::__construct($message, 'BAD_REQUEST', 400, $errors);
    }
}

