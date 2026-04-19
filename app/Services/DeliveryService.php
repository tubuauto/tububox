<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\DeliveryStatus;
use App\Exceptions\BadRequestException;
use App\Exceptions\ForbiddenException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationException;
use App\Policies\DeliveryPolicy;
use App\Repositories\DeliveryLogRepository;
use App\Repositories\DeliveryRepository;
use App\Repositories\DeliveryTrackingRepository;
use App\Repositories\OrganizationRepository;
use App\Requests\DeliveryCreateRequest;

final class DeliveryService
{
    public function __construct(
        private readonly DeliveryRepository $deliveries,
        private readonly DeliveryLogRepository $deliveryLogs,
        private readonly DeliveryTrackingRepository $tracking,
        private readonly OrganizationRepository $organizations,
        private readonly DeliveryPolicy $policy,
        private readonly WebhookService $webhooks
    ) {
    }

    /**
     * @param array<string, mixed> $auth
     * @param array<string, mixed> $payload
     * @return array{delivery:array<string, mixed>,idempotent:bool}
     */
    public function create(int $tenantId, array $auth, array $payload): array
    {
        $payload = $this->normalizeSourcePayload($payload);
        $errors = DeliveryCreateRequest::validate($payload);
        if (count($errors) > 0) {
            throw new ValidationException($errors);
        }

        $idempotencyKey = (string) ($payload['idempotency_key'] ?? '');
        if ($idempotencyKey !== '') {
            $exists = $this->deliveries->findByTenantAndIdempotency($tenantId, $idempotencyKey);
            if ($exists !== null) {
                return ['delivery' => $exists, 'idempotent' => true];
            }
        }

        $pickup = is_array($payload['pickup'] ?? null) ? $payload['pickup'] : [];
        $dropoff = is_array($payload['dropoff'] ?? null) ? $payload['dropoff'] : [];
        $goods = is_array($payload['goods'] ?? null) ? $payload['goods'] : [];
        $pricing = is_array($payload['pricing'] ?? null) ? $payload['pricing'] : [];
        $cod = is_array($payload['cod'] ?? null) ? $payload['cod'] : [];
        $sourceType = strtolower(trim((string) ($payload['source_type'] ?? 'merchant_dashboard')));

        if (!$this->canCreateForTenant($auth, $tenantId, $sourceType)) {
            throw new ForbiddenException('No permission to create delivery for another tenant.');
        }

        $storeId = $this->resolveStoreId($tenantId, $payload['store_id'] ?? null);
        $delivery = $this->deliveries->create([
            'tenant_id' => $tenantId,
            'store_id' => $storeId,
            'source_type' => $sourceType,
            'source_platform' => $payload['source_platform'] ?? null,
            'source_order_no' => $payload['source_order_no'] ?? null,
            'external_ref' => $payload['external_ref'] ?? null,
            'idempotency_key' => $idempotencyKey !== '' ? $idempotencyKey : null,
            'sender_name' => (string) $pickup['name'],
            'sender_phone' => $pickup['phone'] ?? null,
            'pickup_address' => (string) $pickup['address'],
            'pickup_lat' => $pickup['lat'] ?? null,
            'pickup_lng' => $pickup['lng'] ?? null,
            'recipient_name' => (string) $dropoff['name'],
            'recipient_phone' => $dropoff['phone'] ?? null,
            'dropoff_address' => (string) $dropoff['address'],
            'dropoff_lat' => $dropoff['lat'] ?? null,
            'dropoff_lng' => $dropoff['lng'] ?? null,
            'goods_type' => $goods['type'] ?? null,
            'goods_weight' => $goods['weight'] ?? null,
            'goods_note' => $goods['note'] ?? null,
            'delivery_fee_cents' => (int) ($pricing['delivery_fee_cents'] ?? 0),
            'cod_required' => (bool) ($cod['required'] ?? false),
            'cod_amount_cents' => (int) ($cod['amount_cents'] ?? 0),
            'cod_currency' => $cod['currency'] ?? 'CAD',
            'cod_status' => (bool) ($cod['required'] ?? false) ? 'pending' : 'none',
            'status' => DeliveryStatus::PENDING,
            'scheduled_at' => $payload['scheduled_at'] ?? null,
        ]);

        $this->deliveryLogs->create(
            deliveryId: (int) $delivery['id'],
            status: DeliveryStatus::PENDING,
            note: 'Delivery created',
            actorType: (string) ($auth['role'] ?? 'system'),
            actorId: $auth['id'] ?? null
        );

        $this->webhooks->dispatchDeliveryStatus($delivery);

        return ['delivery' => $delivery, 'idempotent' => false];
    }

    /**
     * @param array<string, mixed> $auth
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function list(array $auth, array $filters): array
    {
        return $this->deliveries->list(
            filters: $filters,
            tenantId: $auth['tenant_id'] !== null ? (int) $auth['tenant_id'] : null,
            isAdmin: (bool) ($auth['is_admin'] ?? false)
        );
    }

    /**
     * @param array<string, mixed> $auth
     * @return array<string, mixed>
     */
    public function getOrFail(array $auth, int $deliveryId): array
    {
        $delivery = $this->deliveries->findById($deliveryId);
        if ($delivery === null) {
            throw new NotFoundException('Delivery not found.');
        }

        if (!$this->policy->canView($auth, $delivery)) {
            throw new ForbiddenException('No permission to access this delivery.');
        }

        return $delivery;
    }

    /**
     * @param array<string, mixed> $auth
     * @return array<string, mixed>
     */
    public function assignDriver(array $auth, int $deliveryId, int $driverId, ?string $note = null): array
    {
        $delivery = $this->getOrFail($auth, $deliveryId);

        if (!in_array($delivery['status'], [DeliveryStatus::PENDING, DeliveryStatus::ASSIGNED], true)) {
            throw new BadRequestException('Delivery cannot be assigned in current status.');
        }

        $this->deliveries->assignDriver($deliveryId, $driverId);
        $updated = $this->deliveries->findById($deliveryId);
        if ($updated === null) {
            throw new NotFoundException('Delivery not found after assigning.');
        }

        $this->deliveryLogs->create(
            deliveryId: $deliveryId,
            status: DeliveryStatus::ASSIGNED,
            note: $note ?? 'Assigned to rider #' . $driverId,
            actorType: (string) ($auth['role'] ?? 'operator'),
            actorId: $auth['id'] ?? null
        );

        $this->webhooks->dispatchDeliveryStatus($updated);
        return $updated;
    }

    /**
     * @param array<string, mixed> $auth
     * @return array<string, mixed>
     */
    public function transition(array $auth, int $deliveryId, string $toStatus, ?string $note = null): array
    {
        $delivery = $this->getOrFail($auth, $deliveryId);
        $fromStatus = (string) $delivery['status'];

        if (!DeliveryStatus::canTransition($fromStatus, $toStatus)) {
            throw new BadRequestException(
                sprintf('Invalid status transition: %s -> %s', $fromStatus, $toStatus),
                ['from_status' => $fromStatus, 'to_status' => $toStatus]
            );
        }

        $this->deliveries->updateStatus($deliveryId, $toStatus);
        $updated = $this->deliveries->findById($deliveryId);
        if ($updated === null) {
            throw new NotFoundException('Delivery not found after status update.');
        }

        $this->deliveryLogs->create(
            deliveryId: $deliveryId,
            status: $toStatus,
            note: $note,
            actorType: (string) ($auth['role'] ?? 'system'),
            actorId: $auth['id'] ?? null
        );

        $this->webhooks->dispatchDeliveryStatus($updated);
        return $updated;
    }

    /**
     * @param array<string, mixed> $auth
     */
    public function createTracking(array $auth, int $deliveryId, float $lat, float $lng): void
    {
        $delivery = $this->getOrFail($auth, $deliveryId);
        $driverId = $delivery['assigned_driver_id'] !== null ? (int) $delivery['assigned_driver_id'] : null;
        $this->tracking->create($deliveryId, $driverId, $lat, $lng);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function tracking(int $deliveryId): array
    {
        return $this->tracking->listByDelivery($deliveryId);
    }

    /**
     * @param array<string, mixed> $auth
     */
    private function canCreateForTenant(array $auth, int $tenantId, string $sourceType): bool
    {
        if (($auth['is_admin'] ?? false) === true) {
            return true;
        }

        if ($sourceType === 'marketplace' && (string) ($auth['role'] ?? '') === 'user') {
            return true;
        }

        return (int) ($auth['tenant_id'] ?? 0) === $tenantId;
    }

    private function resolveStoreId(int $tenantId, mixed $storeId): ?int
    {
        if ($storeId === null || trim((string) $storeId) === '') {
            return null;
        }

        $id = (int) $storeId;
        if ($id <= 0) {
            throw new ValidationException(['store_id' => 'store_id is invalid.']);
        }

        $store = $this->organizations->findById($id);
        if ($store === null || (int) ($store['tenant_id'] ?? 0) !== $tenantId) {
            throw new ValidationException(['store_id' => 'store_id is out of tenant scope.']);
        }

        return $id;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function normalizeSourcePayload(array $payload): array
    {
        $sourceType = strtolower(trim((string) ($payload['source_type'] ?? '')));
        $mapped = match ($sourceType) {
            'manual', 'merchant_console' => 'merchant_dashboard',
            'api' => 'merchant_api',
            'platform' => 'marketplace',
            default => $sourceType,
        };

        if ($mapped === '') {
            $mapped = 'merchant_dashboard';
        }

        $payload['source_type'] = $mapped;

        return $payload;
    }
}
