<?php
require_once __DIR__ . '/config.php';

if (!is_logged_in()) {
    json_response(['success' => false, 'message' => 'Unauthorized'], 401);
}

// Parse JSON payload or POST array
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

if (!$input) {
    $input = $_POST;
}

$customer_id = intval($input['customer_id'] ?? 1);
$items = $input['items'] ?? [];
$discount_amount = max(0, floatval($input['discount_amount'] ?? 0));
$payment_method = sanitize($input['payment_method'] ?? 'Cash');
$notes = sanitize($input['notes'] ?? '');
$user_id = $_SESSION['user_id'];

if (empty($items) || !is_array($items)) {
    json_response(['success' => false, 'message' => 'Cart is empty. Please add products to complete checkout.'], 400);
}

try {
    $pdo->beginTransaction();

    // 1. Calculate Cart Subtotal using Adjusted Selling Prices
    $cart_subtotal = 0.00;
    $validated_items = [];

    foreach ($items as $item) {
        $product_id = intval($item['id'] ?? 0);
        $qty = intval($item['quantity'] ?? 1);
        $adjusted_price = max(0, floatval($item['price'] ?? 0));

        if ($product_id <= 0 || $qty <= 0) {
            throw new Exception("Invalid item or quantity in cart.");
        }

        // Fetch Product details & lock row for stock safety
        $stmt = $pdo->prepare("SELECT id, name, barcode, quantity, buying_price, selling_price FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();

        if (!$product) {
            throw new Exception("Product ID {$product_id} not found in inventory.");
        }

        if ($product['quantity'] < $qty) {
            throw new Exception("Insufficient stock for '{$product['name']}'. Available: {$product['quantity']}, Requested: {$qty}");
        }

        $row_subtotal = $adjusted_price * $qty;
        $cart_subtotal += $row_subtotal;

        $validated_items[] = [
            'product' => $product,
            'quantity' => $qty,
            'original_selling_price' => floatval($product['selling_price']),
            'adjusted_selling_price' => $adjusted_price,
            'buying_price' => floatval($product['buying_price'])
        ];
    }

    if ($discount_amount > $cart_subtotal) {
        $discount_amount = $cart_subtotal; // Cap discount at subtotal
    }

    $grand_total = $cart_subtotal - $discount_amount;
    $invoice_no = 'NF-INV-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

    // 2. Insert master Sales record
    $stmtSales = $pdo->prepare("
        INSERT INTO sales (invoice_no, customer_id, user_id, subtotal, discount_amount, grand_total, total_profit, payment_method, notes)
        VALUES (?, ?, ?, ?, ?, ?, 0.00, ?, ?)
    ");
    $stmtSales->execute([$invoice_no, $customer_id, $user_id, $cart_subtotal, $discount_amount, $grand_total, $payment_method, $notes]);
    $sale_id = $pdo->lastInsertId();

    $total_sale_profit = 0.00;
    $saved_sale_items = [];

    // 3. Process Sale Items with Proportional Discount Allocation Formula
    $stmtItem = $pdo->prepare("
        INSERT INTO sale_items 
        (sale_id, product_id, product_name, product_barcode, quantity, buying_price, original_selling_price, adjusted_selling_price, discount_share, final_sale_price, row_profit)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmtUpdateStock = $pdo->prepare("UPDATE products SET quantity = quantity - ? WHERE id = ?");

    foreach ($validated_items as $vItem) {
        $product = $vItem['product'];
        $qty = $vItem['quantity'];
        $adj_price = $vItem['adjusted_selling_price'];
        $buy_price = $vItem['buying_price'];
        $orig_price = $vItem['original_selling_price'];

        // Formula: Discount Share per Item = Total Discount * (Adjusted Selling Price / Subtotal)
        $discount_share = 0.00;
        if ($cart_subtotal > 0) {
            $discount_share = round($discount_amount * ($adj_price / $cart_subtotal), 2);
        }

        // Formula: Final Item Sale Price = Adjusted Selling Price - Discount Share
        $final_sale_price = $adj_price - $discount_share;

        // Formula: Row Profit = (Final Item Sale Price - Buying Price) * Quantity
        $row_profit = round(($final_sale_price - $buy_price) * $qty, 2);
        $total_sale_profit += $row_profit;

        // Insert into sale_items
        $stmtItem->execute([
            $sale_id,
            $product['id'],
            $product['name'],
            $product['barcode'],
            $qty,
            $buy_price,
            $orig_price,
            $adj_price,
            $discount_share,
            $final_sale_price,
            $row_profit
        ]);

        // Deduct inventory quantity
        $stmtUpdateStock->execute([$qty, $product['id']]);

        $saved_sale_items[] = [
            'product_name' => $product['name'],
            'product_barcode' => $product['barcode'],
            'quantity' => $qty,
            'adjusted_selling_price' => $adj_price,
            'final_sale_price' => $final_sale_price,
            'total_item_amount' => $final_sale_price * $qty
        ];
    }

    // 4. Update sales table with exact calculated total_profit
    $stmtUpdateProfit = $pdo->prepare("UPDATE sales SET total_profit = ? WHERE id = ?");
    $stmtUpdateProfit->execute([$total_sale_profit, $sale_id]);

    $pdo->commit();

    // Fetch customer details for receipt
    $stmtCust = $pdo->prepare("SELECT name, phone, address FROM customers WHERE id = ?");
    $stmtCust->execute([$customer_id]);
    $customer_info = $stmtCust->fetch() ?: ['name' => 'Guest Customer', 'phone' => 'N/A', 'address' => 'Walk-in'];

    json_response([
        'success' => true,
        'message' => 'Transaction completed successfully!',
        'sale' => [
            'id' => $sale_id,
            'invoice_no' => $invoice_no,
            'date' => date('Y-m-d h:i A'),
            'seller' => $_SESSION['user_name'],
            'customer' => $customer_info,
            'subtotal' => $cart_subtotal,
            'discount_amount' => $discount_amount,
            'grand_total' => $grand_total,
            'payment_method' => $payment_method,
            'notes' => $notes,
            'items' => $saved_sale_items,
            'shop_name' => SHOP_NAME,
            'shop_location' => SHOP_LOCATION,
            'shop_mobile' => SHOP_MOBILE,
            'developer_website' => DEVELOPER_WEBSITE
        ]
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_response(['success' => false, 'message' => 'Checkout Failed: ' . $e->getMessage()], 500);
}
