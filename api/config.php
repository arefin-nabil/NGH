<?php
/**
 * Configuration & Supabase PostgreSQL / SQLite Database Connection
 * Nobin Glamor House POS System
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ----------------------------------------------------
// APP & SHOP CONFIGURATION
// ----------------------------------------------------
define('SHOP_NAME', getenv('SHOP_NAME') ?: 'Nobin Glamor House');
define('SHOP_LOCATION', getenv('SHOP_LOCATION') ?: 'Yaakob Ali Master Tower, 2nd Floor, Maona, Sripur, Gazipur');
define('SHOP_MOBILE', getenv('SHOP_MOBILE') ?: '+880 1700-123456');
define('SHOP_EMAIL', getenv('SHOP_EMAIL') ?: 'contact@nobinglamorhouse.com');
define('DEVELOPER_WEBSITE', getenv('DEVELOPER_WEBSITE') ?: 'www.developers.com');
define('CURRENCY_SYMBOL', '৳');

// ----------------------------------------------------
// DATABASE CREDENTIALS (SUPABASE POSTGRESQL / SQLITE FALLBACK)
// ----------------------------------------------------
$db_host = getenv('DB_HOST') ?: 'db.itwvustqwgplgiemvaqk.supabase.co';
$db_port = getenv('DB_PORT') ?: '5432';
$db_name = getenv('DB_NAME') ?: 'postgres';
$db_user = getenv('DB_USER') ?: 'postgres';
$db_pass = getenv('DB_PASSWORD') ?: 'KEXxTzRrLkqyMCth';

$pdo = null;

try {
    // Attempt Supabase PostgreSQL Connection
    if (!empty($db_host) && !empty($db_pass)) {
        $dsn = "pgsql:host={$db_host};port={$db_port};dbname={$db_name};sslmode=require";
        $pdo = new PDO($dsn, $db_user, $db_pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } else {
        throw new Exception("Supabase credentials missing");
    }
} catch (Exception $e) {
    // Fallback to local SQLite database if offline or PDO pgsql driver not enabled in local PHP
    try {
        $sqlite_dir = __DIR__ . '/../data';
        if (!file_exists($sqlite_dir)) {
            @mkdir($sqlite_dir, 0777, true);
        }
        $sqlite_file = $sqlite_dir . '/nobin_fashion.sqlite';
        $pdo = new PDO("sqlite:" . $sqlite_file);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Auto-bootstrap SQLite tables if newly created
        init_sqlite_tables($pdo);
    } catch (Exception $ex) {
        die("Database Connection Error: " . $ex->getMessage());
    }
}

/**
 * Initialize SQLite database structure for offline/local testing
 */
function init_sqlite_tables($pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT 'seller',
            can_view_profit INTEGER DEFAULT 0,
            can_add_expenses INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS customers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            phone TEXT UNIQUE,
            email TEXT,
            address TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS suppliers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            company_name TEXT,
            phone TEXT,
            email TEXT,
            address TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            description TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS products (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            barcode TEXT UNIQUE NOT NULL,
            name TEXT NOT NULL,
            description TEXT,
            category_id INTEGER,
            supplier_id INTEGER,
            quantity INTEGER NOT NULL DEFAULT 0,
            min_stock_alert INTEGER NOT NULL DEFAULT 5,
            buying_price REAL NOT NULL DEFAULT 0.00,
            selling_price REAL NOT NULL DEFAULT 0.00,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS expense_categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT UNIQUE NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS expenses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            category_id INTEGER,
            user_id INTEGER,
            amount REAL NOT NULL DEFAULT 0.00,
            payment_method TEXT DEFAULT 'Cash',
            description TEXT,
            expense_date DATE DEFAULT (DATE('now')),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS sales (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            invoice_no TEXT UNIQUE NOT NULL,
            customer_id INTEGER,
            user_id INTEGER,
            subtotal REAL NOT NULL DEFAULT 0.00,
            discount_amount REAL NOT NULL DEFAULT 0.00,
            tax_amount REAL NOT NULL DEFAULT 0.00,
            grand_total REAL NOT NULL DEFAULT 0.00,
            total_profit REAL NOT NULL DEFAULT 0.00,
            payment_method TEXT DEFAULT 'Cash',
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS sale_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sale_id INTEGER,
            product_id INTEGER,
            product_name TEXT,
            product_barcode TEXT,
            quantity INTEGER NOT NULL DEFAULT 1,
            buying_price REAL NOT NULL DEFAULT 0.00,
            original_selling_price REAL NOT NULL DEFAULT 0.00,
            adjusted_selling_price REAL NOT NULL DEFAULT 0.00,
            discount_share REAL NOT NULL DEFAULT 0.00,
            final_sale_price REAL NOT NULL DEFAULT 0.00,
            row_profit REAL NOT NULL DEFAULT 0.00
        );

        CREATE TABLE IF NOT EXISTS sale_deletion_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sale_id INTEGER,
            invoice_no TEXT NOT NULL,
            deleted_by_user_id INTEGER,
            deleted_by_name TEXT,
            sale_grand_total REAL,
            reason TEXT,
            deleted_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
    ");

    // Insert guest customer if missing
    $stmt = $pdo->query("SELECT COUNT(*) FROM customers WHERE id = 1");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO customers (id, name, phone, email, address) VALUES (1, 'Guest Customer', '00000000000', 'guest@nobinglamorhouse.com', 'Walk-in Store')");
    }

    // Insert initial users if missing
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    if ($stmt->fetchColumn() == 0) {
        $adminPass = password_hash('admin123', PASSWORD_BCRYPT);
        $sellerPass = password_hash('seller123', PASSWORD_BCRYPT);
        $pdo->exec("INSERT INTO users (name, email, password_hash, role, can_view_profit, can_add_expenses) VALUES 
            ('Superadmin', 'admin@fashion.com', '{$adminPass}', 'superadmin', 1, 1),
            ('Rahim (Seller)', 'seller@fashion.com', '{$sellerPass}', 'seller', 0, 1)
        ");
    }

    // Insert initial categories if empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM categories");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO categories (name, description) VALUES 
            ('Panjabi & Pajama', 'Men traditional panjabi collections'),
            ('Formal Shirts', 'Men cotton formal shirts'),
            ('Denim Jeans', 'Men premium denim jeans'),
            ('Ladies Wear', 'Kurti, Salwar Kameez & Tops'),
            ('Accessories', 'Belts, Wallets, Ties & Perfumes')
        ");
    }

    // Insert initial suppliers if empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM suppliers");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO suppliers (name, company_name, phone, email, address) VALUES 
            ('Akbar Hossain', 'Fashion Craft BD', '01711223344', 'akbar@fashioncraft.com', 'Dhaka Trade Center, Chawkbazar'),
            ('Monir Fashion', 'Monir Apparels Ltd', '01899887766', 'sales@monirapparels.com', 'Islampur Market, Old Dhaka')
        ");
    }

    // Insert initial products if empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM products");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO products (barcode, name, description, category_id, supplier_id, quantity, min_stock_alert, buying_price, selling_price) VALUES
            ('890123456789', 'Premium Cotton Panjabi - Blue (L)', '100% Organic Cotton Traditional Panjabi', 1, 1, 25, 5, 850.00, 1450.00),
            ('890987654321', 'Silk Panjabi - Black (XL)', 'Royal Silk Festival Edition', 1, 1, 12, 3, 1500.00, 2650.00),
            ('890555666777', 'Slim Fit Cotton Shirt - White (M)', 'Breathable Office Formal Shirt', 2, 2, 40, 10, 500.00, 890.00),
            ('890444333222', 'Vintage Denim Jeans - Blue (32)', 'Stretchable Slim Fit Denim', 3, 2, 4, 5, 750.00, 1290.00),
            ('890111222333', 'Designer Linen Kurti - Red (M)', 'Printed Three Piece Designer Collection', 4, 1, 18, 5, 950.00, 1750.00),
            ('890999888777', 'Genuine Leather Belt - Tan', 'Pure Leather Buckle Belt', 5, 2, 50, 8, 250.00, 550.00)
        ");
    }

    // Insert expense categories if empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM expense_categories");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO expense_categories (name) VALUES 
            ('Shop Rent'),
            ('Electricity & Utility'),
            ('Staff Tea & Lunch'),
            ('Store Maintenance'),
            ('Packaging & Bags')
        ");
    }
}

// ----------------------------------------------------
// AUTH & PERMISSION HELPERS
// ----------------------------------------------------
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: index.php');
        exit;
    }
}

function current_user() {
    return $_SESSION['user'] ?? null;
}

function is_superadmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'superadmin';
}

function can_view_profit() {
    if (is_superadmin()) return true;
    return isset($_SESSION['can_view_profit']) && (bool)$_SESSION['can_view_profit'];
}

function can_add_expenses() {
    if (is_superadmin()) return true;
    return isset($_SESSION['can_add_expenses']) && (bool)$_SESSION['can_add_expenses'];
}

// ----------------------------------------------------
// UTILITY FUNCTIONS
// ----------------------------------------------------
function json_response($data, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function format_currency($amount) {
    return CURRENCY_SYMBOL . ' ' . number_format((float)$amount, 2);
}

function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}
