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
            'requester_user_id' => $this->resolveRequesterUserId($auth, $sourceType),
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
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function listMarketplaceForUser(array $auth, array $filters = []): array
    {
        $view = strtolower(trim((string) ($filters['view'] ?? '')));
        unset($filters['view']);

        if (($auth['is_admin'] ?? false) === true) {
            $filters['source_type'] = 'marketplace';
            $items = $this->deliveries->list($filters, null, true);
            return $this->applyMarketplaceViewFilter($items, $view);
        }

        $role = (string) ($auth['role'] ?? '');
        $userId = (int) ($auth['id'] ?? 0);
        if ($role !== 'user' || $userId <= 0) {
            throw new ForbiddenException('Only marketplace user can access marketplace orders.');
        }

        $filters['source_type'] = 'marketplace';
        $filters['requester_user_id'] = $userId;
        $items = $this->deliveries->list($filters, null, true);
        return $this->applyMarketplaceViewFilter($items, $view);
    }

    /**
     * @param array<string, mixed> $auth
     * @return array<string, mixed>
     */
    public function getMarketplaceForUserOrFail(array $auth, int $deliveryId): array
    {
        $delivery = $this->deliveries->findById($deliveryId);
        if ($delivery === null) {
            throw new NotFoundException('Delivery not found.');
        }

        if ((string) ($delivery['source_type'] ?? '') !== 'marketplace') {
            throw new ForbiddenException('This order is not a marketplace order.');
        }

        if (($auth['is_admin'] ?? false) === true) {
            return $delivery;
        }

        $role = (string) ($auth['role'] ?? '');
        $userId = (int) ($auth['id'] ?? 0);
        if ($role !== 'user' || $userId <= 0) {
            throw new ForbiddenException('Only marketplace user can access marketplace orders.');
        }

        if ((int) ($delivery['requester_user_id'] ?? 0) !== $userId) {
            throw new ForbiddenException('No permission to access this order.');
        }

        return $delivery;
    }

    /**
     * @param array<string, mixed> $auth
     * @return array<string, mixed>
     */
    public function cancelMarketplaceForUser(array $auth, int $deliveryId, ?string $reason = null): array
    {
        $delivery = $this->getMarketplaceForUserOrFail($auth, $deliveryId);
        $status = (string) ($delivery['status'] ?? '');
        if (!in_array($status, [DeliveryStatus::PENDING, DeliveryStatus::ASSIGNED], true)) {
            throw new BadRequestException(
                'Marketplace order can only be cancelled in pending or assigned status.',
                ['status' => 'Only pending/assigned orders can be cancelled.']
            );
        }

        $this->deliveries->updateStatus($deliveryId, DeliveryStatus::CANCELLED);
        $updated = $this->deliveries->findById($deliveryId);
        if ($updated === null) {
            throw new NotFoundException('Delivery not found after cancellation.');
        }

        $this->deliveryLogs->create(
            deliveryId: $deliveryId,
            status: DeliveryStatus::CANCELLED,
            note: $reason !== null && trim($reason) !== '' ? $reason : 'Cancelled by marketplace user',
            actorType: (string) ($auth['role'] ?? 'user'),
            actorId: isset($auth['id']) ? (int) $auth['id'] : null
        );

        $this->webhooks->dispatchDeliveryStatus($updated);

        return $updated;
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

    /**
     * @param array<string, mixed> $auth
     */
    private function resolveRequesterUserId(array $auth, string $sourceType): ?int
    {
        if ($sourceType !== 'marketplace') {
            return null;
        }

        $role = (string) ($auth['role'] ?? '');
        if ($role !== 'user') {
            return null;
        }

        $userId = (int) ($auth['id'] ?? 0);
        return $userId > 0 ? $userId : null;
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

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function applyMarketplaceViewFilter(array $items, string $view): array
    {
        if ($view === '') {
            return $items;
        }

        $allowed = match ($view) {
            'in_progress' => [
                DeliveryStatus::PENDING,
                DeliveryStatus::ASSIGNED,
                DeliveryStatus::DRIVER_ARRIVING_PICKUP,
                DeliveryStatus::PICKED_UP,
                DeliveryStatus::IN_TRANSIT,
                DeliveryStatus::ARRIVED,
                DeliveryStatus::SIGNED,
            ],
            'completed' => [DeliveryStatus::COMPLETED],
            'cancelled' => [DeliveryStatus::CANCELLED, DeliveryStatus::FAILED],
            default => [],
        };

        if (count($allowed) === 0) {
            return $items;
        }

        $filtered = array_values(array_filter($items, static fn (array $item): bool => in_array((string) ($item['status'] ?? ''), $allowed, true)));

        return $filtered;
    }
}
