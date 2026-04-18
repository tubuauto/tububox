<?php

declare(strict_types=1);

namespace App\Repositories;

final class AuditLogRepository extends BaseRepository
{
    /**
     * @param array<string, mixed> $payload
     */
    public function create(array $payload): void
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO audit_logs (
                tenant_id, actor_user_id, actor_role, action, target_type, target_id,
                ip, user_agent, metadata
             ) VALUES (
                :tenant_id, :actor_user_id, :actor_role, :action, :target_type, :target_id,
                :ip, :user_agent, :metadata
             )'
        );
        $stmt->execute([
            'tenant_id' => $payload['tenant_id'] ?? null,
            'actor_user_id' => $payload['actor_user_id'] ?? null,
            'actor_role' => $payload['actor_role'] ?? null,
            'action' => $payload['action'],
            'target_type' => $payload['target_type'] ?? null,
            'target_id' => $payload['target_id'] ?? null,
            'ip' => $payload['ip'] ?? null,
            'user_agent' => $payload['user_agent'] ?? null,
            'metadata' => json_encode($payload['metadata'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listRecent(?int $tenantId, bool $isAdmin, int $limit = 200): array
    {
        $safeLimit = max(1, min(500, $limit));
        $sql = 'SELECT * FROM audit_logs';
        $params = [];

        if (!$isAdmin && $tenantId !== null) {
            $sql .= ' WHERE tenant_id = :tenant_id';
            $params['tenant_id'] = $tenantId;
        } elseif (!$isAdmin && $tenantId === null) {
            return [];
        }

        $sql .= sprintf(' ORDER BY id DESC LIMIT %d', $safeLimit);

        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }
}

