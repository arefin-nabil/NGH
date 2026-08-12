<?php
require_once __DIR__ . '/config.php';

if (!is_logged_in()) {
    json_response(['success' => false, 'message' => 'Unauthorized'], 401);
}

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    json_response(['success' => false, 'message' => 'Invalid sale ID.'], 400);
}

try {
    $stmt = $pdo->prepare("
        SELECT s.*, c.name as customer_name, c.phone as customer_phone, u.name as seller_name
        FROM sales s
        LEFT JOIN customers c ON s.customer_id = c.id
        LEFT JOIN users u ON s.user_id = u.id
        WHERE s.id = ?
    ");
    $stmt->execute([$id]);
    $sale = $stmt->fetch();

    if (!$sale) {
        json_response(['success' => false, 'message' => 'Sale not found.'], 404);
    }

    $stmtItems = $pdo->prepare("SELECT * FROM sale_items WHERE sale_id = ?");
    $stmtItems->execute([$id]);
    $sale['items'] = $stmtItems->fetchAll();

    json_response(['success' => true, 'sale' => $sale]);
} catch (Exception $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 500);
}
