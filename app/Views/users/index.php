<?php declare(strict_types=1); ?>
<h1>User Management</h1>

<?php if (is_array($flash ?? null)): ?>
    <div class="alert <?= (($flash['type'] ?? '') === 'error') ? 'alert-error' : 'alert-success' ?>">
        <?= h($flash['message'] ?? '') ?>
    </div>
<?php endif; ?>

<div class="card">
    <h3>Create User</h3>
    <form method="post" action="/users/create" class="form-grid">
        <div class="card-grid two">
            <input type="text" name="name" placeholder="Full name" required>
            <input type="email" name="email" placeholder="Email" required>
        </div>
        <div class="card-grid two">
            <input type="text" name="phone" placeholder="Phone">
            <input type="password" name="password" placeholder="Password (>=6 chars)" required>
        </div>
        <div class="card-grid two">
            <select name="role" required>
                <option value="">Select role</option>
                <option value="merchant">merchant</option>
                <option value="operator">operator</option>
                <option value="rider">rider</option>
                <option value="user">user</option>
            </select>
            <select name="organization_id">
                <option value="">No organization</option>
                <?php foreach ($organizations as $org): ?>
                    <option value="<?= h($org['id']) ?>"><?= h($org['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn" type="submit">Create User</button>
    </form>
</div>

<div class="card table-wrap">
    <h3>Tenant Users</h3>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Organization</th>
                <th>Status</th>
                <th>Created</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($items as $item): ?>
            <tr>
                <td>#<?= h($item['id']) ?></td>
                <td><?= h($item['name']) ?></td>
                <td><?= h($item['email'] ?: '-') ?></td>
                <td><span class="badge"><?= h($item['role']) ?></span></td>
                <td><?= h($item['organization_name'] ?: '-') ?></td>
                <td><?= h($item['status']) ?></td>
                <td><?= h($item['created_at']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
