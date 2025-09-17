<?php
// Admin Header Component
$page_titles = [
    'dashboard' => 'DASHBOARD',
    'products' => 'MANAGE PRODUCT',
    'inventory' => 'INVENTORY',
    'records' => 'RECORD',
    'settings' => 'SETTING'
];

$current_page = basename($_SERVER['PHP_SELF'], '.php');
$page_title = $page_titles[$current_page] ?? 'DASHBOARD';
?>
<div class="header">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1 class="header-title"><?php echo $page_title; ?></h1>
            <p class="header-subtitle"><?php echo date('D, M-d-Y h:i:s a'); ?></p>
        </div>
        <div class="welcome-message">
            Hi <strong>admin</strong><br>
            <span style="font-size: 18px; font-weight: 600;">WELCOME BACK</span>
        </div>
    </div>
</div>