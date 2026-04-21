<?php declare(strict_types=1); ?>
<h1>Rider H5</h1>

<?php if ($driver === null && !($is_admin ?? false)): ?>
    <div class="alert alert-error">Current account is not bound to a rider profile.</div>
<?php else: ?>
    <?php if (($is_admin ?? false) && $driver === null): ?>
        <div class="alert alert-success">Admin mode: showing assigned deliveries for fulfillment verification.</div>
    <?php endif; ?>
    <?php if (!empty($grab_pool ?? [])): ?>
        <h3>Grab Pool (Paid Marketplace Orders)</h3>
        <div class="card table-wrap">
            <table>
                <thead><tr><th>ID</th><th>Pickup</th><th>Dropoff</th><th>Fee</th><th></th></tr></thead>
                <tbody>
                <?php foreach (($grab_pool ?? []) as $item): ?>
                    <tr>
                        <td>#<?= h($item['id']) ?></td>
                        <td><?= h($item['pickup_address']) ?></td>
                        <td><?= h($item['dropoff_address']) ?></td>
                        <td><?= h($item['quote_fee_cents'] ?: $item['delivery_fee_cents']) ?> <?= h($item['quote_currency'] ?: 'CAD') ?></td>
                        <td>
                            <form method="post" action="/rider/deliveries/<?= h($item['id']) ?>/claim">
                                <button class="btn" type="submit">Claim</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <h3>My Assigned Deliveries</h3>
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
