<?php
// Staff Sidebar Component - Limited Access
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>
<div class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-user">
            <div class="user-avatar">
                <i class="fas fa-user"></i>
            </div>
            <div class="user-info">
                <h4><?php echo htmlspecialchars($currentUser['full_name']); ?></h4>
                <p><?php echo ucfirst($currentUser['role']); ?></p>
            </div>
        </div>
    </div>
    
    <nav class="sidebar-nav">
        <a href="dashboard.php" class="nav-item <?php echo ($current_page == 'dashboard') ? 'active' : ''; ?>">
            <i class="fas fa-tachometer-alt"></i>
            <span>DASHBOARD</span>
        </a>
        
        <a href="pos.php" class="nav-item <?php echo ($current_page == 'pos') ? 'active' : ''; ?>">
            <i class="fas fa-cash-register"></i>
            <span>POINT OF SALE</span>
        </a>
        
        <a href="sales.php" class="nav-item <?php echo ($current_page == 'sales') ? 'active' : ''; ?>">
            <i class="fas fa-chart-line"></i>
            <span>SALES RECORDS</span>
        </a>
        
        <a href="inventory.php" class="nav-item <?php echo ($current_page == 'inventory') ? 'active' : ''; ?>">
            <i class="fas fa-warehouse"></i>
            <span>INVENTORY</span>
        </a>
        
        <!-- Limited access - no manage products, settings -->
    </nav>
    
    <div class="sidebar-footer">
        <a href="dashboard.php?action=logout" class="nav-item" style="color: white;">
            <i class="fas fa-sign-out-alt"></i>
            <span>LOG OUT</span>
        </a>
    </div>
</div>