<?php
$pageTitle = "Sales History";
require_once __DIR__ . '/includes/header.php';

// Date range filters
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

$stmt = $pdo->prepare("
    SELECT s.*, c.name as customer_name, c.phone as customer_phone, u.name as seller_name
    FROM sales s
    LEFT JOIN customers c ON s.customer_id = c.id
    LEFT JOIN users u ON s.user_id = u.id
    WHERE DATE(s.created_at) BETWEEN ? AND ?
    ORDER BY s.id DESC
");
$stmt->execute([$startDate, $endDate]);
$salesList = $stmt->fetchAll();
?>

<div class="card">
    <div class="card-header">
        <div class="card-title">Completed POS Transactions</div>
        <form method="GET" style="display:flex; gap:0.5rem; align-items:center;">
            <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($startDate) ?>">
            <span style="color:var(--text-muted);">to</span>
            <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($endDate) ?>">
            <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Invoice No</th>
                    <th>Date & Time</th>
                    <th>Customer</th>
                    <th>Seller</th>
                    <th>Subtotal</th>
                    <th>Discount</th>
                    <th>Grand Total</th>
                    <?php if (can_view_profit()): ?>
                    <th>Net Profit</th>
                    <?php endif; ?>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($salesList)): ?>
                    <tr><td colspan="10" style="text-align:center; color:var(--text-muted); padding:2rem;">No sales found for the selected date range.</td></tr>
                <?php else: ?>
                    <?php foreach ($salesList as $sale): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($sale['invoice_no']) ?></strong></td>
                            <td><small><?= date('d M Y, h:i A', strtotime($sale['created_at'])) ?></small></td>
                            <td>
                                <strong><?= htmlspecialchars($sale['customer_name'] ?? 'Guest Customer') ?></strong><br>
                                <small style="color:var(--text-muted);"><?= htmlspecialchars($sale['customer_phone'] ?? 'N/A') ?></small>
                            </td>
                            <td><?= htmlspecialchars($sale['seller_name'] ?? 'System') ?></td>
                            <td><?= format_currency($sale['subtotal']) ?></td>
                            <td>- <?= format_currency($sale['discount_amount']) ?></td>
                            <td><strong style="color:var(--accent-gold);"><?= format_currency($sale['grand_total']) ?></strong></td>
                            <?php if (can_view_profit()): ?>
                            <td><strong style="color:var(--accent-emerald);"><?= format_currency($sale['total_profit']) ?></strong></td>
                            <?php endif; ?>
                            <td>
                                <div style="display:flex; gap:0.35rem;">
                                    <button class="btn btn-secondary btn-sm" onclick="reprintReceipt(<?= $sale['id'] ?>)">Receipt</button>
                                    <?php if (is_superadmin()): ?>
                                    <button class="btn btn-danger btn-sm" onclick="openDeleteSaleModal(<?= $sale['id'] ?>, '<?= htmlspecialchars($sale['invoice_no']) ?>')">Delete</button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Delete Sale Confirmation (Superadmin Only) -->
<?php if (is_superadmin()): ?>
<div class="modal-backdrop" id="modal-delete-sale">
    <div class="modal-dialog">
        <div class="modal-header">
            <div class="modal-title" style="color: var(--accent-rose);">Delete Invoice Confirmation</div>
            <button class="modal-close" data-modal-close>&times;</button>
        </div>
        <form onsubmit="handleDeleteSaleSubmit(event)">
            <div class="modal-body">
                <input type="hidden" id="delete-sale-id" name="sale_id">
                <div style="margin-bottom:1rem;">
                    Are you sure you want to permanently delete invoice <strong id="delete-sale-invoice" style="color:var(--accent-gold);"></strong>?
                </div>
                <div style="background: rgba(244,63,94,0.1); border: 1px solid rgba(244,63,94,0.3); padding: 0.75rem; border-radius: var(--radius-sm); font-size: 0.85rem; color: var(--accent-rose); margin-bottom: 1rem;">
                    <strong>Warning:</strong> Deleting this sale will automatically restore product stock quantities and record an audit log in <code>sale_deletion_logs</code>.
                </div>
                <div class="form-group">
                    <label class="form-label">Reason for Deletion *</label>
                    <input type="text" name="reason" class="form-control" placeholder="e.g. Order cancelled by customer / Wrong item scanned" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-danger">Confirm Permanent Deletion</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Modal: Thermal Receipt Reprint Modal -->
<div class="modal-backdrop" id="modal-reprint-receipt">
    <div class="modal-dialog" style="max-width: 360px;">
        <div class="modal-header no-print">
            <div class="modal-title">Receipt Reprint</div>
            <button class="modal-close" data-modal-close>&times;</button>
        </div>
        <div class="modal-body" style="padding: 0.5rem;">
            <div id="printable-reprint-modal">
                <!-- Receipt content -->
            </div>
        </div>
        <div class="modal-footer no-print">
            <button class="btn btn-secondary" data-modal-close>Close</button>
            <button class="btn btn-primary" onclick="window.print()">Print Receipt</button>
        </div>
    </div>
</div>

<script>
function openDeleteSaleModal(id, invoiceNo) {
    document.getElementById('delete-sale-id').value = id;
    document.getElementById('delete-sale-invoice').innerText = invoiceNo;
    openModal('modal-delete-sale');
}

function handleDeleteSaleSubmit(e) {
    e.preventDefault();
    const formData = new FormData(e.target);

    fetch('api/delete-sale.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            closeModal('modal-delete-sale');
            setTimeout(() => window.location.reload(), 800);
        } else {
            showToast(data.message || 'Deletion failed', 'danger');
        }
    })
    .catch(err => {
        showToast('Error connecting to server', 'danger');
    });
}

function reprintReceipt(saleId) {
    fetch(`api/get-sale-details.php?id=${saleId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.sale) {
                renderReprintReceipt(data.sale);
                openModal('modal-reprint-receipt');
            } else {
                showToast('Failed to fetch sale details', 'danger');
            }
        });
}

function renderReprintReceipt(sale) {
    const container = document.getElementById('printable-reprint-modal');
    if (!container) return;

    let itemsHtml = '';
    sale.items.forEach(item => {
        const unitPrice = parseFloat(item.adjusted_selling_price || item.original_selling_price || 0);
        const itemLineTotal = unitPrice * item.quantity;

        itemsHtml += `
            <tr>
                <td style="padding: 3px 0; color:#000;">
                    <strong>${escapeHtml(item.product_name)}</strong><br>
                    <small>Qty: ${item.quantity} x ৳${unitPrice.toFixed(2)}</small>
                </td>
                <td style="text-align: right; vertical-align: top; padding: 3px 0; font-weight:bold; color:#000;">
                    ৳${itemLineTotal.toFixed(2)}
                </td>
            </tr>
        `;
    });

    container.innerHTML = `
        <div class="receipt-container">
            <div class="receipt-header">
                <div class="receipt-shop-name"><?= SHOP_NAME ?></div>
                <div style="font-size: 10px; margin-top:2px;"><?= SHOP_LOCATION ?></div>
                <div style="font-size: 10px;">Mobile: <?= SHOP_MOBILE ?></div>
            </div>
            <div class="receipt-details">
                <div><strong>Invoice:</strong> ${escapeHtml(sale.invoice_no)} (Reprint)</div>
                <div><strong>Date:</strong> ${escapeHtml(sale.created_at)}</div>
                <div><strong>Customer:</strong> ${escapeHtml(sale.customer_name)} (${escapeHtml(sale.customer_phone || '')})</div>
                <div><strong>Salesperson:</strong> ${escapeHtml(sale.seller_name)}</div>
            </div>
            <table class="receipt-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th style="text-align: right;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    ${itemsHtml}
                </tbody>
            </table>
            <div class="receipt-totals">
                <div class="receipt-line"><span>Subtotal:</span> <span>৳${parseFloat(sale.subtotal).toFixed(2)}</span></div>
                <div class="receipt-line"><span>Discount:</span> <span>- ৳${parseFloat(sale.discount_amount).toFixed(2)}</span></div>
                <div class="receipt-line" style="font-weight: bold; font-size: 13px; margin-top: 4px;">
                    <span>Grand Total:</span> <span>৳${parseFloat(sale.grand_total).toFixed(2)}</span>
                </div>
            </div>
            <div class="receipt-footer">
                <div>Thank you for shopping with us!</div>
                <div class="receipt-dev-branding">Developed by: Developers (<?= DEVELOPER_WEBSITE ?>)</div>
            </div>
        </div>
    `;
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
