<?php
// Staff Sales Page - View and manage sales transactions
require_once '../../includes/database.php';
require_once '../../includes/auth.php';

// Initialize database and auth
$db = new Database();
$auth = new Auth($db);
$auth->requireLogin();

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
    $endDate = $_GET['end_date'] ?? date('Y-m-d');
    
    // Fetch all transactions for export
    $exportSql = "
        SELECT 
            s.sale_id,
            s.sale_date,
            s.payment_method,
            s.total_amount,
            s.payment_status,
            GROUP_CONCAT(CONCAT(p.product_name, ' (x', si.quantity, ')') ORDER BY si.sale_item_id SEPARATOR ', ') as products_list,
            SUM(si.quantity) as item_count
        FROM sales s
        LEFT JOIN sale_items si ON s.sale_id = si.sale_id
        LEFT JOIN products p ON si.product_id = p.product_id
        WHERE DATE(s.sale_date) BETWEEN ? AND ?
        GROUP BY s.sale_id
        ORDER BY s.sale_date DESC
    ";
    $exportData = $db->fetchAll($exportSql, [$startDate, $endDate]);
    
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=sales_' . date('Y-m-d_His') . '.csv');
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add BOM for Excel UTF-8 support
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Add CSV header
    fputcsv($output, ['Sale ID', 'Date', 'Products', 'Qty', 'Payment Method', 'Amount', 'Status']);
    
    // Add data rows
    foreach ($exportData as $row) {
        fputcsv($output, [
            $row['sale_id'],
            date('Y-m-d H:i', strtotime($row['sale_date'])),
            $row['products_list'] ?? 'N/A',
            $row['item_count'],
            ucfirst($row['payment_method']),
            number_format($row['total_amount'], 2),
            strtoupper($row['payment_status'])
        ]);
    }
    
    fclose($output);
    exit;
}

// Pagination and filters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Date range filters
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Search filter
$search = $_GET['q'] ?? '';

// Build query with filters
$baseWhere = "WHERE DATE(s.sale_date) BETWEEN ? AND ?";
$params = [$startDate, $endDate];

if ($search) {
    $baseWhere .= " AND (
        s.sale_id LIKE ? OR 
        s.payment_method LIKE ? OR 
        s.total_amount LIKE ? OR 
        s.gcash_reference_no LIKE ? OR
        p.product_name LIKE ?
    )";
    $searchParam = "%$search%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam]);
}

// Get total count for pagination
$countSql = "SELECT COUNT(DISTINCT s.sale_id) as total FROM sales s LEFT JOIN sale_items si ON s.sale_id = si.sale_id LEFT JOIN products p ON si.product_id = p.product_id $baseWhere";
$countResult = $db->fetchOne($countSql, $params);
$totalCount = $countResult['total'] ?? 0;

// Fetch transactions with product details
$sql = "
    SELECT 
        s.sale_id,
        s.sale_date,
        s.payment_method,
        s.total_amount,
        s.payment_status,
        GROUP_CONCAT(CONCAT(p.product_name, ' (x', si.quantity, ')') ORDER BY si.sale_item_id SEPARATOR ', ') as products_list,
        SUM(si.quantity) as item_count
    FROM sales s
    LEFT JOIN sale_items si ON s.sale_id = si.sale_id
    LEFT JOIN products p ON si.product_id = p.product_id
    $baseWhere
    GROUP BY s.sale_id
    ORDER BY s.sale_date DESC
    LIMIT ? OFFSET ?
";
$params[] = $perPage;
$params[] = $offset;
$transactions = $db->fetchAll($sql, $params);

// Get today's sales total
$todaySales = $db->fetchOne("
    SELECT COALESCE(SUM(total_amount), 0) as total 
    FROM sales 
    WHERE DATE(sale_date) = CURDATE() AND payment_status != 'voided'
");

// Get monthly sales total
$monthlySales = $db->fetchOne("
    SELECT COALESCE(SUM(total_amount), 0) as total 
    FROM sales 
    WHERE YEAR(sale_date) = YEAR(CURDATE()) 
    AND MONTH(sale_date) = MONTH(CURDATE())
    AND payment_status != 'voided'
");

include '../components/main-layout.php';
?>

<style>
.records-header { margin-bottom:20px; }
.info-card { display:inline-block; margin-right:15px; }
.info-card .info-title { font-size:13px; color:#7f8c8d; margin-bottom:3px; }
.info-card .info-value { font-size:20px; font-weight:bold; color:#2c3e50; }
.table-simple { width:100%; border-collapse:collapse; }
.table-simple th, .table-simple td { padding:8px; border-bottom:1px solid #eee; }
.btn-outline-primary:hover { background:#3498db !important; color:white !important; border-color:#3498db !important; }
.btn-light { background:#f8f9fa; border:1px solid #dee2e6; color:#495057; }
.btn-light:hover { background:#e2e6ea; }
</style>

<div class="records-header" style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
    <!-- Left: search + filters -->
    <div style="display:flex; flex-direction:column; gap:10px;">
        <div style="display:flex; gap:8px; align-items:center;">
            <input type="text" id="searchQTop" placeholder="Search by ID, Date, GCash, Cash..." class="form-control" style="width:320px;" value="<?php echo htmlspecialchars($search); ?>">
            <button class="btn btn-info" onclick="applySearchTop()">
                <i class="fas fa-search"></i> Search
            </button>
            <?php if ($search): ?>
            <button class="btn btn-light" onclick="clearSearch()" title="Clear search">
                <i class="fas fa-times"></i>
            </button>
            <?php endif; ?>
        </div>
        <div style="display:flex; gap:8px; align-items:center;">
            <label style="margin-right:6px; font-weight:500;">From:</label>
            <input type="date" id="startDate" value="<?php echo $startDate; ?>" class="form-control" style="width:150px;">
            <label style="margin-left:8px; margin-right:6px; font-weight:500;">To:</label>
            <input type="date" id="endDate" value="<?php echo $endDate; ?>" class="form-control" style="width:150px;">
            <button class="btn btn-primary" onclick="applyFilter()">
                <i class="fas fa-filter"></i> Apply
            </button>
        </div>
        <div style="display:flex; gap:6px; align-items:center;">
            <span style="font-size:12px; color:#7f8c8d; margin-right:4px;">Quick Filters:</span>
            <button class="btn btn-sm btn-outline-primary" onclick="setDateFilter('today')" style="padding:6px 12px; font-size:12px; border:1px solid #3498db; color:#3498db; background:white;">
                <i class="fas fa-calendar-day"></i> Today
            </button>
            <button class="btn btn-sm btn-outline-primary" onclick="setDateFilter('week')" style="padding:6px 12px; font-size:12px; border:1px solid #3498db; color:#3498db; background:white;">
                <i class="fas fa-calendar-week"></i> This Week
            </button>
            <button class="btn btn-sm btn-outline-primary" onclick="setDateFilter('month')" style="padding:6px 12px; font-size:12px; border:1px solid #3498db; color:#3498db; background:white;">
                <i class="fas fa-calendar-alt"></i> This Month
            </button>
            <button class="btn btn-sm btn-outline-primary" onclick="setDateFilter('year')" style="padding:6px 12px; font-size:12px; border:1px solid #3498db; color:#3498db; background:white;">
                <i class="fas fa-calendar"></i> This Year
            </button>
        </div>
    </div>

    <!-- Right: info cards and Export -->
    <div style="display:flex; gap:12px; align-items:center;">
        <div style="display:flex; gap:10px; align-items:center;">
            <div class="info-card compact" style="background:#f1f3f4; color:#2c3e50; padding:8px 12px; border-radius:6px; min-width:120px; text-align:right;">
                <div class="info-title" style="color:#7f8c8d; font-size:12px;">Today's Sales</div>
                <div class="info-value" style="font-size:16px;">₱<?php echo number_format($todaySales['total'],2); ?></div>
            </div>
            <div class="info-card compact" style="background:#f1f3f4; color:#2c3e50; padding:8px 12px; border-radius:6px; min-width:120px; text-align:right;">
                <div class="info-title" style="color:#7f8c8d; font-size:12px;">Monthly Sales</div>
                <div class="info-value" style="font-size:16px;">₱<?php echo number_format($monthlySales['total'],2); ?></div>
            </div>
        </div>
        <div style="margin-left:8px;">
            <a href="?export=csv&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>" class="btn btn-success" style="background:#1abc9c; border-color:#16a085;">Export to Excel</a>
        </div>
    </div>
</div>

<table class="table-simple" style="width:100%; border-collapse:collapse; background:#fff;">
    <thead style="background:#2c3e50; color:#fff;">
        <tr>
            <th style="padding:10px; text-align:left;">#</th>
            <th style="padding:10px; text-align:left;">Date</th>
            <th style="padding:10px; text-align:left;">Products</th>
            <th style="padding:10px; text-align:left;">Qty</th>
            <th style="padding:10px; text-align:left;">Payment</th>
            <th style="padding:10px; text-align:left;">Amount</th>
            <th style="padding:10px; text-align:left;">Status</th>
            <th style="padding:10px; text-align:center;">Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($transactions as $i => $t): ?>
        <tr style="background: <?php echo ($i % 2 === 0) ? '#ffffff' : '#f6f8f9'; ?>;">
            <td style="padding:10px;"><?php echo $t['sale_id']; ?></td>
            <td style="padding:10px;"><?php echo date('Y-m-d H:i', strtotime($t['sale_date'])); ?></td>
            <td style="padding:10px; max-width:300px;"><?php echo htmlspecialchars($t['products_list'] ?? 'N/A'); ?></td>
            <td style="padding:10px;"><?php echo $t['item_count']; ?></td>
            <td style="padding:10px;"><?php echo ucfirst($t['payment_method']); ?></td>
            <td style="padding:10px;">₱<?php echo number_format($t['total_amount'],2); ?></td>
            <td style="padding:10px;">
                <?php if ($t['payment_status'] === 'voided'): ?>
                    <span style="background:#e74c3c; color:white; padding:4px 8px; border-radius:4px; font-size:11px; font-weight:600;">VOIDED</span>
                <?php else: ?>
                    <span style="background:#27ae60; color:white; padding:4px 8px; border-radius:4px; font-size:11px; font-weight:600;">PAID</span>
                <?php endif; ?>
            </td>
            <td style="padding:10px; text-align:center;">
                <?php if ($t['payment_status'] !== 'voided'): ?>
                    <button class="btn btn-sm" onclick="openVoidModal(<?php echo $t['sale_id']; ?>, '<?php echo htmlspecialchars($t['products_list'] ?? 'N/A'); ?>', <?php echo $t['total_amount']; ?>)" style="background:#e74c3c; color:white; padding:6px 12px; border:none; border-radius:4px; cursor:pointer; font-size:12px;">
                        <i class="fas fa-ban"></i> Void
                    </button>
                <?php else: ?>
                    <span style="color:#95a5a6; font-size:12px;">—</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php
$totalPages = (int) ceil($totalCount / $perPage);
if ($totalPages > 1):
?>
<div style="margin-top:12px; display:flex; justify-content:center; gap:6px; align-items:center;">
    <?php if ($page > 1): ?>
        <a class="btn btn-light" href="?start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>&q=<?php echo urlencode($search); ?>&page=<?php echo $page-1; ?>">&laquo; Prev</a>
    <?php endif; ?>

    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
        <a class="btn <?php echo $p === $page ? 'btn-primary' : 'btn-light'; ?>" href="?start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>&q=<?php echo urlencode($search); ?>&page=<?php echo $p; ?>"><?php echo $p; ?></a>
    <?php endfor; ?>

    <?php if ($page < $totalPages): ?>
        <a class="btn btn-light" href="?start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>&q=<?php echo urlencode($search); ?>&page=<?php echo $page+1; ?>">Next &raquo;</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<script>
function applyFilter() {
    const s = document.getElementById('startDate').value;
    const e = document.getElementById('endDate').value;
    const q = document.getElementById('searchQTop') ? document.getElementById('searchQTop').value : (document.getElementById('searchQ') ? document.getElementById('searchQ').value : '');
    window.location.href = `?start_date=${s}&end_date=${e}${q?('&q='+encodeURIComponent(q)) : ''}`;
}

function applySearch() {
    const q = document.getElementById('searchQ').value;
    const s = document.getElementById('startDate').value;
    const e = document.getElementById('endDate').value;
    window.location.href = `?q=${encodeURIComponent(q)}&start_date=${s}&end_date=${e}`;
}

function applySearchTop() {
    const q = document.getElementById('searchQTop').value;
    const s = document.getElementById('startDate').value;
    const e = document.getElementById('endDate').value;
    window.location.href = `?q=${encodeURIComponent(q)}&start_date=${s}&end_date=${e}`;
}

function clearSearch() {
    const s = document.getElementById('startDate').value;
    const e = document.getElementById('endDate').value;
    window.location.href = `?start_date=${s}&end_date=${e}`;
}

function setDateFilter(period) {
    const today = new Date();
    let startDate, endDate;
    
    switch(period) {
        case 'today':
            startDate = endDate = formatDate(today);
            break;
            
        case 'week':
            // Get start of week (Sunday)
            const startOfWeek = new Date(today);
            startOfWeek.setDate(today.getDate() - today.getDay());
            startDate = formatDate(startOfWeek);
            endDate = formatDate(today);
            break;
            
        case 'month':
            // Get start of month
            const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
            startDate = formatDate(startOfMonth);
            endDate = formatDate(today);
            break;
            
        case 'year':
            // Get start of year
            const startOfYear = new Date(today.getFullYear(), 0, 1);
            startDate = formatDate(startOfYear);
            endDate = formatDate(today);
            break;
    }
    
    const q = document.getElementById('searchQTop') ? document.getElementById('searchQTop').value : '';
    window.location.href = `?start_date=${startDate}&end_date=${endDate}${q?('&q='+encodeURIComponent(q)) : ''}`;
}

function formatDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

// Enable search on Enter key
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchQTop');
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                applySearchTop();
            }
        });
    }
});

// Void Sale Functions
let currentVoidSaleId = null;

function openVoidModal(saleId, products, amount) {
    currentVoidSaleId = saleId;
    document.getElementById('voidSaleId').textContent = saleId;
    document.getElementById('voidProducts').textContent = products;
    document.getElementById('voidAmount').textContent = '₱' + parseFloat(amount).toFixed(2);
    document.getElementById('voidModal').style.display = 'flex';
    document.getElementById('staffPassword').value = '';
    document.getElementById('voidReason').value = '';
    document.getElementById('staffPassword').focus();
}

function closeVoidModal() {
    document.getElementById('voidModal').style.display = 'none';
    currentVoidSaleId = null;
    document.getElementById('staffPassword').value = '';
    document.getElementById('voidReason').value = '';
}

async function confirmVoid() {
    const password = document.getElementById('staffPassword').value;
    const reason = document.getElementById('voidReason').value;
    
    if (!password) {
        alert('Please enter your password');
        return;
    }
    
    if (!reason.trim()) {
        alert('Please provide a reason for voiding this transaction');
        return;
    }
    
    const voidBtn = document.getElementById('confirmVoidBtn');
    voidBtn.disabled = true;
    voidBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Voiding...';
    
    try {
        const response = await fetch('../api/void-sale.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                sale_id: currentVoidSaleId,
                staff_password: password,
                reason: reason
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('✓ Transaction voided successfully!\n\nInventory has been restored.');
            closeVoidModal();
            window.location.reload();
        } else {
            alert('Error: ' + result.error);
            voidBtn.disabled = false;
            voidBtn.innerHTML = '<i class="fas fa-check"></i> Confirm Void';
        }
    } catch (error) {
        alert('Error voiding transaction: ' + error.message);
        voidBtn.disabled = false;
        voidBtn.innerHTML = '<i class="fas fa-check"></i> Confirm Void';
    }
}

// Allow Enter key to submit in password field
document.addEventListener('DOMContentLoaded', function() {
    const passwordInput = document.getElementById('staffPassword');
    if (passwordInput) {
        passwordInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                confirmVoid();
            }
        });
    }
});
</script>

<!-- Void Sale Modal -->
<div id="voidModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:white; border-radius:12px; padding:30px; max-width:500px; width:90%; box-shadow:0 10px 40px rgba(0,0,0,0.3);">
        <div style="display:flex; align-items:center; margin-bottom:20px; color:#e74c3c;">
            <i class="fas fa-exclamation-triangle" style="font-size:32px; margin-right:15px;"></i>
            <h2 style="margin:0; font-size:24px;">Void Transaction</h2>
        </div>
        
        <div style="background:#fff3cd; border-left:4px solid #ffc107; padding:12px; margin-bottom:20px; border-radius:4px;">
            <strong>⚠️ Warning:</strong> This action will cancel the sale and restore inventory. This cannot be undone.
        </div>
        
        <div style="background:#f8f9fa; padding:15px; border-radius:8px; margin-bottom:20px;">
            <div style="margin-bottom:10px;">
                <strong>Sale ID:</strong> <span id="voidSaleId"></span>
            </div>
            <div style="margin-bottom:10px;">
                <strong>Products:</strong> <span id="voidProducts" style="font-size:13px;"></span>
            </div>
            <div>
                <strong>Amount:</strong> <span id="voidAmount" style="color:#e74c3c; font-weight:bold; font-size:18px;"></span>
            </div>
        </div>
        
        <div style="margin-bottom:15px;">
            <label style="display:block; margin-bottom:8px; font-weight:600; color:#2c3e50;">
                <i class="fas fa-lock"></i> Your Password <span style="color:#e74c3c;">*</span>
            </label>
            <input type="password" id="staffPassword" placeholder="Enter your password" style="width:100%; padding:12px; border:2px solid #dee2e6; border-radius:6px; font-size:14px;" required>
        </div>
        
        <div style="margin-bottom:20px;">
            <label style="display:block; margin-bottom:8px; font-weight:600; color:#2c3e50;">
                <i class="fas fa-comment-alt"></i> Reason for Voiding <span style="color:#e74c3c;">*</span>
            </label>
            <textarea id="voidReason" placeholder="E.g., Wrong order, customer request, system error..." style="width:100%; padding:12px; border:2px solid #dee2e6; border-radius:6px; font-size:14px; min-height:80px; font-family:inherit;" required></textarea>
        </div>
        
        <div style="display:flex; gap:10px; justify-content:flex-end;">
            <button onclick="closeVoidModal()" class="btn" style="background:#95a5a6; color:white; padding:12px 24px; border:none; border-radius:6px; cursor:pointer; font-size:14px; font-weight:600;">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button id="confirmVoidBtn" onclick="confirmVoid()" class="btn" style="background:#e74c3c; color:white; padding:12px 24px; border:none; border-radius:6px; cursor:pointer; font-size:14px; font-weight:600;">
                <i class="fas fa-check"></i> Confirm Void
            </button>
        </div>
    </div>
</div>

<?php include '../components/layout-end.php';
