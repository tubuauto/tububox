<?php declare(strict_types=1); ?>
<h1>API Key Management</h1>

<?php if (is_array($flash ?? null)): ?>
    <div class="alert <?= (($flash['type'] ?? '') === 'error') ? 'alert-error' : 'alert-success' ?>">
        <?= h($flash['message'] ?? '') ?>
    </div>
<?php endif; ?>

<div class="card">
    <h3>Create New API Key</h3>
    <form method="post" action="/api-keys/create">
        <button class="btn" type="submit">Generate API Key</button>
    </form>
    <p class="muted">Create a new key and secret for external systems. Save secret immediately after generation.</p>
</div>

<div class="card table-wrap">
    <h3>Tenant API Keys</h3>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>API Key</th>
                <th>Status</th>
                <th>Last Used</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($items as $item): ?>
            <tr>
                <td>#<?= h($item['id']) ?></td>
                <td><?= h($item['api_key']) ?></td>
                <td><span class="badge"><?= h($item['status']) ?></span></td>
                <td><?= h($item['last_used_at'] ?: '-') ?></td>
                <td><?= h($item['created_at']) ?></td>
                <td>
                    <?php if (($item['status'] ?? '') === 'active'): ?>
                        <form method="post" action="/api-keys/<?= h($item['id']) ?>/disable" class="inline-form">
                            <button class="btn btn-danger" type="submit">Disable</button>
                        </form>
                    <?php endif; ?>
                    <form method="post" action="/api-keys/<?= h($item['id']) ?>/rotate" class="inline-form">
                        <button class="btn btn-light" type="submit">Rotate</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

