<?php
/**
 * Admin Inventory Items Management
 * Warehouse Management System
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_role('admin');

$db = get_db();
$currentUser = current_user();

// 1. Handle POST Actions (Add, Edit, Delete) with CSRF verification
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    check_csrf_or_abort();

    $action = $_POST['action'] ?? '';

    // Action: ADD ITEM
    if ($action === 'create') {
        $itemCode = trim((string)($_POST['item_code'] ?? ''));
        $itemName = trim((string)($_POST['item_name'] ?? ''));
        $category = trim((string)($_POST['category'] ?? ''));
        $location = trim((string)($_POST['location'] ?? ''));
        $quantity = (int)($_POST['quantity'] ?? 0);
        $unit = trim((string)($_POST['unit'] ?? ''));
        $reorderLevel = (int)($_POST['reorder_level'] ?? 0);

        if ($itemCode === '' || $itemName === '' || $category === '' || $location === '' || $unit === '') {
            set_flash('error', 'All fields marked with an asterisk are required.');
            redirect('admin/items.php');
        }

        if ($quantity < 0 || $reorderLevel < 0) {
            set_flash('error', 'Quantity and Reorder Level must be non-negative integers.');
            redirect('admin/items.php');
        }

        // Check unique item_code
        $chk = $db->prepare('SELECT id FROM items WHERE item_code = ? LIMIT 1');
        $chk->execute([$itemCode]);
        if ($chk->fetch()) {
            set_flash('error', 'Item Code already exists. Please choose a unique code.');
            redirect('admin/items.php');
        }

        try {
            $db->beginTransaction();

            $stmt = $db->prepare('
                INSERT INTO items (item_code, item_name, category, location, quantity, unit, reorder_level)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([$itemCode, $itemName, $category, $location, $quantity, $unit, $reorderLevel]);
            $newItemId = (int)$db->lastInsertId();

            // If initial stock quantity > 0, log opening balance movement to guarantee auditability
            if ($quantity > 0) {
                $movStmt = $db->prepare('
                    INSERT INTO stock_movements (item_id, movement_type, quantity, movement_date, reference_note, created_by)
                    VALUES (?, "IN", ?, CURDATE(), "Initial opening balance on item creation", ?)
                ');
                $movStmt->execute([$newItemId, $quantity, $currentUser['id']]);
            }

            $db->commit();
            set_flash('success', "Item [{$itemCode}] successfully created.");
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Item creation error: " . $e->getMessage());
            set_flash('error', 'Failed to create item. Please try again.');
        }

        redirect('admin/items.php');
    }

    // Action: EDIT ITEM (Metadata and thresholds)
    if ($action === 'update') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        $itemCode = trim((string)($_POST['item_code'] ?? ''));
        $itemName = trim((string)($_POST['item_name'] ?? ''));
        $category = trim((string)($_POST['category'] ?? ''));
        $location = trim((string)($_POST['location'] ?? ''));
        $unit = trim((string)($_POST['unit'] ?? ''));
        $reorderLevel = (int)($_POST['reorder_level'] ?? 0);

        if ($itemId <= 0 || $itemCode === '' || $itemName === '' || $category === '' || $location === '' || $unit === '') {
            set_flash('error', 'Missing required fields for update.');
            redirect('admin/items.php');
        }

        if ($reorderLevel < 0) {
            set_flash('error', 'Reorder Level cannot be negative.');
            redirect('admin/items.php');
        }

        // Check unique item_code excluding current item
        $chk = $db->prepare('SELECT id FROM items WHERE item_code = ? AND id != ? LIMIT 1');
        $chk->execute([$itemCode, $itemId]);
        if ($chk->fetch()) {
            set_flash('error', 'Item Code is already in use by another item.');
            redirect('admin/items.php');
        }

        try {
            $stmt = $db->prepare('
                UPDATE items 
                SET item_code = ?, item_name = ?, category = ?, location = ?, unit = ?, reorder_level = ?
                WHERE id = ?
            ');
            $stmt->execute([$itemCode, $itemName, $category, $location, $unit, $reorderLevel, $itemId]);

            set_flash('success', "Item [{$itemCode}] updated successfully.");
        } catch (Exception $e) {
            error_log("Item update error: " . $e->getMessage());
            set_flash('error', 'Unable to update item.');
        }

        redirect('admin/items.php');
    }

    // Action: DELETE ITEM
    if ($action === 'delete') {
        $itemId = (int)($_POST['item_id'] ?? 0);

        if ($itemId <= 0) {
            set_flash('error', 'Invalid item identifier.');
            redirect('admin/items.php');
        }

        // Check if item has movement history
        $chk = $db->prepare('SELECT COUNT(*) AS total FROM stock_movements WHERE item_id = ?');
        $chk->execute([$itemId]);
        $count = (int)($chk->fetch()['total'] ?? 0);

        if ($count > 0) {
            set_flash('error', "Cannot delete this item because it has {$count} recorded stock movement(s). Archival integrity preserved.");
            redirect('admin/items.php');
        }

        try {
            $stmt = $db->prepare('DELETE FROM items WHERE id = ?');
            $stmt->execute([$itemId]);
            set_flash('success', 'Item was successfully deleted.');
        } catch (Exception $e) {
            error_log("Item delete error: " . $e->getMessage());
            set_flash('error', 'Database constraint prevented deleting this item.');
        }

        redirect('admin/items.php');
    }
}

// 2. Fetch Filters & Search Query
$search = trim((string)($_GET['search'] ?? ''));
$categoryFilter = trim((string)($_GET['category'] ?? ''));

$sql = 'SELECT * FROM items WHERE 1=1';
$params = [];

if ($search !== '') {
    $sql .= ' AND (item_code LIKE ? OR item_name LIKE ? OR location LIKE ?)';
    $searchTerm = "%{$search}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($categoryFilter !== '') {
    $sql .= ' AND category = ?';
    $params[] = $categoryFilter;
}

$sql .= ' ORDER BY item_code ASC';

$stmt = $db->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();

// Get unique categories for dropdown filter
$catStmt = $db->query('SELECT DISTINCT category FROM items ORDER BY category ASC');
$categories = $catStmt->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Inventory Items Management';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
    <div>
        <h2 style="font-size:18px; margin:0; font-weight:700;">Warehouse Inventory Items</h2>
        <p style="color:var(--text-secondary); margin:4px 0 0; font-size:13px;">Manage master item catalog, tracking locations, units, and minimum stock alerts.</p>
    </div>
    <button type="button" class="btn btn-primary" data-modal-target="addItemModal">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
        Add New Item
    </button>
</div>

<!-- Search & Category Filters -->
<form method="GET" action="<?= url('admin/items.php') ?>" class="filter-bar">
    <div class="filter-item" style="flex: 2;">
        <label class="form-label" for="search">Search Keywords</label>
        <input 
            type="text" 
            id="search" 
            name="search" 
            class="form-control" 
            placeholder="Search by code, item name, or location..." 
            value="<?= e($search) ?>"
        >
    </div>

    <div class="filter-item">
        <label class="form-label" for="category">Category</label>
        <select id="category" name="category" class="form-control">
            <option value="">All Categories</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= e($cat) ?>" <?= $categoryFilter === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="filter-actions">
        <button type="submit" class="btn btn-primary">Filter</button>
        <?php if ($search !== '' || $categoryFilter !== ''): ?>
            <a href="<?= url('admin/items.php') ?>" class="btn btn-outline-secondary">Reset</a>
        <?php endif; ?>
    </div>
</form>

<!-- Items Table -->
<div class="card">
    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th class="nowrap">Item Code</th>
                        <th>Item Name</th>
                        <th>Category</th>
                        <th>Location</th>
                        <th class="nowrap">Quantity</th>
                        <th class="nowrap">Unit</th>
                        <th class="nowrap">Reorder Level</th>
                        <th class="nowrap">Status</th>
                        <th class="nowrap" style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; color: var(--text-muted); padding: 36px;">
                                No inventory items match your search criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($items as $item): ?>
                            <?php $isLow = (int)$item['quantity'] <= (int)$item['reorder_level']; ?>
                            <tr class="<?= $isLow ? 'row-warning' : '' ?>">
                                <td class="nowrap"><strong><?= e($item['item_code']) ?></strong></td>
                                <td><?= e($item['item_name']) ?></td>
                                <td><span class="badge badge-secondary"><?= e($item['category']) ?></span></td>
                                <td><?= e($item['location']) ?></td>
                                <td class="nowrap">
                                    <strong style="color: <?= (int)$item['quantity'] === 0 ? 'var(--danger)' : ($isLow ? 'var(--warning)' : 'inherit') ?>;">
                                        <?= (int)$item['quantity'] ?>
                                    </strong>
                                </td>
                                <td class="nowrap"><?= e($item['unit']) ?></td>
                                <td class="nowrap"><?= (int)$item['reorder_level'] ?></td>
                                <td class="nowrap">
                                    <?php if ((int)$item['quantity'] === 0): ?>
                                        <span class="badge badge-danger">Out of Stock</span>
                                    <?php elseif ($isLow): ?>
                                        <span class="badge badge-warning">Low Stock</span>
                                    <?php else: ?>
                                        <span class="badge badge-success">Normal</span>
                                    <?php endif; ?>
                                </td>
                                <td class="nowrap" style="text-align: right;">
                                    <a href="<?= url('admin/movements.php?item_id=' . (int)$item['id']) ?>" class="btn btn-sm btn-outline-primary" title="Record Movement">
                                        Stock +/-
                                    </a>
                                    <button 
                                        type="button" 
                                        class="btn btn-sm btn-secondary btn-edit-item"
                                        data-id="<?= (int)$item['id'] ?>"
                                        data-code="<?= e($item['item_code']) ?>"
                                        data-name="<?= e($item['item_name']) ?>"
                                        data-category="<?= e($item['category']) ?>"
                                        data-location="<?= e($item['location']) ?>"
                                        data-unit="<?= e($item['unit']) ?>"
                                        data-reorder="<?= (int)$item['reorder_level'] ?>"
                                    >
                                        Edit
                                    </button>
                                    
                                    <form method="POST" action="<?= url('admin/items.php') ?>" style="display:inline-block;" data-confirm="Are you sure you want to delete item [<?= e($item['item_code']) ?>]? Deletion will be rejected if stock movements exist.">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Add Item -->
<div class="modal-backdrop" id="addItemModal">
    <div class="modal-box">
        <form method="POST" action="<?= url('admin/items.php') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create">

            <div class="modal-header">
                <h3 class="modal-title">Create New Inventory Item</h3>
                <button type="button" class="modal-close" data-modal-close>&times;</button>
            </div>

            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="add_item_code">Item Code <span class="required">*</span></label>
                        <input type="text" id="add_item_code" name="item_code" class="form-control" required placeholder="e.g. ITM-007">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add_item_name">Item Name <span class="required">*</span></label>
                        <input type="text" id="add_item_name" name="item_name" class="form-control" required placeholder="e.g. Barcode Scanner">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="add_category">Category <span class="required">*</span></label>
                        <input type="text" id="add_category" name="category" class="form-control" required placeholder="e.g. Electronics">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add_location">Location <span class="required">*</span></label>
                        <input type="text" id="add_location" name="location" class="form-control" required placeholder="e.g. Aisle C-04">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="add_quantity">Initial Quantity <span class="required">*</span></label>
                        <input type="number" id="add_quantity" name="quantity" class="form-control" value="0" min="0" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add_unit">Unit of Measure <span class="required">*</span></label>
                        <input type="text" id="add_unit" name="unit" class="form-control" required placeholder="e.g. PCS, BOX, ROLL">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="add_reorder_level">Reorder Level <span class="required">*</span></label>
                        <input type="number" id="add_reorder_level" name="reorder_level" class="form-control" value="10" min="0" required>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary">Save Item</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Item -->
<div class="modal-backdrop" id="editItemModal">
    <div class="modal-box">
        <form method="POST" action="<?= url('admin/items.php') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="item_id" id="edit_item_id">

            <div class="modal-header">
                <h3 class="modal-title">Edit Inventory Item</h3>
                <button type="button" class="modal-close" data-modal-close>&times;</button>
            </div>

            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="edit_item_code">Item Code <span class="required">*</span></label>
                        <input type="text" id="edit_item_code" name="item_code" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit_item_name">Item Name <span class="required">*</span></label>
                        <input type="text" id="edit_item_name" name="item_name" class="form-control" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="edit_category">Category <span class="required">*</span></label>
                        <input type="text" id="edit_category" name="category" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit_location">Location <span class="required">*</span></label>
                        <input type="text" id="edit_location" name="location" class="form-control" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="edit_unit">Unit of Measure <span class="required">*</span></label>
                        <input type="text" id="edit_unit" name="unit" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit_reorder_level">Reorder Level <span class="required">*</span></label>
                        <input type="number" id="edit_reorder_level" name="reorder_level" class="form-control" min="0" required>
                    </div>
                </div>
                
                <p style="font-size:12px; color:var(--text-secondary); margin-top:10px;">
                    <em>Note: To adjust stock quantity, use <strong>Stock Movements</strong> (IN/OUT) to preserve audit trails.</em>
                </p>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary">Update Item</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Populate Edit Modal
    document.querySelectorAll('.btn-edit-item').forEach(button => {
        button.addEventListener('click', () => {
            const setVal = (id, val) => {
                const el = document.getElementById(id);
                if (el) el.value = val ?? '';
            };

            setVal('edit_item_id', button.dataset.id);
            setVal('edit_item_code', button.dataset.code);
            setVal('edit_item_name', button.dataset.name);
            setVal('edit_category', button.dataset.category);
            setVal('edit_location', button.dataset.location);
            setVal('edit_unit', button.dataset.unit);
            setVal('edit_reorder_level', button.dataset.reorder);

            openModal('editItemModal');
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
