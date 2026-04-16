<?php declare(strict_types=1); ?>
<h1>Webhook Endpoints</h1>

<?php if (is_array($flash)): ?>
    <div class="alert <?= ($flash['type'] ?? '') === 'error' ? 'alert-error' : 'alert-success' ?>">
        <?= h($flash['message'] ?? '') ?>
    </div>
<?php endif; ?>

<div class="card">
    <h3>Create Endpoint</h3>
    <form method="post" action="/webhooks" class="filter-row">
        <input type="url" name="url" placeholder="https://example.com/webhook" required>
        <select name="event" required>
            <?php foreach ($events as $event): ?>
                <option value="<?= h($event) ?>"><?= h($event) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="status">
            <option value="active">active</option>
            <option value="inactive">inactive</option>
        </select>
        <input type="text" name="secret" placeholder="optional signing secret">
        <button class="btn" type="submit">Add Endpoint</button>
    </form>
</div>

<div class="card table-wrap">
    <h3>Configured Endpoints</h3>
    <table>
        <thead>
        <tr><th>ID</th><th>Event</th><th>URL</th><th>Status</th><th>Secret</th><th>Actions</th></tr>
        </thead>
        <tbody>
        <?php foreach ($items as $item): ?>
            <tr>
                <td>#<?= h($item['id']) ?></td>
                <td><?= h($item['event']) ?></td>
                <td><?= h($item['url']) ?></td>
                <td><span class="badge"><?= h($item['status']) ?></span></td>
                <td><?= empty($item['secret']) ? '-' : '******' ?></td>
                <td>
                    <details>
                        <summary>Edit</summary>
                        <form method="post" action="/webhooks/<?= h($item['id']) ?>/update" class="form-grid inline-form">
                            <input type="url" name="url" value="<?= h($item['url']) ?>" required>
                            <select name="event" required>
                                <?php foreach ($events as $event): ?>
                                    <option value="<?= h($event) ?>" <?= $item['event'] === $event ? 'selected' : '' ?>><?= h($event) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select name="status">
                                <option value="active" <?= $item['status'] === 'active' ? 'selected' : '' ?>>active</option>
                                <option value="inactive" <?= $item['status'] === 'inactive' ? 'selected' : '' ?>>inactive</option>
                            </select>
                            <input type="text" name="secret" value="<?= h((string) ($item['secret'] ?? '')) ?>" placeholder="optional signing secret">
                            <button class="btn btn-light" type="submit">Save</button>
                        </form>
                    </details>
                    <form method="post" action="/webhooks/<?= h($item['id']) ?>/delete" class="inline-form">
                        <button class="btn btn-danger" type="submit">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

