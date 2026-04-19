<?php declare(strict_types=1); ?>
<div class="row-between">
    <h1>Delivery #<?= h($delivery['id']) ?></h1>
    <span class="badge"><?= h($delivery['status']) ?></span>
</div>

<div class="card-grid two">
    <div class="card">
        <h3>Pickup</h3>
        <p><?= h($delivery['sender_name']) ?> / <?= h($delivery['sender_phone']) ?></p>
        <p><?= h($delivery['pickup_address']) ?></p>
    </div>
    <div class="card">
        <h3>Dropoff</h3>
        <p><?= h($delivery['recipient_name']) ?> / <?= h($delivery['recipient_phone']) ?></p>
        <p><?= h($delivery['dropoff_address']) ?></p>
    </div>
</div>

<div class="card">
    <h3>Meta</h3>
    <p>source_type: <?= h($delivery['source_type'] ?: '-') ?></p>
    <p>source_order_no: <?= h($delivery['source_order_no'] ?: '-') ?></p>
    <p>external_ref: <?= h($delivery['external_ref'] ?: '-') ?></p>
    <p>store: <?= h($delivery['store_name'] ?: '-') ?> (<?= h($delivery['store_id'] ?: '-') ?>)</p>
    <p>assigned_rider_id: <?= h($delivery['assigned_driver_id'] ?: '-') ?></p>
    <p>delivery_fee_cents: <?= h($delivery['delivery_fee_cents']) ?></p>
    <p>cod_status: <?= h($delivery['cod_status']) ?></p>
</div>

<div class="card table-wrap">
    <h3>Latest 10 Fulfillment Logs</h3>
    <table>
        <thead><tr><th>ID</th><th>Status</th><th>Note</th><th>Actor</th><th>At</th></tr></thead>
        <tbody>
        <?php if (empty($latest_logs ?? [])): ?>
            <tr><td colspan="5" class="muted">No logs yet.</td></tr>
        <?php else: ?>
            <?php foreach (($latest_logs ?? []) as $log): ?>
                <tr>
                    <td><?= h($log['id']) ?></td>
                    <td><?= h($log['status']) ?></td>
                    <td><?= h($log['note']) ?></td>
                    <td><?= h($log['actor_type']) ?> #<?= h($log['actor_id']) ?></td>
                    <td><?= h($log['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="card table-wrap">
    <h3>All Logs (Max 200)</h3>
    <table>
        <thead><tr><th>ID</th><th>Status</th><th>Note</th><th>Actor</th><th>At</th></tr></thead>
        <tbody>
        <?php foreach ($logs as $log): ?>
            <tr>
                <td><?= h($log['id']) ?></td>
                <td><?= h($log['status']) ?></td>
                <td><?= h($log['note']) ?></td>
                <td><?= h($log['actor_type']) ?> #<?= h($log['actor_id']) ?></td>
                <td><?= h($log['created_at']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
