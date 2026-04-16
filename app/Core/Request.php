<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    /**
     * @param array<string, string> $headers
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly string $requestId,
        private readonly array $headers,
        private readonly array $query,
        private readonly array $body,
        private array $attributes = []
    ) {
    }

    public static function capture(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        $headers = function_exists('getallheaders') ? (array) getallheaders() : [];
        $normalizedHeaders = [];
        foreach ($headers as $key => $value) {
            $normalizedHeaders[strtolower($key)] = $value;
        }
        $requestId = (string) ($normalizedHeaders['x-request-id'] ?? '');
        if ($requestId === '') {
            try {
                $requestId = bin2hex(random_bytes(8));
            } catch (\Throwable) {
                $requestId = uniqid('req_', true);
            }
        }

        $raw = file_get_contents('php://input');
        $contentType = strtolower($normalizedHeaders['content-type'] ?? '');
        $jsonBody = [];

        if (str_contains($contentType, 'application/json') && is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $jsonBody = $decoded;
            }
        }

        $body = $method === 'GET' ? [] : (count($jsonBody) > 0 ? $jsonBody : $_POST);

        return new self(
            method: $method,
            path: $path,
            requestId: $requestId,
            headers: $normalizedHeaders,
            query: $_GET,
            body: $body
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function requestId(): string
    {
        return $this->requestId;
    }

    /**
     * @return array<string, mixed>
     */
    public function query(): array
    {
        return $this->query;
    }

    /**
     * @return array<string, mixed>
     */
    public function body(): array
    {
        return $this->body;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->body)) {
            return $this->body[$key];
        }

        if (array_key_exists($key, $this->query)) {
            return $this->query[$key];
        }

        return $default;
    }

    public function header(string $key, ?string $default = null): ?string
    {
        return $this->headers[strtolower($key)] ?? $default;
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }
}
