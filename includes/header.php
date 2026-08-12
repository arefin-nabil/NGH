<?php
require_once __DIR__ . '/../api/config.php';
require_login();
$user = current_user();
$activePage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - ' : '' ?><?= SHOP_NAME ?> POS</title>
    <!-- Stylesheet -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="app-container">
        <!-- Sidebar Inclusion -->
        <?php include __DIR__ . '/sidebar.php'; ?>

        <!-- Main Wrapper -->
        <div class="main-wrapper">
            <!-- Topbar Header -->
            <header class="topbar">
                <div class="topbar-title">
                    <?= isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Dashboard' ?>
                </div>
                <div class="topbar-actions">
                    <span class="badge badge-info" style="font-size: 0.8rem; padding: 0.35rem 0.75rem;">
                        Role: <?= ucfirst($user['role']) ?>
                    </span>
                    <a href="pos.php" class="btn btn-primary btn-sm">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/></svg>
                        POS Terminal
                    </a>
                </div>
            </header>

            <!-- Main Scrollable Body -->
            <main class="content-body">
