<?php
$pageTitle = "User Management & Seller Permissions";
require_once __DIR__ . '/includes/header.php';

if (!is_superadmin()) {
    echo '<div class="card"><div style="color:var(--accent-rose); font-weight:600;">Access Denied! User management is restricted to Superadmin only.</div></div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Handle Add/Edit User POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'seller';
    $can_view_profit = isset($_POST['can_view_profit']) ? 1 : 0;
    $can_add_expenses = isset($_POST['can_add_expenses']) ? 1 : 0;

    if ($action === 'add') {
        if (!empty($email) && !empty($password)) {
            $passHash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("
                INSERT INTO users (name, email, password_hash, role, can_view_profit, can_add_expenses)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            try {
                $stmt->execute([$name, $email, $passHash, $role, $can_view_profit, $can_add_expenses]);
                header("Location: users.php?msg=User+Created");
                exit;
            } catch (Exception $e) {
                // Email duplicate error
            }
        }
    } elseif ($action === 'edit_permissions') {
        $user_id = intval($_POST['user_id']);
        $stmt = $pdo->prepare("
            UPDATE users
            SET role = ?, can_view_profit = ?, can_add_expenses = ?
            WHERE id = ?
        ");
        $stmt->execute([$role, $can_view_profit, $can_add_expenses, $user_id]);

        // If updating password
        if (!empty($password)) {
            $passHash = password_hash($password, PASSWORD_BCRYPT);
            $stmtPass = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmtPass->execute([$passHash, $user_id]);
        }

        header("Location: users.php?msg=Permissions+Updated");
        exit;
    }
}

// Fetch all Users
$stmtUsers = $pdo->query("SELECT * FROM users ORDER BY id ASC");
$users = $stmtUsers->fetchAll();
?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
    <div style="font-size:0.9rem; color:var(--text-muted);">Manage staff login credentials and toggle access rights per user.</div>
    <button class="btn btn-primary" data-modal-target="modal-add-user">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
        Create New User Account
    </button>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title">Staff Accounts & Permission Matrix</div>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>User ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Can View Profit?</th>
                    <th>Can Add Expenses?</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td>#<?= $u['id'] ?></td>
                        <td><strong><?= htmlspecialchars($u['name']) ?></strong></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td>
                            <span class="badge <?= $u['role'] === 'superadmin' ? 'badge-danger' : 'badge-info' ?>">
                                <?= ucfirst($u['role']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($u['can_view_profit']): ?>
                                <span class="badge badge-success">Allowed</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Restricted</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($u['can_add_expenses']): ?>
                                <span class="badge badge-success">Allowed</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Restricted</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="btn btn-secondary btn-sm" onclick="openEditUserModal(<?= htmlspecialchars(json_encode($u)) ?>)">
                                Edit Permissions
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Add User Account -->
<div class="modal-backdrop" id="modal-add-user">
    <div class="modal-dialog">
        <div class="modal-header">
            <div class="modal-title">Create New Staff Account</div>
            <button class="modal-close" data-modal-close>&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Full Name *</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Rahim Ahmed" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email Address *</label>
                    <input type="email" name="email" class="form-control" placeholder="rahim@fashion.com" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Password *</label>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Account Role *</label>
                    <select name="role" class="form-control" required>
                        <option value="seller">Seller / Cashier</option>
                        <option value="superadmin">Superadmin</option>
                    </select>
                </div>
                <div style="margin-top: 1rem; padding: 0.75rem; background: var(--bg-primary); border-radius: var(--radius-sm);">
                    <div style="font-weight:600; font-size:0.85rem; margin-bottom:0.5rem; color:var(--accent-gold);">Permission Toggles:</div>
                    <label style="display:flex; align-items:center; gap:0.5rem; font-size:0.88rem; margin-bottom:0.5rem; cursor:pointer;">
                        <input type="checkbox" name="can_view_profit" value="1">
                        <span>Allow viewing Profit & Financial Reports</span>
                    </label>
                    <label style="display:flex; align-items:center; gap:0.5rem; font-size:0.88rem; cursor:pointer;">
                        <input type="checkbox" name="can_add_expenses" value="1" checked>
                        <span>Allow logging daily Shop Expenses</span>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary">Create User</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit User Permissions -->
<div class="modal-backdrop" id="modal-edit-user">
    <div class="modal-dialog">
        <div class="modal-header">
            <div class="modal-title">Edit User Permissions</div>
            <button class="modal-close" data-modal-close>&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit_permissions">
            <input type="hidden" id="edit-user-id" name="user_id">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">User Account Name</label>
                    <input type="text" id="edit-user-name" class="form-control" readonly>
                </div>
                <div class="form-group">
                    <label class="form-label">Account Role</label>
                    <select id="edit-user-role" name="role" class="form-control">
                        <option value="seller">Seller / Cashier</option>
                        <option value="superadmin">Superadmin</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Reset Password (Leave blank to keep unchanged)</label>
                    <input type="password" name="password" class="form-control" placeholder="New Password...">
                </div>
                <div style="margin-top: 1rem; padding: 0.75rem; background: var(--bg-primary); border-radius: var(--radius-sm);">
                    <div style="font-weight:600; font-size:0.85rem; margin-bottom:0.5rem; color:var(--accent-gold);">Permission Toggles:</div>
                    <label style="display:flex; align-items:center; gap:0.5rem; font-size:0.88rem; margin-bottom:0.5rem; cursor:pointer;">
                        <input type="checkbox" id="edit-can-view-profit" name="can_view_profit" value="1">
                        <span>Allow viewing Profit & Financial Reports</span>
                    </label>
                    <label style="display:flex; align-items:center; gap:0.5rem; font-size:0.88rem; cursor:pointer;">
                        <input type="checkbox" id="edit-can-add-expenses" name="can_add_expenses" value="1">
                        <span>Allow logging daily Shop Expenses</span>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary">Update Permissions</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditUserModal(user) {
    document.getElementById('edit-user-id').value = user.id;
    document.getElementById('edit-user-name').value = user.name + ' (' + user.email + ')';
    document.getElementById('edit-user-role').value = user.role;
    document.getElementById('edit-can-view-profit').checked = !!parseInt(user.can_view_profit);
    document.getElementById('edit-can-add-expenses').checked = !!parseInt(user.can_add_expenses);
    openModal('modal-edit-user');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
