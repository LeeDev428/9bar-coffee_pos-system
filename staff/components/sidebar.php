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
                <h4><?php echo htmlspecialchars($user['full_name']); ?></h4>
                <p><?php echo ucfirst($user['role']); ?></p>
            </div>
        </div>
    </div>
    
    <nav class="sidebar-nav">
        <a href="../dashboard.php" class="nav-item <?php echo ($current_page == 'dashboard') ? 'active' : ''; ?>">
            <i class="fas fa-tachometer-alt"></i>
            <span>DASHBOARD</span>
        </a>
        
        <a href="pages/pos.php" class="nav-item <?php echo ($current_page == 'pos') ? 'active' : ''; ?>">
            <i class="fas fa-cash-register"></i>
            <span>POINT OF SALE</span>
        </a>
        
        <a href="pages/inventory.php" class="nav-item <?php echo ($current_page == 'inventory') ? 'active' : ''; ?>">
            <i class="fas fa-warehouse"></i>
            <span>INVENTORY</span>
        </a>
        
        <!-- Limited access - no manage products, settings -->
    </nav>
    
    <div class="sidebar-footer">
        <a href="../dashboard.php?action=logout" class="nav-item" style="color: #e74c3c;">
            <i class="fas fa-sign-out-alt"></i>
            <span>LOG OUT</span>
        </a>
    </div>
</div>