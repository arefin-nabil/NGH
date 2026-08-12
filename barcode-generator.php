<?php
$pageTitle = "Thermal Barcode Generator";
require_once __DIR__ . '/includes/header.php';

// Fetch products list for dropdown selection
$products = $pdo->query("SELECT id, barcode, name, selling_price FROM products ORDER BY name ASC")->fetchAll();

$initialBarcode = $_GET['barcode'] ?? ($products[0]['barcode'] ?? '890123456789');
?>

<!-- JsBarcode CDN for standard Barcode rendering -->
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>

<div class="card no-print">
    <div class="card-header">
        <div class="card-title">Generate Thermal Barcode Sticker</div>
    </div>
    <div class="grid-2">
        <div>
            <div class="form-group">
                <label class="form-label">Select Existing Product</label>
                <select id="barcode-product-select" class="form-control" onchange="loadProductBarcode(this.value)">
                    <?php foreach ($products as $p): ?>
                        <option value="<?= htmlspecialchars($p['barcode']) ?>" data-name="<?= htmlspecialchars($p['name']) ?>" data-price="<?= number_format($p['selling_price'], 2) ?>" <?= $initialBarcode === $p['barcode'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['name']) ?> (BC: <?= htmlspecialchars($p['barcode']) ?>) - ৳<?= number_format($p['selling_price'], 2) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Custom Barcode Number</label>
                <input type="text" id="custom-barcode-input" class="form-control" value="<?= htmlspecialchars($initialBarcode) ?>" oninput="generateBarcode()">
            </div>
            <div class="form-group">
                <label class="form-label">Product Name Header</label>
                <input type="text" id="barcode-name-input" class="form-control" value="Premium Cotton Panjabi" oninput="generateBarcode()">
            </div>
            <div class="form-group">
                <label class="form-label">Price Header (৳)</label>
                <input type="text" id="barcode-price-input" class="form-control" value="1450.00" oninput="generateBarcode()">
            </div>
            <button class="btn btn-primary" onclick="window.print()" style="margin-top: 1rem;">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                Print Barcode Label
            </button>
        </div>

        <!-- Clean Label Preview Box (No Black Giant Void) -->
        <div style="background: var(--bg-surface-elevated); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 2rem; display: flex; flex-direction: column; align-items: center; justify-content: center;">
            <div style="font-size:0.8rem; color:var(--text-secondary); margin-bottom:1rem; font-weight:600;">Thermal Sticker Label Preview:</div>
            
            <div id="barcode-sticker-preview" style="background:#ffffff; color:#000000; padding:12px; border-radius:6px; text-align:center; width:240px; box-shadow:0 4px 15px rgba(0,0,0,0.2);">
                <div id="prev-shop-name" style="font-size:11px; font-weight:bold; text-transform:uppercase; font-family:sans-serif; color:#000; line-height:1.2;"><?= SHOP_NAME ?></div>
                <div id="prev-product-name" style="font-size:11px; font-weight:600; margin:4px 0; max-height:30px; overflow:hidden; font-family:sans-serif; color:#000;">Product Name</div>
                <div style="display:flex; justify-content:center; margin:4px 0;">
                    <svg id="barcode-svg"></svg>
                </div>
                <div id="prev-product-price" style="font-size:13px; font-weight:bold; margin-top:2px; font-family:sans-serif; color:#000;">MRP: ৳ 0.00</div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const select = document.getElementById('barcode-product-select');
    if (select && select.value) {
        loadProductBarcode(select.value);
    } else {
        generateBarcode();
    }
});

function loadProductBarcode(barcode) {
    const select = document.getElementById('barcode-product-select');
    const selectedOpt = select.options[select.selectedIndex];
    
    document.getElementById('custom-barcode-input').value = barcode;
    if (selectedOpt) {
        document.getElementById('barcode-name-input').value = selectedOpt.getAttribute('data-name') || '';
        document.getElementById('barcode-price-input').value = selectedOpt.getAttribute('data-price') || '';
    }
    generateBarcode();
}

function generateBarcode() {
    const barcodeCode = document.getElementById('custom-barcode-input').value || '890123456789';
    const prodName = document.getElementById('barcode-name-input').value || 'Fashion Item';
    const prodPrice = document.getElementById('barcode-price-input').value || '0.00';

    document.getElementById('prev-product-name').innerText = prodName;
    document.getElementById('prev-product-price').innerText = 'MRP: ৳ ' + prodPrice;

    try {
        JsBarcode("#barcode-svg", barcodeCode, {
            format: "CODE128",
            width: 1.8,
            height: 48,
            fontSize: 12,
            margin: 2,
            lineColor: "#000000",
            displayValue: true
        });
    } catch (e) {
        console.error("Barcode generation error", e);
    }
}
</script>

<style>
@media print {
    body * {
        visibility: hidden;
    }
    #barcode-sticker-preview, #barcode-sticker-preview * {
        visibility: visible;
    }
    #barcode-sticker-preview {
        position: absolute;
        left: 0;
        top: 0;
        width: 100% !important;
        border: none !important;
        box-shadow: none !important;
        background: #fff !important;
    }
}
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
