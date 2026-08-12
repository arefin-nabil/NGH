<?php
require_once __DIR__ . '/config.php';

$action = $_REQUEST['action'] ?? '';

if ($action === 'login') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        json_response(['success' => false, 'message' => 'Please fill in both email and password.'], 400);
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['can_view_profit'] = (bool)$user['can_view_profit'];
            $_SESSION['can_add_expenses'] = (bool)$user['can_add_expenses'];
            $_SESSION['user'] = [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'],
                'can_view_profit' => (bool)$user['can_view_profit'],
                'can_add_expenses' => (bool)$user['can_add_expenses']
            ];

            json_response(['success' => true, 'message' => 'Login successful! Redirecting...', 'redirect' => 'pos.php']);
        } else {
            json_response(['success' => false, 'message' => 'Invalid email or password credentials.'], 401);
        }
    } catch (Exception $e) {
        json_response(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
    }
} elseif ($action === 'logout') {
    session_unset();
    session_destroy();
    header('Location: ../index.php');
    exit;
} else {
    json_response(['success' => false, 'message' => 'Invalid request action.'], 400);
}
