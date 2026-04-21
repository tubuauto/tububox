<?php declare(strict_types=1); ?>
<h1>Rider Delivery #<?= h($delivery['id']) ?></h1>

<?php if (is_array($flash ?? null)): ?>
    <div class="alert <?= (($flash['type'] ?? '') === 'error') ? 'alert-error' : 'alert-success' ?>">
        <?= h($flash['message'] ?? '') ?>
    </div>
<?php endif; ?>

<div class="card">
    <p>Status: <span class="badge"><?= h($delivery['status']) ?></span></p>
    <p class="muted">For grab orders: claim first, then continue with pickup and delivery actions.</p>
    <p>Pickup: <?= h($delivery['pickup_address']) ?></p>
    <p>Dropoff: <?= h($delivery['dropoff_address']) ?></p>
</div>

<?php $canClaim = ((string) ($delivery['status'] ?? '') === 'pending') && (int) ($delivery['assigned_driver_id'] ?? 0) <= 0 && (string) ($delivery['payment_status'] ?? '') === 'paid'; ?>
<?php if ($canClaim): ?>
    <form method="post" action="/rider/deliveries/<?= h($delivery['id']) ?>/claim" class="card">
        <button class="btn" type="submit">Claim This Order</button>
    </form>
<?php endif; ?>

<div class="card-grid two">
    <form method="post" action="/rider/deliveries/<?= h($delivery['id']) ?>/accept" class="card"><button class="btn btn-light" type="submit">Accept</button></form>
    <form method="post" action="/rider/deliveries/<?= h($delivery['id']) ?>/arrive-pickup" class="card"><button class="btn btn-light" type="submit">Arrive Pickup</button></form>
    <form method="post" action="/rider/deliveries/<?= h($delivery['id']) ?>/pickup" class="card">
        <input type="text" name="note" placeholder="pickup note">
        <button class="btn btn-light" type="submit">Confirm Pickup</button>
    </form>
    <form method="post" action="/rider/deliveries/<?= h($delivery['id']) ?>/arrive-dropoff" class="card"><button class="btn btn-light" type="submit">Arrive Dropoff</button></form>
    <form method="post" action="/rider/deliveries/<?= h($delivery['id']) ?>/sign" class="card">
        <input type="text" name="receiver_name" placeholder="receiver name">
        <input type="text" name="proof_image" placeholder="proof image path">
        <button class="btn btn-light" type="submit">Sign</button>
    </form>
    <form method="post" action="/rider/deliveries/<?= h($delivery['id']) ?>/complete" class="card"><button class="btn btn-light" type="submit">Complete</button></form>
</div>

<div class="card">
    <h3>COD Collect</h3>
    <form method="post" action="/rider/deliveries/<?= h($delivery['id']) ?>/cod-collect" class="form-grid">
        <input type="number" name="expected_amount_cents" placeholder="expected amount cents" required>
        <input type="number" name="collected_amount_cents" placeholder="collected amount cents" required>
        <input type="text" name="method" placeholder="method (cash)">
        <input type="text" name="proof_image" placeholder="proof image path">
        <button class="btn" type="submit">Submit COD</button>
    </form>
</div>

<div class="card table-wrap">
    <h3>Fulfillment Logs</h3>
    <table>
        <thead><tr><th>ID</th><th>Status</th><th>Note</th><th>Actor</th><th>At</th></tr></thead>
        <tbody>
        <?php foreach (($logs ?? []) as $log): ?>
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
