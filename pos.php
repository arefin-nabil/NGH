<?php
$pageTitle = "POS Terminal";
require_once __DIR__ . '/includes/header.php';

// Fetch Categories for Quick Filters
$stmtCats = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
$categories = $stmtCats->fetchAll();
?>

<div class="pos-container">
    <!-- Left Column: Product Search & Catalog Grid -->
    <div class="pos-products-section">
        <div class="pos-search-bar">
            <div style="flex: 1; position: relative;">
                <input type="text" id="pos-search-input" class="form-control" placeholder="Scan Barcode or Search Product Name..." autofocus>
            </div>
            <button class="btn btn-secondary" onclick="loadProducts(document.getElementById('pos-search-input').value)">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                Search
            </button>
        </div>

        <!-- Category Filter Buttons -->
        <div style="display: flex; gap: 0.5rem; overflow-x: auto; padding-bottom: 0.25rem;">
            <button class="btn btn-primary btn-sm cat-filter-btn" data-cat-id="">All Items</button>
            <?php foreach ($categories as $cat): ?>
                <button class="btn btn-secondary btn-sm cat-filter-btn" data-cat-id="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></button>
            <?php endforeach; ?>
        </div>

        <!-- Products Grid Container -->
        <div class="product-cards-grid" id="pos-products-grid">
            <!-- Dynamic Product Cards injected by assets/js/pos.js -->
        </div>
    </div>

    <!-- Right Column: Interactive Cart & Checkout Panel -->
    <div class="pos-cart-section">
        <div class="pos-cart-header">
            <div>
                <div style="font-weight: 700; font-size: 1.1rem; color: var(--text-primary);">Sales Cart</div>
                <div style="font-size: 0.8rem; color: var(--text-muted);">
                    Customer: <span id="selected-customer-name" style="color: var(--accent-gold); font-weight: 600;">Guest Customer</span>
                </div>
            </div>
            <div style="display: flex; gap: 0.5rem;">
                <button class="btn btn-secondary btn-sm" data-modal-target="modal-select-customer" title="Select / Search Customer">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                </button>
                <button class="btn btn-secondary btn-sm" data-modal-target="modal-add-customer" title="Quick Add Customer">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                </button>
                <button class="btn btn-danger btn-sm" onclick="clearCart()" title="Clear Cart">&times;</button>
            </div>
        </div>

        <!-- Cart Items List Container -->
        <div class="pos-cart-items" id="cart-items-container">
            <!-- Dynamic Cart Items injected by assets/js/pos.js -->
        </div>

        <!-- Cart Summary & Payment Panel -->
        <div class="pos-cart-summary">
            <div class="summary-line">
                <span>Subtotal:</span>
                <span id="cart-subtotal" style="font-weight:600;">৳ 0.00</span>
            </div>

            <div class="summary-line" style="align-items: center;">
                <span>Total Discount (৳):</span>
                <input type="number" id="cart-discount-input" class="form-control" style="width: 110px; padding: 0.25rem 0.5rem; text-align: right; font-weight:700; color:var(--accent-rose);" value="0" min="0" step="0.01">
            </div>

            <div class="summary-line" style="align-items: center;">
                <span>Payment Method:</span>
                <select id="payment-method-select" class="form-control" style="width: 140px; padding: 0.25rem 0.5rem;">
                    <option value="Cash">Cash</option>
                    <option value="Card">Card / POS</option>
                    <option value="Mobile Banking">Bkash / Nagad</option>
                </select>
            </div>

            <div class="summary-line total">
                <span>Grand Total:</span>
                <span id="cart-grand-total" style="color: var(--accent-gold);">৳ 0.00</span>
            </div>

            <button id="pos-checkout-btn" class="btn btn-primary" style="width: 100%; justify-content: center; margin-top: 1rem; padding: 0.85rem; font-size: 1rem;" onclick="processCheckout()">
                Checkout & Print Receipt
            </button>
        </div>
    </div>
</div>

<!-- Modal: Select / Search Customer -->
<div class="modal-backdrop" id="modal-select-customer">
    <div class="modal-dialog">
        <div class="modal-header">
            <div class="modal-title">Select Customer</div>
            <button class="modal-close" data-modal-close>&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <input type="text" id="customer-search-input" class="form-control" placeholder="Type Phone Number or Name...">
            </div>
            <div id="customer-search-results" style="max-height: 250px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: var(--radius-sm);">
                <!-- Dynamic search list -->
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="selectCustomer({id: 1, name: 'Guest Customer', phone: '00000000000'})">Use Guest Customer</button>
            <button class="btn btn-secondary" data-modal-close>Close</button>
        </div>
    </div>
</div>

<!-- Modal: Printable Thermal Receipt -->
<div class="modal-backdrop" id="modal-receipt">
    <div class="modal-dialog" style="max-width: 360px;">
        <div class="modal-header no-print">
            <div class="modal-title">Thermal Receipt Preview</div>
            <button class="modal-close" data-modal-close>&times;</button>
        </div>
        <div class="modal-body" style="padding: 0.5rem;">
            <div id="printable-receipt-modal">
                <!-- Thermal receipt content injected here -->
            </div>
        </div>
        <div class="modal-footer no-print">
            <button class="btn btn-secondary" data-modal-close>Close</button>
            <button class="btn btn-primary" onclick="printCurrentReceipt()">Print Receipt</button>
        </div>
    </div>
</div>

<!-- POS JS Engine -->
<script src="assets/js/pos.js"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
