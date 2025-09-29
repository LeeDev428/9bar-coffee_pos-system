<?php
// Admin Main Layout
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

// Require login and admin role
$auth->requireLogin();
$auth->requireAdmin();

$user = $auth->getCurrentUser();

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $auth->logout();
    header('Location: ../../login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Admin Panel'; ?> - 9Bar POS</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>
        
        <div class="main-content">
            <?php include 'header.php'; ?>
            
            <div class="content">
                <?php displayAlert(); ?>
                <!-- Page content will be included here -->