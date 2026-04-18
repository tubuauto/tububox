<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ForbiddenException;
use App\Exceptions\NotFoundException;
use App\Repositories\ApiKeyRepository;

final class ApiKeyService
{
    public function __construct(
        private readonly ApiKeyRepository $apiKeys,
        private readonly AuditService $audit
    ) {
    }

    /**
     * @param array<string, mixed> $auth
     * @return array<int, array<string, mixed>>
     */
    public function list(array $auth): array
    {
        $tenantId = $this->tenantIdOrFail($auth);
        return $this->apiKeys->listByTenant($tenantId);
    }

    /**
     * @param array<string, mixed> $auth
     * @return array<string, mixed>
     */
    public function create(array $auth): array
    {
        $tenantId = $this->tenantIdOrFail($auth);
        $apiKey = 'tbx_' . bin2hex(random_bytes(8));
        $apiSecret = bin2hex(random_bytes(24));

        $created = $this->apiKeys->create($tenantId, $apiKey, $apiSecret);
        $this->audit->record($auth, 'apikey.create', 'api_key', (int) $created['id'], [
            'api_key' => $apiKey,
        ]);

        return $created;
    }

    /**
     * @param array<string, mixed> $auth
     */
    public function disable(array $auth, int $id): void
    {
        $row = $this->apiKeys->findById($id);
        if ($row === null) {
            throw new NotFoundException('API key not found.');
        }

        $tenantId = $this->tenantIdOrFail($auth);
        if (($auth['is_admin'] ?? false) !== true && (int) $row['tenant_id'] !== $tenantId) {
            throw new ForbiddenException('No permission to disable this API key.');
        }

        $this->apiKeys->disable($id);
        $this->audit->record($auth, 'apikey.disable', 'api_key', $id);
    }

    /**
     * @param array<string, mixed> $auth
     * @return array<string, mixed>
     */
    public function rotate(array $auth, int $id): array
    {
        $row = $this->apiKeys->findById($id);
        if ($row === null) {
            throw new NotFoundException('API key not found.');
        }

        $tenantId = $this->tenantIdOrFail($auth);
        if (($auth['is_admin'] ?? false) !== true && (int) $row['tenant_id'] !== $tenantId) {
            throw new ForbiddenException('No permission to rotate this API key.');
        }

        $this->apiKeys->disable($id);
        $newKey = $this->create([
            'id' => $auth['id'] ?? null,
            'tenant_id' => (int) $row['tenant_id'],
            'role' => $auth['role'] ?? null,
            'is_admin' => $auth['is_admin'] ?? false,
        ]);

        $this->audit->record($auth, 'apikey.rotate', 'api_key', (int) $newKey['id'], [
            'rotated_from_id' => $id,
            'new_api_key' => $newKey['api_key'] ?? null,
        ]);

        return $newKey;
    }

    /**
     * @param array<string, mixed> $auth
     */
    private function tenantIdOrFail(array $auth): int
    {
        $tenantId = $auth['tenant_id'] ?? null;
        if ($tenantId === null) {
            throw new ForbiddenException('Tenant scope is required for API key management.');
        }

        return (int) $tenantId;
    }
}

