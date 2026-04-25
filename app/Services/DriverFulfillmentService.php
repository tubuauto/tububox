<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\DeliveryStatus;
use App\Exceptions\BadRequestException;
use App\Exceptions\ForbiddenException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Repositories\CodCollectionRepository;
use App\Repositories\DeliveryAssignmentRepository;
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
        private readonly DeliveryAssignmentRepository $assignments,
        private readonly ProofOfDeliveryRepository $proofs,
        private readonly CodCollectionRepository $codCollections,
        private readonly WebhookService $webhooks
    ) {
    }

    /**
     * @param array<string, mixed> $auth
     * @return array<int, array<string, mixed>>
     */
    public function listGrabPool(array $auth): array
    {
        $driver = $this->drivers->findByUserId((int) ($auth['id'] ?? 0));
        if ($driver === null) {
            if (($auth['is_admin'] ?? false) === true) {
                return [];
            }

            throw new NotFoundException('Current user is not a rider.');
        }

        $tenantId = (int) ($driver['tenant_id'] ?? 0);
        if ($tenantId <= 0) {
            throw new ForbiddenException('Current rider has no tenant scope.');
        }

        return $this->deliveries->listGrabPool($tenantId);
    }

    /**
     * @param array<string, mixed> $auth
     * @return array<string, mixed>
     */
    public function claim(array $auth, int $deliveryId): array
    {
        $delivery = $this->deliveryService->getOrFail($auth, $deliveryId);
        $driver = $this->drivers->findByUserId((int) ($auth['id'] ?? 0));
        if ($driver === null) {
            throw new NotFoundException('Current user is not a rider.');
        }

        $driverId = (int) $driver['id'];
        if ((string) ($delivery['source_type'] ?? '') !== 'marketplace') {
            throw new BadRequestException('Only marketplace orders support rider claim.');
        }

        if ((string) ($delivery['status'] ?? '') !== DeliveryStatus::PENDING) {
            throw new BadRequestException('Only pending orders can be claimed.');
        }

        if ((string) ($delivery['payment_status'] ?? '') !== 'paid') {
            throw new BadRequestException('Order payment is not completed.');
        }

        if ((int) ($delivery['assigned_driver_id'] ?? 0) > 0) {
            throw new BadRequestException('Order already assigned.');
        }

        $claimed = $this->deliveries->claimByDriver($deliveryId, $driverId);
        if (!$claimed) {
            throw new BadRequestException('Order already claimed by another rider.');
        }

        $this->assignments->create($deliveryId, $driverId, isset($auth['id']) ? (int) $auth['id'] : null, 'Claimed by rider');
        $this->deliveryLogs->create(
            deliveryId: $deliveryId,
            status: DeliveryStatus::ASSIGNED,
            note: 'Rider claimed order from grab pool',
            actorType: (string) ($auth['role'] ?? 'rider'),
            actorId: isset($auth['id']) ? (int) $auth['id'] : null
        );

        $updated = $this->deliveries->findById($deliveryId);
        if ($updated === null) {
            throw new NotFoundException('Delivery not found after claim.');
        }

        $this->webhooks->dispatchDeliveryStatus($updated);
        return $updated;
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
     * @return array<string, mixed>
     */
    public function returnToDispatch(array $auth, int $deliveryId, ?string $reason = null): array
    {
        $delivery = $this->deliveryService->getOrFail($auth, $deliveryId);
        $this->ensureDriverAssignment($auth, $delivery);

        $status = (string) ($delivery['status'] ?? '');
        if ($status !== DeliveryStatus::ARRIVED) {
            throw new BadRequestException('Return to dispatch is only allowed at arrived status.');
        }

        $this->deliveries->returnToDispatch($deliveryId);
        $updated = $this->deliveries->findById($deliveryId);
        if ($updated === null) {
            throw new NotFoundException('Delivery not found after returning to dispatch.');
        }

        $note = trim((string) $reason);
        if ($note === '') {
            $note = 'No receiver signed, returned to dispatch center';
        }

        $this->deliveryLogs->create(
            deliveryId: $deliveryId,
            status: DeliveryStatus::DISPATCH_PENDING,
            note: $note,
            actorType: (string) ($auth['role'] ?? 'rider'),
            actorId: isset($auth['id']) ? (int) $auth['id'] : null
        );

        $this->webhooks->dispatchDeliveryStatus($updated);
        return $updated;
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
