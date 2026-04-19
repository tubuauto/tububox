<?php declare(strict_types=1); ?>
<h1>Organizations</h1>

<?php if (is_array($flash ?? null)): ?>
    <div class="alert <?= (($flash['type'] ?? '') === 'error') ? 'alert-error' : 'alert-success' ?>">
        <?= h($flash['message'] ?? '') ?>
    </div>
<?php endif; ?>

<div class="card">
    <h3>Create Organization / Store</h3>
    <form method="post" action="/organizations/create" class="form-grid">
        <input type="text" name="name" placeholder="Organization name" required>
        <input type="text" name="type" placeholder="Type (store / warehouse)">
        <textarea name="address" placeholder="Address"></textarea>
        <div class="card-grid two">
            <input type="text" name="lat" placeholder="Latitude">
            <input type="text" name="lng" placeholder="Longitude">
        </div>
        <button class="btn" type="submit">Create Organization</button>
    </form>
</div>

<div class="card table-wrap">
    <h3>Organization List</h3>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Type</th>
                <th>Address</th>
                <th>Created</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($items as $item): ?>
            <tr>
                <td>#<?= h($item['id']) ?></td>
                <td><?= h($item['name']) ?></td>
                <td><?= h($item['type'] ?: '-') ?></td>
                <td><?= h($item['address'] ?: '-') ?></td>
                <td><?= h($item['created_at']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

