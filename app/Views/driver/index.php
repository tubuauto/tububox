<?php declare(strict_types=1); ?>
<h1>Driver H5</h1>

<?php if ($driver === null): ?>
    <div class="alert alert-error">Current account is not bound to a driver profile.</div>
<?php else: ?>
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
                    <td><a class="btn btn-light" href="/driver/deliveries/<?= h($item['id']) ?>">Open</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

