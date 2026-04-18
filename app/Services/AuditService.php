<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AuditLogRepository;
use Throwable;

final class AuditService
{
    public function __construct(private readonly AuditLogRepository $auditLogs)
    {
    }

    /**
     * @param array<string, mixed>|null $auth
     * @param array<string, mixed> $metadata
     */
    public function record(
        ?array $auth,
        string $action,
        ?string $targetType = null,
        ?int $targetId = null,
        array $metadata = []
    ): void {
        try {
            $this->auditLogs->create([
                'tenant_id' => is_array($auth) ? ($auth['tenant_id'] ?? null) : null,
                'actor_user_id' => is_array($auth) ? ($auth['id'] ?? null) : null,
                'actor_role' => is_array($auth) ? ($auth['role'] ?? null) : null,
                'action' => $action,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'ip' => $this->clientIp(),
                'user_agent' => $this->userAgent(),
                'metadata' => $metadata,
            ]);
        } catch (Throwable) {
            // Keep business flow available even when audit table is not ready.
        }
    }

    private function clientIp(): string
    {
        return (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    private function userAgent(): string
    {
        return (string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown-agent');
    }
}
