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
        
        <a href="inventory.php" class="nav-item <?php echo (in_array($current_page, ['inventory', 'inventory-ingredients', 'inventory-recipes', 'inventory-packaging', 'inventory-addons'])) ? 'active' : ''; ?>" onclick="toggleInventorySubmenu(event)">
            <i class="fas fa-warehouse"></i>
            <span>INVENTORY</span>
            <i class="fas fa-chevron-down submenu-arrow" id="inventory-arrow"></i>
        </a>
        
        <!-- Inventory Submenu -->
        <div class="submenu" id="inventory-submenu" style="display: <?php echo (in_array($current_page, ['inventory', 'inventory-ingredients', 'inventory-recipes', 'inventory-packaging', 'inventory-addons'])) ? 'block' : 'none'; ?>;">
            <a href="inventory.php" class="submenu-item <?php echo ($current_page == 'inventory') ? 'active' : ''; ?>">
                <i class="fas fa-boxes"></i>
                <span>Products</span>
            </a>
            <a href="inventory-ingredients.php" class="submenu-item <?php echo ($current_page == 'inventory-ingredients') ? 'active' : ''; ?>">
                <i class="fas fa-flask"></i>
                <span>Ingredients</span>
            </a>
            <a href="inventory-recipes.php" class="submenu-item <?php echo ($current_page == 'inventory-recipes') ? 'active' : ''; ?>">
                <i class="fas fa-receipt"></i>
                <span>Recipes / BOM</span>
            </a>
            <a href="inventory-packaging.php" class="submenu-item <?php echo ($current_page == 'inventory-packaging') ? 'active' : ''; ?>">
                <i class="fas fa-box"></i>
                <span>Packaging & Supplies</span>
            </a>
            <a href="inventory-addons.php" class="submenu-item <?php echo ($current_page == 'inventory-addons') ? 'active' : ''; ?>">
                <i class="fas fa-plus-circle"></i>
                <span>Add-Ons</span>
            </a>
        </div>
        
        <a href="sales.php" class="nav-item <?php echo ($current_page == 'sales') ? 'active' : ''; ?>">
            <i class="fas fa-chart-line"></i>
            <span>SALES</span>
        </a>
        
        <a href="records.php" class="nav-item <?php echo ($current_page == 'records') ? 'active' : ''; ?>">
            <i class="fas fa-file-alt"></i>
            <span>RECORDS</span>
        </a>
        
        <a href="backup.php" class="nav-item <?php echo ($current_page == 'backup') ? 'active' : ''; ?>">
            <i class="fas fa-shield-alt"></i>
            <span>BACKUP & RESTORE</span>
        </a>
        
        <a href="settings.php" class="nav-item <?php echo ($current_page == 'settings') ? 'active' : ''; ?>">
            <i class="fas fa-cog"></i>
            <span>SETTINGS</span>
        </a>
    </nav>
    
    <div class="sidebar-footer">
        <a href="dashboard.php?action=logout" class="nav-item" style="color: white;">
            <i class="fas fa-sign-out-alt"></i>
            <span>LOG OUT</span>
        </a>
    </div>
</div>

<style>
.submenu {
    background-color: rgba(0, 0, 0, 0.2);
    overflow: hidden;
    transition: max-height 0.3s ease;
}

.submenu-item {
    display: flex;
    align-items: center;
    padding: 12px 20px 12px 40px;
    color: rgba(255, 255, 255, 0.8);
    text-decoration: none;
    transition: all 0.3s ease;
    font-size: 14px;
}

.submenu-item i {
    margin-right: 12px;
    width: 20px;
    font-size: 14px;
}

.submenu-item:hover {
    background-color: rgba(255, 255, 255, 0.1);
    color: white;
    padding-left: 45px;
}

.submenu-item.active {
    background-color: rgba(255, 255, 255, 0.15);
    color: white;
    font-weight: 600;
}

.submenu-arrow {
    margin-left: auto;
    font-size: 12px;
    transition: transform 0.3s ease;
}

.submenu-arrow.rotated {
    transform: rotate(180deg);
}

.nav-item {
    position: relative;
}
</style>

<script>
function toggleInventorySubmenu(event) {
    event.preventDefault();
    const submenu = document.getElementById('inventory-submenu');
    const arrow = document.getElementById('inventory-arrow');
    
    if (submenu.style.display === 'none' || submenu.style.display === '') {
        submenu.style.display = 'block';
        arrow.classList.add('rotated');
    } else {
        submenu.style.display = 'none';
        arrow.classList.remove('rotated');
    }
}

// Initialize arrow state on page load
document.addEventListener('DOMContentLoaded', function() {
    const submenu = document.getElementById('inventory-submenu');
    const arrow = document.getElementById('inventory-arrow');
    
    if (submenu.style.display === 'block') {
        arrow.classList.add('rotated');
    }
});
</script>