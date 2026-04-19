<?php declare(strict_types=1); ?>
<div class="row-between">
    <h1>Deliveries</h1>
    <a class="btn" href="/deliveries/create">Create Delivery</a>
</div>

<form method="get" action="/deliveries" class="filter-row">
    <input type="text" name="source_order_no" placeholder="source_order_no" value="<?= h($query['source_order_no'] ?? '') ?>">
    <select name="source_type">
        <option value="">All Sources</option>
        <?php foreach (['marketplace', 'merchant_dashboard', 'merchant_api'] as $sourceType): ?>
            <option value="<?= h($sourceType) ?>" <?= (($query['source_type'] ?? '') === $sourceType) ? 'selected' : '' ?>><?= h($sourceType) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="status">
        <option value="">All Status</option>
        <?php foreach (['pending','assigned','driver_arriving_pickup','picked_up','in_transit','arrived','signed','completed','failed','cancelled'] as $status): ?>
            <option value="<?= h($status) ?>" <?= (($query['status'] ?? '') === $status) ? 'selected' : '' ?>><?= h($status) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn btn-light" type="submit">Filter</button>
</form>

<div class="card table-wrap">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Source</th>
                <th>Store</th>
                <th>Pickup</th>
                <th>Dropoff</th>
                <th>Status</th>
                <th>Rider</th>
                <th>Created</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($items as $item): ?>
            <tr>
                <td><a href="/deliveries/<?= h($item['id']) ?>">#<?= h($item['id']) ?></a></td>
                <td><?= h(($item['source_type'] ?? '-') . ' / ' . ($item['source_order_no'] ?: '-')) ?></td>
                <td><?= h($item['store_name'] ?: '-') ?></td>
                <td><?= h($item['pickup_address']) ?></td>
                <td><?= h($item['dropoff_address']) ?></td>
                <td><span class="badge"><?= h($item['status']) ?></span></td>
                <td><?= h($item['assigned_driver_id'] ?: '-') ?></td>
                <td><?= h($item['created_at']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
