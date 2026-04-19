<?php

declare(strict_types=1);

namespace App\Repositories;

final class OrganizationRepository extends BaseRepository
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function listByTenant(int $tenantId): array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT * FROM organizations WHERE tenant_id = :tenant_id ORDER BY id DESC'
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
        $stmt = $this->pdo()->prepare('SELECT * FROM organizations WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function create(array $payload): array
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO organizations (tenant_id, name, type, address, lat, lng)
             VALUES (:tenant_id, :name, :type, :address, :lat, :lng)
             RETURNING *'
        );
        $stmt->execute([
            'tenant_id' => $payload['tenant_id'],
            'name' => $payload['name'],
            'type' => $payload['type'] ?? null,
            'address' => $payload['address'] ?? null,
            'lat' => $payload['lat'] ?? null,
            'lng' => $payload['lng'] ?? null,
        ]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listMarketplaceStores(int $limit = 200): array
    {
        $safeLimit = max(1, min(500, $limit));
        $stmt = $this->pdo()->prepare(
            sprintf(
                'SELECT o.*, t.name AS tenant_name
                 FROM organizations o
                 INNER JOIN tenants t ON t.id = o.tenant_id
                 WHERE t.status = :status
                 ORDER BY o.id DESC
                 LIMIT %d',
                $safeLimit
            )
        );
        $stmt->execute(['status' => 'active']);
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }
}
