<?php
/**
 * Admin Dashboard
 * Warehouse Management System
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_role('admin');

$db = get_db();

// 1. Fetch Metrics
// Total Items
$stmt = $db->query('SELECT COUNT(*) AS total_items, COALESCE(SUM(quantity), 0) AS total_qty FROM items');
$itemStats = $stmt->fetch();
$totalItems = (int)($itemStats['total_items'] ?? 0);
$totalStockQty = (int)($itemStats['total_qty'] ?? 0);

// Low Stock Items Count
$stmt = $db->query('SELECT COUNT(*) AS low_stock_count FROM items WHERE quantity <= reorder_level');
$lowStockCount = (int)($stmt->fetch()['low_stock_count'] ?? 0);

// Today's IN & OUT
$stmt = $db->query("
    SELECT 
        COALESCE(SUM(CASE WHEN movement_type = 'IN' THEN quantity ELSE 0 END), 0) AS today_in,
        COALESCE(SUM(CASE WHEN movement_type = 'OUT' THEN quantity ELSE 0 END), 0) AS today_out
    FROM stock_movements
    WHERE movement_date = CURDATE()
");
$todayMovements = $stmt->fetch();
$todayIn = (int)($todayMovements['today_in'] ?? 0);
$todayOut = (int)($todayMovements['today_out'] ?? 0);

// 2. Fetch Low Stock Items List
$stmt = $db->query('
    SELECT id, item_code, item_name, category, location, quantity, unit, reorder_level 
    FROM items 
    WHERE quantity <= reorder_level 
    ORDER BY (quantity - reorder_level) ASC, quantity ASC
    LIMIT 10
');
$lowStockItems = $stmt->fetchAll();

// 3. Fetch Recent Stock Movements
$stmt = $db->query('
    SELECT sm.id, sm.movement_type, sm.quantity, sm.movement_date, sm.reference_note, 
           i.item_code, i.item_name, u.username
    FROM stock_movements sm
    JOIN items i ON sm.item_id = i.id
    JOIN users u ON sm.created_by = u.id
    ORDER BY sm.id DESC
    LIMIT 6
');
$recentMovements = $stmt->fetchAll();

$pageTitle = 'Admin Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Statistics Overview -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon stat-icon-blue">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
        </div>
        <div class="stat-details">
            <span class="stat-label">Total Unique Items</span>
            <span class="stat-value"><?= number_format($totalItems) ?></span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon stat-icon-emerald">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
        </div>
        <div class="stat-details">
            <span class="stat-label">Total Stock Quantity</span>
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

    <div class="stat-card">
        <div class="stat-icon stat-icon-emerald">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="17 1 21 5 17 9"></polyline><path d="M3 11V9a4 4 0 0 1 4-4h14"></path></svg>
        </div>
        <div class="stat-details">
            <span class="stat-label">Today's Stock IN</span>
            <span class="stat-value" style="color: var(--success);"><?= number_format($todayIn) ?></span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon stat-icon-rose">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="7 23 3 19 7 15"></polyline><path d="M21 13v2a4 4 0 0 1-4 4H3"></path></svg>
        </div>
        <div class="stat-details">
            <span class="stat-label">Today's Stock OUT</span>
            <span class="stat-value" style="color: var(--danger);"><?= number_format($todayOut) ?></span>
        </div>
    </div>
</div>

<!-- Low Stock Items Warning Table -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title" style="display:flex; align-items:center; gap:8px;">
            <span style="color: var(--warning);">&#9888;</span> Low Stock Items (Quantity &le; Reorder Level)
        </h2>
        <a href="<?= url('admin/items.php') ?>" class="btn btn-outline-primary btn-sm">Manage All Items</a>
    </div>
    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th class="nowrap">Item Code</th>
                        <th>Item Name</th>
                        <th>Category</th>
                        <th>Location</th>
                        <th class="nowrap">Current Quantity</th>
                        <th class="nowrap">Reorder Level</th>
                        <th class="nowrap">Unit</th>
                        <th class="nowrap">Status</th>
                        <th class="nowrap">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($lowStockItems)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; color: var(--text-muted); padding: 32px;">
                                Excellent! No items are currently below their reorder threshold.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($lowStockItems as $item): ?>
                            <tr class="<?= $item['quantity'] == 0 ? 'row-danger' : 'row-warning' ?>">
                                <td class="nowrap"><strong><?= e($item['item_code']) ?></strong></td>
                                <td><?= e($item['item_name']) ?></td>
                                <td><span class="badge badge-secondary"><?= e($item['category']) ?></span></td>
                                <td><?= e($item['location']) ?></td>
                                <td class="nowrap">
                                    <strong style="color: <?= $item['quantity'] == 0 ? 'var(--danger)' : 'var(--warning)' ?>;">
                                        <?= (int)$item['quantity'] ?>
                                    </strong>
                                </td>
                                <td class="nowrap"><?= (int)$item['reorder_level'] ?></td>
                                <td class="nowrap"><?= e($item['unit']) ?></td>
                                <td class="nowrap">
                                    <?php if ($item['quantity'] == 0): ?>
                                        <span class="badge badge-danger">Out of Stock</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Low Stock Warning</span>
                                    <?php endif; ?>
                                </td>
                                <td class="nowrap">
                                    <a href="<?= url('admin/movements.php?item_id=' . (int)$item['id'] . '&action=in') ?>" class="btn btn-sm btn-success">
                                        + Stock IN
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Recent Stock Movements Table -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Recent Stock Activity</h2>
        <a href="<?= url('admin/movements.php') ?>" class="btn btn-outline-secondary btn-sm">View Full History</a>
    </div>
    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th class="nowrap">Date</th>
                        <th class="nowrap">Movement</th>
                        <th>Item</th>
                        <th class="nowrap">Quantity</th>
                        <th>Reference / Note</th>
                        <th class="nowrap">Handled By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentMovements)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 24px;">
                                No recent movements found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentMovements as $mov): ?>
                            <tr>
                                <td class="nowrap"><?= format_date($mov['movement_date'], 'Y-m-d') ?></td>
                                <td class="nowrap">
                                    <span class="badge <?= $mov['movement_type'] === 'IN' ? 'badge-in' : 'badge-out' ?>">
                                        <?= e($mov['movement_type']) ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?= e($mov['item_code']) ?></strong> - <?= e($mov['item_name']) ?>
                                </td>
                                <td class="nowrap"><strong><?= number_format((int)$mov['quantity']) ?></strong></td>
                                <td><?= e($mov['reference_note'] ?? '-') ?></td>
                                <td class="nowrap"><?= e($mov['username']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
