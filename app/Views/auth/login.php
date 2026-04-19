<?php declare(strict_types=1); ?>
<div class="card auth-card">
    <h1>Delivery SaaS Login</h1>
    <p class="muted">Seed accounts: `admin@tububox.local`, `merchant@tububox.local`, `operator@tububox.local`, `rider@tububox.local` (password: `admin123`)</p>

    <?php if (!empty($errors['auth'])): ?>
        <div class="alert alert-error"><?= h($errors['auth']) ?></div>
    <?php endif; ?>

    <form method="post" action="/login" class="form-grid">
        <label>Email</label>
        <input type="email" name="email" value="<?= h($old['email'] ?? '') ?>" required>
        <?php if (!empty($errors['email'])): ?><small class="err"><?= h($errors['email']) ?></small><?php endif; ?>

        <label>Password</label>
        <input type="password" name="password" required>
        <?php if (!empty($errors['password'])): ?><small class="err"><?= h($errors['password']) ?></small><?php endif; ?>

        <button type="submit" class="btn">Sign In</button>
    </form>
</div>
