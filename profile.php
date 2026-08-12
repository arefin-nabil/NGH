<?php
$pageTitle = "My Profile Settings";
require_once __DIR__ . '/includes/header.php';

$user_id = $_SESSION['user_id'];
$successMsg = '';
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';

    if (empty($name) || empty($email)) {
        $errorMsg = "Name and Email cannot be empty.";
    } else {
        try {
            // Verify user in DB
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $userDb = $stmt->fetch();

            if (!empty($new_password)) {
                if (!password_verify($current_password, $userDb['password_hash'])) {
                    $errorMsg = "Current password is incorrect!";
                } else {
                    $newHash = password_hash($new_password, PASSWORD_BCRYPT);
                    $stmtUp = $pdo->prepare("UPDATE users SET name = ?, email = ?, password_hash = ? WHERE id = ?");
                    $stmtUp->execute([$name, $email, $newHash, $user_id]);
                    $_SESSION['user_name'] = $name;
                    $_SESSION['user_email'] = $email;
                    $successMsg = "Profile and password updated successfully!";
                }
            } else {
                $stmtUp = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
                $stmtUp->execute([$name, $email, $user_id]);
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;
                $successMsg = "Profile details updated successfully!";
            }
        } catch (Exception $e) {
            $errorMsg = "Update failed: " . $e->getMessage();
        }
    }
}

// Fetch fresh details
$stmtFetch = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmtFetch->execute([$user_id]);
$currentUser = $stmtFetch->fetch();
?>

<div class="card" style="max-width: 600px; margin: 0 auto;">
    <div class="card-header">
        <div class="card-title">Update Profile & Security Credentials</div>
    </div>

    <?php if (!empty($successMsg)): ?>
        <div style="background: rgba(16,185,129,0.15); border:1px solid var(--accent-emerald); color:var(--accent-emerald); padding:0.75rem 1rem; border-radius:var(--radius-sm); margin-bottom:1rem; font-weight:600;">
            <?= htmlspecialchars($successMsg) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errorMsg)): ?>
        <div style="background: rgba(244,63,94,0.15); border:1px solid var(--accent-rose); color:var(--accent-rose); padding:0.75rem 1rem; border-radius:var(--radius-sm); margin-bottom:1rem; font-weight:600;">
            <?= htmlspecialchars($errorMsg) ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label class="form-label">Full Name</label>
            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($currentUser['name']) ?>" required>
        </div>

        <div class="form-group">
            <label class="form-label">Email Address</label>
            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($currentUser['email']) ?>" required>
        </div>

        <div style="border-top:1px solid var(--border-color); margin:1.5rem 0; padding-top:1rem;">
            <div style="font-weight:600; font-size:0.9rem; color:var(--accent-gold); margin-bottom:0.75rem;">Change Password (Optional)</div>
            
            <div class="form-group">
                <label class="form-label">Current Password</label>
                <input type="password" name="current_password" class="form-control" placeholder="Required only if changing password">
            </div>

            <div class="form-group">
                <label class="form-label">New Password</label>
                <input type="password" name="new_password" class="form-control" placeholder="Minimum 6 characters">
            </div>
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 0.75rem;">
            Save Profile Changes
        </button>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
