<?php declare(strict_types=1); ?>
<div class="row-between">
    <h1>Marketplace Order #<?= h($delivery['id']) ?></h1>
    <span class="badge"><?= h($delivery['status']) ?></span>
</div>

<div class="card-grid two">
    <div class="card">
        <h3>Store Pickup</h3>
        <p><?= h($delivery['store_name'] ?: '-') ?></p>
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
    <p>source_order_no: <?= h($delivery['source_order_no'] ?: '-') ?></p>
    <p>external_ref: <?= h($delivery['external_ref'] ?: '-') ?></p>
    <p>delivery_fee_cents: <?= h($delivery['delivery_fee_cents']) ?></p>
    <p>cod_status: <?= h($delivery['cod_status']) ?></p>
</div>

<div class="card table-wrap">
    <h3>Latest 10 Fulfillment Logs</h3>
    <table>
        <thead><tr><th>ID</th><th>Status</th><th>Note</th><th>At</th></tr></thead>
        <tbody>
        <?php if (empty($latest_logs ?? [])): ?>
            <tr><td colspan="4" class="muted">No logs yet.</td></tr>
        <?php else: ?>
            <?php foreach (($latest_logs ?? []) as $log): ?>
                <tr>
                    <td><?= h($log['id']) ?></td>
                    <td><?= h($log['status']) ?></td>
                    <td><?= h($log['note']) ?></td>
                    <td><?= h($log['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

