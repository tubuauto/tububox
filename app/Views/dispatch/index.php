<?php declare(strict_types=1); ?>
<h1>Dispatch Console</h1>

<div class="card">
    <h3>Assign Delivery</h3>
    <form method="post" action="/dispatch/assign" class="filter-row">
        <select name="delivery_id" required>
            <option value="">Select pending delivery</option>
            <?php foreach ($pending as $item): ?>
                <option value="<?= h($item['id']) ?>">#<?= h($item['id']) ?> - <?= h($item['pickup_address']) ?> → <?= h($item['dropoff_address']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="driver_id" required>
            <option value="">Select rider</option>
            <?php foreach ($drivers as $driver): ?>
                <option value="<?= h($driver['id']) ?>">#<?= h($driver['id']) ?> <?= h($driver['user_name'] ?? 'Rider') ?></option>
            <?php endforeach; ?>
        </select>
        <input type="text" name="note" placeholder="note">
        <button class="btn" type="submit">Assign</button>
    </form>
</div>

<div class="card">
    <h3>Reassign Delivery</h3>
    <form method="post" action="/dispatch/reassign" class="filter-row">
        <select name="delivery_id" required>
            <option value="">Select assigned delivery</option>
            <?php foreach ($assigned as $item): ?>
                <option value="<?= h($item['id']) ?>">#<?= h($item['id']) ?> - Rider #<?= h($item['assigned_driver_id'] ?: '-') ?></option>
            <?php endforeach; ?>
        </select>
        <select name="driver_id" required>
            <option value="">Select new rider</option>
            <?php foreach ($drivers as $driver): ?>
                <option value="<?= h($driver['id']) ?>">#<?= h($driver['id']) ?> <?= h($driver['user_name'] ?? 'Rider') ?></option>
            <?php endforeach; ?>
        </select>
        <input type="text" name="note" placeholder="reassign note">
        <button class="btn btn-light" type="submit">Reassign</button>
    </form>
</div>

<div class="card">
    <h3>Mark Delivery Failed</h3>
    <form method="post" action="/dispatch/mark-failed" class="filter-row">
        <select name="delivery_id" required>
            <option value="">Select delivery</option>
            <?php foreach (array_merge($pending, $assigned) as $item): ?>
                <option value="<?= h($item['id']) ?>">#<?= h($item['id']) ?> - <?= h($item['status']) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="text" name="reason" placeholder="failure reason" required>
        <button class="btn btn-danger" type="submit">Mark Failed</button>
    </form>
</div>

<div class="card table-wrap">
    <h3>Assigned Deliveries</h3>
    <table>
        <thead><tr><th>ID</th><th>Status</th><th>Rider</th><th>Pickup</th><th>Dropoff</th></tr></thead>
        <tbody>
        <?php foreach ($assigned as $item): ?>
            <tr>
                <td>#<?= h($item['id']) ?></td>
                <td><span class="badge"><?= h($item['status']) ?></span></td>
                <td><?= h($item['assigned_driver_id'] ?: '-') ?></td>
                <td><?= h($item['pickup_address']) ?></td>
                <td><?= h($item['dropoff_address']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="card table-wrap">
    <h3>Failed Deliveries</h3>
    <table>
        <thead><tr><th>ID</th><th>Status</th><th>Reason Time</th><th>Pickup</th><th>Dropoff</th></tr></thead>
        <tbody>
        <?php foreach ($failed as $item): ?>
            <tr>
                <td>#<?= h($item['id']) ?></td>
                <td><span class="badge"><?= h($item['status']) ?></span></td>
                <td><?= h($item['failed_at'] ?: '-') ?></td>
                <td><?= h($item['pickup_address']) ?></td>
                <td><?= h($item['dropoff_address']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
