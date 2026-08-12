/**
 * Nobin Glamor House POS Terminal JavaScript Module
 */

let cart = [];
let selectedCustomer = { id: 1, name: 'Guest Customer', phone: '00000000000' };

document.addEventListener('DOMContentLoaded', () => {
    initPOS();
});

function initPOS() {
    loadProducts();
    setupEventListeners();
    renderCart();
}

function setupEventListeners() {
    // Barcode scanner & search input listener
    const searchInput = document.getElementById('pos-search-input');
    if (searchInput) {
        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const query = searchInput.value.trim();
                if (query.length > 0) {
                    checkBarcodeScan(query);
                }
            }
        });

        searchInput.addEventListener('input', () => {
            const query = searchInput.value.trim();
            if (query.length >= 2) {
                loadProducts(query);
            } else if (query.length === 0) {
                loadProducts();
            }
        });
    }

    // Category Filter Buttons
    document.querySelectorAll('.cat-filter-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.cat-filter-btn').forEach(b => b.classList.remove('btn-primary'));
            document.querySelectorAll('.cat-filter-btn').forEach(b => b.classList.add('btn-secondary'));
            btn.classList.remove('btn-secondary');
            btn.classList.add('btn-primary');
            const catId = btn.getAttribute('data-cat-id');
            loadProducts('', catId);
        });
    });

    // Discount & Discount Input handlers
    const discountInput = document.getElementById('cart-discount-input');
    if (discountInput) {
        discountInput.addEventListener('input', () => renderCart());
    }

    // Customer Live Search Input
    const custSearchInput = document.getElementById('customer-search-input');
    if (custSearchInput) {
        custSearchInput.addEventListener('input', () => searchCustomers(custSearchInput.value.trim()));
    }
}

function checkBarcodeScan(barcode) {
    fetch(`api/product-search.php?barcode=${encodeURIComponent(barcode)}`)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.product) {
                addToCart(data.product);
                document.getElementById('pos-search-input').value = '';
                showToast(`Added '${data.product.name}' to cart`, 'success');
            } else {
                loadProducts(barcode);
            }
        })
        .catch(err => console.error(err));
}

function loadProducts(query = '', categoryId = '') {
    const grid = document.getElementById('pos-products-grid');
    if (!grid) return;

    grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 2rem; color: var(--text-muted);">Loading products...</div>';

    fetch(`api/product-search.php?q=${encodeURIComponent(query)}&category_id=${categoryId}`)
        .then(res => res.json())
        .then(data => {
            grid.innerHTML = '';
            if (data.success && data.products && data.products.length > 0) {
                data.products.forEach(p => {
                    const card = document.createElement('div');
                    card.className = 'product-item-card';
                    card.onclick = () => addToCart(p);
                    
                    const isLowStock = p.quantity <= p.min_stock_alert;
                    const stockBadge = isLowStock 
                        ? `<span class="badge badge-danger">${p.quantity} in stock</span>`
                        : `<span class="badge badge-success">${p.quantity} in stock</span>`;

                    card.innerHTML = `
                        <div>
                            <div class="product-item-title">${escapeHtml(p.name)}</div>
                            <div class="product-item-barcode">BC: ${escapeHtml(p.barcode)}</div>
                        </div>
                        <div style="display:flex; justify-content:space-between; align-items:flex-end;">
                            <div class="product-item-price">৳ ${parseFloat(p.selling_price).toFixed(2)}</div>
                            <div class="product-item-stock">${stockBadge}</div>
                        </div>
                    `;
                    grid.appendChild(card);
                });
            } else {
                grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 2rem; color: var(--text-muted);">No products found.</div>';
            }
        })
        .catch(err => {
            grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 2rem; color: var(--accent-rose);">Failed to load products.</div>';
        });
}

function addToCart(product) {
    const existingIndex = cart.findIndex(item => item.id == product.id);
    if (existingIndex > -1) {
        if (cart[existingIndex].quantity + 1 > product.quantity) {
            showToast(`Cannot add more than available stock (${product.quantity})`, 'warning');
            return;
        }
        cart[existingIndex].quantity += 1;
    } else {
        if (product.quantity <= 0) {
            showToast(`Product '${product.name}' is out of stock!`, 'danger');
            return;
        }
        cart.push({
            id: product.id,
            barcode: product.barcode,
            name: product.name,
            buying_price: parseFloat(product.buying_price),
            original_price: parseFloat(product.selling_price),
            price: parseFloat(product.selling_price), // Unit price
            quantity: 1,
            max_stock: parseInt(product.quantity)
        });
    }
    renderCart();
}

function updateCartQty(index, delta) {
    const newQty = cart[index].quantity + delta;
    if (newQty <= 0) {
        removeFromCart(index);
    } else if (newQty > cart[index].max_stock) {
        showToast(`Stock limit reached (${cart[index].max_stock})`, 'warning');
    } else {
        cart[index].quantity = newQty;
        renderCart();
    }
}

function updateCartPrice(index, newPrice) {
    const val = parseFloat(newPrice);
    if (!isNaN(val) && val >= 0) {
        cart[index].price = val;
        renderCart(false);
    }
}

function removeFromCart(index) {
    cart.splice(index, 1);
    renderCart();
}

function clearCart() {
    cart = [];
    document.getElementById('cart-discount-input').value = '0';
    renderCart();
}

function renderCart(fullRebuild = true) {
    const container = document.getElementById('cart-items-container');
    const subtotalEl = document.getElementById('cart-subtotal');
    const grandTotalEl = document.getElementById('cart-grand-total');
    const discountInput = document.getElementById('cart-discount-input');

    let subtotal = 0;
    cart.forEach(item => {
        subtotal += item.price * item.quantity;
    });

    const discount = Math.min(subtotal, Math.max(0, parseFloat(discountInput ? discountInput.value : 0) || 0));

    if (fullRebuild && container) {
        container.innerHTML = '';
        if (cart.length === 0) {
            container.innerHTML = '<div style="text-align:center; color: var(--text-muted); padding: 3rem 1rem;">Cart is empty.<br>Scan barcode or select products to begin.</div>';
        } else {
            cart.forEach((item, idx) => {
                const row = document.createElement('div');
                row.className = 'cart-item-row';
                row.innerHTML = `
                    <div class="cart-item-top">
                        <span class="cart-item-title">${escapeHtml(item.name)}</span>
                        <button class="cart-item-remove" onclick="removeFromCart(${idx})" title="Remove item">&times;</button>
                    </div>
                    <div class="cart-item-controls">
                        <div class="qty-btn-group">
                            <button class="qty-btn" onclick="updateCartQty(${idx}, -1)">-</button>
                            <input class="qty-input" type="text" value="${item.quantity}" readonly>
                            <button class="qty-btn" onclick="updateCartQty(${idx}, 1)">+</button>
                        </div>
                        <div style="display:flex; align-items:center; gap:4px;">
                            <span style="font-size:0.8rem; color:var(--text-muted);">৳</span>
                            <input class="cart-price-input" type="number" step="0.01" value="${item.price.toFixed(2)}" onchange="updateCartPrice(${idx}, this.value)" title="Click to edit unit sale price">
                        </div>
                        <div style="font-weight:700; font-size:0.95rem; color:var(--text-primary);">
                            ৳ ${(item.price * item.quantity).toFixed(2)}
                        </div>
                    </div>
                `;
                container.appendChild(row);
            });
        }
    }

    const grandTotal = subtotal - discount;
    if (subtotalEl) subtotalEl.innerText = '৳ ' + subtotal.toFixed(2);
    if (grandTotalEl) grandTotalEl.innerText = '৳ ' + grandTotal.toFixed(2);
}

function searchCustomers(query) {
    const resultsBox = document.getElementById('customer-search-results');
    if (!resultsBox) return;

    fetch(`api/customer-search.php?q=${encodeURIComponent(query)}`)
        .then(res => res.json())
        .then(data => {
            resultsBox.innerHTML = '';
            if (data.success && data.customers) {
                data.customers.forEach(c => {
                    const item = document.createElement('div');
                    item.style.cssText = 'padding: 8px 12px; cursor: pointer; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; font-size: 0.88rem;';
                    item.innerHTML = `<span><strong>${escapeHtml(c.name)}</strong> (${escapeHtml(c.phone || 'N/A')})</span>`;
                    item.onclick = () => selectCustomer(c);
                    resultsBox.appendChild(item);
                });
            }
        });
}

function selectCustomer(cust) {
    selectedCustomer = cust;
    const nameEl = document.getElementById('selected-customer-name');
    const phoneEl = document.getElementById('selected-customer-phone');
    if (nameEl) nameEl.innerText = cust.name;
    if (phoneEl) phoneEl.innerText = cust.phone || '';
    closeModal('modal-select-customer');
    showToast(`Selected Customer: ${cust.name}`, 'info');
}

function processCheckout() {
    if (cart.length === 0) {
        showToast('Cannot checkout with an empty cart!', 'warning');
        return;
    }

    const discountInput = document.getElementById('cart-discount-input');
    const discountVal = parseFloat(discountInput ? discountInput.value : 0) || 0;
    const paymentMethod = document.getElementById('payment-method-select')?.value || 'Cash';
    const checkoutBtn = document.getElementById('pos-checkout-btn');

    if (checkoutBtn) {
        checkoutBtn.disabled = true;
        checkoutBtn.innerHTML = 'Processing...';
    }

    const payload = {
        customer_id: selectedCustomer.id,
        items: cart,
        discount_amount: discountVal,
        payment_method: paymentMethod,
        notes: document.getElementById('cart-notes-input')?.value || ''
    };

    fetch('api/pos-checkout.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast('Sale Completed Successfully!', 'success');
            renderPrintableReceipt(data.sale);
            clearCart();
            loadProducts();
            openModal('modal-receipt');
        } else {
            showToast(data.message || 'Checkout failed', 'danger');
        }
    })
    .catch(err => {
        showToast('Checkout transaction failed. Server error.', 'danger');
        console.error(err);
    })
    .finally(() => {
        if (checkoutBtn) {
            checkoutBtn.disabled = false;
            checkoutBtn.innerHTML = 'Checkout & Print Receipt';
        }
    });
}

/**
 * Render Thermal Receipt Modal
 * Show Adjusted Selling Price on item line and display Overall Discount in totals
 */
function renderPrintableReceipt(sale) {
    const container = document.getElementById('printable-receipt-modal');
    if (!container) return;

    let itemsHtml = '';
    sale.items.forEach(item => {
        const unitPrice = parseFloat(item.adjusted_selling_price);
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
                <div class="receipt-shop-name">${escapeHtml(sale.shop_name)}</div>
                <div style="font-size: 10px; margin-top:2px;">${escapeHtml(sale.shop_location)}</div>
                <div style="font-size: 10px;">Mobile: ${escapeHtml(sale.shop_mobile)}</div>
            </div>
            <div class="receipt-details">
                <div><strong>Invoice:</strong> ${escapeHtml(sale.invoice_no)}</div>
                <div><strong>Date:</strong> ${escapeHtml(sale.date)}</div>
                <div><strong>Customer:</strong> ${escapeHtml(sale.customer.name)} (${escapeHtml(sale.customer.phone || '')})</div>
                <div><strong>Salesperson:</strong> ${escapeHtml(sale.seller)}</div>
                <div><strong>Payment:</strong> ${escapeHtml(sale.payment_method)}</div>
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
                <div>Please keep this receipt for return/exchange within 7 days.</div>
                <div class="receipt-dev-branding">Developed by: Developers (${escapeHtml(sale.developer_website)})</div>
            </div>
        </div>
    `;
}

function printCurrentReceipt() {
    window.print();
}

function escapeHtml(str) {
    return String(str || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
