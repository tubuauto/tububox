<?php

declare(strict_types=1);

use App\Repositories\Database;

require_once __DIR__ . '/../bootstrap/autoload.php';
require_once __DIR__ . '/../app/Core/helpers.php';

$envPath = __DIR__ . '/../.env';
if (is_file($envPath)) {
    $envVars = parse_ini_file($envPath, false, INI_SCANNER_TYPED);
    if (is_array($envVars)) {
        foreach ($envVars as $key => $value) {
            $stringValue = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
            $_ENV[$key] = $stringValue;
            $_SERVER[$key] = $stringValue;
            if (function_exists('putenv')) {
                putenv(sprintf('%s=%s', $key, $stringValue));
            }
        }
    }
}

$databaseConfig = require __DIR__ . '/../config/database.php';
$db = new Database($databaseConfig);
$pdo = $db->connection();

$pdo->beginTransaction();
try {
    $tenantId = findOrCreateTenant($pdo, 'Demo Tenant');
    $organizationId = findOrCreateOrganization($pdo, $tenantId, 'Main Store');
    findOrCreateOrganization($pdo, $tenantId, 'Downtown Store');

    findOrCreateUser($pdo, [
        'tenant_id' => $tenantId,
        'organization_id' => $organizationId,
        'role' => 'admin',
        'name' => 'Platform Admin',
        'phone' => '10000000000',
        'email' => 'admin@tububox.local',
        'password_hash' => password_hash('admin123', PASSWORD_BCRYPT),
    ]);

    findOrCreateUser($pdo, [
        'tenant_id' => $tenantId,
        'organization_id' => $organizationId,
        'role' => 'merchant',
        'name' => 'Merchant Owner',
        'phone' => '10000000009',
        'email' => 'merchant@tububox.local',
        'password_hash' => password_hash('admin123', PASSWORD_BCRYPT),
    ]);

    findOrCreateUser($pdo, [
        'tenant_id' => $tenantId,
        'organization_id' => $organizationId,
        'role' => 'operator',
        'name' => 'Operator User',
        'phone' => '10000000001',
        'email' => 'operator@tububox.local',
        'password_hash' => password_hash('admin123', PASSWORD_BCRYPT),
    ]);

    $driverUserId = findOrCreateUser($pdo, [
        'tenant_id' => $tenantId,
        'organization_id' => $organizationId,
        'role' => 'rider',
        'name' => 'Rider User',
        'phone' => '10000000002',
        'email' => 'rider@tububox.local',
        'password_hash' => password_hash('admin123', PASSWORD_BCRYPT),
    ]);

    findOrCreateUser($pdo, [
        'tenant_id' => null,
        'organization_id' => null,
        'role' => 'user',
        'name' => 'Marketplace User',
        'phone' => '10000000003',
        'email' => 'user@tububox.local',
        'password_hash' => password_hash('admin123', PASSWORD_BCRYPT),
    ]);

    findOrCreateDriver($pdo, $driverUserId, $tenantId);
    findOrCreateApiKey($pdo, $tenantId, 'demo_key', 'demo_secret');

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}

echo "Seed finished.\n";
echo "Web login: admin@tububox.local / admin123\n";
echo "Merchant: merchant@tububox.local / admin123\n";
echo "Operator: operator@tububox.local / admin123\n";
echo "Rider: rider@tububox.local / admin123\n";
echo "Marketplace User: user@tububox.local / admin123\n";
echo "API key: demo_key / demo_secret\n";

function findOrCreateTenant(PDO $pdo, string $name): int
{
    $stmt = $pdo->prepare('SELECT id FROM tenants WHERE name = :name LIMIT 1');
    $stmt->execute(['name' => $name]);
    $row = $stmt->fetch();
    if (is_array($row)) {
        return (int) $row['id'];
    }

    $insert = $pdo->prepare(
        'INSERT INTO tenants (name, type, status) VALUES (:name, :type, :status) RETURNING id'
    );
    $insert->execute(['name' => $name, 'type' => 'merchant', 'status' => 'active']);
    return (int) $insert->fetchColumn();
}

function findOrCreateOrganization(PDO $pdo, int $tenantId, string $name): int
{
    $stmt = $pdo->prepare(
        'SELECT id FROM organizations WHERE tenant_id = :tenant_id AND name = :name LIMIT 1'
    );
    $stmt->execute(['tenant_id' => $tenantId, 'name' => $name]);
    $row = $stmt->fetch();
    if (is_array($row)) {
        return (int) $row['id'];
    }

    $insert = $pdo->prepare(
        'INSERT INTO organizations (tenant_id, name, type, address) VALUES (:tenant_id, :name, :type, :address) RETURNING id'
    );
    $insert->execute([
        'tenant_id' => $tenantId,
        'name' => $name,
        'type' => 'store',
        'address' => 'Richmond, BC',
    ]);
    return (int) $insert->fetchColumn();
}

/**
 * @param array<string, mixed> $payload
 */
function findOrCreateUser(PDO $pdo, array $payload): int
{
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $payload['email']]);
    $row = $stmt->fetch();
    if (is_array($row)) {
        return (int) $row['id'];
    }

    $insert = $pdo->prepare(
        'INSERT INTO users (tenant_id, organization_id, role, name, phone, email, password_hash, status)
         VALUES (:tenant_id, :organization_id, :role, :name, :phone, :email, :password_hash, :status)
         RETURNING id'
    );
    $insert->execute([
        'tenant_id' => $payload['tenant_id'],
        'organization_id' => $payload['organization_id'],
        'role' => $payload['role'],
        'name' => $payload['name'],
        'phone' => $payload['phone'],
        'email' => $payload['email'],
        'password_hash' => $payload['password_hash'],
        'status' => 'active',
    ]);

    return (int) $insert->fetchColumn();
}

function findOrCreateDriver(PDO $pdo, int $userId, int $tenantId): int
{
    $stmt = $pdo->prepare('SELECT id FROM drivers WHERE user_id = :user_id LIMIT 1');
    $stmt->execute(['user_id' => $userId]);
    $row = $stmt->fetch();
    if (is_array($row)) {
        return (int) $row['id'];
    }

    $insert = $pdo->prepare(
        'INSERT INTO drivers (user_id, tenant_id, vehicle_type, license_plate, status, online_status)
         VALUES (:user_id, :tenant_id, :vehicle_type, :license_plate, :status, :online_status)
         RETURNING id'
    );
    $insert->execute([
        'user_id' => $userId,
        'tenant_id' => $tenantId,
        'vehicle_type' => 'bike',
        'license_plate' => 'N/A',
        'status' => 'active',
        'online_status' => true,
    ]);
    return (int) $insert->fetchColumn();
}

function findOrCreateApiKey(PDO $pdo, int $tenantId, string $apiKey, string $apiSecret): int
{
    $stmt = $pdo->prepare('SELECT id FROM api_keys WHERE api_key = :api_key LIMIT 1');
    $stmt->execute(['api_key' => $apiKey]);
    $row = $stmt->fetch();
    if (is_array($row)) {
        return (int) $row['id'];
    }

    $insert = $pdo->prepare(
        'INSERT INTO api_keys (tenant_id, api_key, api_secret, status)
         VALUES (:tenant_id, :api_key, :api_secret, :status) RETURNING id'
    );
    $insert->execute([
        'tenant_id' => $tenantId,
        'api_key' => $apiKey,
        'api_secret' => $apiSecret,
        'status' => 'active',
    ]);
    return (int) $insert->fetchColumn();
}
