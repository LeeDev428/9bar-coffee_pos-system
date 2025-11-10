<?php
session_start();
require_once 'includes/database.php';
require_once 'includes/auth.php';

try {
    $db = new Database();
    $auth = new Auth($db);
} catch (Exception $e) {
    die('Database connection failed: ' . $e->getMessage());
}

$error = '';
$success = '';

// If coming from another page (login/forgot-password), clear any existing reset session
if (isset($_GET['clear_session'])) {
    unset($_SESSION['reset_step']);
    unset($_SESSION['reset_user_id']);
    unset($_SESSION['reset_code_id']);
    unset($_SESSION['reset_code']);
}

$step = $_SESSION['reset_step'] ?? 'code'; // 'code' or 'password'
$verifiedUserId = $_SESSION['reset_user_id'] ?? null;

// Handle code verification (Step 1)
if ($_POST && isset($_POST['verification_code'])) {
    $code = trim($_POST['verification_code']);
    
    if (empty($code)) {
        $error = 'Please enter the verification code.';
    } elseif (!preg_match('/^\d{6}$/', $code)) {
        $error = 'Verification code must be 6 digits.';
    } else {
        // Check code in database
        $sql = "SELECT pr.id, pr.user_id, pr.token, pr.expires_at, u.username FROM password_resets pr JOIN users u ON pr.user_id = u.user_id WHERE pr.token = ? LIMIT 1";
        try {
            $row = $db->fetchOne($sql, [$code]);
        } catch (PDOException $e) {
            // Handle missing table
            if ($e->getCode() === '42S02' || strpos($e->getMessage(), 'password_resets') !== false) {
                $createSql = "CREATE TABLE IF NOT EXISTS `password_resets` (
                  `id` int NOT NULL AUTO_INCREMENT,
                  `user_id` int DEFAULT NULL,
                  `email` varchar(255) NOT NULL,
                  `token` varchar(255) NOT NULL,
                  `expires_at` datetime NOT NULL,
                  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                  PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                try {
                    $db->query($createSql);
                    $row = $db->fetchOne($sql, [$code]);
                } catch (Exception $e2) {
                    error_log('Failed to create password_resets table: ' . $e2->getMessage());
                    $row = false;
                }
            } else {
                throw $e;
            }
        }
        
        if (!$row) {
            $error = 'Invalid verification code.';
        } elseif (strtotime($row['expires_at']) < time()) {
            $error = 'This verification code has expired. Please request a new one.';
        } else {
            // Code is valid - move to password reset step and mark code as used
            $_SESSION['reset_step'] = 'password';
            $_SESSION['reset_user_id'] = $row['user_id'];
            $_SESSION['reset_code_id'] = $row['id'];
            $_SESSION['reset_code'] = $code; // Store the code that was verified
            $step = 'password';
            $verifiedUserId = $row['user_id'];
            
            // Immediately delete this code so it can't be reused
            $deleteCodeSql = "DELETE FROM password_resets WHERE id = ?";
            $db->query($deleteCodeSql, [$row['id']]);
        }
    }
}

// Handle password reset (Step 2)
if ($_POST && isset($_POST['new_password']) && $step === 'password' && $verifiedUserId) {
    $newPassword = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (empty($newPassword) || empty($confirm)) {
        $error = 'Please fill out both password fields.';
    } elseif ($newPassword !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (strlen($newPassword) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        // Update the user's password (hash)
        $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
        $updateSql = "UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?";
        $db->query($updateSql, [$hashed, $verifiedUserId]);

        // Delete any remaining codes for this user (cleanup)
        $deleteSql = "DELETE FROM password_resets WHERE user_id = ?";
        $db->query($deleteSql, [$verifiedUserId]);

        // Clear session
        unset($_SESSION['reset_step']);
        unset($_SESSION['reset_user_id']);
        unset($_SESSION['reset_code_id']);
        unset($_SESSION['reset_code']);
        
        $success = 'Your password has been updated successfully! You can now <a href="login.php" style="color:#3498db;">login</a>.';
        $step = 'success';
    }
}

// Security: If user navigates away and comes back, invalidate the session
// If they're on the password step but lost their session or refreshed, require new code
if ($step === 'password' && !isset($_SESSION['reset_user_id'])) {
    // Session was lost or expired - reset to code entry
    $_SESSION['reset_step'] = 'code';
    unset($_SESSION['reset_user_id']);
    unset($_SESSION['reset_code_id']);
    unset($_SESSION['reset_code']);
    $step = 'code';
    $error = 'Your session expired. Please enter a new verification code.';
}

// Additional security: Check if the code that was verified still exists in the session
// If they're on password step but no verified code in session, force restart
if ($step === 'password' && !isset($_SESSION['reset_code'])) {
    unset($_SESSION['reset_step']);
    unset($_SESSION['reset_user_id']);
    unset($_SESSION['reset_code_id']);
    $step = 'code';
    $error = 'Session invalid. Please request a new verification code.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - 9BARs COFFEE POS</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="login-container">
        <div class="login-left">
            <div class="login-logo">
                <img src="assets/img/9bar-pos-logo2.png" alt="9BARs COFFEE Logo">
            </div>
            
            <h2 style="color: white; font-size: 2.5rem; margin-bottom: 10px;">
                9BARs<br>COFFEE
            </h2>
            
            <p class="login-tagline">
                Drink Good Coffee.
            </p>
        </div>

        <div class="login-right">
            <div class="welcome-section">
                <h1 class="welcome-title">Reset Password</h1>
                <p class="welcome-subtitle">
                    <?php if ($step === 'code'): ?>
                        Enter the 6-digit code sent to your email
                    <?php elseif ($step === 'password'): ?>
                        Create your new password
                    <?php else: ?>
                        Password reset complete
                    <?php endif; ?>
                </p>
            </div>

            <?php if ($error): ?>
            <div class="error-modal" style="position:relative; margin-bottom:20px;">
                <div style="color: #e74c3c; margin-bottom: 10px;"><strong>⚠️</strong></div>
                <p style="color: #2c3e50;"><?php echo htmlspecialchars($error); ?></p>
            </div>
            <?php endif; ?>

            <?php if ($step === 'success'): ?>
                <div style="max-width:420px; margin:0 auto; text-align:center;">
                    <div style="background:#d4edda; border:2px solid #28a745; color:#155724; padding:20px; border-radius:8px; margin-bottom:20px;">
                        <i class="fas fa-check-circle" style="font-size:48px; margin-bottom:10px;"></i>
                        <p style="margin:0;"><?php echo $success; ?></p>
                    </div>
                </div>
            <?php elseif ($step === 'code'): ?>
                <form method="POST" class="login-form" style="max-width:400px; margin:0 auto;">
                    <div class="form-group">
                        <label for="verification_code" class="form-label">Verification Code</label>
                        <input type="text" id="verification_code" name="verification_code" class="form-control" 
                               placeholder="000000" maxlength="6" pattern="\d{6}" 
                               style="font-size:24px; text-align:center; letter-spacing:8px; font-weight:600;"
                               required autocomplete="off">
                        <small style="color:#666; font-size:12px; display:block; margin-top:8px;">
                            Check your email for the 6-digit code
                        </small>
                    </div>

                    <button type="submit" class="btn-login">Verify Code</button>

                    <div style="text-align:center; margin-top:16px;">
                        <a href="forgot-password.php" style="color: #3498db; text-decoration: none; font-size:14px;">← Request New Code</a>
                        <span style="color:#ccc; margin:0 8px;">|</span>
                        <a href="login.php" style="color: #95a5a6; text-decoration: none; font-size:14px;">Back to Login</a>
                    </div>
                </form>
            <?php elseif ($step === 'password'): ?>
                <form method="POST" class="login-form" style="max-width:400px; margin:0 auto;">
                    <div style="background:#d4edda; border:1px solid #c3e6cb; color:#155724; padding:12px; border-radius:6px; margin-bottom:20px; text-align:center; font-size:14px;">
                        ✓ Code verified successfully
                    </div>
                    
                    <div style="background:#fff3cd; border:1px solid #ffeaa7; color:#856404; padding:10px; border-radius:6px; margin-bottom:20px; font-size:12px;">
                        ⚠️ <strong>Note:</strong> Your verification code has been used. If you leave this page, you'll need to request a new code.
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="password" id="new_password" name="new_password" class="form-control" 
                               minlength="6" required autocomplete="new-password">
                        <small style="color:#666; font-size:12px; display:block; margin-top:5px;">
                            At least 6 characters
                        </small>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" 
                               minlength="6" required autocomplete="new-password">
                    </div>

                    <button type="submit" class="btn-login">Reset Password</button>

                    <div style="text-align:center; margin-top:16px;">
                        <a href="login.php?clear_reset=1" onclick="return confirmCancel();" style="color: #95a5a6; text-decoration: none; font-size:14px;">Cancel & Back to Login</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function confirmCancel() {
            return confirm('Are you sure you want to cancel? You will need to request a new verification code.');
        }
    </script>
</body>
</html>
