<?php
require_once __DIR__ . '/config.php';

if (!is_logged_in()) {
    json_response(['success' => false, 'message' => 'Unauthorized'], 401);
}

if (!is_superadmin()) {
    json_response(['success' => false, 'message' => 'Access Denied! Sale deletion is restricted to Superadmin only.'], 403);
}

$sale_id = intval($_POST['sale_id'] ?? 0);
$reason = sanitize($_POST['reason'] ?? 'Superadmin manual deletion');

if ($sale_id <= 0) {
    json_response(['success' => false, 'message' => 'Invalid Sale ID.'], 400);
}

try {
    $pdo->beginTransaction();

    // 1. Fetch sale master details
    $stmt = $pdo->prepare("SELECT * FROM sales WHERE id = ?");
    $stmt->execute([$sale_id]);
    $sale = $stmt->fetch();

    if (!$sale) {
        throw new Exception("Sale record not found.");
    }

    // 2. Fetch sale items to restore product inventory
    $stmtItems = $pdo->prepare("SELECT product_id, quantity, product_name FROM sale_items WHERE sale_id = ?");
    $stmtItems->execute([$sale_id]);
    $items = $stmtItems->fetchAll();

    $stmtRestoreStock = $pdo->prepare("UPDATE products SET quantity = quantity + ? WHERE id = ?");

    foreach ($items as $item) {
        if ($item['product_id']) {
            $stmtRestoreStock->execute([$item['quantity'], $item['product_id']]);
        }
    }

    // 3. Log sale deletion into sale_deletion_logs
    $stmtLog = $pdo->prepare("
        INSERT INTO sale_deletion_logs (sale_id, invoice_no, deleted_by_user_id, deleted_by_name, sale_grand_total, reason)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmtLog->execute([
        $sale['id'],
        $sale['invoice_no'],
        $_SESSION['user_id'],
        $_SESSION['user_name'],
        $sale['grand_total'],
        $reason
    ]);

    // 4. Delete sale record (sale_items deleted via CASCADE or manual delete)
    $stmtDelItems = $pdo->prepare("DELETE FROM sale_items WHERE sale_id = ?");
    $stmtDelItems->execute([$sale_id]);

    $stmtDelSale = $pdo->prepare("DELETE FROM sales WHERE id = ?");
    $stmtDelSale->execute([$sale_id]);

    $pdo->commit();

    json_response([
        'success' => true,
        'message' => "Invoice {$sale['invoice_no']} deleted successfully. Stock quantities restored!"
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_response(['success' => false, 'message' => 'Error deleting sale: ' . $e->getMessage()], 500);
}
