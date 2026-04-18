<?php declare(strict_types=1); ?>
<h1>Operation Audit Logs</h1>

<div class="card table-wrap">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Time</th>
                <th>Action</th>
                <th>Actor</th>
                <th>Target</th>
                <th>IP</th>
                <th>Metadata</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($items as $item): ?>
            <tr>
                <td>#<?= h($item['id']) ?></td>
                <td><?= h($item['created_at']) ?></td>
                <td><span class="badge"><?= h($item['action']) ?></span></td>
                <td><?= h($item['actor_role']) ?> #<?= h($item['actor_user_id']) ?></td>
                <td><?= h($item['target_type']) ?> #<?= h($item['target_id']) ?></td>
                <td><?= h($item['ip']) ?></td>
                <td><code><?= h((string) ($item['metadata'] ?? '{}')) ?></code></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

