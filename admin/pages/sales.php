<?php
// Admin Sales History Page
$page_title = 'SALES HISTORY';
include '../components/main-layout.php';

// Date filters
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$search = $_GET['q'] ?? '';

// Build query with optional search
$params = [$startDate, $endDate];
$searchSql = '';
if ($search) {
    $searchSql = "AND (p.product_name LIKE ? OR s.sale_id LIKE ? OR u.username LIKE ? )";
    $searchTerm = '%' . $search . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

// Transactions query
$transactions = $db->fetchAll(
    "SELECT s.sale_id, s.sale_date, s.total_amount, s.payment_method, u.username,
            (SELECT COUNT(*) FROM sale_items si WHERE si.sale_id = s.sale_id) as item_count
     FROM sales s
     JOIN users u ON s.user_id = u.user_id
     LEFT JOIN sale_items si ON s.sale_id = si.sale_id
     LEFT JOIN products p ON si.product_id = p.product_id
     WHERE DATE(s.sale_date) BETWEEN ? AND ? " . $searchSql . "
     GROUP BY s.sale_id
     ORDER BY s.sale_date DESC",
    $params
);

// Today's sales and monthly sales
$today = date('Y-m-d');
$monthStart = date('Y-m-01');
$todaySales = $db->fetchOne("SELECT IFNULL(SUM(total_amount),0) as total FROM sales WHERE DATE(sale_date)=?", [$today]);
$monthlySales = $db->fetchOne("SELECT IFNULL(SUM(total_amount),0) as total FROM sales WHERE DATE(sale_date) BETWEEN ? AND ?", [$monthStart, $today]);

// Export to CSV handling (simple)
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="sales_export_' . $startDate . '_to_' . $endDate . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Sale ID', 'Date', 'Staff', 'Items', 'Payment', 'Amount']);
    foreach ($transactions as $t) {
        fputcsv($out, [$t['sale_id'], $t['sale_date'], $t['username'], $t['item_count'], $t['payment_method'], $t['total_amount']]);
    }
    fclose($out);
    exit();
}
?>

<style>
.records-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
.filter-bar { display:flex; gap:10px; align-items:center; margin-bottom:15px; }
.info-cards { display:flex; gap:12px; margin-bottom:15px; }
.info-card { background:white; padding:12px 16px; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,0.08); }
.info-title { font-size:12px; color:#7f8c8d; }
.info-value { font-size:18px; font-weight:700; }
.table-simple { width:100%; border-collapse:collapse; }
.table-simple th, .table-simple td { padding:8px; border-bottom:1px solid #eee; }
</style>

<div class="records-header" style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
    <!-- Left: filters -->
    <div style="display:flex; gap:8px; align-items:center;">
        <label style="color:#fff; display:none;">From</label>
        <input type="date" id="startDate" value="<?php echo $startDate; ?>" class="form-control" style="width:150px;">
        <span style="color:#fff; margin:0 6px;">TO</span>
        <input type="date" id="endDate" value="<?php echo $endDate; ?>" class="form-control" style="width:150px;">
        <button class="btn btn-primary" onclick="applyFilter()">FILTER</button>
    </div>

    <!-- Center: Export -->
    <div style="display:flex; justify-content:center; align-items:center;">
        <a href="?export=csv&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>" class="btn btn-success" style="background:#1abc9c; border-color:#16a085;">Export to Excel</a>
    </div>

    <!-- Right: info cards -->
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
</div>

<table class="table-simple" style="width:100%; border-collapse:collapse; background:#fff;">
    <thead style="background:#2c3e50; color:#fff;">
        <tr>
            <th style="padding:10px; text-align:left;">#</th>
            <th style="padding:10px; text-align:left;">Date</th>
            <th style="padding:10px; text-align:left;">Staff</th>
            <th style="padding:10px; text-align:left;">Items</th>
            <th style="padding:10px; text-align:left;">Payment</th>
            <th style="padding:10px; text-align:left;">Amount</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($transactions as $i => $t): ?>
        <tr style="background: <?php echo ($i % 2 === 0) ? '#ffffff' : '#f6f8f9'; ?>;">
            <td style="padding:10px;"><?php echo $t['sale_id']; ?></td>
            <td style="padding:10px;"><?php echo date('Y-m-d H:i', strtotime($t['sale_date'])); ?></td>
            <td style="padding:10px;"><?php echo htmlspecialchars($t['username']); ?></td>
            <td style="padding:10px;"><?php echo $t['item_count']; ?></td>
            <td style="padding:10px;"><?php echo ucfirst($t['payment_method']); ?></td>
            <td style="padding:10px;">₱<?php echo number_format($t['total_amount'],2); ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<script>
function applyFilter() {
    const s = document.getElementById('startDate').value;
    const e = document.getElementById('endDate').value;
    const q = document.getElementById('searchQ') ? document.getElementById('searchQ').value : '';
    window.location.href = `?start_date=${s}&end_date=${e}${q?('&q='+encodeURIComponent(q)) : ''}`;
}
function applySearch() {
    const q = document.getElementById('searchQ').value;
    const s = document.getElementById('startDate').value;
    const e = document.getElementById('endDate').value;
    window.location.href = `?q=${encodeURIComponent(q)}&start_date=${s}&end_date=${e}`;
}
</script>

<?php include '../components/layout-end.php';
