<?php
session_start();

// Clear any existing reset session when starting a new forgot password request
if (!isset($_POST['email'])) {
    unset($_SESSION['reset_step']);
    unset($_SESSION['reset_user_id']);
    unset($_SESSION['reset_code_id']);
    unset($_SESSION['reset_code']);
}

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

if ($_POST) {
    $emailOrUsername = sanitizeInput($_POST['email_or_username'] ?? '');

    if (empty($emailOrUsername)) {
        $error = 'Please enter your email or username.';
    } else {
        $sql = "SELECT user_id, username, email FROM users WHERE (email = ? OR username = ?) AND status = 'active' LIMIT 1";
        $user = $db->fetchOne($sql, [$emailOrUsername, $emailOrUsername]);

        // Always return a generic message to avoid revealing whether the account exists
        $devCode = '';
        if ($user) {
            // Generate 6-digit verification code
            $code = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            $expiresAt = date('Y-m-d H:i:s', time() + 600); // 10 minutes expiry
            
            // Store code with email in password_resets table
            $insertSql = "INSERT INTO password_resets (user_id, email, token, expires_at, created_at) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)";
            try {
                $db->query($insertSql, [$user['user_id'], $user['email'], $code, $expiresAt]);
            } catch (Exception $e) {
                // Log and continue — the table might not exist yet in the database.
                error_log('Failed to insert password reset code: ' . $e->getMessage());
            }

            $devCode = $code;

            require_once __DIR__ . '/includes/mailer.php';
            $smtpConfig = file_exists(__DIR__ . '/includes/smtp_config.php') ? require __DIR__ . '/includes/smtp_config.php' : null;

            // Send verification code via email
            $subject = 'Password Reset Verification Code';
            $body = "<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2 style='color: #3E363F;'>Password Reset Request</h2>
                <p>Hello {$user['username']},</p>
                <p>We received a request to reset your password. Your verification code is:</p>
                <div style='background: #f5f5f5; border: 2px solid #3E363F; border-radius: 8px; padding: 20px; text-align: center; margin: 20px 0;'>
                    <h1 style='color: #3E363F; font-size: 36px; letter-spacing: 8px; margin: 0;'>{$code}</h1>
                </div>
                <p>This code will expire in <strong>10 minutes</strong>.</p>
                <p>If you didn't request this, please ignore this message.</p>
                <p style='color: #999; font-size: 12px; margin-top: 30px;'>9BARs COFFEE POS System</p>
            </div>";

            $mailSent = false;
            try {
                $mailSent = sendMail($user['email'], $user['username'], $subject, $body);
            } catch (Exception $e) {
                error_log('sendMail() exception: ' . $e->getMessage());
                $mailSent = false;
            }

            if ($mailSent) {
                $success = 'A 6-digit verification code has been sent to your email. Please check your inbox.';
            } else {
                // Keep the generic message but provide the dev code when sending failed (helpful in local dev)
                $success = "A verification code has been sent to your email.";
                $success .= " <strong>For development:</strong> Your code is <code style='background:#f5f5f5;padding:5px 10px;border-radius:4px;font-size:18px;letter-spacing:2px;'>{$devCode}</code> (expires in 10 minutes).";
                error_log('Password reset email not sent for user_id=' . $user['user_id'] . ' email=' . $user['email'] . ' code=' . $devCode);
            }
        } else {
            $success = 'If an account with that email or username exists, a reset link has been sent.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - 9BARs COFFEE POS</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Small overrides to align with login layout if needed */
        .forgot-message { max-width: 420px; margin: 0 auto; text-align: center; }
    </style>
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
                <h1 class="welcome-title">Forgot Password</h1>
                <p class="welcome-subtitle">Enter your email or username to receive a verification code</p>
            </div>

            <?php if ($error): ?>
            <div class="error-modal" style="position:relative; margin-bottom:20px;">
                <div style="color: #e74c3c; margin-bottom: 10px;"><strong>⚠️</strong></div>
                <p style="color: #2c3e50;"><?php echo htmlspecialchars($error); ?></p>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="forgot-message">
                    <div class="alert alert-success" style="margin-bottom:20px;">
                        <?php echo $success; ?>
                    </div>
                    <a href="reset-password.php" style="display:inline-block;background:#3498db;color:white;padding:12px 24px;border-radius:8px;text-decoration:none;margin:10px 0;">Enter Verification Code</a>
                    <br>
                    <a href="login.php" style="color:#95a5a6; text-decoration:none; font-size:14px; margin-top:10px; display:inline-block;">← Back to Login</a>
                </div>
            <?php else: ?>
                <form method="POST" class="login-form" style="max-width:400px; margin:0 auto;">
                    <div class="form-group">
                        <label for="email_or_username" class="form-label">Email or Username</label>
                        <input type="text" id="email_or_username" name="email_or_username" class="form-control" required>
                    </div>

                    <button type="submit" class="btn-login">Send Verification Code</button>

                    <div style="text-align:center; margin-top:16px;">
                        <a href="login.php" style="color: #95a5a6; text-decoration: none;">← Back to Login</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - 9BARs COFFEE POS</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Small overrides to align with login layout if needed */
        .forgot-message { max-width: 420px; margin: 0 auto; text-align: center; }
    </style>
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
                <h1 class="welcome-title">Forgot Password</h1>
                <p class="welcome-subtitle">Enter your email or username to receive a reset link</p>
            </div>

            <?php if ($error): ?>
            <div class="error-modal" style="position:relative; margin-bottom:20px;">
                <div style="color: #e74c3c; margin-bottom: 10px;"><strong>⚠️</strong></div>
                <p style="color: #2c3e50;"><?php echo htmlspecialchars($error); ?></p>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="forgot-message">
                    <div class="alert alert-success" style="margin-bottom:20px;">
                        <?php echo $success; ?>
                    </div>
                    <a href="login.php" style="color:#3498db; text-decoration:none;">← Back to Login</a>
                </div>
            <?php else: ?>
                <form method="POST" class="login-form" style="max-width:400px; margin:0 auto;">
                    <div class="form-group">
                        <label for="email_or_username" class="form-label">Email or Username</label>
                        <input type="text" id="email_or_username" name="email_or_username" class="form-control" required>
                    </div>

                    <button type="submit" class="btn-login">Send Reset Link</button>

                    <div style="text-align:center; margin-top:16px;">
                        <a href="login.php" style="color: #95a5a6; text-decoration: none;">← Back to Login</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>
