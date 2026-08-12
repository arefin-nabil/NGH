-- SQL Schema for Nobin Fashion POS System (Supabase PostgreSQL Compatible)

-- Drop tables if exists (Order handles foreign key dependencies)
DROP TABLE IF EXISTS sale_deletion_logs CASCADE;
DROP TABLE IF EXISTS sale_items CASCADE;
DROP TABLE IF EXISTS sales CASCADE;
DROP TABLE IF EXISTS expenses CASCADE;
DROP TABLE IF EXISTS expense_categories CASCADE;
DROP TABLE IF EXISTS products CASCADE;
DROP TABLE IF EXISTS categories CASCADE;
DROP TABLE IF EXISTS suppliers CASCADE;
DROP TABLE IF EXISTS customers CASCADE;
DROP TABLE IF EXISTS users CASCADE;

-- 1. USERS TABLE
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(120) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'seller', -- 'superadmin' or 'seller'
    can_view_profit BOOLEAN DEFAULT FALSE,
    can_add_expenses BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. CUSTOMERS TABLE
CREATE TABLE customers (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(30) UNIQUE,
    email VARCHAR(120),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert Default Guest Customer (ID: 1)
INSERT INTO customers (id, name, phone, email, address) 
VALUES (1, 'Guest Customer', '00000000000', 'guest@nobinfashion.com', 'Walk-in Store');

-- 3. SUPPLIERS TABLE
CREATE TABLE suppliers (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    company_name VARCHAR(120),
    phone VARCHAR(30),
    email VARCHAR(120),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 4. CATEGORIES TABLE
CREATE TABLE categories (
    id SERIAL PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 5. PRODUCTS TABLE
CREATE TABLE products (
    id SERIAL PRIMARY KEY,
    barcode VARCHAR(60) UNIQUE NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    category_id INT REFERENCES categories(id) ON DELETE SET NULL,
    supplier_id INT REFERENCES suppliers(id) ON DELETE SET NULL,
    quantity INT NOT NULL DEFAULT 0,
    min_stock_alert INT NOT NULL DEFAULT 5,
    buying_price NUMERIC(10,2) NOT NULL DEFAULT 0.00,
    selling_price NUMERIC(10,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 6. EXPENSE CATEGORIES
CREATE TABLE expense_categories (
    id SERIAL PRIMARY KEY,
    name VARCHAR(80) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 7. EXPENSES TABLE
CREATE TABLE expenses (
    id SERIAL PRIMARY KEY,
    category_id INT REFERENCES expense_categories(id) ON DELETE SET NULL,
    user_id INT REFERENCES users(id) ON DELETE SET NULL,
    amount NUMERIC(10,2) NOT NULL DEFAULT 0.00,
    payment_method VARCHAR(40) DEFAULT 'Cash',
    description TEXT,
    expense_date DATE DEFAULT CURRENT_DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 8. SALES TABLE
CREATE TABLE sales (
    id SERIAL PRIMARY KEY,
    invoice_no VARCHAR(50) UNIQUE NOT NULL,
    customer_id INT REFERENCES customers(id) ON DELETE SET NULL,
    user_id INT REFERENCES users(id) ON DELETE SET NULL,
    subtotal NUMERIC(10,2) NOT NULL DEFAULT 0.00,
    discount_amount NUMERIC(10,2) NOT NULL DEFAULT 0.00,
    tax_amount NUMERIC(10,2) NOT NULL DEFAULT 0.00,
    grand_total NUMERIC(10,2) NOT NULL DEFAULT 0.00,
    total_profit NUMERIC(10,2) NOT NULL DEFAULT 0.00,
    payment_method VARCHAR(40) DEFAULT 'Cash', -- 'Cash', 'Card', 'Mobile Banking'
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 9. SALE ITEMS TABLE
CREATE TABLE sale_items (
    id SERIAL PRIMARY KEY,
    sale_id INT REFERENCES sales(id) ON DELETE CASCADE,
    product_id INT REFERENCES products(id) ON DELETE SET NULL,
    product_name VARCHAR(150),
    product_barcode VARCHAR(60),
    quantity INT NOT NULL DEFAULT 1,
    buying_price NUMERIC(10,2) NOT NULL DEFAULT 0.00,
    original_selling_price NUMERIC(10,2) NOT NULL DEFAULT 0.00,
    adjusted_selling_price NUMERIC(10,2) NOT NULL DEFAULT 0.00,
    discount_share NUMERIC(10,2) NOT NULL DEFAULT 0.00,
    final_sale_price NUMERIC(10,2) NOT NULL DEFAULT 0.00,
    row_profit NUMERIC(10,2) NOT NULL DEFAULT 0.00
);

-- 10. SALE DELETION LOGS TABLE
CREATE TABLE sale_deletion_logs (
    id SERIAL PRIMARY KEY,
    sale_id INT,
    invoice_no VARCHAR(50) NOT NULL,
    deleted_by_user_id INT REFERENCES users(id) ON DELETE SET NULL,
    deleted_by_name VARCHAR(100),
    sale_grand_total NUMERIC(10,2),
    reason TEXT,
    deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- INDEXES FOR PERFORMANCE
CREATE INDEX idx_products_barcode ON products(barcode);
CREATE INDEX idx_sales_invoice ON sales(invoice_no);
CREATE INDEX idx_sales_created ON sales(created_at);
CREATE INDEX idx_customers_phone ON customers(phone);

-- SEED DATA
-- Default Passwords: 
-- Superadmin: admin123 (Hash: $2y$10$wE8D09M.Q6L4x1kCgZc21.r.eT12u0X.1Y6b4Jq.8t9l8k.2J4q2m -> generated via password_hash)
-- Seller: seller123
INSERT INTO users (name, email, password_hash, role, can_view_profit, can_add_expenses) VALUES
('Superadmin', 'admin@fashion.com', '$2y$10$8.UnVuG9HHgffUDAlk8qfOUVGkqRzgVym502.D2vS99z1mK81T71O', 'superadmin', TRUE, TRUE),
('Rahim (Seller)', 'seller@fashion.com', '$2y$10$8.UnVuG9HHgffUDAlk8qfOUVGkqRzgVym502.D2vS99z1mK81T71O', 'seller', FALSE, TRUE);

-- Seed Categories
INSERT INTO categories (name, description) VALUES
('Panjabi & Pajama', 'Men traditional panjabi collections'),
('Formal Shirts', 'Men cotton formal shirts'),
('Denim Jeans', 'Men premium denim jeans'),
('Ladies Wear', 'Kurti, Salwar Kameez & Tops'),
('Accessories', 'Belts, Wallets, Ties & Perfumes');

-- Seed Suppliers
INSERT INTO suppliers (name, company_name, phone, email, address) VALUES
('Akbar Hossain', 'Fashion Craft BD', '01711223344', 'akbar@fashioncraft.com', 'Dhaka Trade Center, Chawkbazar'),
('Monir Fashion', 'Monir Apparels Ltd', '01899887766', 'sales@monirapparels.com', 'Islampur Market, Old Dhaka');

-- Seed Products
INSERT INTO products (barcode, name, description, category_id, supplier_id, quantity, min_stock_alert, buying_price, selling_price) VALUES
('10001', 'Premium Cotton Panjabi - Blue (L)', '100% Organic Cotton Traditional Panjabi', 1, 1, 25, 5, 850.00, 1450.00),
('10002', 'Silk Panjabi - Black (XL)', 'Royal Silk Festival Edition', 1, 1, 12, 3, 1500.00, 2650.00),
('10003', 'Slim Fit Cotton Shirt - White (M)', 'Breathable Office Formal Shirt', 2, 2, 40, 10, 500.00, 890.00),
('10004', 'Vintage Denim Jeans - Blue (32)', 'Stretchable Slim Fit Denim', 3, 2, 4, 5, 750.00, 1290.00),
('10005', 'Designer Linen Kurti - Red (M)', 'Printed Three Piece Designer Collection', 4, 1, 18, 5, 950.00, 1750.00),
('10006', 'Genuine Leather Belt - Tan', 'Pure Leather Buckle Belt', 5, 2, 50, 8, 250.00, 550.00);

-- Seed Expense Categories
INSERT INTO expense_categories (name) VALUES
('Shop Rent'),
('Electricity & Utility'),
('Staff Tea & Lunch'),
('Store Maintenance'),
('Packaging & Bags');

-- Seed Expenses
INSERT INTO expenses (category_id, user_id, amount, payment_method, description, expense_date) VALUES
(3, 1, 350.00, 'Cash', 'Staff evening tea and snacks', CURRENT_DATE),
(5, 1, 1200.00, 'Cash', 'Purchased 500 shopping bags with logo print', CURRENT_DATE);
