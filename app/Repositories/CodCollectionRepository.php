<?php

declare(strict_types=1);

namespace App\Repositories;

final class CodCollectionRepository extends BaseRepository
{
    public function create(
        int $deliveryId,
        int $expectedAmountCents,
        int $collectedAmountCents,
        ?string $method,
        ?string $proofImage,
        ?int $driverId,
        ?string $note
    ): void {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO cod_collections (
                delivery_id, expected_amount_cents, collected_amount_cents, method,
                proof_image, status, collected_by_driver_id, collected_at, note
            ) VALUES (
                :delivery_id, :expected_amount_cents, :collected_amount_cents, :method,
                :proof_image, :status, :collected_by_driver_id, CURRENT_TIMESTAMP, :note
            )'
        );
        $stmt->execute([
            'delivery_id' => $deliveryId,
            'expected_amount_cents' => $expectedAmountCents,
            'collected_amount_cents' => $collectedAmountCents,
            'method' => $method,
            'proof_image' => $proofImage,
            'status' => $expectedAmountCents === $collectedAmountCents ? 'collected' : 'failed',
            'collected_by_driver_id' => $driverId,
            'note' => $note,
        ]);
    }
}

