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
    
    <!-- CSS Assets -->
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
            width: 240px;
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
            margin-left: 240px;
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
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <?php include 'header.php'; ?>

        <!-- Content Area -->
        <div class="content">
            <?php displayAlert(); ?>
            <!-- Page content will be included here -->