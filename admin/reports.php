<?php
/**
 * Admin Reports & Analytics
 * Warehouse Management System
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';
require_role('admin');

$db = get_db();

// 1. Filter Parameters
$filterItem = (int)($_GET['item_id'] ?? 0);
$filterCategory = trim((string)($_GET['category'] ?? ''));
$filterType = strtoupper(trim((string)($_GET['movement_type'] ?? '')));
$filterStartDate = trim((string)($_GET['start_date'] ?? ''));
$filterEndDate = trim((string)($_GET['end_date'] ?? ''));

// Build SQL where clause
$whereConditions = ['1=1'];
$params = [];

if ($filterItem > 0) {
    $whereConditions[] = 'sm.item_id = ?';
    $params[] = $filterItem;
}

if ($filterCategory !== '') {
    $whereConditions[] = 'i.category = ?';
    $params[] = $filterCategory;
}

if (in_array($filterType, ['IN', 'OUT'], true)) {
    $whereConditions[] = 'sm.movement_type = ?';
    $params[] = $filterType;
}

if ($filterStartDate !== '') {
    $whereConditions[] = 'sm.movement_date >= ?';
    $params[] = $filterStartDate;
}

if ($filterEndDate !== '') {
    $whereConditions[] = 'sm.movement_date <= ?';
    $params[] = $filterEndDate;
}

$whereClause = implode(' AND ', $whereConditions);

// 2. Calculate Summary Metrics (Total IN, Total OUT, Net Movement)
$summarySql = "
    SELECT 
        COALESCE(SUM(CASE WHEN sm.movement_type = 'IN' THEN sm.quantity ELSE 0 END), 0) AS total_in,
        COALESCE(SUM(CASE WHEN sm.movement_type = 'OUT' THEN sm.quantity ELSE 0 END), 0) AS total_out
    FROM stock_movements sm
    JOIN items i ON sm.item_id = i.id
    WHERE {$whereClause}
";
$sumStmt = $db->prepare($summarySql);
$sumStmt->execute($params);
$summary = $sumStmt->fetch();

$totalIn = (int)($summary['total_in'] ?? 0);
$totalOut = (int)($summary['total_out'] ?? 0);
$netMovement = $totalIn - $totalOut;

// 3. Fetch Detailed Rows
$reportSql = "
    SELECT sm.id, sm.movement_type, sm.quantity, sm.movement_date, sm.reference_note,
           i.item_code, i.item_name, i.category, i.unit,
           u.username, u.full_name
    FROM stock_movements sm
    JOIN items i ON sm.item_id = i.id
    JOIN users u ON sm.created_by = u.id
    WHERE {$whereClause}
    ORDER BY sm.movement_date DESC, sm.id DESC
";
$repStmt = $db->prepare($reportSql);
$repStmt->execute($params);
$reportRows = $repStmt->fetchAll();

// Dropdown options
$itemsList = $db->query('SELECT id, item_code, item_name FROM items ORDER BY item_code ASC')->fetchAll();
$categoriesList = $db->query('SELECT DISTINCT category FROM items ORDER BY category ASC')->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Stock Reports';
require_once __DIR__ . '/../includes/header.php';
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
    <div>
        <h2 style="font-size:18px; margin:0; font-weight:700;">Stock Movement Analysis & Audit Report</h2>
        <p style="color:var(--text-secondary); margin:4px 0 0; font-size:13px;">Filter inventory turnover, net changes, and generate clean print-ready reports.</p>
    </div>
    <div>
        <button type="button" class="btn btn-outline-primary" onclick="window.print();">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
            Print / Export PDF
        </button>
    </div>
</div>

<!-- Filters Bar -->
<form method="GET" action="<?= url('admin/reports.php') ?>" class="filter-bar">
    <div class="filter-item">
        <label class="form-label" for="report_item">Item</label>
        <select id="report_item" name="item_id" class="form-control">
            <option value="">All Items</option>
            <?php foreach ($itemsList as $it): ?>
                <option value="<?= (int)$it['id'] ?>" <?= $filterItem === (int)$it['id'] ? 'selected' : '' ?>>
                    <?= e($it['item_code']) ?> - <?= e($it['item_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="filter-item">
        <label class="form-label" for="report_cat">Category</label>
        <select id="report_cat" name="category" class="form-control">
            <option value="">All Categories</option>
            <?php foreach ($categoriesList as $c): ?>
                <option value="<?= e($c) ?>" <?= $filterCategory === $c ? 'selected' : '' ?>><?= e($c) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="filter-item" style="max-width: 140px;">
        <label class="form-label" for="report_type">Type</label>
        <select id="report_type" name="movement_type" class="form-control">
            <option value="">All</option>
            <option value="IN" <?= $filterType === 'IN' ? 'selected' : '' ?>>IN only</option>
            <option value="OUT" <?= $filterType === 'OUT' ? 'selected' : '' ?>>OUT only</option>
        </select>
    </div>

    <div class="filter-item">
        <label class="form-label" for="report_start">Start Date</label>
        <input type="date" id="report_start" name="start_date" class="form-control" value="<?= e($filterStartDate) ?>">
    </div>

    <div class="filter-item">
        <label class="form-label" for="report_end">End Date</label>
        <input type="date" id="report_end" name="end_date" class="form-control" value="<?= e($filterEndDate) ?>">
    </div>

    <div class="filter-actions">
        <button type="submit" class="btn btn-primary">Run Report</button>
        <?php if ($filterItem > 0 || $filterCategory !== '' || $filterType !== '' || $filterStartDate !== '' || $filterEndDate !== ''): ?>
            <a href="<?= url('admin/reports.php') ?>" class="btn btn-outline-secondary">Reset</a>
        <?php endif; ?>
    </div>
</form>

<!-- Summary Metrics Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon stat-icon-emerald">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="17 1 21 5 17 9"></polyline><path d="M3 11V9a4 4 0 0 1 4-4h14"></path></svg>
        </div>
        <div class="stat-details">
            <span class="stat-label">Total Stock IN</span>
            <span class="stat-value" style="color: var(--success);"><?= number_format($totalIn) ?></span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon stat-icon-rose">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="7 23 3 19 7 15"></polyline><path d="M21 13v2a4 4 0 0 1-4 4H3"></path></svg>
        </div>
        <div class="stat-details">
            <span class="stat-label">Total Stock OUT</span>
            <span class="stat-value" style="color: var(--danger);"><?= number_format($totalOut) ?></span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon stat-icon-blue">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 14 14"></polyline></svg>
        </div>
        <div class="stat-details">
            <span class="stat-label">Net Movement (IN - OUT)</span>
            <span class="stat-value" style="color: <?= $netMovement >= 0 ? 'var(--primary)' : 'var(--danger)' ?>;">
                <?= ($netMovement > 0 ? '+' : '') . number_format($netMovement) ?>
            </span>
        </div>
    </div>
</div>

<!-- Detailed Movement Table -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Movement Log Detailed View (<?= count($reportRows) ?> entries)</h3>
        <span style="font-size:12px; color:var(--text-muted);">Generated: <?= date('Y-m-d H:i') ?></span>
    </div>
    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th class="nowrap">Date</th>
                        <th class="nowrap">Item Code</th>
                        <th>Item Name</th>
                        <th>Category</th>
                        <th class="nowrap" style="color: var(--success); text-align: right;">IN</th>
                        <th class="nowrap" style="color: var(--danger); text-align: right;">OUT</th>
                        <th>Reference / Notes</th>
                        <th class="nowrap">Operator</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reportRows)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 36px;">
                                No records found for the selected reporting criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($reportRows as $row): ?>
                            <tr>
                                <td class="nowrap"><?= format_date($row['movement_date'], 'Y-m-d') ?></td>
                                <td class="nowrap"><strong><?= e($row['item_code']) ?></strong></td>
                                <td><?= e($row['item_name']) ?></td>
                                <td><span class="badge badge-secondary"><?= e($row['category']) ?></span></td>
                                <td class="nowrap" style="text-align: right;">
                                    <?php if ($row['movement_type'] === 'IN'): ?>
                                        <strong style="color: var(--success);">+<?= number_format((int)$row['quantity']) ?>&nbsp;<?= e($row['unit']) ?></strong>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td class="nowrap" style="text-align: right;">
                                    <?php if ($row['movement_type'] === 'OUT'): ?>
                                        <strong style="color: var(--danger);">-<?= number_format((int)$row['quantity']) ?>&nbsp;<?= e($row['unit']) ?></strong>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?= e($row['reference_note'] ?? '-') ?></td>
                                <td class="nowrap"><?= e($row['username']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
