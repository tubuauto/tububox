<?php declare(strict_types=1); ?>
<h1>Create Marketplace Order</h1>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <?php foreach ($errors as $key => $message): ?>
            <div><?= h((string) $key) ?>: <?= h((string) $message) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="post" action="/marketplace/orders" class="card form-grid">
    <h3>Store</h3>
    <select name="store_id" required>
        <option value="">Select store</option>
        <?php foreach (($stores ?? []) as $store): ?>
            <option value="<?= h($store['id']) ?>" <?= ((string) ($old['store_id'] ?? '') === (string) $store['id']) ? 'selected' : '' ?>>
                <?= h(($store['tenant_name'] ?? '-') . ' / ' . ($store['name'] ?? '-')) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <h3>Source</h3>
    <input type="text" name="source_order_no" placeholder="source_order_no (optional)" value="<?= h($old['source_order_no'] ?? '') ?>">
    <input type="text" name="external_ref" placeholder="external_ref" value="<?= h($old['external_ref'] ?? '') ?>">
    <input type="text" name="idempotency_key" placeholder="idempotency_key" value="<?= h($old['idempotency_key'] ?? '') ?>">

    <h3>Dropoff</h3>
    <input type="text" name="dropoff_name" placeholder="receiver name" value="<?= h($old['dropoff_name'] ?? '') ?>" required>
    <input type="text" name="dropoff_phone" placeholder="receiver phone" value="<?= h($old['dropoff_phone'] ?? '') ?>">
    <input type="text" name="dropoff_address" placeholder="dropoff address" value="<?= h($old['dropoff_address'] ?? '') ?>" required>
    <div class="card-grid two">
        <input type="number" step="0.0000001" name="dropoff_lat" placeholder="dropoff lat (optional)" value="<?= h($old['dropoff_lat'] ?? '') ?>">
        <input type="number" step="0.0000001" name="dropoff_lng" placeholder="dropoff lng (optional)" value="<?= h($old['dropoff_lng'] ?? '') ?>">
    </div>

    <h3>Goods & Pricing</h3>
    <input type="text" name="goods_type" placeholder="goods type" value="<?= h($old['goods_type'] ?? 'marketplace_goods') ?>">
    <input type="number" step="0.01" name="goods_weight" placeholder="goods weight" value="<?= h($old['goods_weight'] ?? '') ?>">
    <input type="number" name="delivery_fee_cents" placeholder="delivery fee cents" value="<?= h($old['delivery_fee_cents'] ?? '0') ?>">
    <textarea name="goods_note" placeholder="goods note"><?= h($old['goods_note'] ?? '') ?></textarea>

    <h3>COD</h3>
    <label><input type="checkbox" name="cod_required" value="1" <?= (($old['cod_required'] ?? '') === '1') ? 'checked' : '' ?>> COD required</label>
    <input type="number" name="cod_amount_cents" placeholder="cod amount cents" value="<?= h($old['cod_amount_cents'] ?? '0') ?>">
    <input type="text" name="cod_currency" placeholder="currency" value="<?= h($old['cod_currency'] ?? 'CAD') ?>">

    <button class="btn" type="submit">Create Marketplace Order</button>
</form>

