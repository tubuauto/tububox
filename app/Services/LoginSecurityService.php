<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\UnauthorizedException;
use App\Repositories\LoginAttemptRepository;
use Throwable;

final class LoginSecurityService
{
    private const WINDOW_MINUTES = 15;
    private const MAX_FAILED_PER_IDENTIFIER = 5;
    private const MAX_FAILED_PER_IP = 25;

    public function __construct(
        private readonly LoginAttemptRepository $attempts,
        private readonly AuditService $audit
    ) {
    }

    public function assertAllowed(string $identifier, string $ip): void
    {
        try {
            $failedByIdentifier = $this->attempts->failedCountByIdentifier($identifier, self::WINDOW_MINUTES);
            if ($failedByIdentifier >= self::MAX_FAILED_PER_IDENTIFIER) {
                throw new UnauthorizedException(
                    sprintf('Too many failed login attempts for this account. Please retry in %d minutes.', self::WINDOW_MINUTES)
                );
            }

            $failedByIp = $this->attempts->failedCountByIp($ip, self::WINDOW_MINUTES);
            if ($failedByIp >= self::MAX_FAILED_PER_IP) {
                throw new UnauthorizedException(
                    sprintf('Too many failed login attempts from this IP. Please retry in %d minutes.', self::WINDOW_MINUTES)
                );
            }
        } catch (UnauthorizedException $e) {
            throw $e;
        } catch (Throwable $e) {
            // Keep login available if security tables are not migrated yet.
        }
    }

    /**
     * @param array<string, mixed>|null $user
     */
    public function recordResult(string $identifier, string $ip, ?string $userAgent, bool $success, ?array $user): void
    {
        try {
            $this->attempts->record($identifier, $ip, $userAgent, $success);
        } catch (Throwable $e) {
            // Keep login available if security tables are not migrated yet.
        }

        $this->audit->record(
            $user,
            $success ? 'auth.login.success' : 'auth.login.failed',
            targetType: 'user',
            targetId: is_array($user) ? (($user['id'] ?? null) !== null ? (int) $user['id'] : null) : null,
            metadata: [
                'identifier' => $identifier,
                'ip' => $ip,
            ]
        );
    }
}
