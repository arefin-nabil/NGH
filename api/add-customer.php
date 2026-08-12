<?php
require_once __DIR__ . '/config.php';

if (!is_logged_in()) {
    json_response(['success' => false, 'message' => 'Unauthorized'], 401);
}

$name = trim($_POST['name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$email = trim($_POST['email'] ?? '');
$address = trim($_POST['address'] ?? '');

if (empty($name) || empty($phone)) {
    json_response(['success' => false, 'message' => 'Customer name and phone number are required.'], 400);
}

try {
    // Check if phone already exists
    $stmt = $pdo->prepare("SELECT id FROM customers WHERE phone = ? LIMIT 1");
    $stmt->execute([$phone]);
    if ($stmt->fetch()) {
        json_response(['success' => false, 'message' => 'A customer with this phone number already exists.'], 400);
    }

    $stmt = $pdo->prepare("INSERT INTO customers (name, phone, email, address) VALUES (?, ?, ?, ?)");
    $stmt->execute([$name, $phone, $email, $address]);
    $new_id = $pdo->lastInsertId();

    json_response([
        'success' => true,
        'message' => 'Customer added successfully!',
        'customer' => [
            'id' => $new_id,
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
            'address' => $address
        ]
    ]);
} catch (Exception $e) {
    json_response(['success' => false, 'message' => 'Error adding customer: ' . $e->getMessage()], 500);
}
