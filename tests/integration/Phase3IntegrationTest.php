<?php

declare(strict_types=1);

use App\Exceptions\BadRequestException;
use App\Exceptions\ForbiddenException;
use App\Policies\DeliveryPolicy;
use App\Policies\TenantPolicy;
use App\Repositories\Database;
use App\Repositories\DeliveryAssignmentRepository;
use App\Repositories\DeliveryLogRepository;
use App\Repositories\DeliveryRepository;
use App\Repositories\DeliveryTrackingRepository;
use App\Repositories\DriverRepository;
use App\Repositories\WebhookRepository;
use App\Services\DeliveryService;
use App\Services\DispatchService;
use App\Services\WebhookService;

require_once __DIR__ . '/../../bootstrap/autoload.php';
require_once __DIR__ . '/../../app/Core/helpers.php';

$envPath = __DIR__ . '/../../.env';
if (is_file($envPath)) {
    $envVars = parse_ini_file($envPath, false, INI_SCANNER_TYPED);
    if (is_array($envVars)) {
        foreach ($envVars as $key => $value) {
            $stringValue = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
            $_ENV[$key] = $stringValue;
            $_SERVER[$key] = $stringValue;
            putenv(sprintf('%s=%s', $key, $stringValue));
        }
    }
}

$databaseConfig = require __DIR__ . '/../../config/database.php';
$db = new Database($databaseConfig);

try {
    $pdo = $db->connection();
    $pdo->query('SELECT 1');
} catch (Throwable $e) {
    echo "[SKIP] DB connection unavailable: {$e->getMessage()}\n";
    exit(0);
}

if (!tableExists($pdo, 'deliveries')) {
    echo "[SKIP] deliveries table not found. Run migrations first.\n";
    exit(0);
}

$deliveries = new DeliveryRepository($db);
$deliveryLogs = new DeliveryLogRepository($db);
$tracking = new DeliveryTrackingRepository($db);
$drivers = new DriverRepository($db);
$assignments = new DeliveryAssignmentRepository($db);
$webhookRepo = new WebhookRepository($db);

$webhookService = new WebhookService($webhookRepo);
$deliveryService = new DeliveryService(
    $deliveries,
    $deliveryLogs,
    $tracking,
    new DeliveryPolicy(new TenantPolicy()),
    $webhookService
);
$dispatchService = new DispatchService($deliveryService, $drivers, $assignments);

$pdo->beginTransaction();
try {
    $tenantA = createTenant($pdo, 'it-tenant-a-' . uniqid());
    $tenantB = createTenant($pdo, 'it-tenant-b-' . uniqid());

    $driverUserA1 = createUser($pdo, $tenantA, 'driver', 'it-driver-a1-' . uniqid() . '@local');
    $driverA1 = createDriver($pdo, $tenantA, $driverUserA1);
    $driverUserA2 = createUser($pdo, $tenantA, 'driver', 'it-driver-a2-' . uniqid() . '@local');
    $driverA2 = createDriver($pdo, $tenantA, $driverUserA2);

    $authTenantA = [
        'id' => 10001,
        'tenant_id' => $tenantA,
        'role' => 'dispatcher',
        'is_admin' => false,
    ];
    $authTenantB = [
        'id' => 10002,
        'tenant_id' => $tenantB,
        'role' => 'dispatcher',
        'is_admin' => false,
    ];

    $payloadA = payload('it-order-a-' . uniqid(), 'it-idem-' . uniqid());
    $create1 = $deliveryService->create($tenantA, $authTenantA, $payloadA);
    $create2 = $deliveryService->create($tenantA, $authTenantA, $payloadA);
    assertTrue((int) $create1['delivery']['id'] === (int) $create2['delivery']['id'], 'Idempotency should return same delivery id');
    assertTrue($create2['idempotent'] === true, 'Second create should be idempotent');
    $deliveryAId = (int) $create1['delivery']['id'];

    $invalidTransitionCaught = false;
    try {
        $deliveryService->transition($authTenantA, $deliveryAId, 'completed', 'invalid jump');
    } catch (BadRequestException) {
        $invalidTransitionCaught = true;
    }
    assertTrue($invalidTransitionCaught, 'Invalid status transition should throw BadRequestException');

    $dispatchService->assign($authTenantA, [
        'delivery_id' => $deliveryAId,
        'driver_id' => $driverA1,
        'note' => 'initial assign',
    ]);

    $deliveryService->transition($authTenantA, $deliveryAId, 'driver_arriving_pickup');
    $deliveryService->transition($authTenantA, $deliveryAId, 'picked_up');
    $deliveryService->transition($authTenantA, $deliveryAId, 'in_transit');
    $deliveryService->transition($authTenantA, $deliveryAId, 'arrived');
    $deliveryService->transition($authTenantA, $deliveryAId, 'signed');
    $completed = $deliveryService->transition($authTenantA, $deliveryAId, 'completed');
    assertTrue((string) $completed['status'] === 'completed', 'Delivery A should reach completed status');

    $payloadB = payload('it-order-b-' . uniqid(), null);
    $createB = $deliveryService->create($tenantA, $authTenantA, $payloadB);
    $deliveryBId = (int) $createB['delivery']['id'];

    $dispatchService->assign($authTenantA, [
        'delivery_id' => $deliveryBId,
        'driver_id' => $driverA1,
        'note' => 'assign B',
    ]);
    $reassigned = $dispatchService->reassign($authTenantA, [
        'delivery_id' => $deliveryBId,
        'driver_id' => $driverA2,
        'note' => 'reassign B',
    ]);
    assertTrue((int) $reassigned['assigned_driver_id'] === $driverA2, 'Delivery B should be reassigned to driver A2');

    $failed = $dispatchService->markFailed($authTenantA, [
        'delivery_id' => $deliveryBId,
        'reason' => 'integration test mark failed',
    ]);
    assertTrue((string) $failed['status'] === 'failed', 'Delivery B should be failed');

    $isolationCaught = false;
    try {
        $deliveryService->getOrFail($authTenantB, $deliveryAId);
    } catch (ForbiddenException) {
        $isolationCaught = true;
    }
    assertTrue($isolationCaught, 'Tenant B must not access tenant A delivery');

    $endpoint = $webhookService->createEndpoint($authTenantA, [
        'url' => 'https://example.com/webhook',
        'event' => 'delivery.completed',
        'status' => 'active',
        'secret' => 'phase3-secret',
    ]);
    $endpointId = (int) $endpoint['id'];

    $endpointList = $webhookService->listEndpoints($authTenantA);
    assertTrue(count($endpointList) >= 1, 'Webhook endpoint list should include new endpoint');

    $updatedEndpoint = $webhookService->updateEndpoint($authTenantA, $endpointId, [
        'status' => 'inactive',
    ]);
    assertTrue((string) $updatedEndpoint['status'] === 'inactive', 'Webhook endpoint should be updated');

    $webhookIsolationCaught = false;
    try {
        $webhookService->deleteEndpoint($authTenantB, $endpointId);
    } catch (ForbiddenException) {
        $webhookIsolationCaught = true;
    }
    assertTrue($webhookIsolationCaught, 'Tenant B must not delete tenant A webhook endpoint');

    $webhookService->deleteEndpoint($authTenantA, $endpointId);
    echo "[PASS] Phase3 integration test passed.\n";
} catch (Throwable $e) {
    echo "[FAIL] {$e->getMessage()}\n";
    $pdo->rollBack();
    exit(1);
}

$pdo->rollBack();
exit(0);

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT EXISTS (
            SELECT 1 FROM information_schema.tables
            WHERE table_schema = :schema AND table_name = :table_name
        ) AS exists'
    );
    $stmt->execute(['schema' => 'public', 'table_name' => $table]);
    $row = $stmt->fetch();
    return is_array($row) && ((string) ($row['exists'] ?? 'f') === 't');
}

function createTenant(PDO $pdo, string $name): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO tenants (name, type, status) VALUES (:name, :type, :status) RETURNING id'
    );
    $stmt->execute([
        'name' => $name,
        'type' => 'merchant',
        'status' => 'active',
    ]);

    return (int) $stmt->fetchColumn();
}

function createUser(PDO $pdo, int $tenantId, string $role, string $email): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO users (tenant_id, role, name, email, password_hash, status)
         VALUES (:tenant_id, :role, :name, :email, :password_hash, :status)
         RETURNING id'
    );
    $stmt->execute([
        'tenant_id' => $tenantId,
        'role' => $role,
        'name' => $role . '-user',
        'email' => $email,
        'password_hash' => password_hash('integration', PASSWORD_BCRYPT),
        'status' => 'active',
    ]);

    return (int) $stmt->fetchColumn();
}

function createDriver(PDO $pdo, int $tenantId, int $userId): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO drivers (user_id, tenant_id, vehicle_type, status, online_status)
         VALUES (:user_id, :tenant_id, :vehicle_type, :status, :online_status)
         RETURNING id'
    );
    $stmt->execute([
        'user_id' => $userId,
        'tenant_id' => $tenantId,
        'vehicle_type' => 'bike',
        'status' => 'active',
        'online_status' => true,
    ]);

    return (int) $stmt->fetchColumn();
}

/**
 * @return array<string, mixed>
 */
function payload(string $sourceOrderNo, ?string $idempotencyKey): array
{
    return [
        'idempotency_key' => $idempotencyKey,
        'source_type' => 'platform',
        'source_platform' => 'integration-test',
        'source_order_no' => $sourceOrderNo,
        'external_ref' => $sourceOrderNo,
        'pickup' => [
            'name' => 'Sender',
            'phone' => '1111111111',
            'address' => 'Pickup Street 1',
            'lat' => 49.1,
            'lng' => -123.1,
        ],
        'dropoff' => [
            'name' => 'Receiver',
            'phone' => '2222222222',
            'address' => 'Dropoff Street 2',
            'lat' => 49.2,
            'lng' => -123.2,
        ],
        'goods' => [
            'type' => 'general',
            'weight' => 1.5,
        ],
        'pricing' => [
            'delivery_fee_cents' => 1000,
        ],
        'cod' => [
            'required' => false,
            'amount_cents' => 0,
            'currency' => 'CAD',
        ],
    ];
}

