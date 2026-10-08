<?php
/**
 * User Dashboard (Read-Only Inventory Overview)
 * Warehouse Management System
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_login();

$db = get_db();

// Metrics
$stmt = $db->query('SELECT COUNT(*) AS total_items, COALESCE(SUM(quantity), 0) AS total_qty FROM items');
$itemStats = $stmt->fetch();
$totalItems = (int)($itemStats['total_items'] ?? 0);
$totalStockQty = (int)($itemStats['total_qty'] ?? 0);

// Low Stock Count
$stmt = $db->query('SELECT COUNT(*) AS low_stock_count FROM items WHERE quantity <= reorder_level');
$lowStockCount = (int)($stmt->fetch()['low_stock_count'] ?? 0);

// Low Stock Items List
$stmt = $db->query('
    SELECT id, item_code, item_name, category, location, quantity, unit, reorder_level 
    FROM items 
    WHERE quantity <= reorder_level 
    ORDER BY (quantity - reorder_level) ASC, quantity ASC
    LIMIT 6
');
$lowStockItems = $stmt->fetchAll();

// Recent Stock History
$stmt = $db->query('
    SELECT sm.id, sm.movement_type, sm.quantity, sm.movement_date, sm.reference_note, 
           i.item_code, i.item_name, i.unit, u.username
    FROM stock_movements sm
    JOIN items i ON sm.item_id = i.id
    JOIN users u ON sm.created_by = u.id
    ORDER BY sm.id DESC
    LIMIT 8
');
$recentMovements = $stmt->fetchAll();

$pageTitle = 'Staff Overview Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Statistics Overview -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon stat-icon-blue">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
        </div>
        <div class="stat-details">
            <span class="stat-label">Catalog Items</span>
            <span class="stat-value"><?= number_format($totalItems) ?></span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon stat-icon-emerald">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
        </div>
        <div class="stat-details">
            <span class="stat-label">Total Units on Hand</span>
            <span class="stat-value"><?= number_format($totalStockQty) ?></span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon stat-icon-amber">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
        </div>
        <div class="stat-details">
            <span class="stat-label">Low Stock Alerts</span>
            <span class="stat-value" style="color: <?= $lowStockCount > 0 ? 'var(--warning)' : 'inherit' ?>;"><?= number_format($lowStockCount) ?></span>
        </div>
    </div>
</div>

<!-- Low Stock Watchlist -->
<?php if (!empty($lowStockItems)): ?>
<div class="card">
    <div class="card-header">
        <h3 class="card-title" style="display:flex; align-items:center; gap:8px;">
            <span style="color: var(--warning);">&#9888;</span> Low Stock Items Watchlist
        </h3>
        <a href="<?= url('user/items.php') ?>" class="btn btn-outline-secondary btn-sm">Browse Catalog</a>
    </div>
    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Item Code</th>
                        <th>Item Name</th>
                        <th>Category</th>
                        <th>Location</th>
                        <th>On Hand</th>
                        <th>Reorder Threshold</th>
                        <th>Unit</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lowStockItems as $it): ?>
                        <tr class="row-warning">
                            <td><strong><?= e($it['item_code']) ?></strong></td>
                            <td><?= e($it['item_name']) ?></td>
                            <td><span class="badge badge-secondary"><?= e($it['category']) ?></span></td>
                            <td><?= e($it['location']) ?></td>
                            <td><strong style="color: var(--warning);"><?= (int)$it['quantity'] ?></strong></td>
                            <td><?= (int)$it['reorder_level'] ?></td>
                            <td><?= e($it['unit']) ?></td>
                            <td>
                                <a href="<?= url('user/item-details.php?id=' . (int)$it['id']) ?>" class="btn btn-sm btn-outline-primary">
                                    View
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Recent Stock History -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Recent Stock Activity Ledger</h3>
    </div>
    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Item Code</th>
                        <th>Item Name</th>
                        <th>Quantity</th>
                        <th>Reference / Note</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentMovements)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 32px;">
                                No recent activity logged.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentMovements as $m): ?>
                            <tr>
                                <td><?= format_date($m['movement_date'], 'Y-m-d') ?></td>
                                <td>
                                    <span class="badge <?= $m['movement_type'] === 'IN' ? 'badge-in' : 'badge-out' ?>">
                                        <?= e($m['movement_type']) ?>
                                    </span>
                                </td>
                                <td><strong><?= e($m['item_code']) ?></strong></td>
                                <td><?= e($m['item_name']) ?></td>
                                <td>
                                    <strong style="color: <?= $m['movement_type'] === 'IN' ? 'var(--success)' : 'var(--danger)' ?>;">
                                        <?= $m['movement_type'] === 'IN' ? '+' : '-' ?><?= number_format((int)$m['quantity']) ?> <?= e($m['unit']) ?>
                                    </strong>
                                </td>
                                <td><?= e($m['reference_note'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
