<?php

declare(strict_types=1);

namespace App\Repositories;

final class ProofOfDeliveryRepository extends BaseRepository
{
    public function create(
        int $deliveryId,
        ?string $receiverName,
        ?string $proofImage,
        ?string $note
    ): void {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO proof_of_delivery (delivery_id, receiver_name, proof_type, proof_image, note)
             VALUES (:delivery_id, :receiver_name, :proof_type, :proof_image, :note)'
        );
        $stmt->execute([
            'delivery_id' => $deliveryId,
            'receiver_name' => $receiverName,
            'proof_type' => 'signature',
            'proof_image' => $proofImage,
            'note' => $note,
        ]);
    }
}

