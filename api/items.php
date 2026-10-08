<?php
/**
 * Items Search & Catalog API Endpoint
 * Warehouse Management System
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';

// API Authentication Guard
if (!is_logged_in()) {
    json_response([
        'success' => false,
        'message' => 'Unauthorized: Please authenticate to access this API endpoint.'
    ], 401);
}

// Ensure request returns JSON
header('Content-Type: application/json; charset=utf-8');

try {
    $db = get_db();

    // Query parameters
    $query = trim((string)($_GET['q'] ?? ''));
    $category = trim((string)($_GET['category'] ?? ''));
    $limit = min(max((int)($_GET['limit'] ?? 50), 1), 100);

    $sql = '
        SELECT id, item_code, item_name, category, location, quantity, unit, reorder_level, updated_at
        FROM items
        WHERE 1=1
    ';
    $params = [];

    if ($query !== '') {
        $sql .= ' AND (item_code LIKE ? OR item_name LIKE ? OR location LIKE ?)';
        $searchTerm = "%{$query}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    if ($category !== '') {
        $sql .= ' AND category = ?';
        $params[] = $category;
    }

    $sql .= ' ORDER BY item_code ASC LIMIT ' . $limit;

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll();

    // Format output
    $formattedData = array_map(function ($item) {
        return [
            'id'            => (int)$item['id'],
            'item_code'     => (string)$item['item_code'],
            'item_name'     => (string)$item['item_name'],
            'category'      => (string)$item['category'],
            'location'      => (string)$item['location'],
            'quantity'      => (int)$item['quantity'],
            'unit'          => (string)$item['unit'],
            'reorder_level' => (int)$item['reorder_level'],
            'is_low_stock'  => (int)$item['quantity'] <= (int)$item['reorder_level'],
            'updated_at'    => (string)$item['updated_at'],
        ];
    }, $items);

    json_response([
        'success' => true,
        'count'   => count($formattedData),
        'data'    => $formattedData
    ]);

} catch (Exception $e) {
    error_log("API Items error: " . $e->getMessage());
    json_response([
        'success' => false,
        'message' => 'Internal server error processing inventory query.'
    ], 500);
}
