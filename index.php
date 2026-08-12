<?php
require_once __DIR__ . '/api/config.php';

if (is_logged_in()) {
    header('Location: pos.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= SHOP_NAME ?> POS</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            align-items: center;
            justify-content: center;
            background: radial-gradient(circle at top right, #1e293b, #0f172a);
        }
        .login-card {
            width: 100%;
            max-width: 420px;
            background: rgba(30, 41, 59, 0.8);
            backdrop-filter: blur(12px);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 2.5rem 2rem;
            box-shadow: var(--shadow-lg);
        }
        .login-brand {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-brand-icon {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, var(--accent-gold), var(--accent-rose));
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 0.75rem;
            box-shadow: 0 8px 20px rgba(245, 158, 11, 0.35);
        }
        .login-title {
            font-family: var(--font-heading);
            font-size: 1.6rem;
            font-weight: 700;
        }
        .login-subtitle {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-top: 0.2rem;
        }
        .demo-credentials-box {
            background-color: var(--bg-primary);
            border: 1px dashed var(--border-color);
            border-radius: var(--radius-md);
            padding: 1rem;
            margin-top: 1.5rem;
            font-size: 0.8rem;
        }
        .demo-btn {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            padding: 4px 8px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.75rem;
        }
        .demo-btn:hover {
            color: var(--accent-gold);
            border-color: var(--accent-gold);
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-brand">
            <div class="login-brand-icon">N</div>
            <div class="login-title"><?= SHOP_NAME ?></div>
            <div class="login-subtitle">Point of Sale System</div>
        </div>

        <form id="login-form">
            <div class="form-group">
                <label class="form-label">Email Address</label>
                <input type="email" id="email" name="email" class="form-control" placeholder="admin@fashion.com" required autofocus>
            </div>

            <div class="form-group">
                <label class="form-label">Password</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>

            <button type="submit" id="login-btn" class="btn btn-primary" style="width: 100%; justify-content: center; margin-top: 1rem; padding: 0.8rem;">
                Sign In to Terminal
            </button>
        </form>

        <div class="demo-credentials-box">
            <div style="font-weight: 600; color: var(--accent-gold); margin-bottom: 0.5rem;">Quick Demo Shortcuts:</div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem;">
                <span>Superadmin: <code>admin@fashion.com</code> / <code>admin123</code></span>
                <button type="button" class="demo-btn" onclick="fillCredentials('admin@fashion.com', 'admin123')">Fill</button>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span>Seller Account: <code>seller@fashion.com</code> / <code>seller123</code></span>
                <button type="button" class="demo-btn" onclick="fillCredentials('seller@fashion.com', 'seller123')">Fill</button>
            </div>
        </div>
    </div>

    <script src="assets/js/app.js"></script>
    <script>
        function fillCredentials(email, pass) {
            document.getElementById('email').value = email;
            document.getElementById('password').value = pass;
        }

        document.getElementById('login-form').addEventListener('submit', (e) => {
            e.preventDefault();
            const btn = document.getElementById('login-btn');
            btn.disabled = true;
            btn.innerHTML = 'Signing in...';

            const formData = new FormData(e.target);

            fetch('api/auth.php?action=login', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => window.location.href = data.redirect, 800);
                } else {
                    showToast(data.message || 'Login failed', 'danger');
                    btn.disabled = false;
                    btn.innerHTML = 'Sign In to Terminal';
                }
            })
            .catch(err => {
                showToast('Server connection error', 'danger');
                btn.disabled = false;
                btn.innerHTML = 'Sign In to Terminal';
            });
        });
    </script>
</body>
</html>
