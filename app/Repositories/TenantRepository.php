<?php

declare(strict_types=1);

namespace App\Repositories;

final class TenantRepository extends BaseRepository
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function listActiveMerchants(int $limit = 200): array
    {
        $safeLimit = max(1, min(500, $limit));
        $stmt = $this->pdo()->prepare(
            sprintf(
                'SELECT * FROM tenants
                 WHERE status = :status AND type = :type
                 ORDER BY id DESC
                 LIMIT %d',
                $safeLimit
            )
        );
        $stmt->execute([
            'status' => 'active',
            'type' => 'merchant',
        ]);
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM tenants WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public function resolveDefaultFulfillmentTenantId(): ?int
    {
        $stmt = $this->pdo()->prepare(
            "SELECT id
             FROM tenants
             WHERE status = :status
               AND type IN ('platform', 'merchant')
             ORDER BY CASE WHEN type = 'platform' THEN 0 ELSE 1 END ASC, id ASC
             LIMIT 1"
        );
        $stmt->execute(['status' => 'active']);
        $id = $stmt->fetchColumn();
        if ($id === false || $id === null) {
            return null;
        }

        return (int) $id;
    }
}

