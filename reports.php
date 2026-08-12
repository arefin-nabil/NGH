<?php
$pageTitle = "Financial Performance & Profit Reports";
require_once __DIR__ . '/includes/header.php';

if (!can_view_profit()) {
    echo '<div class="card"><div style="color:var(--accent-rose); font-weight:600;">Access Denied! Profit reports are restricted to Superadmin and authorized personnel.</div></div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Date Range Filters
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// 1. Sales & Revenue & COGS Calculations
$stmtSales = $pdo->prepare("
    SELECT 
        COUNT(id) as total_orders,
        COALESCE(SUM(subtotal), 0) as raw_subtotal,
        COALESCE(SUM(discount_amount), 0) as total_discounts,
        COALESCE(SUM(grand_total), 0) as total_revenue,
        COALESCE(SUM(total_profit), 0) as gross_profit
    FROM sales
    WHERE DATE(created_at) BETWEEN ? AND ?
");
$stmtSales->execute([$startDate, $endDate]);
$salesReport = $stmtSales->fetch();

$totalRevenue = floatval($salesReport['total_revenue']);
$grossProfit = floatval($salesReport['gross_profit']);
$cogs = $totalRevenue - $grossProfit; // Cost of Goods Sold

// 2. Expenses Calculation for period
$stmtExp = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0) as total_expense
    FROM expenses
    WHERE expense_date BETWEEN ? AND ?
");
$stmtExp->execute([$startDate, $endDate]);
$totalExpenses = floatval($stmtExp->fetchColumn());

// 3. Net Profit = Gross Profit - Total Expenses
$netProfit = $grossProfit - $totalExpenses;

// 4. Category breakdown report
$stmtCatBreakdown = $pdo->prepare("
    SELECT c.name as category_name, SUM(si.quantity) as items_sold, SUM(si.final_sale_price * si.quantity) as category_revenue
    FROM sale_items si
    JOIN sales s ON si.sale_id = s.id
    LEFT JOIN products p ON si.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE DATE(s.created_at) BETWEEN ? AND ?
    GROUP BY c.id, c.name
    ORDER BY category_revenue DESC
");
$stmtCatBreakdown->execute([$startDate, $endDate]);
$catBreakdown = $stmtCatBreakdown->fetchAll();
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;" class="no-print">
    <form method="GET" style="display:flex; gap:0.5rem; align-items:center;">
        <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($startDate) ?>">
        <span style="color:var(--text-muted);">to</span>
        <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($endDate) ?>">
        <button type="submit" class="btn btn-secondary">Generate Statement</button>
    </form>

    <button class="btn btn-primary" onclick="window.print()">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
        Print Financial Report
    </button>
</div>

<!-- Financial Summary Cards -->
<div class="grid-4">
    <div class="stat-card">
        <div>
            <div class="stat-label">Total Gross Revenue</div>
            <div class="stat-value" style="color: var(--accent-gold);"><?= format_currency($totalRevenue) ?></div>
        </div>
        <div class="stat-icon gold">
            <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
    </div>

    <div class="stat-card">
        <div>
            <div class="stat-label">Cost of Goods (COGS)</div>
            <div class="stat-value"><?= format_currency($cogs) ?></div>
        </div>
        <div class="stat-icon sky">
            <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
        </div>
    </div>

    <div class="stat-card">
        <div>
            <div class="stat-label">Total Shop Expenses</div>
            <div class="stat-value" style="color: var(--accent-rose);"><?= format_currency($totalExpenses) ?></div>
        </div>
        <div class="stat-icon rose">
            <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
    </div>

    <div class="stat-card">
        <div>
            <div class="stat-label">Net Profit</div>
            <div class="stat-value" style="color: var(--accent-emerald);"><?= format_currency($netProfit) ?></div>
        </div>
        <div class="stat-icon emerald">
            <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
        </div>
    </div>
</div>

<!-- Detailed Profit & Loss Statement Table -->
<div class="card">
    <div class="card-header">
        <div class="card-title">Profit & Loss Breakdown (<?= date('d M Y', strtotime($startDate)) ?> to <?= date('d M Y', strtotime($endDate)) ?>)</div>
    </div>
    <div class="table-responsive">
        <table class="table">
            <tbody>
                <tr>
                    <td><strong>Gross Product Subtotal Sales</strong></td>
                    <td style="text-align: right; font-weight: 600;"><?= format_currency($salesReport['raw_subtotal']) ?></td>
                </tr>
                <tr>
                    <td>Less: Customer Discounts Allowed</td>
                    <td style="text-align: right; color: var(--accent-rose);">- <?= format_currency($salesReport['total_discounts']) ?></td>
                </tr>
                <tr style="border-top: 1px solid var(--border-color); background: rgba(255,255,255,0.02);">
                    <td><strong>NET SALES REVENUE</strong></td>
                    <td style="text-align: right; font-weight: 700; color: var(--accent-gold); font-size: 1.1rem;"><?= format_currency($totalRevenue) ?></td>
                </tr>
                <tr>
                    <td>Less: Cost of Goods Sold (Buying Inventory Cost)</td>
                    <td style="text-align: right; color: var(--text-muted);">- <?= format_currency($cogs) ?></td>
                </tr>
                <tr style="border-top: 1px solid var(--border-color); background: rgba(255,255,255,0.02);">
                    <td><strong>GROSS MARGIN PROFIT</strong></td>
                    <td style="text-align: right; font-weight: 700; color: var(--text-primary); font-size: 1.1rem;"><?= format_currency($grossProfit) ?></td>
                </tr>
                <tr>
                    <td>Less: Operational & Shop Expenses</td>
                    <td style="text-align: right; color: var(--accent-rose);">- <?= format_currency($totalExpenses) ?></td>
                </tr>
                <tr style="border-top: 2px double var(--accent-emerald); background: rgba(16,185,129,0.1);">
                    <td><strong style="font-size: 1.1rem; color: var(--accent-emerald);">FINAL NET PROFIT</strong></td>
                    <td style="text-align: right; font-weight: 700; color: var(--accent-emerald); font-size: 1.3rem;"><?= format_currency($netProfit) ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Sales by Category -->
<div class="card">
    <div class="card-header">
        <div class="card-title">Sales Revenue by Fashion Category</div>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Category Name</th>
                    <th>Total Items Sold</th>
                    <th>Category Revenue Share</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($catBreakdown)): ?>
                    <tr><td colspan="3" style="text-align:center; color:var(--text-muted); padding:2rem;">No sales data available.</td></tr>
                <?php else: ?>
                    <?php foreach ($catBreakdown as $cat): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($cat['category_name'] ?? 'General') ?></strong></td>
                            <td><?= number_format($cat['items_sold']) ?> units</td>
                            <td><strong style="color:var(--accent-gold);"><?= format_currency($cat['category_revenue']) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
