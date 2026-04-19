<?php declare(strict_types=1); ?>
<h1>Create Delivery</h1>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <?php foreach ($errors as $key => $message): ?>
            <div><?= h((string) $key) ?>: <?= h((string) $message) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="post" action="/deliveries" class="card form-grid">
    <h3>Source</h3>
    <input type="text" value="merchant_dashboard" disabled>
    <select name="store_id">
        <option value="">Select store (optional)</option>
        <?php foreach (($stores ?? []) as $store): ?>
            <option value="<?= h($store['id']) ?>" <?= ((string) ($old['store_id'] ?? '') === (string) $store['id']) ? 'selected' : '' ?>>
                #<?= h($store['id']) ?> <?= h($store['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <input type="text" name="source_order_no" placeholder="source_order_no" value="<?= h($old['source_order_no'] ?? '') ?>">
    <input type="text" name="external_ref" placeholder="external_ref" value="<?= h($old['external_ref'] ?? '') ?>">
    <input type="text" name="idempotency_key" placeholder="idempotency_key" value="<?= h($old['idempotency_key'] ?? '') ?>">

    <h3>Pickup</h3>
    <input type="text" name="pickup_name" placeholder="pickup name" value="<?= h($old['pickup_name'] ?? '') ?>" required>
    <input type="text" name="pickup_phone" placeholder="pickup phone" value="<?= h($old['pickup_phone'] ?? '') ?>">
    <input type="text" name="pickup_address" placeholder="pickup address" value="<?= h($old['pickup_address'] ?? '') ?>" required>

    <h3>Dropoff</h3>
    <input type="text" name="dropoff_name" placeholder="dropoff name" value="<?= h($old['dropoff_name'] ?? '') ?>" required>
    <input type="text" name="dropoff_phone" placeholder="dropoff phone" value="<?= h($old['dropoff_phone'] ?? '') ?>">
    <input type="text" name="dropoff_address" placeholder="dropoff address" value="<?= h($old['dropoff_address'] ?? '') ?>" required>

    <h3>Goods & Pricing</h3>
    <input type="text" name="goods_type" placeholder="goods type" value="<?= h($old['goods_type'] ?? '') ?>">
    <input type="number" step="0.01" name="goods_weight" placeholder="goods weight" value="<?= h($old['goods_weight'] ?? '') ?>">
    <input type="number" name="delivery_fee_cents" placeholder="delivery fee cents" value="<?= h($old['delivery_fee_cents'] ?? '0') ?>">

    <h3>COD</h3>
    <label><input type="checkbox" name="cod_required" value="1" <?= (($old['cod_required'] ?? '') === '1') ? 'checked' : '' ?>> COD required</label>
    <input type="number" name="cod_amount_cents" placeholder="cod amount cents" value="<?= h($old['cod_amount_cents'] ?? '0') ?>">
    <input type="text" name="cod_currency" placeholder="currency" value="<?= h($old['cod_currency'] ?? 'CAD') ?>">

    <button class="btn" type="submit">Create</button>
</form>
