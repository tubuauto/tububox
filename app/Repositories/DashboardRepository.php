<?php

declare(strict_types=1);

namespace App\Repositories;

final class DashboardRepository extends BaseRepository
{
    /**
     * @return array<string, int>
     */
    public function deliveryStats(?int $tenantId, bool $isAdmin): array
    {
        $where = '';
        $params = [];

        if (!$isAdmin && $tenantId !== null) {
            $where = 'WHERE tenant_id = :tenant_id';
            $params['tenant_id'] = $tenantId;
        }

        $sql = <<<SQL
            SELECT
                COUNT(*) AS total,
                COUNT(*) FILTER (WHERE status = 'pending') AS pending,
                COUNT(*) FILTER (WHERE status = 'dispatch_pending') AS dispatch_pending,
                COUNT(*) FILTER (WHERE status = 'assigned') AS assigned,
                COUNT(*) FILTER (WHERE status = 'in_transit') AS in_transit,
                COUNT(*) FILTER (WHERE status = 'completed') AS completed,
                COUNT(*) FILTER (WHERE status = 'failed') AS failed
            FROM deliveries
            {$where}
        SQL;

        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        $result = is_array($row) ? $row : [];

        return [
            'total' => (int) ($result['total'] ?? 0),
            'pending' => (int) ($result['pending'] ?? 0),
            'dispatch_pending' => (int) ($result['dispatch_pending'] ?? 0),
            'assigned' => (int) ($result['assigned'] ?? 0),
            'in_transit' => (int) ($result['in_transit'] ?? 0),
            'completed' => (int) ($result['completed'] ?? 0),
            'failed' => (int) ($result['failed'] ?? 0),
        ];
    }
}
