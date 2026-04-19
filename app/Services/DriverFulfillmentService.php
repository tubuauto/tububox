<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ForbiddenException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Repositories\CodCollectionRepository;
use App\Repositories\DeliveryLogRepository;
use App\Repositories\DeliveryRepository;
use App\Repositories\DriverRepository;
use App\Repositories\ProofOfDeliveryRepository;

final class DriverFulfillmentService
{
    public function __construct(
        private readonly DeliveryService $deliveryService,
        private readonly DeliveryRepository $deliveries,
        private readonly DeliveryLogRepository $deliveryLogs,
        private readonly DriverRepository $drivers,
        private readonly ProofOfDeliveryRepository $proofs,
        private readonly CodCollectionRepository $codCollections
    ) {
    }

    /**
     * @param array<string, mixed> $auth
     */
    public function accept(array $auth, int $deliveryId): array
    {
        $delivery = $this->deliveryService->getOrFail($auth, $deliveryId);
        $this->ensureDriverAssignment($auth, $delivery);

        $this->deliveryLogs->create(
            deliveryId: $deliveryId,
            status: (string) ($delivery['status'] ?? 'assigned'),
            note: 'Driver accepted delivery',
            actorType: (string) ($auth['role'] ?? 'rider'),
            actorId: isset($auth['id']) ? (int) $auth['id'] : null
        );

        return $delivery;
    }

    /**
     * @param array<string, mixed> $auth
     */
    public function arrivePickup(array $auth, int $deliveryId): array
    {
        $delivery = $this->deliveryService->getOrFail($auth, $deliveryId);
        $this->ensureDriverAssignment($auth, $delivery);
        return $this->deliveryService->transition($auth, $deliveryId, 'driver_arriving_pickup', 'Driver arrived pickup point');
    }

    /**
     * @param array<string, mixed> $auth
     */
    public function pickup(array $auth, int $deliveryId, ?string $note = null): array
    {
        $delivery = $this->deliveryService->getOrFail($auth, $deliveryId);
        $this->ensureDriverAssignment($auth, $delivery);
        return $this->deliveryService->transition($auth, $deliveryId, 'picked_up', $note ?? 'Pickup confirmed');
    }

    /**
     * @param array<string, mixed> $auth
     */
    public function arriveDropoff(array $auth, int $deliveryId): array
    {
        $delivery = $this->deliveryService->getOrFail($auth, $deliveryId);
        $this->ensureDriverAssignment($auth, $delivery);

        if ((string) $delivery['status'] === 'picked_up') {
            $this->deliveryService->transition($auth, $deliveryId, 'in_transit', 'Driver in transit');
        }

        return $this->deliveryService->transition($auth, $deliveryId, 'arrived', 'Driver arrived dropoff point');
    }

    /**
     * @param array<string, mixed> $auth
     * @param array<string, mixed> $payload
     */
    public function sign(array $auth, int $deliveryId, array $payload): array
    {
        $delivery = $this->deliveryService->getOrFail($auth, $deliveryId);
        $this->ensureDriverAssignment($auth, $delivery);

        $this->proofs->create(
            deliveryId: $deliveryId,
            receiverName: $payload['receiver_name'] ?? null,
            proofImage: $payload['proof_image'] ?? null,
            note: $payload['note'] ?? null
        );

        return $this->deliveryService->transition($auth, $deliveryId, 'signed', 'Delivery signed by receiver');
    }

    /**
     * @param array<string, mixed> $auth
     */
    public function complete(array $auth, int $deliveryId): array
    {
        $delivery = $this->deliveryService->getOrFail($auth, $deliveryId);
        $this->ensureDriverAssignment($auth, $delivery);
        return $this->deliveryService->transition($auth, $deliveryId, 'completed', 'Delivery completed');
    }

    /**
     * @param array<string, mixed> $auth
     * @param array<string, mixed> $payload
     */
    public function collectCod(array $auth, int $deliveryId, array $payload): array
    {
        $delivery = $this->deliveryService->getOrFail($auth, $deliveryId);
        $this->ensureDriverAssignment($auth, $delivery);

        $expected = (int) ($payload['expected_amount_cents'] ?? 0);
        $collected = (int) ($payload['collected_amount_cents'] ?? 0);
        if ($expected <= 0 || $collected < 0) {
            throw new ValidationException([
                'expected_amount_cents' => 'expected_amount_cents must be greater than 0.',
                'collected_amount_cents' => 'collected_amount_cents must be >= 0.',
            ], 'Invalid COD amounts.');
        }

        $driver = $this->drivers->findByUserId((int) $auth['id']);
        $this->codCollections->create(
            deliveryId: $deliveryId,
            expectedAmountCents: $expected,
            collectedAmountCents: $collected,
            method: $payload['method'] ?? null,
            proofImage: $payload['proof_image'] ?? null,
            driverId: $driver !== null ? (int) $driver['id'] : null,
            note: $payload['note'] ?? null
        );

        $this->deliveries->updateCodStatus(
            deliveryId: $deliveryId,
            codStatus: $expected === $collected ? 'collected' : 'failed'
        );

        return $this->deliveryService->getOrFail($auth, $deliveryId);
    }

    /**
     * @param array<string, mixed> $auth
     * @param array<string, mixed> $delivery
     */
    private function ensureDriverAssignment(array $auth, array $delivery): void
    {
        if (($auth['is_admin'] ?? false) === true) {
            return;
        }

        $driver = $this->drivers->findByUserId((int) ($auth['id'] ?? 0));
        if ($driver === null) {
            throw new NotFoundException('Current user is not a rider.');
        }

        if ((int) ($delivery['assigned_driver_id'] ?? 0) !== (int) $driver['id']) {
            throw new ForbiddenException('Delivery is not assigned to current rider.');
        }
    }
}
