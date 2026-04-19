<?php declare(strict_types=1); ?>
<h1>Rider H5</h1>

<?php if ($driver === null && !($is_admin ?? false)): ?>
    <div class="alert alert-error">Current account is not bound to a rider profile.</div>
<?php else: ?>
    <?php if (($is_admin ?? false) && $driver === null): ?>
        <div class="alert alert-success">Admin mode: showing assigned deliveries for fulfillment verification.</div>
    <?php endif; ?>
    <div class="card table-wrap">
        <table>
            <thead><tr><th>ID</th><th>Status</th><th>Pickup</th><th>Dropoff</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td>#<?= h($item['id']) ?></td>
                    <td><span class="badge"><?= h($item['status']) ?></span></td>
                    <td><?= h($item['pickup_address']) ?></td>
                    <td><?= h($item['dropoff_address']) ?></td>
                    <td><a class="btn btn-light" href="/rider/deliveries/<?= h($item['id']) ?>">Open</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
