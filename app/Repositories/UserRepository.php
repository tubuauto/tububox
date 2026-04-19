<?php

declare(strict_types=1);

namespace App\Repositories;

final class UserRepository extends BaseRepository
{
    /**
     * @return array<string, mixed>|null
     */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listByTenant(int $tenantId): array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT u.*, o.name AS organization_name
             FROM users u
             LEFT JOIN organizations o ON o.id = u.organization_id
             WHERE u.tenant_id = :tenant_id
             ORDER BY u.id DESC'
        );
        $stmt->execute(['tenant_id' => $tenantId]);
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    public function existsByEmail(string $email): bool
    {
        $stmt = $this->pdo()->prepare('SELECT 1 FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function create(array $payload): array
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO users (
                tenant_id, organization_id, role, name, phone, email, password_hash, status
            ) VALUES (
                :tenant_id, :organization_id, :role, :name, :phone, :email, :password_hash, :status
            )
            RETURNING *'
        );
        $stmt->execute([
            'tenant_id' => $payload['tenant_id'] ?? null,
            'organization_id' => $payload['organization_id'] ?? null,
            'role' => $payload['role'],
            'name' => $payload['name'],
            'phone' => $payload['phone'] ?? null,
            'email' => $payload['email'] ?? null,
            'password_hash' => $payload['password_hash'],
            'status' => $payload['status'] ?? 'active',
        ]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : [];
    }
}
