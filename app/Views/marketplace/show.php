<?php declare(strict_types=1); ?>
<div class="row-between">
    <h1>Marketplace Order #<?= h($delivery['id']) ?></h1>
    <span class="badge"><?= h($delivery['status']) ?></span>
</div>

<?php if (is_array($flash ?? null)): ?>
    <div class="alert <?= (($flash['type'] ?? '') === 'error') ? 'alert-error' : 'alert-success' ?>">
        <?= h($flash['message'] ?? '') ?>
    </div>
<?php endif; ?>

<?php if ((string) ($delivery['status'] ?? '') === 'awaiting_payment'): ?>
    <form method="post" action="/marketplace/orders/<?= h($delivery['id']) ?>/pay" class="card form-grid">
        <h3>Pay And Publish To Grab Pool</h3>
        <p class="muted">Quote: <?= h($delivery['quote_fee_cents'] ?: $delivery['delivery_fee_cents']) ?> <?= h($delivery['quote_currency'] ?: 'CAD') ?></p>
        <input type="text" name="payment_method" placeholder="payment method (wallet/card)" value="wallet">
        <input type="text" name="payment_reference" placeholder="payment reference (optional)">
        <button class="btn" type="submit">Pay Now</button>
    </form>
<?php endif; ?>

<?php if (in_array((string) ($delivery['status'] ?? ''), ['awaiting_payment', 'pending', 'assigned'], true)): ?>
    <form method="post" action="/marketplace/orders/<?= h($delivery['id']) ?>/cancel" class="card form-grid">
        <h3>Cancel Order</h3>
        <input type="text" name="reason" placeholder="cancel reason (optional)">
        <button class="btn btn-danger" type="submit">Cancel Order</button>
    </form>
<?php endif; ?>

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
    <p>quote_fee_cents: <?= h($delivery['quote_fee_cents'] ?? '-') ?> <?= h($delivery['quote_currency'] ?? 'CAD') ?></p>
    <p>quote_distance_km: <?= h($delivery['quote_distance_km'] ?? '-') ?></p>
    <p>quote_status: <?= h($delivery['quote_status'] ?? '-') ?></p>
    <p>payment_status: <?= h($delivery['payment_status'] ?? '-') ?></p>
    <p>payment_amount_cents: <?= h($delivery['payment_amount_cents'] ?? '-') ?></p>
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

<div class="card table-wrap">
    <h3>Fulfillment Timeline</h3>
    <table>
        <thead><tr><th>ID</th><th>Status</th><th>Note</th><th>Actor</th><th>At</th></tr></thead>
        <tbody>
        <?php $timeline = array_reverse($logs ?? []); ?>
        <?php if (empty($timeline)): ?>
            <tr><td colspan="5" class="muted">No timeline records.</td></tr>
        <?php else: ?>
            <?php foreach ($timeline as $log): ?>
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
    <h3>Rider Tracking</h3>
    <?php $latestPoint = !empty($tracking ?? []) ? $tracking[0] : null; ?>
    <?php if (is_array($latestPoint)): ?>
        <p class="muted">
            Latest: <?= h((string) $latestPoint['lat']) ?>, <?= h((string) $latestPoint['lng']) ?>
            <a href="https://www.openstreetmap.org/?mlat=<?= h((string) $latestPoint['lat']) ?>&mlon=<?= h((string) $latestPoint['lng']) ?>#map=16/<?= h((string) $latestPoint['lat']) ?>/<?= h((string) $latestPoint['lng']) ?>" target="_blank" rel="noopener noreferrer">Open Map</a>
        </p>
    <?php endif; ?>
    <table>
        <thead><tr><th>ID</th><th>Rider</th><th>Lat</th><th>Lng</th><th>At</th></tr></thead>
        <tbody>
        <?php if (empty($tracking ?? [])): ?>
            <tr><td colspan="5" class="muted">No tracking points yet.</td></tr>
        <?php else: ?>
            <?php foreach (($tracking ?? []) as $point): ?>
                <tr>
                    <td><?= h($point['id']) ?></td>
                    <td><?= h($point['driver_id'] ?: '-') ?></td>
                    <td><?= h($point['lat']) ?></td>
                    <td><?= h($point['lng']) ?></td>
                    <td><?= h($point['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

