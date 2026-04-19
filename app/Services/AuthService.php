<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\UserRepository;

final class AuthService
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function attempt(string $email, string $password): ?array
    {
        $user = $this->users->findByEmail($email);
        if ($user === null) {
            return null;
        }

        $hash = (string) ($user['password_hash'] ?? '');
        if ($hash === '' || !password_verify($password, $hash)) {
            return null;
        }

        return $this->normalizeSessionUser($user);
    }

    /**
     * @param array<string, mixed> $sessionUser
     */
    public function login(array $sessionUser): void
    {
        session_regenerate_id(true);
        $_SESSION['user'] = $sessionUser;
        $_SESSION['auth_fingerprint'] = $this->fingerprint();
        $_SESSION['auth_issued_at'] = time();
        $_SESSION['auth_last_seen_at'] = time();
    }

    public function logout(): void
    {
        unset($_SESSION['user']);
        unset($_SESSION['auth_fingerprint'], $_SESSION['auth_issued_at'], $_SESSION['auth_last_seen_at']);
        session_regenerate_id(true);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function user(): ?array
    {
        $user = $_SESSION['user'] ?? null;
        if (!is_array($user)) {
            return null;
        }

        $storedFingerprint = (string) ($_SESSION['auth_fingerprint'] ?? '');
        if ($storedFingerprint === '' || !hash_equals($storedFingerprint, $this->fingerprint())) {
            $this->logout();
            return null;
        }

        $lastSeen = (int) ($_SESSION['auth_last_seen_at'] ?? 0);
        $maxIdle = 60 * 60 * 12;
        if ($lastSeen > 0 && (time() - $lastSeen) > $maxIdle) {
            $this->logout();
            return null;
        }

        $_SESSION['auth_last_seen_at'] = time();
        return $user;
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private function normalizeSessionUser(array $user): array
    {
        $role = $this->normalizeRoleLabel((string) ($user['role'] ?? 'operator'));
        return [
            'id' => (int) $user['id'],
            'tenant_id' => $user['tenant_id'] !== null ? (int) $user['tenant_id'] : null,
            'organization_id' => $user['organization_id'] !== null ? (int) $user['organization_id'] : null,
            'role' => $role,
            'name' => (string) ($user['name'] ?? ''),
            'email' => (string) ($user['email'] ?? ''),
            'is_admin' => $role === 'admin',
        ];
    }

    private function normalizeRoleLabel(string $role): string
    {
        $normalized = strtolower(trim($role));
        return match ($normalized) {
            'tenant_admin' => 'merchant',
            'dispatcher' => 'operator',
            'driver' => 'rider',
            'api_partner' => 'merchant',
            default => $normalized === '' ? 'operator' : $normalized,
        };
    }

    private function fingerprint(): string
    {
        $userAgent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown-agent');
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        $secret = (string) env('APP_SECRET', 'change-me');

        return hash('sha256', $userAgent . '|' . $ip . '|' . $secret);
    }
}
