<?php

declare(strict_types=1);

namespace App\Repositories;

use Throwable;

final class ApiKeyRepository extends BaseRepository
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function listByTenant(int $tenantId): array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT * FROM api_keys WHERE tenant_id = :tenant_id ORDER BY id DESC'
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
        $stmt = $this->pdo()->prepare('SELECT * FROM api_keys WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findActive(string $key, string $secret): ?array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT * FROM api_keys WHERE api_key = :api_key AND api_secret = :api_secret AND status = :status LIMIT 1'
        );
        $stmt->execute([
            'api_key' => $key,
            'api_secret' => $secret,
            'status' => 'active',
        ]);
        $row = $stmt->fetch();

        if (is_array($row) && isset($row['id'])) {
            $this->touchLastUsedAt((int) $row['id']);
        }

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function create(int $tenantId, string $apiKey, string $apiSecret): array
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO api_keys (tenant_id, api_key, api_secret, status)
             VALUES (:tenant_id, :api_key, :api_secret, :status)
             RETURNING *'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'api_key' => $apiKey,
            'api_secret' => $apiSecret,
            'status' => 'active',
        ]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : [];
    }

    public function disable(int $id): void
    {
        $stmt = $this->pdo()->prepare(
            'UPDATE api_keys SET status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'status' => 'inactive',
        ]);
    }

    private function touchLastUsedAt(int $id): void
    {
        try {
            $stmt = $this->pdo()->prepare(
                'UPDATE api_keys SET last_used_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = :id'
            );
            $stmt->execute(['id' => $id]);
        } catch (Throwable) {
            // Keep API authentication available if last_used_at is not migrated yet.
        }
    }
}
