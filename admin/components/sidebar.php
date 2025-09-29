<?php
// Admin Sidebar Component
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>
<div class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-user">
            <div class="user-avatar">
                <i class="fas fa-user"></i>
            </div>
            <div class="user-info">
                <h4><?php echo htmlspecialchars($user['full_name']); ?></h4>
                <p><?php echo ucfirst($user['role']); ?></p>
            </div>
        </div>
    </div>
    
    <nav class="sidebar-nav">
        <a href="dashboard.php" class="nav-item <?php echo ($current_page == 'dashboard') ? 'active' : ''; ?>">
            <i class="fas fa-tachometer-alt"></i>
            <span>DASHBOARD</span>
        </a>
        
        <a href="products.php" class="nav-item <?php echo ($current_page == 'products') ? 'active' : ''; ?>">
            <i class="fas fa-boxes"></i>
            <span>MANAGE PRODUCTS</span>
        </a>
        
        <a href="inventory.php" class="nav-item <?php echo ($current_page == 'inventory') ? 'active' : ''; ?>">
            <i class="fas fa-warehouse"></i>
            <span>INVENTORY</span>
        </a>
        
        <a href="sales.php" class="nav-item <?php echo ($current_page == 'sales') ? 'active' : ''; ?>">
            <i class="fas fa-chart-line"></i>
            <span>SALES</span>
        </a>
        
        <a href="records.php" class="nav-item <?php echo ($current_page == 'records') ? 'active' : ''; ?>">
            <i class="fas fa-file-alt"></i>
            <span>RECORDS</span>
        </a>
        
        <a href="settings.php" class="nav-item <?php echo ($current_page == 'settings') ? 'active' : ''; ?>">
            <i class="fas fa-cog"></i>
            <span>SETTINGS</span>
        </a>
    </nav>
    
    <div class="sidebar-footer">
        <a href="dashboard.php?action=logout" class="nav-item" style="color: #e74c3c;">
            <i class="fas fa-sign-out-alt"></i>
            <span>LOG OUT</span>
        </a>
    </div>
</div>