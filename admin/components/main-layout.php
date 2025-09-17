<?php
// Main Admin Layout - Complete layout with header and sidebar
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

// Check if user is authenticated and is admin
$auth->requireLogin();
$auth->requireAdmin();

// Get user info
$currentUser = $auth->getCurrentUser();
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
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            font-size: 14px;
        }

        /* Sidebar */
        .sidebar {
            width: 180px;
            height: 100vh;
            /* dark gradient to match the login left panel */
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            position: fixed;
            left: 0;
            top: 0;
            z-index: 1000;
            overflow-y: auto;
            border-right: 1px solid rgba(255,255,255,0.04);
            color: #ffffff;
        }

        .sidebar-user {
            padding: 20px 15px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.04);
        }

        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: rgba(255,255,255,0.12);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            color: #ffffff;
            font-size: 20px;
        }

        .user-info h4 {
            color: #ffffff;
            font-size: 14px;
            margin-bottom: 3px;
            font-weight: 500;
        }

        .user-info p {
            color: rgba(255,255,255,0.85);
            font-size: 11px;
            text-transform: uppercase;
        }

        .sidebar-nav {
            padding: 15px 0;
        }

        .nav-item {
            display: flex;
            align-items: center;
            color: #ffffff;
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
            background: rgba(255,255,255,0.06); /* subtle white highlight */
            color: #ffffff;
            text-decoration: none;
        }

        .nav-item i {
            margin-right: 10px;
            width: 16px;
            text-align: center;
            font-size: 14px;
            color: #ffffff;
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
            color: #2c3e50;
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
            background: #bdc3c7;
            border-radius: 8px;
            padding: 15px 20px;
            text-align: left;
        }

        .welcome-title {
            font-size: 14px;
            color: #2c3e50;
            margin-bottom: 3px;
        }

        .welcome-subtitle {
            font-size: 16px;
            font-weight: 600;
            color: #2c3e50;
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
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: #7fb3c3; /* Teal color matching the image */
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

        /* Charts Container */
        .charts-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }

        .chart-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .chart-title {
            font-size: 14px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Chart canvas sizing */
        #salesChart {
            max-height: 300px !important;
        }

        #categoryChart {
            max-height: 250px !important;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .charts-grid {
                grid-template-columns: 1fr;
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
                <p>Administrator</p>
            </div>
        </div>
        
        <nav class="sidebar-nav">
            <?php
            $current_page = basename($_SERVER['PHP_SELF']);
            $nav_items = [
                ['icon' => 'bi-speedometer2', 'text' => 'DASHBOARD', 'page' => 'dashboard.php'],
                ['icon' => 'bi-box-seam', 'text' => 'MANAGE PRODUCT', 'page' => 'products.php'],
                ['icon' => 'bi-boxes', 'text' => 'INVENTORY', 'page' => 'inventory.php'],
                ['icon' => 'bi-receipt', 'text' => 'RECORD', 'page' => 'records.php'],
                ['icon' => 'bi-gear', 'text' => 'SETTING', 'page' => 'settings.php']
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
                    <div class="welcome-title">Hi <strong>admin</strong></div>
                    <div class="welcome-subtitle">WELCOME BACK</div>
                </div>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content">