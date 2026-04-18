<?php

declare(strict_types=1);

namespace App\Repositories;

final class LoginAttemptRepository extends BaseRepository
{
    public function record(string $identifier, string $ip, ?string $userAgent, bool $success): void
    {
        $stmt = $this->pdo()->prepare(
            'INSERT INTO login_attempts (identifier, ip, user_agent, success)
             VALUES (:identifier, :ip, :user_agent, :success)'
        );
        $stmt->execute([
            'identifier' => $identifier,
            'ip' => $ip,
            'user_agent' => $userAgent,
            'success' => $success ? 'true' : 'false',
        ]);
    }

    public function failedCountByIdentifier(string $identifier, int $windowMinutes = 15): int
    {
        $stmt = $this->pdo()->prepare(
            'SELECT COUNT(*) AS total
             FROM login_attempts
             WHERE identifier = :identifier
               AND success = false
               AND attempted_at >= (CURRENT_TIMESTAMP - (:window || \' minutes\')::interval)'
        );
        $stmt->execute([
            'identifier' => $identifier,
            'window' => (string) $windowMinutes,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function failedCountByIp(string $ip, int $windowMinutes = 15): int
    {
        $stmt = $this->pdo()->prepare(
            'SELECT COUNT(*) AS total
             FROM login_attempts
             WHERE ip = :ip
               AND success = false
               AND attempted_at >= (CURRENT_TIMESTAMP - (:window || \' minutes\')::interval)'
        );
        $stmt->execute([
            'ip' => $ip,
            'window' => (string) $windowMinutes,
        ]);

        return (int) $stmt->fetchColumn();
    }
}

