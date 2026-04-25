<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ForbiddenException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Repositories\WebhookRepository;

final class WebhookService
{
    public function __construct(private readonly WebhookRepository $webhooks)
    {
    }

    /**
     * @param array<string, mixed> $delivery
     */
    public function dispatchDeliveryStatus(array $delivery): void
    {
        $event = 'delivery.' . (string) $delivery['status'];
        $tenantId = (int) $delivery['tenant_id'];
        $deliveryId = (int) $delivery['id'];

        $payload = [
            'event' => $event,
            'tenant_id' => $tenantId,
            'delivery' => [
                'id' => $deliveryId,
                'source_order_no' => $delivery['source_order_no'],
                'status' => $delivery['status'],
                'assigned_driver_id' => $delivery['assigned_driver_id'],
                'picked_up_at' => $delivery['picked_up_at'],
                'completed_at' => $delivery['completed_at'],
            ],
            'timestamp' => gmdate('c'),
        ];

        $endpoints = $this->webhooks->listActiveEndpoints($tenantId, $event);
        foreach ($endpoints as $endpoint) {
            $result = $this->sendJson(
                url: (string) $endpoint['url'],
                payload: $payload,
                secret: isset($endpoint['secret']) ? (string) $endpoint['secret'] : null
            );
            $this->webhooks->createLog(
                deliveryId: $deliveryId,
                endpointId: (int) $endpoint['id'],
                payload: $payload,
                response: $result['response'],
                status: $result['status']
            );
        }
    }

    /**
     * @param array<string, mixed> $auth
     * @return array<int, array<string, mixed>>
     */
    public function listEndpoints(array $auth): array
    {
        $tenantId = $this->tenantIdOrFail($auth);
        return $this->webhooks->listByTenant($tenantId);
    }

    /**
     * @param array<string, mixed> $auth
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createEndpoint(array $auth, array $payload): array
    {
        $tenantId = $this->tenantIdOrFail($auth);
        $this->validateEndpointPayload($payload);

        return $this->webhooks->createEndpoint([
            'tenant_id' => $tenantId,
            'url' => trim((string) $payload['url']),
            'event' => trim((string) $payload['event']),
            'status' => $this->normalizeStatus((string) ($payload['status'] ?? 'active')),
            'secret' => $this->normalizeSecret($payload['secret'] ?? null),
        ]);
    }

    /**
     * @param array<string, mixed> $auth
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function updateEndpoint(array $auth, int $id, array $payload): array
    {
        $tenantId = $this->tenantIdOrFail($auth);
        $current = $this->webhooks->findById($id);
        if ($current === null) {
            throw new NotFoundException('Webhook endpoint not found.');
        }

        if (($auth['is_admin'] ?? false) !== true && (int) $current['tenant_id'] !== $tenantId) {
            throw new ForbiddenException('No permission to modify this webhook endpoint.');
        }

        $updatePayload = [
            'url' => trim((string) ($payload['url'] ?? $current['url'])),
            'event' => trim((string) ($payload['event'] ?? $current['event'])),
            'status' => $this->normalizeStatus((string) ($payload['status'] ?? $current['status'])),
            'secret' => array_key_exists('secret', $payload)
                ? $this->normalizeSecret($payload['secret'])
                : $this->normalizeSecret($current['secret']),
        ];

        $this->validateEndpointPayload($updatePayload);
        $updated = $this->webhooks->updateEndpoint($id, $updatePayload);
        if ($updated === null) {
            throw new NotFoundException('Webhook endpoint not found after update.');
        }

        return $updated;
    }

    /**
     * @param array<string, mixed> $auth
     */
    public function deleteEndpoint(array $auth, int $id): void
    {
        $tenantId = $this->tenantIdOrFail($auth);
        $current = $this->webhooks->findById($id);
        if ($current === null) {
            throw new NotFoundException('Webhook endpoint not found.');
        }

        if (($auth['is_admin'] ?? false) !== true && (int) $current['tenant_id'] !== $tenantId) {
            throw new ForbiddenException('No permission to delete this webhook endpoint.');
        }

        $this->webhooks->deleteEndpoint($id);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status:string,response:string}
     */
    public function sendJson(string $url, array $payload, ?string $secret = null): array
    {
        $timestamp = (string) time();
        $json = (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $signature = $secret !== null && $secret !== ''
            ? hash_hmac('sha256', $timestamp . '.' . $json, $secret)
            : '';

        $headers = [
            "Content-Type: application/json",
            "X-Tubu-Timestamp: {$timestamp}",
        ];
        if ($signature !== '') {
            $headers[] = "X-Tubu-Signature: sha256={$signature}";
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers) . "\r\n",
                'content' => $json,
                'timeout' => 3,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            return [
                'status' => 'failed',
                'response' => 'Request failed',
            ];
        }

        return [
            'status' => 'success',
            'response' => $response,
        ];
    }

    /**
     * @param array<string, mixed> $auth
     */
    private function tenantIdOrFail(array $auth): int
    {
        $tenantId = $auth['tenant_id'] ?? null;
        if ($tenantId === null) {
            throw new ForbiddenException('Tenant scope is required.');
        }

        return (int) $tenantId;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function validateEndpointPayload(array $payload): void
    {
        $errors = [];
        $url = trim((string) ($payload['url'] ?? ''));
        $event = trim((string) ($payload['event'] ?? ''));

        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            $errors['url'] = 'A valid URL is required.';
        }

        $allowedEvents = [
            'delivery.created',
            'delivery.assigned',
            'delivery.picked_up',
            'delivery.in_transit',
            'delivery.signed',
            'delivery.completed',
            'delivery.failed',
            'delivery.cancelled',
            'delivery.dispatch_pending',
            'delivery.cod_collected',
        ];

        if ($event === '' || !in_array($event, $allowedEvents, true)) {
            $errors['event'] = 'Unsupported webhook event.';
        }

        if (count($errors) > 0) {
            throw new ValidationException($errors);
        }
    }

    private function normalizeStatus(string $status): string
    {
        return in_array($status, ['active', 'inactive'], true) ? $status : 'active';
    }

    private function normalizeSecret(mixed $secret): ?string
    {
        if ($secret === null) {
            return null;
        }

        $value = trim((string) $secret);
        return $value === '' ? null : $value;
    }
}
