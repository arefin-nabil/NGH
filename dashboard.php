<?php
$pageTitle = "Shop Dashboard";
require_once __DIR__ . '/includes/header.php';

// Fetch Metrics for Today
$todayStr = date('Y-m-d');

// 1. Today's Sales Count & Total Revenue
$stmtSales = $pdo->prepare("
    SELECT COUNT(*) as total_count, COALESCE(SUM(grand_total), 0) as total_revenue, COALESCE(SUM(total_profit), 0) as total_profit
    FROM sales
    WHERE DATE(created_at) = ?
");
$stmtSales->execute([$todayStr]);
$todayMetrics = $stmtSales->fetch();

// 2. Today's Total Expenses
$stmtExpense = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0) as total_expense
    FROM expenses
    WHERE expense_date = ?
");
$stmtExpense->execute([$todayStr]);
$todayExpense = $stmtExpense->fetchColumn();

// 3. Low Stock Items List
$stmtLowStock = $pdo->query("
    SELECT p.*, c.name as category_name, s.name as supplier_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN suppliers s ON p.supplier_id = s.id
    WHERE p.quantity <= p.min_stock_alert
    ORDER BY p.quantity ASC
    LIMIT 10
");
$lowStockItems = $stmtLowStock->fetchAll();

// 4. Recent Transactions (Activity Feed)
$stmtRecent = $pdo->query("
    SELECT s.*, c.name as customer_name, u.name as seller_name
    FROM sales s
    LEFT JOIN customers c ON s.customer_id = c.id
    LEFT JOIN users u ON s.user_id = u.id
    ORDER BY s.id DESC
    LIMIT 6
");
$recentSales = $stmtRecent->fetchAll();
?>

<!-- Metrics Grid -->
<div class="grid-4">
    <div class="stat-card">
        <div>
            <div class="stat-label">Today's Sales</div>
            <div class="stat-value"><?= number_format($todayMetrics['total_count']) ?> <span style="font-size:0.9rem; font-weight:normal; color:var(--text-muted);">orders</span></div>
        </div>
        <div class="stat-icon sky">
            <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
        </div>
    </div>

    <div class="stat-card">
        <div>
            <div class="stat-label">Today's Revenue</div>
            <div class="stat-value"><?= format_currency($todayMetrics['total_revenue']) ?></div>
        </div>
        <div class="stat-icon gold">
            <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
    </div>

    <?php if (can_view_profit()): ?>
    <div class="stat-card">
        <div>
            <div class="stat-label">Today's Net Profit</div>
            <div class="stat-value" style="color: var(--accent-emerald);"><?= format_currency($todayMetrics['total_profit'] - $todayExpense) ?></div>
        </div>
        <div class="stat-icon emerald">
            <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
        </div>
    </div>
    <?php endif; ?>

    <div class="stat-card">
        <div>
            <div class="stat-label">Today's Expenses</div>
            <div class="stat-value" style="color: var(--accent-rose);"><?= format_currency($todayExpense) ?></div>
        </div>
        <div class="stat-icon rose">
            <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
    </div>
</div>

<div class="grid-2">
    <!-- Low Stock Alert Card -->
    <div class="card">
        <div class="card-header">
            <div class="card-title" style="display:flex; align-items:center; gap:0.5rem;">
                <span class="badge badge-danger">Alert</span> Low Stock Products
            </div>
            <a href="products.php" class="btn btn-secondary btn-sm">Manage Products</a>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Supplier</th>
                        <th>Qty Left</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($lowStockItems)): ?>
                        <tr><td colspan="4" style="text-align:center; color:var(--text-muted); padding:2rem;">All product stocks are healthy!</td></tr>
                    <?php else: ?>
                        <?php foreach ($lowStockItems as $item): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($item['name']) ?></strong><br>
                                    <small style="color:var(--text-muted);">BC: <?= htmlspecialchars($item['barcode']) ?></small>
                                </td>
                                <td><?= htmlspecialchars($item['supplier_name'] ?? 'N/A') ?></td>
                                <td><strong style="color:var(--accent-rose);"><?= $item['quantity'] ?></strong></td>
                                <td><span class="badge badge-danger">Low Stock</span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Sales Activity Feed -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">Recent POS Transactions</div>
            <a href="sales-history.php" class="btn btn-secondary btn-sm">View All Sales</a>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Customer</th>
                        <th>Total</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentSales)): ?>
                        <tr><td colspan="4" style="text-align:center; color:var(--text-muted); padding:2rem;">No sales recorded today yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentSales as $sale): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($sale['invoice_no']) ?></strong></td>
                                <td><?= htmlspecialchars($sale['customer_name'] ?? 'Guest Customer') ?></td>
                                <td><strong style="color:var(--accent-gold);"><?= format_currency($sale['grand_total']) ?></strong></td>
                                <td><small style="color:var(--text-muted);"><?= date('h:i A', strtotime($sale['created_at'])) ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
