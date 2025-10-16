<?php
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
        // Try to find user by email or username
        $sql = "SELECT user_id, username, email FROM users WHERE (email = ? OR username = ?) AND status = 'active' LIMIT 1";
        $user = $db->fetchOne($sql, [$emailOrUsername, $emailOrUsername]);

        // We intentionally do not reveal whether the user exists. We'll still
        // generate a token and attempt to send an email only if a user was found.
        $devResetLink = '';
        if ($user) {
            // Generate token and insert into password_resets
            $token = bin2hex(random_bytes(16));
            $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour

            $insertSql = "INSERT INTO password_resets (user_id, token, expires_at, created_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)";
            $db->query($insertSql, [$user['user_id'], $token, $expiresAt]);

            // Build reset link
            $resetLink = sprintf('%s/reset-password.php?token=%s', rtrim((isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')) . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']), '/') , $token);
            $devResetLink = $resetLink;

            // Try to send email via mailer helper
            require_once __DIR__ . '/includes/mailer.php';
            $smtpConfig = file_exists(__DIR__ . '/includes/smtp_config.php') ? require __DIR__ . '/includes/smtp_config.php' : null;

            $mailSent = false;
            if (!empty($smtpConfig) && $smtpConfig['enabled']) {
                $subject = 'Password Reset Request';
                $body = "<p>Hello {$user['username']},</p>\n<p>We received a request to reset your password. Click the link below to reset it (expires in 1 hour):</p>\n<p><a href=\"{$resetLink}\">Reset Password</a></p>\n<p>If you didn't request this, ignore this message.</p>";
                $mailSent = sendMail($user['email'], $user['username'], $subject, $body);
            }

            // If mail was sent successfully, do not expose the link in the UI.
            if ($mailSent) {
                $success = 'If an account with that email or username exists, a reset link has been sent.';
            } else {
                // For development or if sending failed, show the link so it's usable
                $success = "If an account with that email or username exists, a reset link has been sent.";
                $success .= " For development or if email sending is not configured, use this link: <a href=\"{$devResetLink}\">Reset Password</a> (expires in 1 hour).";
            }
        } else {
            // Generic message to avoid account enumeration
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
    <title>Forgot Password - 9BARS COFFEE POS</title>
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
                <img src="assets/img/9bar-pos-logo2.png" alt="9BARS COFFEE Logo">
            </div>
            
            <h2 style="color: white; font-size: 2.5rem; margin-bottom: 10px;">
                9BARS<br>COFFEE
            </h2>
            
            <p class="login-tagline">
                Find the best drink to accompany your days
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
<?php
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

        // We intentionally do not reveal whether the user exists. We'll still
        // generate a token and attempt to send an email only if a user was found.
        $devResetLink = '';
        if ($user) {
            // Generate token and insert into password_resets
            $token = bin2hex(random_bytes(16));
            $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour

            $insertSql = "INSERT INTO password_resets (user_id, token, expires_at, created_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)";
            $db->query($insertSql, [$user['user_id'], $token, $expiresAt]);

            // Build reset link
            $resetLink = sprintf('%s/reset-password.php?token=%s', rtrim((isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')) . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']), '/') , $token);
            $devResetLink = $resetLink;

            // Try to send email via mailer helper
            require_once __DIR__ . '/includes/mailer.php';
            $smtpConfig = file_exists(__DIR__ . '/includes/smtp_config.php') ? require __DIR__ . '/includes/smtp_config.php' : null;

            $mailSent = false;
            if (!empty($smtpConfig) && $smtpConfig['enabled']) {
                $subject = 'Password Reset Request';
                $body = "<p>Hello {$user['username']},</p>\n<p>We received a request to reset your password. Click the link below to reset it (expires in 1 hour):</p>\n<p><a href=\"{$resetLink}\">Reset Password</a></p>\n<p>If you didn't request this, ignore this message.</p>";
                $mailSent = sendMail($user['email'], $user['username'], $subject, $body);
            }

            // If mail was sent successfully, do not expose the link in the UI.
            if ($mailSent) {
                $success = 'If an account with that email or username exists, a reset link has been sent.';
            } else {
                // For development or if sending failed, show the link so it's usable
                $success = "If an account with that email or username exists, a reset link has been sent. \n";
                $success .= "For development or if email sending is not configured, use this link: <a href=\"{$devResetLink}\">Reset Password</a> (expires in 1 hour).";
            }
        } else {
            // Generic message to avoid account enumeration
            $success = 'If an account with that email or username exists, a reset link has been sent.';
        }
            $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hour

            $insertSql = "INSERT INTO password_resets (user_id, token, expires_at, created_at) VALUES (?, ?, ?, CURRENT_TIMESTAMP)";
            $db->query($insertSql, [$user['user_id'], $token, $expiresAt]);

            // In production you'd email a link. For development show the link on screen.
            $resetLink = sprintf('%s/reset-password.php?token=%s', rtrim((isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')) . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']), '/') , $token);

            $success = "A password reset link has been generated. For development, use this link: <a href=\"{$resetLink}\">Reset Password</a> (expires in 1 hour).";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - 9BARS COFFEE POS</title>
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
                <img src="assets/img/9bar-pos-logo2.png" alt="9BARS COFFEE Logo">
            </div>
            
            <h2 style="color: white; font-size: 2.5rem; margin-bottom: 10px;">
                9BARS<br>COFFEE
            </h2>
            
            <p class="login-tagline">
                Find the best drink to accompany your days
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
