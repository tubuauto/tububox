<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ForbiddenException;
use App\Exceptions\ValidationException;
use App\Repositories\DriverRepository;
use App\Repositories\OrganizationRepository;
use App\Repositories\UserRepository;

final class TenantUserService
{
    /**
     * @var array<int, string>
     */
    private const ALLOWED_ROLES = ['tenant_admin', 'operator', 'dispatcher', 'driver'];

    public function __construct(
        private readonly UserRepository $users,
        private readonly OrganizationRepository $organizations,
        private readonly DriverRepository $drivers,
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

        return $this->users->listByTenant($tenantId);
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

        $email = strtolower(trim((string) $payload['email']));
        if ($this->users->existsByEmail($email)) {
            throw new ValidationException(['email' => 'Email already exists.']);
        }

        $organizationId = $this->organizationIdOrFail($tenantId, $payload['organization_id'] ?? null);
        $role = trim((string) $payload['role']);

        $created = $this->users->create([
            'tenant_id' => $tenantId,
            'organization_id' => $organizationId,
            'role' => $role,
            'name' => trim((string) $payload['name']),
            'phone' => $this->nullableTrim($payload['phone'] ?? null),
            'email' => $email,
            'password_hash' => password_hash((string) $payload['password'], PASSWORD_BCRYPT),
            'status' => 'active',
        ]);

        if ($role === 'driver') {
            $driver = $this->drivers->findByUserId((int) $created['id']);
            if ($driver === null) {
                $this->drivers->createForUser((int) $created['id'], $tenantId);
            }
        }

        $this->audit->record($auth, 'user.create', 'user', (int) ($created['id'] ?? 0), [
            'role' => $role,
            'email' => $email,
        ]);

        return $created;
    }

    /**
     * @param array<string, mixed> $auth
     * @return array<int, array<string, mixed>>
     */
    public function organizations(array $auth): array
    {
        $tenantId = $this->tenantIdOrFail($auth);

        return $this->organizations->listByTenant($tenantId);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, string>
     */
    private function validateCreate(array $payload): array
    {
        $errors = [];
        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            $errors['name'] = 'Name is required.';
        }

        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        if ($email === '') {
            $errors['email'] = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email format is invalid.';
        }

        $password = (string) ($payload['password'] ?? '');
        if (strlen($password) < 6) {
            $errors['password'] = 'Password must be at least 6 characters.';
        }

        $role = trim((string) ($payload['role'] ?? ''));
        if (!in_array($role, self::ALLOWED_ROLES, true)) {
            $errors['role'] = 'Role is invalid.';
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

    private function organizationIdOrFail(int $tenantId, mixed $organizationId): ?int
    {
        if ($organizationId === null || trim((string) $organizationId) === '') {
            return null;
        }

        $id = (int) $organizationId;
        if ($id <= 0) {
            throw new ValidationException(['organization_id' => 'Organization is invalid.']);
        }

        $organization = $this->organizations->findById($id);
        if ($organization === null || (int) ($organization['tenant_id'] ?? 0) !== $tenantId) {
            throw new ValidationException(['organization_id' => 'Organization is out of tenant scope.']);
        }

        return $id;
    }

    private function nullableTrim(mixed $value): ?string
    {
        $string = trim((string) $value);
        return $string === '' ? null : $string;
    }
}

