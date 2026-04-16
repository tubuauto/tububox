<?php

declare(strict_types=1);

namespace App\Repositories;

final class DeliveryTrackingRepository extends BaseRepository
{
    public function create(int $deliveryId, ?int $driverId, float $lat, float $lng): void
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO delivery_tracking (delivery_id, driver_id, lat, lng)
             VALUES (:delivery_id, :driver_id, :lat, :lng)'
        );
        $stmt->execute([
            'delivery_id' => $deliveryId,
            'driver_id' => $driverId,
            'lat' => $lat,
            'lng' => $lng,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listByDelivery(int $deliveryId): array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT * FROM delivery_tracking WHERE delivery_id = :delivery_id ORDER BY id DESC LIMIT 500'
        );
        $stmt->execute(['delivery_id' => $deliveryId]);
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }
}

