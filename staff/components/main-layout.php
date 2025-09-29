<?php
// Staff Main Layout - Complete layout with header and sidebar
require_once '../../includes/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/ProductManager.php';
require_once '../../includes/SalesManager.php';

// Initialize database and auth
try {
    $db = new Database();
    $auth = new Auth($db);
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Handle logout BEFORE any HTML output
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $auth->logout();
    header('Location: ../../login.php');
    exit();
}

// Check if user is authenticated and is staff or admin
$auth->requireLogin();
if (!in_array($_SESSION['role'], ['staff', 'admin'])) {
    header('Location: ../../login.php');
    exit();
}

// Get user info
$currentUser = $auth->getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>9Bar Coffee Staff</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f6f2ed; /* cream background */
            font-size: 14px;
        }

        /* Sidebar */
        .sidebar {
            width: 180px;
            height: 100vh;
            background: linear-gradient(135deg,#3b2f2b 0%,#5a3f36 100%); /* coffee gradient */
            position: fixed;
            left: 0;
            top: 0;
            z-index: 1000;
            overflow-y: auto;
        }

        .sidebar-user {
            padding: 20px 15px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #c79a6e; /* caramel avatar */
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            color: #3b2f2b;
            font-size: 20px;
        }

        .user-info h4 {
            color: white;
            font-size: 14px;
            margin-bottom: 3px;
            font-weight: 500;
        }

        .user-info p {
            color: rgba(255,255,255,0.7);
            font-size: 11px;
            text-transform: uppercase;
        }

        .sidebar-nav {
            padding: 15px 0;
        }

        .nav-item {
            display: flex;
            align-items: center;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            padding: 12px 20px;
            margin: 1px 0;
            transition: all 0.2s ease;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .nav-item:hover,
        .nav-item.active {
            background: rgba(199,154,110,0.18); /* soft caramel highlight */
            color: white;
            text-decoration: none;
        }

        .nav-item i {
            margin-right: 10px;
            width: 16px;
            text-align: center;
            font-size: 14px;
        }

        .sidebar-footer {
            position: absolute;
            bottom: 15px;
            width: 100%;
        }

        /* Main Content */
        .main-content {
            margin-left: 180px;
            min-height: 100vh;
            background: #f5f5f5;
        }

        /* Header */
        .header {
            background: white;
            padding: 20px 25px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 25px;
        }

        .header-title {
            font-size: 18px;
            font-weight: 600;
            color: #3b2f2b;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .header-subtitle {
            font-size: 12px;
            color: #7f8c8d;
            margin: 3px 0 0 0;
        }

        .welcome-section {
            background: #f6eadf;
            border-radius: 8px;
            padding: 15px 20px;
            text-align: left;
        }

        .welcome-title {
            font-size: 14px;
            color: #3b2f2b;
            margin-bottom: 3px;
        }

        .welcome-subtitle {
            font-size: 16px;
            font-weight: 600;
            color: #3b2f2b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Content Area */
        .content {
            padding: 0 25px 25px 25px;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: linear-gradient(135deg,#5a3f36 0%,#3b2f2b 100%);
            border-radius: 8px;
            padding: 20px 15px;
            text-align: center;
            color: white;
            min-height: 120px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .stat-icon {
            font-size: 24px;
            margin-bottom: 8px;
            opacity: 0.8;
        }

        .stat-title {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
            opacity: 0.9;
            font-weight: 600;
        }

        .stat-value {
            font-size: 22px;
            font-weight: 700;
            line-height: 1;
        }

        /* POS Specific Styles */
        .pos-container {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 20px;
            height: calc(100vh - 150px);
        }

        .products-section {
            background: #fffdfa;
            border-radius: 8px;
            padding: 20px;
            overflow-y: auto;
        }

        .cart-section {
            background: white;
            border-radius: 8px;
            padding: 20px;
            display: flex;
            flex-direction: column;
        }

        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .product-card {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .product-card:hover {
            background: #f6e9dd;
            border-color: #c79a6e;
        }

        .product-name {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .product-price {
            font-size: 16px;
            color: #3b2f2b;
            font-weight: bold;
        }

        .cart-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }

        .cart-total {
            background: #5a3f36;
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin-top: auto;
        }

        /* Table Styles */
        .table-responsive {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .table th {
            background-color: #5a3f36;
            color: white;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-primary {
            background-color: #5a3f36;
            border-color: #5a3f36;
        }

        .btn-primary:hover {
            background-color: #46312d;
            border-color: #46312d;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .pos-container {
                grid-template-columns: 1fr;
                height: auto;
            }
            
            .product-grid {
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-user">
            <div class="user-avatar">
                <i class="bi bi-person-fill"></i>
            </div>
            <div class="user-info">
                <h4><?php echo htmlspecialchars($currentUser['full_name']); ?></h4>
                <p><?php echo ucfirst($currentUser['role']); ?></p>
            </div>
        </div>
        
        <nav class="sidebar-nav">
            <?php
            $current_page = basename($_SERVER['PHP_SELF']);
            $nav_items = [
                ['icon' => 'bi-speedometer2', 'text' => 'DASHBOARD', 'page' => 'dashboard.php'],
                ['icon' => 'bi-calculator', 'text' => 'POINT OF SALE', 'page' => 'pos.php'],
                ['icon' => 'bi-boxes', 'text' => 'INVENTORY', 'page' => 'inventory.php']
            ];
            
            foreach ($nav_items as $item) {
                $active = ($current_page == $item['page']) ? 'active' : '';
                echo "<a href='{$item['page']}' class='nav-item {$active}'>";
                echo "<i class='bi {$item['icon']}'></i>";
                echo "<span>{$item['text']}</span>";
                echo "</a>";
            }
            ?>
        </nav>
        
        <div class="sidebar-footer">
            <a href="dashboard.php?action=logout" class="nav-item" style="color: #e74c3c;">
                <i class="bi bi-box-arrow-right"></i>
                <span>LOG OUT</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1 class="header-title"><?php echo isset($page_title) ? $page_title : 'DASHBOARD'; ?></h1>
                    <p class="header-subtitle"><?php echo date('D, M-d-Y h:i:s a'); ?></p>
                </div>
                <div class="welcome-section">
                    <div class="welcome-title">Hi <strong><?php echo htmlspecialchars($currentUser['username']); ?></strong></div>
                    <div class="welcome-subtitle">WELCOME BACK</div>
                </div>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content">