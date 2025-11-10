<?php
// Admin Header Component
$page_titles = [
    'dashboard' => 'DASHBOARD',
    'products' => 'MANAGE PRODUCTS',
    'inventory' => 'INVENTORY MANAGEMENT',
    'records' => 'RECORDS',
    'sales' => 'SALES',
    'settings' => 'SETTINGS'
];

$current_page = basename($_SERVER['PHP_SELF'], '.php');
$page_title = $page_titles[$current_page] ?? ($page_title ?? 'DASHBOARD');
?>
<div class="header">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1 class="header-title"><?php echo $page_title; ?></h1>
            <p class="header-subtitle">
            <?php
date_default_timezone_set('Asia/Manila'); // Make sure time is correct
echo date('D, M d, Y h:i:s A');
?>

            </p>
        </div>
        <div style="color: #7f8c8d; font-size: 0.9rem;">
            Welcome back, <?php echo htmlspecialchars($user['full_name']); ?>
        </div>
    </div>
</div>