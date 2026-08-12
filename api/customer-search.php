<?php
require_once __DIR__ . '/config.php';

if (!is_logged_in()) {
    json_response(['success' => false, 'message' => 'Unauthorized'], 401);
}

$query = trim($_GET['q'] ?? '');

try {
    if (empty($query)) {
        // Return recent 10 customers
        $stmt = $pdo->query("SELECT id, name, phone, email, address FROM customers ORDER BY id ASC LIMIT 10");
        $customers = $stmt->fetchAll();
    } else {
        $stmt = $pdo->prepare("SELECT id, name, phone, email, address FROM customers WHERE phone LIKE ? OR LOWER(name) LIKE ? ORDER BY name ASC LIMIT 15");
        $searchTerm = "%{$query}%";
        $stmt->execute([$searchTerm, strtolower($searchTerm)]);
        $customers = $stmt->fetchAll();
    }

    json_response(['success' => true, 'customers' => $customers]);
} catch (Exception $e) {
    json_response(['success' => false, 'message' => $e->getMessage()], 500);
}
