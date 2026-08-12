            </main>
        </div>
    </div>

    <!-- Quick Add Customer Modal -->
    <div class="modal-backdrop" id="modal-add-customer">
        <div class="modal-dialog">
            <div class="modal-header">
                <div class="modal-title">Add New Customer</div>
                <button class="modal-close" data-modal-close>&times;</button>
            </div>
            <form onsubmit="handleQuickCustomerSubmit(event)">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Full Name *</label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Tanvir Ahmed" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone Number *</label>
                        <input type="text" name="phone" class="form-control" placeholder="e.g. 01712345678" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email Address (Optional)</label>
                        <input type="email" name="email" class="form-control" placeholder="tanvir@example.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Delivery Address / Area</label>
                        <textarea name="address" class="form-control" rows="2" placeholder="House #, Road #, City"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Customer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- JS Scripts -->
    <script src="assets/js/app.js"></script>
</body>
</html>
