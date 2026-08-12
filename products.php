<?php
$pageTitle = "Products & Inventory Management";
require_once __DIR__ . '/includes/header.php';

// Handle Add/Edit Product POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $barcode = sanitize($_POST['barcode'] ?? '');
    $name = sanitize($_POST['name'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $category_id = intval($_POST['category_id'] ?? 0) ?: null;
    $supplier_id = intval($_POST['supplier_id'] ?? 0) ?: null;
    $quantity = intval($_POST['quantity'] ?? 0);
    $min_stock_alert = intval($_POST['min_stock_alert'] ?? 5);
    $buying_price = floatval($_POST['buying_price'] ?? 0);
    $selling_price = floatval($_POST['selling_price'] ?? 0);

    if ($action === 'add') {
        if (empty($barcode)) {
            $barcode = '890' . str_pad(mt_rand(1, 999999999), 9, '0', STR_PAD_LEFT);
        }
        $stmt = $pdo->prepare("
            INSERT INTO products (barcode, name, description, category_id, supplier_id, quantity, min_stock_alert, buying_price, selling_price)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$barcode, $name, $description, $category_id, $supplier_id, $quantity, $min_stock_alert, $buying_price, $selling_price]);
        header("Location: products.php?msg=Product+Added");
        exit;
    } elseif ($action === 'edit') {
        $id = intval($_POST['id']);
        $stmt = $pdo->prepare("
            UPDATE products
            SET barcode = ?, name = ?, description = ?, category_id = ?, supplier_id = ?, quantity = ?, min_stock_alert = ?, buying_price = ?, selling_price = ?
            WHERE id = ?
        ");
        $stmt->execute([$barcode, $name, $description, $category_id, $supplier_id, $quantity, $min_stock_alert, $buying_price, $selling_price, $id]);
        header("Location: products.php?msg=Product+Updated");
        exit;
    } elseif ($action === 'delete' && is_superadmin()) {
        $id = intval($_POST['id']);
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: products.php?msg=Product+Deleted");
        exit;
    }
}

// Fetch categories & suppliers dropdown
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
$suppliers = $pdo->query("SELECT * FROM suppliers ORDER BY name ASC")->fetchAll();

// Search Query
$search = trim($_GET['search'] ?? '');
$catFilter = intval($_GET['cat_id'] ?? 0);

$sql = "
    SELECT p.*, c.name as category_name, s.name as supplier_name, s.company_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN suppliers s ON p.supplier_id = s.id
    WHERE 1=1
";
$params = [];

if (!empty($search)) {
    $sql .= " AND (p.barcode LIKE ? OR LOWER(p.name) LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%" . strtolower($search) . "%";
}
if ($catFilter > 0) {
    $sql .= " AND p.category_id = ?";
    $params[] = $catFilter;
}

$sql .= " ORDER BY p.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
    <form method="GET" style="display:flex; gap:0.5rem;">
        <input type="text" name="search" class="form-control" placeholder="Search by name or barcode..." value="<?= htmlspecialchars($search) ?>" style="width:250px;">
        <select name="cat_id" class="form-control" style="width:160px;">
            <option value="0">All Categories</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= $catFilter === $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-secondary">Filter</button>
    </form>

    <button class="btn btn-primary" data-modal-target="modal-add-product">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Add New Product
    </button>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title">Catalog Inventory Items</div>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Barcode</th>
                    <th>Product Name</th>
                    <th>Category</th>
                    <th>Supplier</th>
                    <th>Stock Qty</th>
                    <th>Buying Price</th>
                    <th>Selling Price</th>
                    <?php if (can_view_profit()): ?>
                    <th>Margin</th>
                    <?php endif; ?>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($products)): ?>
                    <tr><td colspan="9" style="text-align:center; color:var(--text-muted); padding:2rem;">No products found in catalog.</td></tr>
                <?php else: ?>
                    <?php foreach ($products as $p): ?>
                        <?php 
                        $margin = $p['selling_price'] - $p['buying_price'];
                        $isLowStock = $p['quantity'] <= $p['min_stock_alert'];
                        ?>
                        <tr>
                            <td><code><?= htmlspecialchars($p['barcode']) ?></code></td>
                            <td>
                                <strong><?= htmlspecialchars($p['name']) ?></strong>
                                <?php if (!empty($p['description'])): ?>
                                    <br><small style="color:var(--text-muted);"><?= htmlspecialchars($p['description']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($p['category_name'] ?? 'Uncategorized') ?></td>
                            <td><?= htmlspecialchars($p['company_name'] ?: ($p['supplier_name'] ?? 'N/A')) ?></td>
                            <td>
                                <strong style="color: <?= $isLowStock ? 'var(--accent-rose)' : 'var(--text-primary)' ?>;">
                                    <?= $p['quantity'] ?>
                                </strong>
                                <?php if ($isLowStock): ?>
                                    <span class="badge badge-danger">Low</span>
                                <?php endif; ?>
                            </td>
                            <td><?= format_currency($p['buying_price']) ?></td>
                            <td><strong style="color:var(--accent-gold);"><?= format_currency($p['selling_price']) ?></strong></td>
                            <?php if (can_view_profit()): ?>
                            <td><span style="color:var(--accent-emerald); font-weight:600;">+ <?= format_currency($margin) ?></span></td>
                            <?php endif; ?>
                            <td>
                                <div style="display:flex; gap:0.35rem;">
                                    <a href="barcode-generator.php?barcode=<?= urlencode($p['barcode']) ?>" class="btn btn-secondary btn-sm" title="Print Barcode">Barcode</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Add Product -->
<div class="modal-backdrop" id="modal-add-product">
    <div class="modal-dialog">
        <div class="modal-header">
            <div class="modal-title">Add Product to Inventory</div>
            <button class="modal-close" data-modal-close>&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Barcode Number *</label>
                    <div style="display:flex; gap:0.5rem;">
                        <input type="text" id="add-product-barcode" name="barcode" class="form-control" placeholder="e.g. 890123456789" required style="flex:1;">
                        <button type="button" class="btn btn-secondary" onclick="generateAutoBarcode()">Generate</button>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Product Name *</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Cotton Panjabi - Maroon (L)" required>
                </div>
                <div class="grid-2" style="margin-bottom:0;">
                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <select name="category_id" class="form-control">
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Linked Supplier</label>
                        <select name="supplier_id" class="form-control">
                            <option value="">Select Supplier</option>
                            <?php foreach ($suppliers as $sup): ?>
                                <option value="<?= $sup['id'] ?>"><?= htmlspecialchars($sup['company_name'] ?: $sup['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="grid-2" style="margin-bottom:0;">
                    <div class="form-group">
                        <label class="form-label">Initial Quantity *</label>
                        <input type="number" name="quantity" class="form-control" value="10" min="0" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Low Stock Alert Level</label>
                        <input type="number" name="min_stock_alert" class="form-control" value="5" min="1">
                    </div>
                </div>
                <div class="grid-2" style="margin-bottom:0;">
                    <div class="form-group">
                        <label class="form-label">Buying Cost Price (৳) *</label>
                        <input type="number" step="0.01" name="buying_price" class="form-control" placeholder="850.00" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Retail Selling Price (৳) *</label>
                        <input type="number" step="0.01" name="selling_price" class="form-control" placeholder="1450.00" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Short Description / Fabric Details</label>
                    <textarea name="description" class="form-control" rows="2" placeholder="e.g. 100% Export Quality Cotton"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary">Save Product</button>
            </div>
        </form>
    </div>
</div>

<script>
function generateAutoBarcode() {
    // Generate standard 12-digit EAN-12 barcode starting with '890'
    const randomSuffix = Math.floor(1000009 + Math.random() * 8999999);
    const barcode = '890' + randomSuffix;
    document.getElementById('add-product-barcode').value = barcode;
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
