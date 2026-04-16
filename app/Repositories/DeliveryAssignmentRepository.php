<?php

declare(strict_types=1);

namespace App\Repositories;

final class DeliveryAssignmentRepository extends BaseRepository
{
    public function create(int $deliveryId, int $driverId, ?int $assignedBy = null, ?string $note = null): void
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO delivery_assignments (delivery_id, driver_id, assigned_by, note)
             VALUES (:delivery_id, :driver_id, :assigned_by, :note)'
        );
        $stmt->execute([
            'delivery_id' => $deliveryId,
            'driver_id' => $driverId,
            'assigned_by' => $assignedBy,
            'note' => $note,
        ]);
    }
}

