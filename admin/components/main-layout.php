<?php
// Main Admin Layout - Complete layout with header and sidebar
require_once '../../includes/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/ProductManager.php';
require_once '../../includes/SalesManager.php';

// Check if user is authenticated and is admin
requireLogin();
if (!isAdmin()) {
    redirect('/login.php');
    exit;
}

// Get user info
$currentUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>BrewTopia Admin</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            --primary-color: #8B4513;
            --secondary-color: #D2B48C;
            --accent-color: #F5E6D3;
            --dark-color: #5D2F0A;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
        }

        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 280px;
            height: 100vh;
            background: linear-gradient(180deg, var(--primary-color) 0%, var(--dark-color) 100%);
            position: fixed;
            left: 0;
            top: 0;
            z-index: 1000;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }

        .sidebar .brand {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar .brand h3 {
            color: white;
            margin: 0;
            font-weight: bold;
        }

        .sidebar .user-info {
            padding: 15px 20px;
            color: white;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar .nav {
            padding: 20px 0;
        }

        .sidebar .nav-item {
            margin: 5px 15px;
        }

        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 15px;
            border-radius: 10px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            text-decoration: none;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: white;
            transform: translateX(5px);
        }

        .sidebar .nav-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        /* Main Content */
        .main-content {
            margin-left: 280px;
            min-height: 100vh;
        }

        /* Header */
        .header {
            background: white;
            padding: 20px 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .header h1 {
            color: var(--primary-color);
            margin: 0;
            font-size: 28px;
            font-weight: bold;
        }

        .welcome-text {
            color: #666;
            margin: 0;
            font-size: 14px;
        }

        /* Content Area */
        .content {
            padding: 0 30px 30px 30px;
        }

        /* Cards */
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }

        .card-header {
            background: var(--accent-color);
            border-bottom: none;
            padding: 20px;
            border-radius: 15px 15px 0 0 !important;
        }

        .card-title {
            color: var(--primary-color);
            font-weight: bold;
            margin: 0;
        }

        /* Buttons */
        .btn-primary {
            background: var(--primary-color);
            border-color: var(--primary-color);
            border-radius: 10px;
            padding: 10px 20px;
        }

        .btn-primary:hover {
            background: var(--dark-color);
            border-color: var(--dark-color);
        }

        /* Tables */
        .table {
            margin: 0;
        }

        .table thead th {
            background: var(--secondary-color);
            color: var(--dark-color);
            border: none;
            font-weight: bold;
        }

        .table tbody tr:hover {
            background: rgba(139, 69, 19, 0.05);
        }

        /* Status badges */
        .badge {
            font-size: 12px;
            padding: 5px 10px;
            border-radius: 20px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="brand">
            <h3>☕ BrewTopia</h3>
            <small style="color: rgba(255,255,255,0.7);">Admin Panel</small>
        </div>
        
        <div class="user-info">
            <div><i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?></div>
            <small style="opacity: 0.7;">Administrator</small>
        </div>
        
        <nav class="nav flex-column">
            <div class="nav-item">
                <a href="dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="bi bi-speedometer2"></i>
                    Dashboard
                </a>
            </div>
            <div class="nav-item">
                <a href="products.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'products.php' ? 'active' : ''; ?>">
                    <i class="bi bi-box-seam"></i>
                    Manage Products
                </a>
            </div>
            <div class="nav-item">
                <a href="inventory.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'inventory.php' ? 'active' : ''; ?>">
                    <i class="bi bi-boxes"></i>
                    Inventory
                </a>
            </div>
            <div class="nav-item">
                <a href="records.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'records.php' ? 'active' : ''; ?>">
                    <i class="bi bi-receipt"></i>
                    Sales Records
                </a>
            </div>
            <div class="nav-item">
                <a href="settings.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
                    <i class="bi bi-gear"></i>
                    Settings
                </a>
            </div>
            <div class="nav-item">
                <a href="../../logout.php" class="nav-link text-danger">
                    <i class="bi bi-box-arrow-right"></i>
                    Logout
                </a>
            </div>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header d-flex justify-content-between align-items-center">
            <div>
                <h1><?php echo isset($page_title) ? $page_title : 'Admin Dashboard'; ?></h1>
                <p class="welcome-text">Welcome back, Administrator</p>
            </div>
            <div class="text-muted">
                <?php echo date('D, M j, Y g:i:s A'); ?>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content">