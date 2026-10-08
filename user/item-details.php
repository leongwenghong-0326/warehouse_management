<?php
/**
 * Item Details View (Read-only)
 * Warehouse Management System
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_login();

$db = get_db();
$itemId = (int)($_GET['id'] ?? 0);

if ($itemId <= 0) {
    set_flash('error', 'Invalid item specified.');
    redirect('user/items.php');
}

// Fetch Item details
$stmt = $db->prepare('SELECT * FROM items WHERE id = ? LIMIT 1');
$stmt->execute([$itemId]);
$item = $stmt->fetch();

if (!$item) {
    set_flash('error', 'Item could not be found.');
    redirect('user/items.php');
}

// Fetch Stock Movement History for this item
$histStmt = $db->prepare('
    SELECT sm.id, sm.movement_type, sm.quantity, sm.movement_date, sm.reference_note, sm.created_at,
           u.username
    FROM stock_movements sm
    JOIN users u ON sm.created_by = u.id
    WHERE sm.item_id = ?
    ORDER BY sm.movement_date DESC, sm.id DESC
');
$histStmt->execute([$itemId]);
$movements = $histStmt->fetchAll();

$isLow = (int)$item['quantity'] <= (int)$item['reorder_level'];
$pageTitle = 'Item Details: ' . $item['item_code'];
require_once __DIR__ . '/../includes/header.php';
?>

<div style="margin-bottom:20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
    <div>
        <a href="<?= url('user/items.php') ?>" class="btn btn-outline-secondary btn-sm" style="margin-bottom:8px;">
            &larr; Back to Catalog
        </a>
        <h2 style="font-size:20px; margin:0; font-weight:700;">
            [<?= e($item['item_code']) ?>] <?= e($item['item_name']) ?>
        </h2>
    </div>
    <div>
        <?php if ((int)$item['quantity'] === 0): ?>
            <span class="badge badge-danger" style="font-size: 14px; padding: 6px 12px;">Out of Stock</span>
        <?php elseif ($isLow): ?>
            <span class="badge badge-warning" style="font-size: 14px; padding: 6px 12px;">Low Stock Warning</span>
        <?php else: ?>
            <span class="badge badge-success" style="font-size: 14px; padding: 6px 12px;">In Stock Normal</span>
        <?php endif; ?>
    </div>
</div>

<!-- Item Details Card -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Specification & Location Information</h3>
    </div>
    <div class="card-body">
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap:20px;">
            <div>
                <span class="text-muted" style="font-size:12px; text-transform:uppercase; font-weight:600;">Category</span>
                <p style="margin:4px 0 0; font-size:16px; font-weight:600; color:var(--text-primary);">
                    <?= e($item['category']) ?>
                </p>
            </div>

            <div>
                <span class="text-muted" style="font-size:12px; text-transform:uppercase; font-weight:600;">Storage Bin / Location</span>
                <p style="margin:4px 0 0; font-size:16px; font-weight:600; color:var(--primary);">
                    <?= e($item['location']) ?>
                </p>
            </div>

            <div>
                <span class="text-muted" style="font-size:12px; text-transform:uppercase; font-weight:600;">Current Available Quantity</span>
                <p style="margin:4px 0 0; font-size:18px; font-weight:700; color:<?= $isLow ? 'var(--warning)' : 'var(--text-primary)' ?>;">
                    <?= number_format((int)$item['quantity']) ?> <?= e($item['unit']) ?>
                </p>
            </div>

            <div>
                <span class="text-muted" style="font-size:12px; text-transform:uppercase; font-weight:600;">Minimum Reorder Threshold</span>
                <p style="margin:4px 0 0; font-size:16px; font-weight:600; color:var(--text-primary);">
                    <?= number_format((int)$item['reorder_level']) ?> <?= e($item['unit']) ?>
                </p>
            </div>

            <div>
                <span class="text-muted" style="font-size:12px; text-transform:uppercase; font-weight:600;">Item Created Date</span>
                <p style="margin:4px 0 0; font-size:14px; color:var(--text-secondary);">
                    <?= format_date($item['created_at']) ?>
                </p>
            </div>

            <div>
                <span class="text-muted" style="font-size:12px; text-transform:uppercase; font-weight:600;">Last Updated</span>
                <p style="margin:4px 0 0; font-size:14px; color:var(--text-secondary);">
                    <?= format_date($item['updated_at']) ?>
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Associated Movement History -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Movement Log for this Item (<?= count($movements) ?> transactions)</h3>
    </div>
    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Quantity</th>
                        <th>Reference / Notes</th>
                        <th>Handled By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($movements)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 24px;">
                                No stock transactions recorded for this item yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($movements as $m): ?>
                            <tr>
                                <td><?= format_date($m['movement_date'], 'Y-m-d') ?></td>
                                <td>
                                    <span class="badge <?= $m['movement_type'] === 'IN' ? 'badge-in' : 'badge-out' ?>">
                                        <?= e($m['movement_type']) ?>
                                    </span>
                                </td>
                                <td>
                                    <strong style="color: <?= $m['movement_type'] === 'IN' ? 'var(--success)' : 'var(--danger)' ?>;">
                                        <?= $m['movement_type'] === 'IN' ? '+' : '-' ?><?= number_format((int)$m['quantity']) ?> <?= e($item['unit']) ?>
                                    </strong>
                                </td>
                                <td><?= e($m['reference_note'] ?? '-') ?></td>
                                <td><?= e($m['username']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
