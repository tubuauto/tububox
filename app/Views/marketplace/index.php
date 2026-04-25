<?php declare(strict_types=1); ?>
<div class="row-between">
    <h1>My Marketplace Orders</h1>
    <a class="btn" href="/marketplace/orders/create">Create Order</a>
</div>

<?php if (is_array($flash ?? null)): ?>
    <div class="alert <?= (($flash['type'] ?? '') === 'error') ? 'alert-error' : 'alert-success' ?>">
        <?= h($flash['message'] ?? '') ?>
    </div>
<?php endif; ?>

<form method="get" action="/marketplace/orders" class="filter-row">
    <select name="view">
        <option value="">All Orders</option>
        <option value="in_progress" <?= (($query['view'] ?? '') === 'in_progress') ? 'selected' : '' ?>>In Progress</option>
        <option value="completed" <?= (($query['view'] ?? '') === 'completed') ? 'selected' : '' ?>>Completed</option>
        <option value="cancelled" <?= (($query['view'] ?? '') === 'cancelled') ? 'selected' : '' ?>>Cancelled/Failed</option>
    </select>
    <input type="text" name="source_order_no" placeholder="source_order_no" value="<?= h($query['source_order_no'] ?? '') ?>">
    <select name="status">
        <option value="">All Status</option>
        <?php foreach (['awaiting_payment','pending','dispatch_pending','assigned','driver_arriving_pickup','picked_up','in_transit','arrived','signed','completed','failed','cancelled'] as $status): ?>
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
                <th>Store</th>
                <th>Source</th>
                <th>Dropoff</th>
                <th>Status</th>
                <th>Payment</th>
                <th>Created</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($items as $item): ?>
            <tr>
                <td><a href="/marketplace/orders/<?= h($item['id']) ?>">#<?= h($item['id']) ?></a></td>
                <td><?= h($item['store_name'] ?: '-') ?></td>
                <td><?= h($item['source_order_no'] ?: '-') ?></td>
                <td><?= h($item['dropoff_address']) ?></td>
                <td><span class="badge"><?= h($item['status']) ?></span></td>
                <td><?= h($item['payment_status'] ?? '-') ?></td>
                <td><?= h($item['created_at']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

