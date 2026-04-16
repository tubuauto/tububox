<?php

declare(strict_types=1);

namespace App\Repositories;

final class ApiKeyRepository extends BaseRepository
{
    /**
     * @return array<string, mixed>|null
     */
    public function findActive(string $key, string $secret): ?array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT * FROM api_keys WHERE api_key = :api_key AND api_secret = :api_secret AND status = :status LIMIT 1'
        );
        $stmt->execute([
            'api_key' => $key,
            'api_secret' => $secret,
            'status' => 'active',
        ]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }
}

