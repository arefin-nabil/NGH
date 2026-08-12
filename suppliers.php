<?php
$pageTitle = "Suppliers Traceability & Vendor Inventory";
require_once __DIR__ . '/includes/header.php';

// Handle Add/Edit Supplier POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $name = sanitize($_POST['name'] ?? '');
    $company_name = sanitize($_POST['company_name'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $address = sanitize($_POST['address'] ?? '');

    if ($action === 'add') {
        $stmt = $pdo->prepare("INSERT INTO suppliers (name, company_name, phone, email, address) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $company_name, $phone, $email, $address]);
        header("Location: suppliers.php?msg=Supplier+Added");
        exit;
    } elseif ($action === 'edit') {
        $id = intval($_POST['id']);
        $stmt = $pdo->prepare("UPDATE suppliers SET name = ?, company_name = ?, phone = ?, email = ?, address = ? WHERE id = ?");
        $stmt->execute([$name, $company_name, $phone, $email, $address, $id]);
        header("Location: suppliers.php?msg=Supplier+Updated");
        exit;
    }
}

// Selected Supplier filter tab
$selectedSupplierId = intval($_GET['supplier_id'] ?? 0);

// Fetch Suppliers List
$stmtSuppliers = $pdo->query("SELECT * FROM suppliers ORDER BY name ASC");
$suppliers = $stmtSuppliers->fetchAll();

// Fetch Stock Inventory filtered by supplier if selected
$supplierInventory = [];
if ($selectedSupplierId > 0) {
    $stmtInv = $pdo->prepare("
        SELECT p.*, c.name as category_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.supplier_id = ?
        ORDER BY p.name ASC
    ");
    $stmtInv->execute([$selectedSupplierId]);
    $supplierInventory = $stmtInv->fetchAll();
}
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
    <div style="font-size:0.9rem; color:var(--text-muted);">Trace product sourcing and view vendor-specific stock inventory.</div>
    <button class="btn btn-primary" data-modal-target="modal-add-supplier">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Add New Supplier
    </button>
</div>

<!-- Tabs Bar -->
<div style="display:flex; gap:0.5rem; overflow-x:auto; margin-bottom:1.25rem;">
    <a href="suppliers.php" class="btn <?= $selectedSupplierId === 0 ? 'btn-primary' : 'btn-secondary' ?> btn-sm">All Vendors Directory</a>
    <?php foreach ($suppliers as $sup): ?>
        <a href="suppliers.php?supplier_id=<?= $sup['id'] ?>" class="btn <?= $selectedSupplierId === $sup['id'] ? 'btn-primary' : 'btn-secondary' ?> btn-sm">
            <?= htmlspecialchars($sup['company_name'] ?: $sup['name']) ?>
        </a>
    <?php endforeach; ?>
</div>

<?php if ($selectedSupplierId > 0): ?>
    <!-- Supplier Inventory Stock Breakdown -->
    <?php
    $stmtSupInfo = $pdo->prepare("SELECT * FROM suppliers WHERE id = ?");
    $stmtSupInfo->execute([$selectedSupplierId]);
    $currentSup = $stmtSupInfo->fetch();
    
    $totalStockQty = 0;
    $totalBuyingVal = 0.0;
    $totalSellingVal = 0.0;
    foreach ($supplierInventory as $inv) {
        $totalStockQty += $inv['quantity'];
        $totalBuyingVal += ($inv['buying_price'] * $inv['quantity']);
        $totalSellingVal += ($inv['selling_price'] * $inv['quantity']);
    }
    ?>
    
    <div class="grid-4">
        <div class="stat-card">
            <div>
                <div class="stat-label">Vendor Name</div>
                <div class="stat-value" style="font-size:1.2rem; color:var(--accent-gold);"><?= htmlspecialchars($currentSup['name']) ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div>
                <div class="stat-label">Total Products In Stock</div>
                <div class="stat-value"><?= number_format($totalStockQty) ?> <span style="font-size:0.8rem;">units</span></div>
            </div>
        </div>
        <div class="stat-card">
            <div>
                <div class="stat-label">Total Cost Value</div>
                <div class="stat-value"><?= format_currency($totalBuyingVal) ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div>
                <div class="stat-label">Total Retail Value</div>
                <div class="stat-value" style="color:var(--accent-emerald);"><?= format_currency($totalSellingVal) ?></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title">Inventory Supplied by <?= htmlspecialchars($currentSup['company_name'] ?: $currentSup['name']) ?></div>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Barcode</th>
                        <th>Product Name</th>
                        <th>Category</th>
                        <th>Stock Qty</th>
                        <th>Buying Price</th>
                        <th>Selling Price</th>
                        <th>Total Cost Value</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($supplierInventory)): ?>
                        <tr><td colspan="7" style="text-align:center; color:var(--text-muted); padding:2rem;">No products linked to this supplier yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($supplierInventory as $p): ?>
                            <tr>
                                <td><code><?= htmlspecialchars($p['barcode']) ?></code></td>
                                <td><strong><?= htmlspecialchars($p['name']) ?></strong></td>
                                <td><?= htmlspecialchars($p['category_name'] ?? 'General') ?></td>
                                <td><strong style="color:var(--text-primary);"><?= $p['quantity'] ?></strong></td>
                                <td><?= format_currency($p['buying_price']) ?></td>
                                <td><?= format_currency($p['selling_price']) ?></td>
                                <td><strong><?= format_currency($p['buying_price'] * $p['quantity']) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php else: ?>
    <!-- Full Suppliers Directory Table -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">Registered Suppliers Directory</div>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Contact Name</th>
                        <th>Company Name</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Address</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($suppliers)): ?>
                        <tr><td colspan="6" style="text-align:center; color:var(--text-muted); padding:2rem;">No suppliers added yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($suppliers as $sup): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($sup['name']) ?></strong></td>
                                <td><?= htmlspecialchars($sup['company_name'] ?: 'N/A') ?></td>
                                <td><?= htmlspecialchars($sup['phone'] ?: 'N/A') ?></td>
                                <td><?= htmlspecialchars($sup['email'] ?: 'N/A') ?></td>
                                <td><?= htmlspecialchars($sup['address'] ?: 'N/A') ?></td>
                                <td>
                                    <a href="suppliers.php?supplier_id=<?= $sup['id'] ?>" class="btn btn-secondary btn-sm">View Stock</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- Modal: Add Supplier -->
<div class="modal-backdrop" id="modal-add-supplier">
    <div class="modal-dialog">
        <div class="modal-header">
            <div class="modal-title">Add Supplier</div>
            <button class="modal-close" data-modal-close>&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Contact Name *</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Akbar Hossain" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Company / Brand Name</label>
                    <input type="text" name="company_name" class="form-control" placeholder="e.g. Fashion Craft BD">
                </div>
                <div class="form-group">
                    <label class="form-label">Phone Number *</label>
                    <input type="text" name="phone" class="form-control" placeholder="e.g. 01711223344" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control" placeholder="supplier@example.com">
                </div>
                <div class="form-group">
                    <label class="form-label">Warehouse Address</label>
                    <textarea name="address" class="form-control" rows="2" placeholder="Market / Factory location"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary">Save Supplier</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
