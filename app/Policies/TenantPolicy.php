<?php

declare(strict_types=1);

namespace App\Policies;

final class TenantPolicy
{
    /**
     * @param array<string, mixed> $auth
     */
    public function canAccessTenant(array $auth, ?int $targetTenantId): bool
    {
        if (($auth['is_admin'] ?? false) === true) {
            return true;
        }

        $tenantId = $auth['tenant_id'] ?? null;
        if ($tenantId === null || $targetTenantId === null) {
            return false;
        }

        return (int) $tenantId === $targetTenantId;
    }
}

