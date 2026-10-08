<?php
/**
 * Admin Stock Movements (IN / OUT Transactions & History)
 * Warehouse Management System
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_role('admin');

$db = get_db();
$currentUser = current_user();

// 1. Process Stock Movement Submission (POST)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    check_csrf_or_abort();

    $itemId = (int)($_POST['item_id'] ?? 0);
    $movementType = strtoupper(trim((string)($_POST['movement_type'] ?? '')));
    $quantity = (int)($_POST['quantity'] ?? 0);
    $movementDate = trim((string)($_POST['movement_date'] ?? date('Y-m-d')));
    $referenceNote = trim((string)($_POST['reference_note'] ?? ''));

    // Validation
    if ($itemId <= 0) {
        set_flash('error', 'Please select a valid inventory item.');
        redirect('admin/movements.php');
    }

    if (!in_array($movementType, ['IN', 'OUT'], true)) {
        set_flash('error', 'Invalid movement type. Must be either IN or OUT.');
        redirect('admin/movements.php');
    }

    if ($quantity <= 0) {
        set_flash('error', 'Movement quantity must be a positive integer greater than zero.');
        redirect('admin/movements.php');
    }

    if (empty($movementDate)) {
        $movementDate = date('Y-m-d');
    }

    // Execute with Database Transaction and Row Locking
    try {
        $db->beginTransaction();

        // 1. Lock item row and retrieve current quantity
        $lockStmt = $db->prepare('SELECT id, item_code, item_name, quantity FROM items WHERE id = ? FOR UPDATE');
        $lockStmt->execute([$itemId]);
        $item = $lockStmt->fetch();

        if (!$item) {
            $db->rollBack();
            set_flash('error', 'Selected item does not exist.');
            redirect('admin/movements.php');
        }

        $currentStock = (int)$item['quantity'];

        // 2. Business rule validation: Prevent negative stock
        if ($movementType === 'OUT' && $quantity > $currentStock) {
            $db->rollBack();
            set_flash('error', "Insufficient stock for [{$item['item_code']}]. Current stock is {$currentStock}, cannot deduct {$quantity}. Stock must never become negative.");
            redirect('admin/movements.php');
        }

        // 3. Calculate new quantity
        $newStock = ($movementType === 'IN') ? ($currentStock + $quantity) : ($currentStock - $quantity);

        // 4. Insert into stock_movements
        $movStmt = $db->prepare('
            INSERT INTO stock_movements (item_id, movement_type, quantity, movement_date, reference_note, created_by)
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        $movStmt->execute([$itemId, $movementType, $quantity, $movementDate, $referenceNote, $currentUser['id']]);

        // 5. Update items quantity
        $updateStmt = $db->prepare('UPDATE items SET quantity = ? WHERE id = ?');
        $updateStmt->execute([$newStock, $itemId]);

        // 6. Commit transaction
        $db->commit();

        set_flash('success', "Stock {$movementType} of {$quantity} units recorded for [{$item['item_code']}]. New balance: {$newStock}.");
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Stock movement transaction failure: " . $e->getMessage());
        set_flash('error', 'Database error during stock movement transaction. No changes were made.');
    }

    redirect('admin/movements.php');
}

// 2. Fetch Filter Parameters for Stock History
$filterItem = (int)($_GET['item_id'] ?? 0);
$filterType = strtoupper(trim((string)($_GET['movement_type'] ?? '')));
$filterStartDate = trim((string)($_GET['start_date'] ?? ''));
$filterEndDate = trim((string)($_GET['end_date'] ?? ''));

// Preset from query string (e.g. from dashboard quick action)
$presetAction = strtoupper(trim((string)($_GET['action'] ?? '')));

// Build Query for Movements
$sql = '
    SELECT sm.id, sm.movement_type, sm.quantity, sm.movement_date, sm.reference_note, sm.created_at,
           i.id AS item_id, i.item_code, i.item_name, i.unit,
           u.username, u.full_name
    FROM stock_movements sm
    JOIN items i ON sm.item_id = i.id
    JOIN users u ON sm.created_by = u.id
    WHERE 1=1
';
$params = [];

if ($filterItem > 0) {
    $sql .= ' AND sm.item_id = ?';
    $params[] = $filterItem;
}

if (in_array($filterType, ['IN', 'OUT'], true)) {
    $sql .= ' AND sm.movement_type = ?';
    $params[] = $filterType;
}

if ($filterStartDate !== '') {
    $sql .= ' AND sm.movement_date >= ?';
    $params[] = $filterStartDate;
}

if ($filterEndDate !== '') {
    $sql .= ' AND sm.movement_date <= ?';
    $params[] = $filterEndDate;
}

$sql .= ' ORDER BY sm.id DESC LIMIT 200';

$stmt = $db->prepare($sql);
$stmt->execute($params);
$movements = $stmt->fetchAll();

// Fetch all items for dropdown selections
$itemsList = $db->query('SELECT id, item_code, item_name, quantity, unit FROM items ORDER BY item_code ASC')->fetchAll();

$pageTitle = 'Stock Movements';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
    <div>
        <h2 style="font-size:18px; margin:0; font-weight:700;">Stock Movement Operations & Ledger</h2>
        <p style="color:var(--text-secondary); margin:4px 0 0; font-size:13px;">Record incoming stock or stock dispatch with row-level transaction safety.</p>
    </div>
    <button type="button" class="btn btn-primary" data-modal-target="recordMovementModal">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
        Record Stock Movement
    </button>
</div>

<!-- Filters Bar -->
<form method="GET" action="<?= url('admin/movements.php') ?>" class="filter-bar">
    <div class="filter-item">
        <label class="form-label" for="filter_item">Filter by Item</label>
        <select id="filter_item" name="item_id" class="form-control">
            <option value="">All Inventory Items</option>
            <?php foreach ($itemsList as $it): ?>
                <option value="<?= (int)$it['id'] ?>" <?= $filterItem === (int)$it['id'] ? 'selected' : '' ?>>
                    <?= e($it['item_code']) ?> - <?= e($it['item_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="filter-item" style="max-width: 150px;">
        <label class="form-label" for="filter_type">Type</label>
        <select id="filter_type" name="movement_type" class="form-control">
            <option value="">All Types</option>
            <option value="IN" <?= $filterType === 'IN' ? 'selected' : '' ?>>Stock IN</option>
            <option value="OUT" <?= $filterType === 'OUT' ? 'selected' : '' ?>>Stock OUT</option>
        </select>
    </div>

    <div class="filter-item">
        <label class="form-label" for="filter_start">From Date</label>
        <input type="date" id="filter_start" name="start_date" class="form-control" value="<?= e($filterStartDate) ?>">
    </div>

    <div class="filter-item">
        <label class="form-label" for="filter_end">To Date</label>
        <input type="date" id="filter_end" name="end_date" class="form-control" value="<?= e($filterEndDate) ?>">
    </div>

    <div class="filter-actions">
        <button type="submit" class="btn btn-primary">Apply Filters</button>
        <?php if ($filterItem > 0 || $filterType !== '' || $filterStartDate !== '' || $filterEndDate !== ''): ?>
            <a href="<?= url('admin/movements.php') ?>" class="btn btn-outline-secondary">Reset</a>
        <?php endif; ?>
    </div>
</form>

<!-- Movement Ledger Table -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Movement Audit Log (Showing up to 200 records)</h3>
    </div>
    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th class="nowrap">Date</th>
                        <th class="nowrap">Type</th>
                        <th class="nowrap">Item Code</th>
                        <th>Item Name</th>
                        <th class="nowrap">Quantity</th>
                        <th>Reference / Note</th>
                        <th class="nowrap">Recorded By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($movements)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 36px;">
                                No stock movement records found matching your filters.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($movements as $m): ?>
                            <tr>
                                <td class="nowrap"><?= format_date($m['movement_date'], 'Y-m-d') ?></td>
                                <td class="nowrap">
                                    <span class="badge <?= $m['movement_type'] === 'IN' ? 'badge-in' : 'badge-out' ?>">
                                        <?= e($m['movement_type']) ?>
                                    </span>
                                </td>
                                <td class="nowrap"><strong><?= e($m['item_code']) ?></strong></td>
                                <td><?= e($m['item_name']) ?></td>
                                <td class="nowrap">
                                    <strong style="color: <?= $m['movement_type'] === 'IN' ? 'var(--success)' : 'var(--danger)' ?>;">
                                        <?= $m['movement_type'] === 'IN' ? '+' : '-' ?><?= number_format((int)$m['quantity']) ?>&nbsp;<?= e($m['unit']) ?>
                                    </strong>
                                </td>
                                <td><?= e($m['reference_note'] ?? '-') ?></td>
                                <td class="nowrap">
                                    <span title="<?= e($m['full_name']) ?>"><?= e($m['username']) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Record Stock Movement -->
<div class="modal-backdrop <?= ($filterItem > 0 && in_array($presetAction, ['IN', 'OUT'])) ? 'show' : '' ?>" id="recordMovementModal">
    <div class="modal-box">
        <form method="POST" action="<?= url('admin/movements.php') ?>">
            <?= csrf_field() ?>

            <div class="modal-header">
                <h3 class="modal-title">Record Stock Movement</h3>
                <button type="button" class="modal-close" data-modal-close>&times;</button>
            </div>

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="mov_item_id">Select Item <span class="required">*</span></label>
                    <select id="mov_item_id" name="item_id" class="form-control" required>
                        <option value="">-- Choose Item --</option>
                        <?php foreach ($itemsList as $it): ?>
                            <option value="<?= (int)$it['id'] ?>" <?= $filterItem === (int)$it['id'] ? 'selected' : '' ?>>
                                [<?= e($it['item_code']) ?>] <?= e($it['item_name']) ?> (Current Stock: <?= (int)$it['quantity'] ?> <?= e($it['unit']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="mov_type">Movement Type <span class="required">*</span></label>
                        <select id="mov_type" name="movement_type" class="form-control" required>
                            <option value="IN" <?= $presetAction === 'IN' ? 'selected' : '' ?>>Stock IN (+)</option>
                            <option value="OUT" <?= $presetAction === 'OUT' ? 'selected' : '' ?>>Stock OUT (-)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="mov_qty">Quantity <span class="required">*</span></label>
                        <input type="number" id="mov_qty" name="quantity" class="form-control" min="1" required placeholder="Must be > 0">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="mov_date">Date of Movement <span class="required">*</span></label>
                    <input type="date" id="mov_date" name="movement_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="mov_ref">Reference / Note</label>
                    <input type="text" id="mov_ref" name="reference_note" class="form-control" placeholder="e.g. PO #1234, Delivery Slip, Damaged Goods, Sales Order">
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary">Submit Transaction</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
