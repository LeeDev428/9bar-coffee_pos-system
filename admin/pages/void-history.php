<?php
// Admin Void History Page - View all void transactions with filtering
require_once '../../includes/database.php';
require_once '../../includes/auth.php';

// Initialize database and auth
$db = new Database();
$auth = new Auth($db);
$auth->requireLogin();
$auth->requireAdmin();

// Pagination and filters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Date range filters
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d', strtotime('+1 day')); // Add 1 day to catch timezone differences

// Role filter
$roleFilter = $_GET['role'] ?? 'all'; // all, admin, staff

// Status filter
$statusFilter = $_GET['status'] ?? 'all'; // all, completed, pending_approval, approved, rejected

// Search filter
$search = $_GET['q'] ?? '';

// Build query with filters
$baseWhere = "WHERE DATE(vh.void_date) BETWEEN ? AND ?";
$params = [$startDate, $endDate];

if ($roleFilter !== 'all') {
    $baseWhere .= " AND vh.voided_by_role = ?";
    $params[] = $roleFilter;
}

if ($statusFilter !== 'all') {
    $baseWhere .= " AND vh.status = ?";
    $params[] = $statusFilter;
}

if ($search) {
    $baseWhere .= " AND (
        vh.sale_id LIKE ? OR 
        vh.voided_by_username LIKE ? OR 
        vh.void_reason LIKE ? OR
        vh.original_amount LIKE ?
    )";
    $searchParam = "%$search%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
}

// Get total count for pagination
$countSql = "SELECT COUNT(*) as total FROM void_history vh $baseWhere";
$countResult = $db->fetchOne($countSql, $params);
$totalCount = $countResult['total'] ?? 0;

// Fetch void transactions
$sql = "
    SELECT 
        vh.*,
        s.transaction_number,
        s.sale_date as original_sale_date
    FROM void_history vh
    LEFT JOIN sales s ON vh.sale_id = s.sale_id
    $baseWhere
    ORDER BY vh.void_date DESC
    LIMIT ? OFFSET ?
";
$params[] = $perPage;
$params[] = $offset;
$voidTransactions = $db->fetchAll($sql, $params);

// Get summary statistics
$totalVoidedAmount = $db->fetchOne("
    SELECT COALESCE(SUM(original_amount), 0) as total 
    FROM void_history 
    WHERE DATE(void_date) BETWEEN ? AND ?
", [$startDate, $endDate]);

$voidCountByRole = $db->fetchAll("
    SELECT voided_by_role, COUNT(*) as count 
    FROM void_history 
    WHERE DATE(void_date) BETWEEN ? AND ?
    GROUP BY voided_by_role
", [$startDate, $endDate]);

$adminVoidCount = 0;
$staffVoidCount = 0;
foreach ($voidCountByRole as $row) {
    if ($row['voided_by_role'] === 'admin') $adminVoidCount = $row['count'];
    if ($row['voided_by_role'] === 'staff') $staffVoidCount = $row['count'];
}

include '../components/main-layout.php';
?>

<style>
.void-header { margin-bottom:20px; display:flex; justify-content:space-between; align-items:center; }
.stat-card { background:#fff; padding:15px 20px; border-radius:8px; box-shadow:0 2px 4px rgba(0,0,0,0.1); display:inline-block; margin-right:15px; }
.stat-card .stat-label { font-size:12px; color:#7f8c8d; margin-bottom:5px; }
.stat-card .stat-value { font-size:24px; font-weight:bold; color:#2c3e50; }
.stat-card.danger .stat-value { color:#e74c3c; }
.stat-card.warning .stat-value { color:#f39c12; }
.stat-card.info .stat-value { color:#3498db; }
.table-void { width:100%; border-collapse:collapse; background:#fff; }
.table-void th, .table-void td { padding:12px; border-bottom:1px solid #eee; text-align:left; }
.table-void th { background:#34495e; color:#fff; font-weight:600; }
.table-void tbody tr:hover { background:#f8f9fa; }
.badge { padding:4px 8px; border-radius:4px; font-size:11px; font-weight:600; display:inline-block; }
.badge-admin { background:#3498db; color:white; }
.badge-staff { background:#9b59b6; color:white; }
.badge-completed { background:#27ae60; color:white; }
.badge-pending { background:#f39c12; color:white; }
.badge-approved { background:#1abc9c; color:white; }
.badge-rejected { background:#e74c3c; color:white; }
.filter-section { background:#fff; padding:15px; border-radius:8px; margin-bottom:20px; box-shadow:0 2px 4px rgba(0,0,0,0.1); }
.btn-view { background:#3498db; color:white; padding:6px 12px; border:none; border-radius:4px; cursor:pointer; font-size:12px; }
.btn-view:hover { background:#2980b9; }
</style>

<div class="void-header">
    <h2><i class="fas fa-history"></i> Void Transaction History</h2>
    <div>
        <div class="stat-card danger">
            <div class="stat-label">Total Voided Amount</div>
            <div class="stat-value">₱<?php echo number_format($totalVoidedAmount['total'], 2); ?></div>
        </div>
        <div class="stat-card info">
            <div class="stat-label">Admin Voids</div>
            <div class="stat-value"><?php echo $adminVoidCount; ?></div>
        </div>
        <div class="stat-card warning">
            <div class="stat-label">Staff Voids</div>
            <div class="stat-value"><?php echo $staffVoidCount; ?></div>
        </div>
    </div>
</div>

<div class="filter-section">
    <div style="display:flex; gap:15px; align-items:flex-end; flex-wrap:wrap;">
        <div>
            <label style="display:block; margin-bottom:5px; font-weight:500; font-size:13px;">From Date:</label>
            <input type="date" id="startDate" value="<?php echo $startDate; ?>" class="form-control" style="width:150px;">
        </div>
        <div>
            <label style="display:block; margin-bottom:5px; font-weight:500; font-size:13px;">To Date:</label>
            <input type="date" id="endDate" value="<?php echo $endDate; ?>" class="form-control" style="width:150px;">
        </div>
        <div>
            <label style="display:block; margin-bottom:5px; font-weight:500; font-size:13px;">Voided By:</label>
            <select id="roleFilter" class="form-control" style="width:120px;">
                <option value="all" <?php echo $roleFilter === 'all' ? 'selected' : ''; ?>>All</option>
                <option value="admin" <?php echo $roleFilter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                <option value="staff" <?php echo $roleFilter === 'staff' ? 'selected' : ''; ?>>Staff</option>
            </select>
        </div>
        <div>
            <label style="display:block; margin-bottom:5px; font-weight:500; font-size:13px;">Status:</label>
            <select id="statusFilter" class="form-control" style="width:150px;">
                <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All</option>
                <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                <option value="pending_approval" <?php echo $statusFilter === 'pending_approval' ? 'selected' : ''; ?>>Pending</option>
                <option value="approved" <?php echo $statusFilter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
            </select>
        </div>
        <div style="flex:1;">
            <label style="display:block; margin-bottom:5px; font-weight:500; font-size:13px;">Search:</label>
            <input type="text" id="searchInput" placeholder="Sale ID, username, reason..." class="form-control" value="<?php echo htmlspecialchars($search); ?>" style="width:100%; max-width:300px;">
        </div>
        <div>
            <button class="btn btn-primary" onclick="applyFilters()" style="padding:10px 20px;">
                <i class="fas fa-filter"></i> Apply Filters
            </button>
            <?php if ($search || $roleFilter !== 'all' || $statusFilter !== 'all'): ?>
            <button class="btn btn-light" onclick="clearFilters()" style="padding:10px 20px; margin-left:8px;">
                <i class="fas fa-times"></i> Clear
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (empty($voidTransactions)): ?>
    <div style="background:#fff; padding:40px; text-align:center; border-radius:8px;">
        <i class="fas fa-inbox" style="font-size:48px; color:#bdc3c7; margin-bottom:15px;"></i>
        <p style="color:#7f8c8d; font-size:16px;">No void transactions found for the selected filters.</p>
    </div>
<?php else: ?>
<table class="table-void">
    <thead>
        <tr>
            <th>Void ID</th>
            <th>Sale ID</th>
            <th>Transaction #</th>
            <th>Void Date</th>
            <th>Voided By</th>
            <th>Role</th>
            <th>Amount</th>
            <th>Payment Method</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($voidTransactions as $void): ?>
        <tr>
            <td><strong>#<?php echo $void['void_id']; ?></strong></td>
            <td><?php echo $void['sale_id']; ?></td>
            <td style="font-size:11px; color:#7f8c8d;"><?php echo htmlspecialchars($void['transaction_number'] ?? 'N/A'); ?></td>
            <td><?php echo date('Y-m-d H:i', strtotime($void['void_date'])); ?></td>
            <td><?php echo htmlspecialchars($void['voided_by_username']); ?></td>
            <td>
                <span class="badge badge-<?php echo $void['voided_by_role']; ?>">
                    <?php echo strtoupper($void['voided_by_role']); ?>
                </span>
            </td>
            <td style="font-weight:600; color:#e74c3c;">₱<?php echo number_format($void['original_amount'], 2); ?></td>
            <td><?php echo ucfirst($void['original_payment_method']); ?></td>
            <td>
                <span class="badge badge-<?php echo $void['status']; ?>">
                    <?php echo str_replace('_', ' ', strtoupper($void['status'])); ?>
                </span>
            </td>
            <td>
                <button class="btn-view" onclick="viewVoidDetails(<?php echo $void['void_id']; ?>)">
                    <i class="fas fa-eye"></i> Details
                </button>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php
$totalPages = (int) ceil($totalCount / $perPage);
if ($totalPages > 1):
?>
<div style="margin-top:20px; display:flex; justify-content:center; gap:6px; align-items:center;">
    <?php if ($page > 1): ?>
        <a class="btn btn-light" href="?start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>&role=<?php echo $roleFilter; ?>&status=<?php echo $statusFilter; ?>&q=<?php echo urlencode($search); ?>&page=<?php echo $page-1; ?>">&laquo; Prev</a>
    <?php endif; ?>

    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
        <a class="btn <?php echo $p === $page ? 'btn-primary' : 'btn-light'; ?>" href="?start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>&role=<?php echo $roleFilter; ?>&status=<?php echo $statusFilter; ?>&q=<?php echo urlencode($search); ?>&page=<?php echo $p; ?>"><?php echo $p; ?></a>
    <?php endfor; ?>

    <?php if ($page < $totalPages): ?>
        <a class="btn btn-light" href="?start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>&role=<?php echo $roleFilter; ?>&status=<?php echo $statusFilter; ?>&q=<?php echo urlencode($search); ?>&page=<?php echo $page+1; ?>">Next &raquo;</a>
    <?php endif; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- Void Details Modal -->
<div id="voidDetailsModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:white; border-radius:12px; padding:30px; max-width:700px; width:90%; box-shadow:0 10px 40px rgba(0,0,0,0.3); max-height:90vh; overflow-y:auto;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h3 style="margin:0;"><i class="fas fa-info-circle"></i> Void Transaction Details</h3>
            <button onclick="closeVoidDetails()" style="background:none; border:none; font-size:24px; color:#95a5a6; cursor:pointer;">&times;</button>
        </div>
        <div id="voidDetailsContent">
            <!-- Content loaded via AJAX -->
        </div>
    </div>
</div>

<script>
function applyFilters() {
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;
    const role = document.getElementById('roleFilter').value;
    const status = document.getElementById('statusFilter').value;
    const search = document.getElementById('searchInput').value;
    
    let url = `?start_date=${startDate}&end_date=${endDate}&role=${role}&status=${status}`;
    if (search) {
        url += `&q=${encodeURIComponent(search)}`;
    }
    window.location.href = url;
}

function clearFilters() {
    window.location.href = 'void-history.php';
}

async function viewVoidDetails(voidId) {
    document.getElementById('voidDetailsModal').style.display = 'flex';
    document.getElementById('voidDetailsContent').innerHTML = '<div style="text-align:center; padding:40px;"><i class="fas fa-spinner fa-spin" style="font-size:32px; color:#3498db;"></i><p>Loading...</p></div>';
    
    try {
        const response = await fetch(`../api/get-void-details.php?void_id=${voidId}`);
        const result = await response.json();
        
        if (result.success) {
            const data = result.data;
            let productsHtml = '';
            const products = JSON.parse(data.products_voided);
            products.forEach(p => {
                productsHtml += `
                    <tr>
                        <td>Product ID: ${p.product_id}</td>
                        <td>x${p.quantity}</td>
                        <td>₱${parseFloat(p.price).toFixed(2)}</td>
                        <td>₱${parseFloat(p.subtotal).toFixed(2)}</td>
                    </tr>
                `;
            });
            
            const html = `
                <table style="width:100%; border-collapse:collapse;">
                    <tr style="border-bottom:1px solid #eee;">
                        <td style="padding:10px; font-weight:600; width:180px;">Void ID:</td>
                        <td style="padding:10px;">#${data.void_id}</td>
                    </tr>
                    <tr style="border-bottom:1px solid #eee;">
                        <td style="padding:10px; font-weight:600;">Sale ID:</td>
                        <td style="padding:10px;">${data.sale_id}</td>
                    </tr>
                    <tr style="border-bottom:1px solid #eee;">
                        <td style="padding:10px; font-weight:600;">Transaction Number:</td>
                        <td style="padding:10px; font-size:12px;">${data.transaction_number || 'N/A'}</td>
                    </tr>
                    <tr style="border-bottom:1px solid #eee;">
                        <td style="padding:10px; font-weight:600;">Original Sale Date:</td>
                        <td style="padding:10px;">${data.original_sale_date}</td>
                    </tr>
                    <tr style="border-bottom:1px solid #eee;">
                        <td style="padding:10px; font-weight:600;">Void Date:</td>
                        <td style="padding:10px;">${data.void_date}</td>
                    </tr>
                    <tr style="border-bottom:1px solid #eee;">
                        <td style="padding:10px; font-weight:600;">Voided By:</td>
                        <td style="padding:10px;">${data.voided_by_username} <span class="badge badge-${data.voided_by_role}">${data.voided_by_role.toUpperCase()}</span></td>
                    </tr>
                    <tr style="border-bottom:1px solid #eee;">
                        <td style="padding:10px; font-weight:600;">Original Amount:</td>
                        <td style="padding:10px; font-weight:bold; color:#e74c3c; font-size:18px;">₱${parseFloat(data.original_amount).toFixed(2)}</td>
                    </tr>
                    <tr style="border-bottom:1px solid #eee;">
                        <td style="padding:10px; font-weight:600;">Payment Method:</td>
                        <td style="padding:10px;">${data.original_payment_method.toUpperCase()}</td>
                    </tr>
                    <tr style="border-bottom:1px solid #eee;">
                        <td style="padding:10px; font-weight:600;">Inventory Restored:</td>
                        <td style="padding:10px;">${data.inventory_restored === '1' ? '<span style="color:#27ae60;">✓ Yes</span>' : '<span style="color:#e74c3c;">✗ No</span>'}</td>
                    </tr>
                    <tr style="border-bottom:1px solid #eee;">
                        <td style="padding:10px; font-weight:600;">Status:</td>
                        <td style="padding:10px;"><span class="badge badge-${data.status}">${data.status.replace('_', ' ').toUpperCase()}</span></td>
                    </tr>
                    <tr style="border-bottom:1px solid #eee;">
                        <td style="padding:10px; font-weight:600; vertical-align:top;">Void Reason:</td>
                        <td style="padding:10px;">${data.void_reason}</td>
                    </tr>
                </table>
                
                <h4 style="margin-top:20px; margin-bottom:10px;">Products Voided:</h4>
                <table style="width:100%; border-collapse:collapse; background:#f8f9fa; border-radius:6px;">
                    <thead>
                        <tr style="background:#34495e; color:white;">
                            <th style="padding:8px; text-align:left;">Product</th>
                            <th style="padding:8px; text-align:left;">Qty</th>
                            <th style="padding:8px; text-align:left;">Price</th>
                            <th style="padding:8px; text-align:left;">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${productsHtml}
                    </tbody>
                </table>
            `;
            
            document.getElementById('voidDetailsContent').innerHTML = html;
        } else {
            document.getElementById('voidDetailsContent').innerHTML = `<div style="text-align:center; padding:40px; color:#e74c3c;"><i class="fas fa-exclamation-triangle" style="font-size:32px; margin-bottom:10px;"></i><p>Error: ${result.error}</p></div>`;
        }
    } catch (error) {
        document.getElementById('voidDetailsContent').innerHTML = `<div style="text-align:center; padding:40px; color:#e74c3c;"><p>Error loading details: ${error.message}</p></div>`;
    }
}

function closeVoidDetails() {
    document.getElementById('voidDetailsModal').style.display = 'none';
}

// Close modal on outside click
document.getElementById('voidDetailsModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeVoidDetails();
    }
});
</script>

<?php include '../components/layout-end.php';
