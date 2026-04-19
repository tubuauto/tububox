<?php
declare(strict_types=1);

$auth = $auth ?? ($_SESSION['user'] ?? null);
$role = is_array($auth) ? (string) ($auth['role'] ?? 'guest') : 'guest';
$isOwner = in_array($role, ['admin', 'merchant'], true);
$isMerchantOps = in_array($role, ['admin', 'merchant', 'operator'], true);
$isRider = in_array($role, ['admin', 'rider'], true);
$isUser = in_array($role, ['admin', 'user'], true);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>tububox</title>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <div class="brand">tububox</div>
        <nav class="menu">
            <a href="/dashboard">Dashboard</a>
            <?php if ($isUser): ?><a href="/marketplace/orders">Marketplace</a><?php endif; ?>
            <?php if ($isMerchantOps): ?><a href="/deliveries">Deliveries</a><?php endif; ?>
            <?php if ($isMerchantOps): ?><a href="/dispatch">Dispatch</a><?php endif; ?>
            <?php if ($isMerchantOps): ?><a href="/webhooks">Webhooks</a><?php endif; ?>
            <?php if ($isOwner): ?><a href="/api-keys">API Keys</a><?php endif; ?>
            <?php if ($isOwner): ?><a href="/audit-logs">Audit Logs</a><?php endif; ?>
            <?php if ($isOwner): ?><a href="/stores">Stores</a><?php endif; ?>
            <?php if ($isOwner): ?><a href="/users">Users</a><?php endif; ?>
            <?php if ($isRider): ?><a href="/rider/deliveries">Rider H5</a><?php endif; ?>
        </nav>
    </aside>
    <main class="main">
        <header class="topbar">
            <div>
                <strong><?= h($auth['name'] ?? 'Guest') ?></strong>
                <span class="muted">Role: <?= h($role) ?></span>
            </div>
            <?php if (is_array($auth)): ?>
                <form method="post" action="/logout">
                    <button class="btn btn-light" type="submit">Logout</button>
                </form>
            <?php endif; ?>
        </header>
        <section class="content">
            <?= $content ?>
        </section>
    </main>
</div>
</body>
</html>
