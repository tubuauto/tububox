<?php
declare(strict_types=1);

$auth = $auth ?? ($_SESSION['user'] ?? null);
$role = is_array($auth) ? (string) ($auth['role'] ?? 'guest') : 'guest';
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
            <a href="/deliveries">Deliveries</a>
            <a href="/dispatch">Dispatch</a>
            <a href="/webhooks">Webhooks</a>
            <a href="/api-keys">API Keys</a>
            <a href="/audit-logs">Audit Logs</a>
            <a href="/stores">Stores</a>
            <a href="/users">Users</a>
            <a href="/rider/deliveries">Rider H5</a>
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
