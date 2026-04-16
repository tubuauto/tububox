<?php declare(strict_types=1); ?>
<h1>Driver Delivery #<?= h($delivery['id']) ?></h1>
<div class="card">
    <p>Status: <span class="badge"><?= h($delivery['status']) ?></span></p>
    <p>Pickup: <?= h($delivery['pickup_address']) ?></p>
    <p>Dropoff: <?= h($delivery['dropoff_address']) ?></p>
</div>

<div class="card-grid two">
    <form method="post" action="/driver/deliveries/<?= h($delivery['id']) ?>/accept" class="card"><button class="btn btn-light" type="submit">Accept</button></form>
    <form method="post" action="/driver/deliveries/<?= h($delivery['id']) ?>/arrive-pickup" class="card"><button class="btn btn-light" type="submit">Arrive Pickup</button></form>
    <form method="post" action="/driver/deliveries/<?= h($delivery['id']) ?>/pickup" class="card">
        <input type="text" name="note" placeholder="pickup note">
        <button class="btn btn-light" type="submit">Confirm Pickup</button>
    </form>
    <form method="post" action="/driver/deliveries/<?= h($delivery['id']) ?>/arrive-dropoff" class="card"><button class="btn btn-light" type="submit">Arrive Dropoff</button></form>
    <form method="post" action="/driver/deliveries/<?= h($delivery['id']) ?>/sign" class="card">
        <input type="text" name="receiver_name" placeholder="receiver name">
        <input type="text" name="proof_image" placeholder="proof image path">
        <button class="btn btn-light" type="submit">Sign</button>
    </form>
    <form method="post" action="/driver/deliveries/<?= h($delivery['id']) ?>/complete" class="card"><button class="btn btn-light" type="submit">Complete</button></form>
</div>

<div class="card">
    <h3>COD Collect</h3>
    <form method="post" action="/driver/deliveries/<?= h($delivery['id']) ?>/cod-collect" class="form-grid">
        <input type="number" name="expected_amount_cents" placeholder="expected amount cents" required>
        <input type="number" name="collected_amount_cents" placeholder="collected amount cents" required>
        <input type="text" name="method" placeholder="method (cash)">
        <input type="text" name="proof_image" placeholder="proof image path">
        <button class="btn" type="submit">Submit COD</button>
    </form>
</div>

