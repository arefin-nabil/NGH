<?php
require_once __DIR__ . '/config.php';

if (!is_logged_in()) {
    json_response(['success' => false, 'message' => 'Unauthorized'], 401);
}

$barcode = trim($_GET['barcode'] ?? '');
$query = trim($_GET['q'] ?? '');
$category_id = intval($_GET['category_id'] ?? 0);

try {
    if (!empty($barcode)) {
        // Exact Barcode lookup
        $stmt = $pdo->prepare("
            SELECT p.*, c.name as category_name, s.name as supplier_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN suppliers s ON p.supplier_id = s.id
            WHERE p.barcode = ?
            LIMIT 1
        ");
        $stmt->execute([$barcode]);
        $product = $stmt->fetch();

        if ($product) {
            json_response(['success' => true, 'exact_match' => true, 'product' => $product]);
        } else {
            json_response(['success' => false, 'message' => 'Product not found for barcode: ' . $barcode], 404);
        }
    } else {
        // Multi-field search
        $sql = "
            SELECT p.*, c.name as category_name, s.name as supplier_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN suppliers s ON p.supplier_id = s.id
            WHERE 1=1
        ";
        $params = [];

        if (!empty($query)) {
            $sql .= " AND (p.barcode LIKE ? OR LOWER(p.name) LIKE ? OR LOWER(p.description) LIKE ?)";
            $searchTerm = "%{$query}%";
            $params[] = $searchTerm;
            $params[] = strtolower($searchTerm);
            $params[] = strtolower($searchTerm);
        }

        if ($category_id > 0) {
            $sql .= " AND p.category_id = ?";
            $params[] = $category_id;
        }

        $sql .= " ORDER BY p.name ASC LIMIT 30";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll();

        json_response(['success' => true, 'exact_match' => false, 'products' => $products]);
    }
} catch (Exception $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 500);
}
