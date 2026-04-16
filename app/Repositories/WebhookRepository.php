<?php

declare(strict_types=1);

namespace App\Repositories;

final class WebhookRepository extends BaseRepository
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function listByTenant(int $tenantId): array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT * FROM webhook_endpoints WHERE tenant_id = :tenant_id ORDER BY id DESC'
        );
        $stmt->execute(['tenant_id' => $tenantId]);
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM webhook_endpoints WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createEndpoint(array $payload): array
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO webhook_endpoints (tenant_id, url, event, status, secret)
             VALUES (:tenant_id, :url, :event, :status, :secret)
             RETURNING *'
        );
        $stmt->execute([
            'tenant_id' => $payload['tenant_id'],
            'url' => $payload['url'],
            'event' => $payload['event'],
            'status' => $payload['status'] ?? 'active',
            'secret' => $payload['secret'] ?? null,
        ]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : [];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|null
     */
    public function updateEndpoint(int $id, array $payload): ?array
    {
        $stmt = $this->pdo()->prepare(
            'UPDATE webhook_endpoints
             SET url = :url, event = :event, status = :status, secret = :secret, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id
             RETURNING *'
        );
        $stmt->execute([
            'id' => $id,
            'url' => $payload['url'],
            'event' => $payload['event'],
            'status' => $payload['status'] ?? 'active',
            'secret' => $payload['secret'] ?? null,
        ]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public function deleteEndpoint(int $id): void
    {
        $stmt = $this->pdo()->prepare('DELETE FROM webhook_endpoints WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listActiveEndpoints(int $tenantId, string $event): array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT * FROM webhook_endpoints
             WHERE tenant_id = :tenant_id AND event = :event AND status = :status
             ORDER BY id DESC'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'event' => $event,
            'status' => 'active',
        ]);
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function createLog(
        ?int $deliveryId,
        ?int $endpointId,
        array $payload,
        ?string $response,
        string $status
    ): void {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO webhook_logs (delivery_id, webhook_endpoint_id, payload, response, status)
             VALUES (:delivery_id, :webhook_endpoint_id, :payload, :response, :status)'
        );
        $stmt->execute([
            'delivery_id' => $deliveryId,
            'webhook_endpoint_id' => $endpointId,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'response' => $response,
            'status' => $status,
        ]);
    }
}
