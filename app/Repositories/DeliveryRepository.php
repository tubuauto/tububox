<?php

declare(strict_types=1);

namespace App\Repositories;

use DateTimeImmutable;

final class DeliveryRepository extends BaseRepository
{
    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function create(array $payload): array
    {
        $sql = <<<'SQL'
            INSERT INTO deliveries (
                tenant_id,
                store_id,
                source_type, source_platform, source_order_no, external_ref, idempotency_key,
                sender_name, sender_phone, pickup_address, pickup_lat, pickup_lng,
                recipient_name, recipient_phone, dropoff_address, dropoff_lat, dropoff_lng,
                goods_type, goods_weight, goods_note,
                delivery_fee_cents,
                cod_required, cod_amount_cents, cod_currency, cod_status,
                status, scheduled_at
            ) VALUES (
                :tenant_id,
                :store_id,
                :source_type, :source_platform, :source_order_no, :external_ref, :idempotency_key,
                :sender_name, :sender_phone, :pickup_address, :pickup_lat, :pickup_lng,
                :recipient_name, :recipient_phone, :dropoff_address, :dropoff_lat, :dropoff_lng,
                :goods_type, :goods_weight, :goods_note,
                :delivery_fee_cents,
                :cod_required, :cod_amount_cents, :cod_currency, :cod_status,
                :status, :scheduled_at
            ) RETURNING *
        SQL;

        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute([
            'tenant_id' => $payload['tenant_id'],
            'store_id' => $payload['store_id'] ?? null,
            'source_type' => $payload['source_type'] ?? 'merchant_dashboard',
            'source_platform' => $payload['source_platform'] ?? null,
            'source_order_no' => $payload['source_order_no'] ?? null,
            'external_ref' => $payload['external_ref'] ?? null,
            'idempotency_key' => $payload['idempotency_key'] ?? null,
            'sender_name' => $payload['sender_name'],
            'sender_phone' => $payload['sender_phone'] ?? null,
            'pickup_address' => $payload['pickup_address'],
            'pickup_lat' => $payload['pickup_lat'] ?? null,
            'pickup_lng' => $payload['pickup_lng'] ?? null,
            'recipient_name' => $payload['recipient_name'],
            'recipient_phone' => $payload['recipient_phone'] ?? null,
            'dropoff_address' => $payload['dropoff_address'],
            'dropoff_lat' => $payload['dropoff_lat'] ?? null,
            'dropoff_lng' => $payload['dropoff_lng'] ?? null,
            'goods_type' => $payload['goods_type'] ?? null,
            'goods_weight' => $payload['goods_weight'] ?? null,
            'goods_note' => $payload['goods_note'] ?? null,
            'delivery_fee_cents' => $payload['delivery_fee_cents'] ?? 0,
            'cod_required' => $this->toPgBoolean($payload['cod_required'] ?? false),
            'cod_amount_cents' => $payload['cod_amount_cents'] ?? 0,
            'cod_currency' => $payload['cod_currency'] ?? 'CAD',
            'cod_status' => $payload['cod_status'] ?? 'none',
            'status' => $payload['status'] ?? 'pending',
            'scheduled_at' => $payload['scheduled_at'] ?? null,
        ]);

        $row = $stmt->fetch();
        return is_array($row) ? $row : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $deliveryId): ?array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT d.*, o.name AS store_name
             FROM deliveries d
             LEFT JOIN organizations o ON o.id = d.store_id
             WHERE d.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $deliveryId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByTenantAndIdempotency(int $tenantId, string $idempotencyKey): ?array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT d.*, o.name AS store_name
             FROM deliveries d
             LEFT JOIN organizations o ON o.id = d.store_id
             WHERE d.tenant_id = :tenant_id AND d.idempotency_key = :idempotency_key
             LIMIT 1'
        );
        $stmt->execute([
            'tenant_id' => $tenantId,
            'idempotency_key' => $idempotencyKey,
        ]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function list(array $filters, ?int $tenantId, bool $isAdmin = false): array
    {
        $where = [];
        $params = [];

        if (!$isAdmin && $tenantId === null) {
            return [];
        }

        if (!$isAdmin && $tenantId !== null) {
            $where[] = 'd.tenant_id = :tenant_id';
            $params['tenant_id'] = $tenantId;
        }

        if (!empty($filters['status'])) {
            $where[] = 'd.status = :status';
            $params['status'] = (string) $filters['status'];
        }

        if (!empty($filters['source_order_no'])) {
            $where[] = 'd.source_order_no = :source_order_no';
            $params['source_order_no'] = (string) $filters['source_order_no'];
        }

        if (!empty($filters['source_type'])) {
            $where[] = 'd.source_type = :source_type';
            $params['source_type'] = (string) $filters['source_type'];
        }

        if (!empty($filters['assigned_driver_id'])) {
            $where[] = 'd.assigned_driver_id = :assigned_driver_id';
            $params['assigned_driver_id'] = (int) $filters['assigned_driver_id'];
        }

        if (!empty($filters['store_id'])) {
            $where[] = 'd.store_id = :store_id';
            $params['store_id'] = (int) $filters['store_id'];
        }

        $sql = 'SELECT d.*, o.name AS store_name FROM deliveries d LEFT JOIN organizations o ON o.id = d.store_id';
        if (count($where) > 0) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY d.created_at DESC LIMIT 200';

        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    public function updateStatus(int $deliveryId, string $status): void
    {
        $now = new DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $timestamp = $now->format('Y-m-d H:i:s');

        $columns = ['status = :status', 'updated_at = :updated_at'];
        $params = [
            'id' => $deliveryId,
            'status' => $status,
            'updated_at' => $timestamp,
        ];

        if ($status === 'picked_up') {
            $columns[] = 'picked_up_at = :picked_up_at';
            $params['picked_up_at'] = $timestamp;
        }

        if ($status === 'completed') {
            $columns[] = 'completed_at = :completed_at';
            $params['completed_at'] = $timestamp;
        }

        if ($status === 'failed') {
            $columns[] = 'failed_at = :failed_at';
            $params['failed_at'] = $timestamp;
        }

        if ($status === 'cancelled') {
            $columns[] = 'cancelled_at = :cancelled_at';
            $params['cancelled_at'] = $timestamp;
        }

        $sql = 'UPDATE deliveries SET ' . implode(', ', $columns) . ' WHERE id = :id';
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);
    }

    public function assignDriver(int $deliveryId, int $driverId): void
    {
        $stmt = $this->pdo()->prepare(
            'UPDATE deliveries
             SET assigned_driver_id = :driver_id, status = :status, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $deliveryId,
            'driver_id' => $driverId,
            'status' => 'assigned',
        ]);
    }

    public function updateCodStatus(int $deliveryId, string $codStatus): void
    {
        $stmt = $this->pdo()->prepare(
            'UPDATE deliveries SET cod_status = :cod_status, updated_at = CURRENT_TIMESTAMP WHERE id = :id'
        );
        $stmt->execute([
            'id' => $deliveryId,
            'cod_status' => $codStatus,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listByDriver(int $driverId): array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT d.*, o.name AS store_name
             FROM deliveries d
             LEFT JOIN organizations o ON o.id = d.store_id
             WHERE d.assigned_driver_id = :driver_id
             ORDER BY d.created_at DESC
             LIMIT 200'
        );
        $stmt->execute(['driver_id' => $driverId]);
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    private function toPgBoolean(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        $normalized = strtolower(trim((string) $value));
        return in_array($normalized, ['1', 'true', 'on', 'yes'], true) ? 'true' : 'false';
    }
}
