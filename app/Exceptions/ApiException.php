<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class ApiException extends RuntimeException
{
    /**
     * @param array<string, mixed> $errors
     */
    public function __construct(
        string $message,
        private readonly string $errorCodeKey,
        private readonly int $httpStatus = 400,
        private readonly array $errors = []
    ) {
        parent::__construct($message);
    }

    public function errorCodeKey(): string
    {
        return $this->errorCodeKey;
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }

    /**
     * @return array<string, mixed>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}

