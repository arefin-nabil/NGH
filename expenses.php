<?php
$pageTitle = "Shop Expense Tracker";
require_once __DIR__ . '/includes/header.php';

if (!can_add_expenses()) {
    echo '<div class="card"><div style="color:var(--accent-rose); font-weight:600;">Access Denied! You do not have permission to manage expenses.</div></div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Handle Add Expense POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add_expense') {
        $category_id = intval($_POST['category_id'] ?? 0) ?: null;
        $custom_category = sanitize($_POST['custom_category'] ?? '');
        $amount = floatval($_POST['amount'] ?? 0);
        $payment_method = sanitize($_POST['payment_method'] ?? 'Cash');
        $description = sanitize($_POST['description'] ?? '');
        $expense_date = $_POST['expense_date'] ?? date('Y-m-d');
        $user_id = $_SESSION['user_id'];

        // If user typed a custom one-time category title, combine it with description
        if (!empty($custom_category)) {
            $description = (!empty($description)) ? "[$custom_category] $description" : $custom_category;
        }

        if ($amount > 0) {
            $stmt = $pdo->prepare("
                INSERT INTO expenses (category_id, user_id, amount, payment_method, description, expense_date)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$category_id, $user_id, $amount, $payment_method, $description, $expense_date]);
            header("Location: expenses.php?msg=Expense+Logged");
            exit;
        }
    }
}

// Fetch Expense Categories
$categories = $pdo->query("SELECT * FROM expense_categories ORDER BY name ASC")->fetchAll();

// Date Filters
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

$stmt = $pdo->prepare("
    SELECT e.*, ec.name as category_name, u.name as logged_by_user
    FROM expenses e
    LEFT JOIN expense_categories ec ON e.category_id = ec.id
    LEFT JOIN users u ON e.user_id = u.id
    WHERE e.expense_date BETWEEN ? AND ?
    ORDER BY e.expense_date DESC, e.id DESC
");
$stmt->execute([$startDate, $endDate]);
$expenses = $stmt->fetchAll();

// Calculate total expenses for period
$totalExpenses = 0.0;
foreach ($expenses as $ex) {
    $totalExpenses += $ex['amount'];
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
    <form method="GET" style="display:flex; gap:0.5rem; align-items:center;">
        <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($startDate) ?>">
        <span style="color:var(--text-muted);">to</span>
        <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($endDate) ?>">
        <button type="submit" class="btn btn-secondary btn-sm">Filter Date</button>
    </form>

    <button class="btn btn-primary" data-modal-target="modal-add-expense">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Log Expense Entry
    </button>
</div>

<div class="stat-card" style="margin-bottom: 1.5rem;">
    <div>
        <div class="stat-label">Total Period Expenses</div>
        <div class="stat-value" style="color: var(--accent-rose);"><?= format_currency($totalExpenses) ?></div>
    </div>
    <div class="stat-icon rose">
        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title">Expense Log Records</div>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Category</th>
                    <th>Custom Description / Details</th>
                    <th>Payment Source</th>
                    <th>Amount</th>
                    <th>Logged By</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($expenses)): ?>
                    <tr><td colspan="6" style="text-align:center; color:var(--text-muted); padding:2rem;">No expense logs recorded for this period.</td></tr>
                <?php else: ?>
                    <?php foreach ($expenses as $ex): ?>
                        <tr>
                            <td><small><?= date('d M Y', strtotime($ex['expense_date'])) ?></small></td>
                            <td><span class="badge badge-info"><?= htmlspecialchars($ex['category_name'] ?? 'General Expense') ?></span></td>
                            <td><?= htmlspecialchars($ex['description'] ?: 'N/A') ?></td>
                            <td><?= htmlspecialchars($ex['payment_method']) ?></td>
                            <td><strong style="color:var(--accent-rose);"><?= format_currency($ex['amount']) ?></strong></td>
                            <td><?= htmlspecialchars($ex['logged_by_user'] ?? 'System') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Add Expense Entry -->
<div class="modal-backdrop" id="modal-add-expense">
    <div class="modal-dialog">
        <div class="modal-header">
            <div class="modal-title">Log Daily Shop Expense</div>
            <button class="modal-close" data-modal-close>&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_expense">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Category (Optional)</label>
                    <select name="category_id" class="form-control">
                        <option value="">-- Select Standard Category --</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Or Custom Expense Title / Specific Tag</label>
                    <input type="text" name="custom_category" class="form-control" placeholder="e.g. Generator Fuel, Tea, Banner Printing">
                </div>
                <div class="grid-2" style="margin-bottom:0;">
                    <div class="form-group">
                        <label class="form-label">Amount (৳) *</label>
                        <input type="number" step="0.01" name="amount" class="form-control" placeholder="350.00" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Expense Date</label>
                        <input type="date" name="expense_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Payment Method</label>
                    <select name="payment_method" class="form-control">
                        <option value="Cash">Cash Drawer</option>
                        <option value="Mobile Banking">Bkash / Nagad</option>
                        <option value="Card">Bank Account</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Detailed Notes / Description</label>
                    <textarea name="description" class="form-control" rows="2" placeholder="e.g. Evening tea for shop staff"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary">Save Expense Log</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
