<?php
/**
 * Get Void Transaction Details API
 * Returns detailed information about a specific void transaction
 */
require_once '../../includes/database.php';
require_once '../../includes/auth.php';

header('Content-Type: application/json');

// Initialize database and auth
try {
    $db = new Database();
    $auth = new Auth($db);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// Check if user is logged in and is admin
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$currentUser = $auth->getCurrentUser();
if ($currentUser['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden - Admin access required']);
    exit;
}

$voidId = intval($_GET['void_id'] ?? 0);

if (!$voidId) {
    echo json_encode(['success' => false, 'error' => 'Void ID is required']);
    exit;
}

try {
    // Get void transaction details with sale information
    $voidDetails = $db->fetchOne("
        SELECT 
            vh.*,
            s.transaction_number,
            s.sale_date as original_sale_date
        FROM void_history vh
        LEFT JOIN sales s ON vh.sale_id = s.sale_id
        WHERE vh.void_id = ?
    ", [$voidId]);
    
    if (!$voidDetails) {
        echo json_encode(['success' => false, 'error' => 'Void transaction not found']);
        exit;
    }
    
    // Format dates
    $voidDetails['void_date'] = date('Y-m-d H:i:s', strtotime($voidDetails['void_date']));
    $voidDetails['original_sale_date'] = $voidDetails['original_sale_date'] 
        ? date('Y-m-d H:i:s', strtotime($voidDetails['original_sale_date'])) 
        : 'N/A';
    
    echo json_encode([
        'success' => true,
        'data' => $voidDetails
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error fetching void details: ' . $e->getMessage()
    ]);
}
?>
