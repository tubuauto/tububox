<?php

declare(strict_types=1);

namespace App\Policies;

final class DeliveryPolicy
{
    public function __construct(private readonly TenantPolicy $tenantPolicy)
    {
    }

    /**
     * @param array<string, mixed> $auth
     * @param array<string, mixed> $delivery
     */
    public function canView(array $auth, array $delivery): bool
    {
        return $this->tenantPolicy->canAccessTenant($auth, (int) $delivery['tenant_id']);
    }
}
