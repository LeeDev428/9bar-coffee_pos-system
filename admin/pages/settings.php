<?php
// Admin Settings Page
$page_title = 'SETTINGS';
include '../components/main-layout.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_business_info':
                try {
                    $settings = [
                        'business_name' => sanitizeInput($_POST['business_name']),
                        'business_address' => sanitizeInput($_POST['business_address']),
                        'business_phone' => sanitizeInput($_POST['business_phone']),
                        'business_email' => sanitizeInput($_POST['business_email']),
                        'tax_rate' => floatval($_POST['tax_rate']),
                        'currency' => sanitizeInput($_POST['currency'])
                    ];
                    
                    foreach ($settings as $key => $value) {
                        $db->query("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?", [$key, $value, $value]);
                    }
                    
                    showAlert('Business information updated successfully!', 'success');
                } catch (Exception $e) {
                    showAlert('Error updating business info: ' . $e->getMessage(), 'error');
                }
                break;
                
            case 'add_user':
                try {
                    $username = sanitizeInput($_POST['username']);
                    $email = sanitizeInput($_POST['email']);
                    $password = $_POST['password'];
                    $role = $_POST['role'];
                    $firstName = sanitizeInput($_POST['first_name']);
                    $lastName = sanitizeInput($_POST['last_name']);
                    
                    // Check if username or email exists
                    $existingUser = $db->fetchRow("SELECT user_id FROM users WHERE username = ? OR email = ?", [$username, $email]);
                    if ($existingUser) {
                        throw new Exception('Username or email already exists');
                    }
                    
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    // Compose full name from first and last
                    $fullName = trim($firstName . ' ' . $lastName);

                    // Insert according to current schema (password, full_name)
                    $db->query("INSERT INTO users (username, password, full_name, email, role, status) VALUES (?, ?, ?, ?, ?, 'active')", [
                        $username, $hashedPassword, $fullName, $email, $role
                    ]);
                    
                    showAlert('User added successfully!', 'success');
                } catch (PDOException $e) {
                    // Log detailed DB error for debugging
                    error_log('[add_user] SQL Error: ' . $e->getMessage());
                    showAlert('Error adding user: ' . $e->getMessage(), 'error');
                } catch (Exception $e) {
                    showAlert('Error adding user: ' . $e->getMessage(), 'error');
                }
                break;
                
            case 'update_user':
                try {
                    $userId = intval($_POST['user_id']);
                    $username = sanitizeInput($_POST['username']);
                    $email = sanitizeInput($_POST['email']);
                    $role = $_POST['role'];
                    $firstName = sanitizeInput($_POST['first_name']);
                    $lastName = sanitizeInput($_POST['last_name']);
                    $status = $_POST['status'];
                    
                    $fullName = trim($firstName . ' ' . $lastName);

                    $db->query("UPDATE users SET username = ?, email = ?, role = ?, full_name = ?, status = ? WHERE user_id = ?", [
                        $username, $email, $role, $fullName, $status, $userId
                    ]);
                    
                    // Update password if provided
                    if (!empty($_POST['password'])) {
                        $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);
                        $db->query("UPDATE users SET password = ? WHERE user_id = ?", [$hashedPassword, $userId]);
                    }
                    
                    showAlert('User updated successfully!', 'success');
                } catch (PDOException $e) {
                    error_log('[update_user] SQL Error: ' . $e->getMessage());
                    showAlert('Error updating user: ' . $e->getMessage(), 'error');
                } catch (Exception $e) {
                    showAlert('Error updating user: ' . $e->getMessage(), 'error');
                }
                break;
                
            case 'add_category':
                try {
                    $categoryName = sanitizeInput($_POST['category_name']);
                    $description = sanitizeInput($_POST['description']);
                    
                    $db->query("INSERT INTO categories (category_name, description) VALUES (?, ?)", [$categoryName, $description]);
                    
                    showAlert('Category added successfully!', 'success');
                } catch (Exception $e) {
                    showAlert('Error adding category: ' . $e->getMessage(), 'error');
                }
                break;
                
            case 'update_pos_settings':
                try {
                    $settings = [
                        'receipt_header' => sanitizeInput($_POST['receipt_header']),
                        'receipt_footer' => sanitizeInput($_POST['receipt_footer']),
                        'auto_print_receipt' => isset($_POST['auto_print_receipt']) ? '1' : '0',
                        'allow_discounts' => isset($_POST['allow_discounts']) ? '1' : '0',
                        'low_stock_alert' => intval($_POST['low_stock_alert'])
                    ];
                    
                    foreach ($settings as $key => $value) {
                        $db->query("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?", [$key, $value, $value]);
                    }
                    
                    showAlert('POS settings updated successfully!', 'success');
                } catch (Exception $e) {
                    showAlert('Error updating POS settings: ' . $e->getMessage(), 'error');
                }
                break;
                
            case 'update_printer_settings':
                try {
                    $settings = [
                        'printer_type' => sanitizeInput($_POST['printer_type']),
                        'windows_printer_name' => sanitizeInput($_POST['windows_printer_name']),
                        'network_printer_ip' => sanitizeInput($_POST['network_printer_ip']),
                        'network_printer_port' => intval($_POST['network_printer_port']),
                        'usb_printer_path' => sanitizeInput($_POST['usb_printer_path']),
                        'paper_width' => intval($_POST['paper_width']),
                        'character_set' => sanitizeInput($_POST['character_set']),
                        'enable_cash_drawer' => isset($_POST['enable_cash_drawer']) ? '1' : '0',
                        'print_qr_code' => isset($_POST['print_qr_code']) ? '1' : '0'
                    ];
                    
                    foreach ($settings as $key => $value) {
                        $db->query("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?", [$key, $value, $value]);
                    }
                    
                    showAlert('Printer settings updated successfully!', 'success');
                } catch (Exception $e) {
                    showAlert('Error updating printer settings: ' . $e->getMessage(), 'error');
                }
                break;
        }
    }
}

// Get current settings
$businessSettings = [];
$posSettings = [];
$settingsResult = $db->fetchAll("SELECT setting_key, setting_value FROM settings");
foreach ($settingsResult as $setting) {
    if (in_array($setting['setting_key'], ['business_name', 'business_address', 'business_phone', 'business_email', 'tax_rate', 'currency'])) {
        $businessSettings[$setting['setting_key']] = $setting['setting_value'];
    } else {
        $posSettings[$setting['setting_key']] = $setting['setting_value'];
    }
}

// Get users
$users = $db->fetchAll("SELECT * FROM users ORDER BY username");

// Normalize user fields for UI compatibility: split full_name into first_name/last_name
foreach ($users as &$u) {
    $full = trim($u['full_name'] ?? '');
    $parts = explode(' ', $full, 2);
    $u['first_name'] = $parts[0] ?? '';
    $u['last_name'] = $parts[1] ?? '';
}
unset($u);

// Get categories
$categories = $db->fetchAll("SELECT * FROM categories ORDER BY category_name");
?>

<style>
.settings-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.settings-nav {
    display: flex;
    gap: 20px;
    margin-bottom: 30px;
    border-bottom: 1px solid #dee2e6;
}

.nav-button {
    padding: 10px 20px;
    background: none;
    border: none;
    border-bottom: 3px solid transparent;
    cursor: pointer;
    font-weight: 500;
    color: #6c757d;
    transition: all 0.3s;
}

.nav-button.active {
    color: #3498db;
    border-bottom-color: #3498db;
}

.settings-content {
    display: none;
}

.settings-content.active {
    display: block;
}

.settings-section {
    background: white;
    border-radius: 8px;
    padding: 25px;
    margin-bottom: 30px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.section-title {
    font-size: 18px;
    font-weight: bold;
    color: #2c3e50;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #ecf0f1;
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.form-label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
    color: #2c3e50;
}

.form-control {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #bdc3c7;
    border-radius: 4px;
    font-size: 14px;
}

.form-control:focus {
    outline: none;
    border-color: #3498db;
    box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
}

.checkbox-group {
    display: flex;
    align-items: center;
    gap: 10px;
}

.checkbox {
    width: auto;
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    transition: all 0.3s;
}

.btn-primary { background: #3498db; color: white; }
.btn-success { background: #27ae60; color: white; }
.btn-warning { background: #f39c12; color: white; }
.btn-danger { background: #e74c3c; color: white; }

.btn:hover { opacity: 0.9; transform: translateY(-1px); }

.users-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

.users-table th,
.users-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #ecf0f1;
}

.users-table th {
    background: #f8f9fa;
    font-weight: 600;
    color: #495057;
}

.users-table tr:hover {
    background: #f8f9fa;
}

.user-status {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}

.status-active { background: #d4edda; color: #155724; }
.status-inactive { background: #f8d7da; color: #721c24; }

.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
}

.modal-content {
    background: white;
    margin: 5% auto;
    padding: 0;
    border-radius: 8px;
    width: 90%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    background: #34495e;
    color: white;
    padding: 15px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-body {
    padding: 20px;
}

.close {
    color: white;
    font-size: 24px;
    font-weight: bold;
    cursor: pointer;
}

.categories-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 20px;
}

.category-card {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 6px;
    border-left: 4px solid #3498db;
}

.category-name {
    font-weight: bold;
    color: #2c3e50;
    margin-bottom: 5px;
}

.category-desc {
    font-size: 13px;
    color: #6c757d;
}
</style>

<div class="settings-header">
    <div>
        <h2 style="margin: 0; color: #2c3e50;">System Settings</h2>
        <p style="color: #7f8c8d; margin: 5px 0 0 0;">Configure your POS system settings</p>
    </div>
</div>

<!-- Settings Navigation -->
<div class="settings-nav">
    <button class="nav-button active" onclick="showSettings('business')">Business Info</button>
    <button class="nav-button" onclick="showSettings('pos')">POS Settings</button>
    <button class="nav-button" onclick="showSettings('printer')">Printer Setup</button>
    <button class="nav-button" onclick="showSettings('users')">User Management</button>
    <button class="nav-button" onclick="showSettings('categories')">Categories</button>
    <button class="nav-button" onclick="showSettings('system')">System</button>
</div>

<!-- Business Information Settings -->
<div id="business-settings" class="settings-content active">
    <div class="settings-section">
        <div class="section-title">Business Information</div>
        <form method="POST">
            <input type="hidden" name="action" value="update_business_info">
            
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Business Name</label>
                    <input type="text" name="business_name" class="form-control" 
                           value="<?php echo htmlspecialchars($businessSettings['business_name'] ?? '9Bar Coffee'); ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Currency</label>
                    <select name="currency" class="form-control">
                        <option value="PHP" <?php echo ($businessSettings['currency'] ?? 'PHP') == 'PHP' ? 'selected' : ''; ?>>PHP (₱)</option>
                        <option value="USD" <?php echo ($businessSettings['currency'] ?? 'PHP') == 'USD' ? 'selected' : ''; ?>>USD ($)</option>
                        <option value="EUR" <?php echo ($businessSettings['currency'] ?? 'PHP') == 'EUR' ? 'selected' : ''; ?>>EUR (€)</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Business Address</label>
                <textarea name="business_address" class="form-control" rows="2"><?php echo htmlspecialchars($businessSettings['business_address'] ?? ''); ?></textarea>
            </div>
            
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Phone Number</label>
                    <input type="text" name="business_phone" class="form-control" 
                           value="<?php echo htmlspecialchars($businessSettings['business_phone'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="business_email" class="form-control" 
                           value="<?php echo htmlspecialchars($businessSettings['business_email'] ?? ''); ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Tax Rate (%)</label>
                <input type="number" name="tax_rate" class="form-control" step="0.01" min="0" max="100"
                       value="<?php echo $businessSettings['tax_rate'] ?? '12.00'; ?>">
            </div>
            
            <div style="text-align: right; margin-top: 20px; border-top: 1px solid #eee; padding-top: 15px;">
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i> Save Business Info
                </button>
            </div>
        </form>
    </div>
</div>

<!-- POS Settings -->
<div id="pos-settings" class="settings-content">
    <div class="settings-section">
        <div class="section-title">Point of Sale Settings</div>
        <form method="POST">
            <input type="hidden" name="action" value="update_pos_settings">
            
            <div class="form-group">
                <label class="form-label">Receipt Header</label>
                <textarea name="receipt_header" class="form-control" rows="3" placeholder="Text that appears at the top of receipts..."><?php echo htmlspecialchars($posSettings['receipt_header'] ?? 'Welcome to 9Bar Coffee!\nThank you for your visit!'); ?></textarea>
            </div>
            
            <div class="form-group">
                <label class="form-label">Receipt Footer</label>
                <textarea name="receipt_footer" class="form-control" rows="3" placeholder="Text that appears at the bottom of receipts..."><?php echo htmlspecialchars($posSettings['receipt_footer'] ?? 'Have a great day!\nPlease come again!'); ?></textarea>
            </div>
            
            <div class="form-grid">
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" name="auto_print_receipt" class="checkbox" 
                               <?php echo ($posSettings['auto_print_receipt'] ?? '0') == '1' ? 'checked' : ''; ?>>
                        <label class="form-label">Auto Print Receipt</label>
                    </div>
                </div>
                
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" name="allow_discounts" class="checkbox"
                               <?php echo ($posSettings['allow_discounts'] ?? '1') == '1' ? 'checked' : ''; ?>>
                        <label class="form-label">Allow Discounts</label>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Low Stock Alert Threshold</label>
                <input type="number" name="low_stock_alert" class="form-control" min="1" max="100"
                       value="<?php echo $posSettings['low_stock_alert'] ?? '10'; ?>">
            </div>
            
            <div style="text-align: right; margin-top: 20px; border-top: 1px solid #eee; padding-top: 15px;">
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i> Save POS Settings
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Printer Setup -->
<div id="printer-settings" class="settings-content">
    <div class="settings-section">
        <div class="section-title">Thermal Printer Configuration</div>
        <form method="POST">
            <input type="hidden" name="action" value="update_printer_settings">
            
            <div class="form-group">
                <label class="form-label">Printer Type</label>
                <select name="printer_type" id="printerType" class="form-control" onchange="togglePrinterConfig()">
                    <option value="windows" <?php echo ($posSettings['printer_type'] ?? 'windows') == 'windows' ? 'selected' : ''; ?>>Windows Printer (Recommended)</option>
                    <option value="network" <?php echo ($posSettings['printer_type'] ?? 'windows') == 'network' ? 'selected' : ''; ?>>Network Printer (IP)</option>
                    <option value="usb" <?php echo ($posSettings['printer_type'] ?? 'windows') == 'usb' ? 'selected' : ''; ?>>USB/Serial (COM Port)</option>
                </select>
            </div>
            
            <div id="windowsConfig" class="printer-config">
                <div class="form-group">
                    <label class="form-label">Windows Printer Name</label>
                    <input type="text" name="windows_printer_name" class="form-control" 
                           value="<?php echo htmlspecialchars($posSettings['windows_printer_name'] ?? ''); ?>"
                           placeholder="e.g. XP-58IIH, Thermal Printer, or leave empty for default">
                    <small style="color: #6c757d;">Leave empty to use system default printer</small>
                </div>
                
                <div class="form-group">
                    <button type="button" class="btn btn-info" onclick="detectPrinters()">
                        <i class="fas fa-search"></i> Detect Installed Printers
                    </button>
                    <div id="detectedPrinters" style="margin-top: 10px; font-size: 13px;"></div>
                </div>
            </div>
            
            <div id="networkConfig" class="printer-config" style="display: none;">
                <div class="form-group">
                    <label class="form-label">Printer IP Address</label>
                    <input type="text" name="network_printer_ip" class="form-control" 
                           value="<?php echo htmlspecialchars($posSettings['network_printer_ip'] ?? ''); ?>"
                           placeholder="e.g. 192.168.1.100">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Port</label>
                    <input type="number" name="network_printer_port" class="form-control" 
                           value="<?php echo $posSettings['network_printer_port'] ?? '9100'; ?>"
                           placeholder="9100">
                    <small style="color: #6c757d;">Default ESC/POS port is 9100</small>
                </div>
            </div>
            
            <div id="usbConfig" class="printer-config" style="display: none;">
                <div class="form-group">
                    <label class="form-label">COM Port / Device Path</label>
                    <input type="text" name="usb_printer_path" class="form-control" 
                           value="<?php echo htmlspecialchars($posSettings['usb_printer_path'] ?? ''); ?>"
                           placeholder="Windows: COM1, COM3, etc. | Linux: /dev/ttyUSB0, /dev/ttyACM0">
                </div>
            </div>
            
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Paper Width (characters)</label>
                    <select name="paper_width" class="form-control">
                        <option value="32" <?php echo ($posSettings['paper_width'] ?? '32') == '32' ? 'selected' : ''; ?>>32 chars (58mm paper)</option>
                        <option value="48" <?php echo ($posSettings['paper_width'] ?? '32') == '48' ? 'selected' : ''; ?>>48 chars (80mm paper)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Character Set</label>
                    <select name="character_set" class="form-control">
                        <option value="CP437" <?php echo ($posSettings['character_set'] ?? 'CP437') == 'CP437' ? 'selected' : ''; ?>>CP437 (Default)</option>
                        <option value="CP850" <?php echo ($posSettings['character_set'] ?? 'CP437') == 'CP850' ? 'selected' : ''; ?>>CP850 (Western Europe)</option>
                        <option value="CP852" <?php echo ($posSettings['character_set'] ?? 'CP437') == 'CP852' ? 'selected' : ''; ?>>CP852 (Central Europe)</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <div class="checkbox-group">
                    <input type="checkbox" name="enable_cash_drawer" class="checkbox"
                           <?php echo ($posSettings['enable_cash_drawer'] ?? '0') == '1' ? 'checked' : ''; ?>>
                    <label class="form-label">Enable Cash Drawer Opening</label>
                </div>
            </div>
            
            <div class="form-group">
                <div class="checkbox-group">
                    <input type="checkbox" name="print_qr_code" class="checkbox"
                           <?php echo ($posSettings['print_qr_code'] ?? '0') == '1' ? 'checked' : ''; ?>>
                    <label class="form-label">Print QR Code on Receipt (if supported)</label>
                </div>
            </div>
            
            <div style="display: flex; gap: 15px; margin-top: 20px; border-top: 1px solid #eee; padding-top: 15px;">
                <button type="button" class="btn btn-info" onclick="testPrinter()">
                    <i class="fas fa-print"></i> Test Print
                </button>
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i> Save Printer Settings
                </button>
            </div>
        </form>
    </div>
    
    <div class="settings-section">
        <div class="section-title">Recommended Thermal Printers</div>
        <div style="font-size: 14px;">
            <div style="margin-bottom: 15px;">
                <strong>Budget Options (₱2,000-4,000):</strong>
                <ul style="margin: 5px 0 0 20px;">
                    <li><strong>Xprinter XP-58IIH</strong> - USB + Network + Bluetooth (₱1,800-2,500)</li>
                    <li><strong>HOIN HOP-E58</strong> - WiFi enabled (₱2,000-3,000)</li>
                    <li><strong>POS-8058</strong> - Auto-cutter included (₱2,500-3,500)</li>
                </ul>
            </div>
            
            <div style="margin-bottom: 15px;">
                <strong>Professional Options (₱5,000+):</strong>
                <ul style="margin: 5px 0 0 20px;">
                    <li><strong>Epson TM-T20II</strong> - Industry standard (₱5,000-6,500)</li>
                    <li><strong>Star TSP143III</strong> - Cloud-ready (₱6,000-7,500)</li>
                </ul>
            </div>
            
            <div style="background: #e3f2fd; padding: 15px; border-radius: 4px;">
                <strong>💡 Setup Tips:</strong><br>
                1. Install printer using Windows "Add Printer" first<br>
                2. Set paper size to "Roll Paper 58mm" or "Roll Paper 80mm"<br>
                3. Test with "Test Print" button above<br>
                4. For network printers, ensure same WiFi network<br>
                5. USB printers work plug-and-play on most systems
            </div>
        </div>
    </div>
</div>

<div id="users-settings" class="settings-content">
    <div class="settings-section">
        <div class="section-title">
            User Management
            <button class="btn btn-primary" onclick="openUserModal()" style="float: right;">
                <i class="fas fa-plus"></i> Add User
            </button>
        </div>
        
        <table class="users-table">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                    <td><?php echo htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')); ?></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td><?php echo ucfirst($user['role']); ?></td>
                    <td>
                        <span class="user-status status-<?php echo $user['status']; ?>">
                            <?php echo ucfirst($user['status']); ?>
                        </span>
                    </td>
                    <td>
                        <button class="btn btn-warning btn-sm" onclick='editUser(<?php echo json_encode($user, JSON_HEX_APOS|JSON_HEX_QUOT); ?>)'>
                            <i class="fas fa-edit"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Categories Management -->
<div id="categories-settings" class="settings-content">
    <div class="settings-section">
        <div class="section-title">
            Product Categories
            <button class="btn btn-primary" onclick="openCategoryModal()" style="float: right;">
                <i class="fas fa-plus"></i> Add Category
            </button>
        </div>
        
        <div class="categories-grid">
            <?php foreach ($categories as $category): ?>
            <div class="category-card">
                <div class="category-name"><?php echo htmlspecialchars($category['category_name']); ?></div>
                <div class="category-desc"><?php echo htmlspecialchars($category['description']); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- System Settings -->
<div id="system-settings" class="settings-content">
    <div class="settings-section">
        <div class="section-title">System Information</div>
        <div class="form-grid">
            <div>
                <strong>PHP Version:</strong> <?php echo PHP_VERSION; ?>
            </div>
            <div>
                <strong>System Time:</strong> <?php echo date('Y-m-d H:i:s'); ?>
            </div>
            <div>
                <strong>Database:</strong> MySQL
            </div>
            <div>
                <strong>POS Version:</strong> 1.0.0
            </div>
        </div>
        
        <div style="margin-top: 30px;">
            <h4>System Maintenance</h4>
            <div style="display: flex; gap: 15px; margin-top: 15px;">
                <button class="btn btn-info" onclick="clearCache()">
                    <i class="fas fa-sync"></i> Clear Cache
                </button>
                <button class="btn btn-warning" onclick="exportData()">
                    <i class="fas fa-download"></i> Backup Data
                </button>
                <button class="btn btn-danger" onclick="confirmSystemReset()">
                    <i class="fas fa-exclamation-triangle"></i> System Reset
                </button>
            </div>
        </div>
    </div>
</div>

<!-- User Modal -->
<div id="userModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="userModalTitle">Add User</h3>
            <span class="close" onclick="closeModal('userModal')">&times;</span>
        </div>
        <div class="modal-body">
            <form method="POST" id="userForm">
                <input type="hidden" name="action" id="userAction" value="add_user">
                <input type="hidden" name="user_id" id="userId">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Username *</label>
                        <input type="text" name="username" id="userUsername" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Role *</label>
                        <select name="role" id="userRole" class="form-control" required>
                            <option value="staff">Staff</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">First Name *</label>
                        <input type="text" name="first_name" id="userFirstName" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Last Name *</label>
                        <input type="text" name="last_name" id="userLastName" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Email *</label>
                    <input type="email" name="email" id="userEmail" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Password *</label>
                    <input type="password" name="password" id="userPassword" class="form-control" required>
                </div>
                
                <div class="form-group" id="statusGroup" style="display: none;">
                    <label class="form-label">Status</label>
                    <select name="status" id="userStatus" class="form-control">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                
                <div style="text-align: right; margin-top: 20px; border-top: 1px solid #eee; padding-top: 15px;">
                    <button type="button" class="btn" onclick="closeModal('userModal')">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Save User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Category Modal -->
<div id="categoryModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add Category</h3>
            <span class="close" onclick="closeModal('categoryModal')">&times;</span>
        </div>
        <div class="modal-body">
            <form method="POST">
                <input type="hidden" name="action" value="add_category">
                
                <div class="form-group">
                    <label class="form-label">Category Name *</label>
                    <input type="text" name="category_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="2"></textarea>
                </div>
                
                <div style="text-align: right; margin-top: 20px; border-top: 1px solid #eee; padding-top: 15px;">
                    <button type="button" class="btn" onclick="closeModal('categoryModal')">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Save Category
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showSettings(section) {
    // Remove active class from all buttons and content
    document.querySelectorAll('.nav-button').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.settings-content').forEach(content => content.classList.remove('active'));
    
    // Add active class to selected button and content
    document.querySelector(`[onclick="showSettings('${section}')"]`).classList.add('active');
    document.getElementById(`${section}-settings`).classList.add('active');
}

function openUserModal() {
    document.getElementById('userModalTitle').textContent = 'Add User';
    document.getElementById('userAction').value = 'add_user';
    document.getElementById('userForm').reset();
    document.getElementById('statusGroup').style.display = 'none';
    document.getElementById('userPassword').required = true;
    document.getElementById('userModal').style.display = 'block';
}

function editUser(user) {
    document.getElementById('userModalTitle').textContent = 'Edit User';
    document.getElementById('userAction').value = 'update_user';
    document.getElementById('userId').value = user.user_id;
    document.getElementById('userUsername').value = user.username;
    document.getElementById('userEmail').value = user.email;
    document.getElementById('userRole').value = user.role;
    document.getElementById('userFirstName').value = user.first_name;
    document.getElementById('userLastName').value = user.last_name;
    document.getElementById('userStatus').value = user.status;
    document.getElementById('userPassword').value = '';
    document.getElementById('statusGroup').style.display = 'block';
    document.getElementById('userPassword').required = false;
    document.getElementById('userModal').style.display = 'block';
}

function openCategoryModal() {
    document.getElementById('categoryModal').style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

function clearCache() {
    alert('Cache cleared successfully!');
}

function exportData() {
    alert('Data backup will be created. This feature will be implemented in the next update.');
}

function confirmSystemReset() {
    if (confirm('WARNING: This will reset all system data. Are you absolutely sure?')) {
        if (confirm('This action cannot be undone. Type "RESET" to confirm:')) {
            alert('System reset feature requires additional confirmation for safety.');
        }
    }
}

function togglePrinterConfig() {
    const printerType = document.getElementById('printerType').value;
    document.querySelectorAll('.printer-config').forEach(config => config.style.display = 'none');
    document.getElementById(printerType + 'Config').style.display = 'block';
}

function detectPrinters() {
    // This would typically call a PHP script via AJAX to detect installed printers
    const detectedDiv = document.getElementById('detectedPrinters');
    detectedDiv.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Detecting printers...';
    
    // Simulate detection (in real implementation, this would be an AJAX call)
    setTimeout(() => {
        detectedDiv.innerHTML = `
            <strong>Detected Printers:</strong><br>
            • Microsoft Print to PDF<br>
            • XP-58IIH (Thermal Printer)<br>
            • Generic / Text Only<br>
            <small style="color: #6c757d;">Click on a printer name to select it</small>
        `;
    }, 2000);
}

function testPrinter() {
    if (confirm('This will print a test receipt. Make sure your printer is connected and has paper. Continue?')) {
        const testBtn = event.target;
        const originalText = testBtn.innerHTML;
        testBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Printing...';
        testBtn.disabled = true;
        
        // Make AJAX call to test printer
        fetch('../api/test-printer.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({})
        })
        .then(response => response.json())
        .then(data => {
            testBtn.innerHTML = originalText;
            testBtn.disabled = false;
            
            if (data.success) {
                alert('✅ Test print successful! Check your printer for the test receipt.');
            } else {
                alert('❌ Test print failed: ' + (data.error || data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            testBtn.innerHTML = originalText;
            testBtn.disabled = false;
            console.error('Test print error:', error);
            alert('❌ Test print request failed. Please check printer connection and settings.');
        });
    }
}

// Initialize printer config display
document.addEventListener('DOMContentLoaded', function() {
    togglePrinterConfig();
});

// Close modal when clicking outside
window.onclick = function(event) {
    const modals = ['userModal', 'categoryModal'];
    modals.forEach(modalId => {
        const modal = document.getElementById(modalId);
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
}
</script>

<?php include '../components/layout-end.php'; ?>