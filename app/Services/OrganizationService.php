<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ForbiddenException;
use App\Exceptions\ValidationException;
use App\Repositories\OrganizationRepository;

final class OrganizationService
{
    public function __construct(
        private readonly OrganizationRepository $organizations,
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

        return $this->organizations->listByTenant($tenantId);
    }

    /**
     * @param array<string, mixed> $auth
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function create(array $auth, array $payload): array
    {
        $tenantId = $this->tenantIdOrFail($auth);
        $errors = $this->validateCreate($payload);
        if (count($errors) > 0) {
            throw new ValidationException($errors);
        }

        $created = $this->organizations->create([
            'tenant_id' => $tenantId,
            'name' => trim((string) $payload['name']),
            'type' => $this->nullableTrim($payload['type'] ?? null),
            'address' => $this->nullableTrim($payload['address'] ?? null),
            'lat' => $this->nullableNumber($payload['lat'] ?? null),
            'lng' => $this->nullableNumber($payload['lng'] ?? null),
        ]);

        $this->audit->record($auth, 'organization.create', 'organization', (int) ($created['id'] ?? 0), [
            'name' => $created['name'] ?? null,
        ]);

        return $created;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, string>
     */
    private function validateCreate(array $payload): array
    {
        $errors = [];

        if (trim((string) ($payload['name'] ?? '')) === '') {
            $errors['name'] = 'Organization name is required.';
        }

        $lat = $this->nullableNumber($payload['lat'] ?? null);
        if ($lat !== null && ($lat < -90 || $lat > 90)) {
            $errors['lat'] = 'Latitude must be between -90 and 90.';
        }

        $lng = $this->nullableNumber($payload['lng'] ?? null);
        if ($lng !== null && ($lng < -180 || $lng > 180)) {
            $errors['lng'] = 'Longitude must be between -180 and 180.';
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $auth
     */
    private function tenantIdOrFail(array $auth): int
    {
        $tenantId = $auth['tenant_id'] ?? null;
        if ($tenantId === null) {
            throw new ForbiddenException('Tenant scope is required.');
        }

        return (int) $tenantId;
    }

    private function nullableTrim(mixed $value): ?string
    {
        $string = trim((string) $value);
        return $string === '' ? null : $string;
    }

    private function nullableNumber(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);
        if ($string === '') {
            return null;
        }

        return (float) $string;
    }
}

