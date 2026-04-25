<?php declare(strict_types=1); ?>
<h1>Dashboard</h1>
<div class="card-grid">
    <div class="card stat"><span>Total</span><strong><?= h($stats['total']) ?></strong></div>
    <div class="card stat"><span>Pending</span><strong><?= h($stats['pending']) ?></strong></div>
    <div class="card stat"><span>Dispatch Pending</span><strong><?= h($stats['dispatch_pending'] ?? 0) ?></strong></div>
    <div class="card stat"><span>Assigned</span><strong><?= h($stats['assigned']) ?></strong></div>
    <div class="card stat"><span>In Transit</span><strong><?= h($stats['in_transit']) ?></strong></div>
    <div class="card stat"><span>Completed</span><strong><?= h($stats['completed']) ?></strong></div>
    <div class="card stat"><span>Failed</span><strong><?= h($stats['failed']) ?></strong></div>
</div>
