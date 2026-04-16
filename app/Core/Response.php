<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private int $status = 200,
        private array $headers = [],
        private string $body = ''
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function json(array $payload, int $status = 200): self
    {
        return new self(
            status: $status,
            headers: ['Content-Type' => 'application/json; charset=utf-8'],
            body: (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    public static function html(string $html, int $status = 200): self
    {
        return new self(
            status: $status,
            headers: ['Content-Type' => 'text/html; charset=utf-8'],
            body: $html
        );
    }

    public static function redirect(string $location, int $status = 302): self
    {
        return new self($status, ['Location' => $location], '');
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header(sprintf('%s: %s', $name, $value));
        }

        echo $this->body;
    }
}

