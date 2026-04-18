<?php

declare(strict_types=1);

namespace App\Repositories;

final class DeliveryLogRepository extends BaseRepository
{
    public function create(
        int $deliveryId,
        string $status,
        ?string $note = null,
        ?string $actorType = null,
        ?int $actorId = null
    ): void {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO delivery_logs (delivery_id, status, note, actor_type, actor_id)
             VALUES (:delivery_id, :status, :note, :actor_type, :actor_id)'
        );
        $stmt->execute([
            'delivery_id' => $deliveryId,
            'status' => $status,
            'note' => $note,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listByDelivery(int $deliveryId): array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT * FROM delivery_logs WHERE delivery_id = :delivery_id ORDER BY id DESC LIMIT 200'
        );
        $stmt->execute(['delivery_id' => $deliveryId]);
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listLatestByDelivery(int $deliveryId, int $limit = 10): array
    {
        $safeLimit = max(1, min(100, $limit));
        $sql = sprintf(
            'SELECT * FROM delivery_logs WHERE delivery_id = :delivery_id ORDER BY id DESC LIMIT %d',
            $safeLimit
        );

        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute(['delivery_id' => $deliveryId]);
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }
}
