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
$token = $_GET['token'] ?? ($_POST['token'] ?? '');

if (empty($token)) {
    $error = 'Invalid or missing token.';
} else {
    // Fetch the token
    $sql = "SELECT pr.id, pr.user_id, pr.token, pr.expires_at, u.username FROM password_resets pr JOIN users u ON pr.user_id = u.user_id WHERE pr.token = ? LIMIT 1";
    $row = $db->fetchOne($sql, [$token]);

    if (!$row) {
        $error = 'Invalid or expired token.';
    } else {
        // Check expiry
        if (strtotime($row['expires_at']) < time()) {
            $error = 'This reset token has expired.';
        }
    }
}

if ($_POST && empty($error)) {
    $newPassword = $_POST['password'] ?? '';
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
        $db->query($updateSql, [$hashed, $row['user_id']]);

        // Delete used token(s)
        $deleteSql = "DELETE FROM password_resets WHERE user_id = ?";
        $db->query($deleteSql, [$row['user_id']]);

        $success = 'Your password has been updated. You can now <a href="login.php">login</a>.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - 9BARS COFFEE POS</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
                <h1 class="welcome-title">Reset Password</h1>
                <p class="welcome-subtitle">Create a new password for your account</p>
            </div>

            <?php if ($error): ?>
            <div class="error-modal" style="position:relative; margin-bottom:20px;">
                <div style="color: #e74c3c; margin-bottom: 10px;"><strong>⚠️</strong></div>
                <p style="color: #2c3e50;"><?php echo htmlspecialchars($error); ?></p>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div style="max-width:420px; margin:0 auto; text-align:center;">
                    <div class="alert alert-success" style="margin-bottom:20px;">
                        <?php echo $success; ?>
                    </div>
                    <a href="login.php" style="color:#3498db; text-decoration:none;">← Back to Login</a>
                </div>
            <?php elseif (isset($row) && $row): ?>
                <form method="POST" class="login-form" style="max-width:400px; margin:0 auto;">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    <div class="form-group">
                        <label for="password" class="form-label">New Password</label>
                        <input type="password" id="password" name="password" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                    </div>

                    <button type="submit" class="btn-login">Set New Password</button>

                    <div style="text-align:center; margin-top:16px;">
                        <a href="login.php" style="color: #95a5a6; text-decoration: none;">← Back to Login</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>
