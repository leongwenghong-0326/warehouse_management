<?php
/**
 * User Inventory Items Browser (Read-only)
 * Warehouse Management System
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_login();

$db = get_db();

// Search & Filter
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

// Categories for dropdown
$catStmt = $db->query('SELECT DISTINCT category FROM items ORDER BY category ASC');
$categories = $catStmt->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Browse Inventory';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="margin-bottom:20px;">
    <h2 style="font-size:18px; margin:0; font-weight:700;">Warehouse Catalog Directory</h2>
    <p style="color:var(--text-secondary); margin:4px 0 0; font-size:13px;">View real-time item availability, storage bin locations, and reorder alerts.</p>
</div>

<!-- Filters Bar -->
<form method="GET" action="<?= url('user/items.php') ?>" class="filter-bar">
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
        <button type="submit" class="btn btn-primary">Search</button>
        <?php if ($search !== '' || $categoryFilter !== ''): ?>
            <a href="<?= url('user/items.php') ?>" class="btn btn-outline-secondary">Reset</a>
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
                        <th>Item Code</th>
                        <th>Item Name</th>
                        <th>Category</th>
                        <th>Location</th>
                        <th>On Hand Quantity</th>
                        <th>Unit</th>
                        <th>Status</th>
                        <th style="text-align: right;">View Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 36px;">
                                No items found matching your search.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($items as $item): ?>
                            <?php $isLow = (int)$item['quantity'] <= (int)$item['reorder_level']; ?>
                            <tr class="<?= $isLow ? 'row-warning' : '' ?>">
                                <td><strong><?= e($item['item_code']) ?></strong></td>
                                <td><?= e($item['item_name']) ?></td>
                                <td><span class="badge badge-secondary"><?= e($item['category']) ?></span></td>
                                <td><?= e($item['location']) ?></td>
                                <td>
                                    <strong style="color: <?= (int)$item['quantity'] === 0 ? 'var(--danger)' : ($isLow ? 'var(--warning)' : 'inherit') ?>;">
                                        <?= (int)$item['quantity'] ?>
                                    </strong>
                                </td>
                                <td><?= e($item['unit']) ?></td>
                                <td>
                                    <?php if ((int)$item['quantity'] === 0): ?>
                                        <span class="badge badge-danger">Out of Stock</span>
                                    <?php elseif ($isLow): ?>
                                        <span class="badge badge-warning">Low Stock</span>
                                    <?php else: ?>
                                        <span class="badge badge-success">In Stock</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right;">
                                    <a href="<?= url('user/item-details.php?id=' . (int)$item['id']) ?>" class="btn btn-sm btn-outline-primary">
                                        View Details &rarr;
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
