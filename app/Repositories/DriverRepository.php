<?php

declare(strict_types=1);

namespace App\Repositories;

final class DriverRepository extends BaseRepository
{
    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $driverId): ?array
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM drivers WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $driverId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByUserId(int $userId): ?array
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM drivers WHERE user_id = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listByTenant(int $tenantId): array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT d.*, u.name AS user_name, u.phone AS user_phone
             FROM drivers d
             LEFT JOIN users u ON u.id = d.user_id
             WHERE d.tenant_id = :tenant_id
             ORDER BY d.id DESC'
        );
        $stmt->execute(['tenant_id' => $tenantId]);
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function createForUser(int $userId, ?int $tenantId, string $vehicleType = 'bike'): array
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO drivers (user_id, tenant_id, vehicle_type, status, online_status)
             VALUES (:user_id, :tenant_id, :vehicle_type, :status, :online_status)
             RETURNING *'
        );
        $stmt->execute([
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'vehicle_type' => $vehicleType,
            'status' => 'active',
            'online_status' => false,
        ]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : [];
    }
}
