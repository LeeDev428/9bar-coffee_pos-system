<?php
require_once 'includes/database.php';
require_once 'includes/auth.php';

// Initialize database and auth
try {
    $db = new Database();
    $auth = new Auth($db);
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Check if user is already logged in
if ($auth->isLoggedIn()) {
    redirectTo('dashboard.php');
}

$error = '';
$countdown = 0;
$isLocked = false;

// Handle login form submission
if ($_POST) {
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $loginResult = $auth->login($username, $password);
        
        if ($loginResult['success']) {
            showAlert($loginResult['message'], 'success');
            redirectTo('dashboard.php');
        } else {
            $error = $loginResult['message'];
            $isLocked = $loginResult['locked'] ?? false;
            if ($isLocked) {
                $countdown = 30; // Show countdown for locked accounts
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - 9BARS COFFEE POS</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="login-container">
        <!-- Left Side - Logo and Branding -->
        <div class="login-left">
            <div class="login-logo">
                <img src="assets/img/9barscoffee_logo.png" alt="9BARS COFFEE Logo">
            </div>
            
            <h2 style="color: white; font-size: 2.5rem; margin-bottom: 10px;">
                9BARS<br>COFFEE
            </h2>
            
            <p class="login-tagline">
                Find the best drink to accompany your days
            </p>
        </div>
        
        <!-- Right Side - Login Form -->
        <div class="login-right">
            <div class="welcome-section">
                <h1 class="welcome-title">Welcome,</h1>
                <p class="welcome-subtitle">Please login to your account</p>
            </div>
            
            <!-- Error Modal Simulation -->
            <?php if ($error): ?>
            <div class="error-modal" style="
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: white;
                padding: 20px;
                border-radius: 8px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                text-align: center;
                z-index: 1000;
                min-width: 300px;
            ">
                <div style="color: #e74c3c; margin-bottom: 15px;">
                    <strong>⚠️</strong>
                </div>
                <p style="margin-bottom: 15px; color: #2c3e50;">
                    <?php echo htmlspecialchars($error); ?>
                </p>
                <?php if ($countdown > 0): ?>
                <p style="font-size: 0.9rem; color: #95a5a6; margin-bottom: 15px;">
                    <span id="countdown"><?php echo $countdown; ?></span> seconds remaining
                </p>
                <?php endif; ?>
                <button onclick="closeErrorModal()" style="
                    background: #3498db;
                    color: white;
                    border: none;
                    padding: 10px 20px;
                    border-radius: 5px;
                    cursor: pointer;
                ">OK</button>
            </div>
            <div class="overlay" style="
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                z-index: 999;
            "></div>
            <?php endif; ?>
            
            <form method="POST" class="login-form">
                <div class="form-group">
                    <label for="username" class="form-label">Username</label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        class="form-control" 
                        value="<?php echo htmlspecialchars($username ?? ''); ?>"
                        required
                        <?php echo ($isLocked) ? 'disabled' : ''; ?>
                    >
                </div>
                
                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="form-control" 
                        required
                        <?php echo ($isLocked) ? 'disabled' : ''; ?>
                    >
                </div>
                
                <button 
                    type="submit" 
                    class="btn-login"
                    <?php echo ($isLocked) ? 'disabled' : ''; ?>
                >
                    Sign In
                </button>
                
                <div style="text-align: center; margin-top: 20px;">
                    <p style="color: #95a5a6; font-size: 0.9rem;">
                        Default Login:<br>
                        <strong>Username:</strong> admin | <strong>Password:</strong> admin123<br>
                        <strong>Username:</strong> staff1 | <strong>Password:</strong> staff123
                    </p>
                </div>
            </form>
            
            <div style="text-align: center; margin-top: 30px;">
                <a href="index.php" style="color: #95a5a6; text-decoration: none; font-size: 0.9rem;">
                    ← Back to Home
                </a>
            </div>
        </div>
    </div>
    
    <script>
        function closeErrorModal() {
            document.querySelector('.error-modal').style.display = 'none';
            document.querySelector('.overlay').style.display = 'none';
            
            // Re-enable form fields
            document.getElementById('username').disabled = false;
            document.getElementById('password').disabled = false;
            document.querySelector('.btn-login').disabled = false;
        }
        
        // Countdown timer
        <?php if ($error && $isLocked && $countdown > 0): ?>
        let timeLeft = <?php echo $countdown; ?>;
        const countdownElement = document.getElementById('countdown');
        
        const timer = setInterval(() => {
            timeLeft--;
            if (countdownElement) {
                countdownElement.textContent = timeLeft;
            }
            
            if (timeLeft <= 0) {
                clearInterval(timer);
                // Auto-refresh the page to allow login again
                window.location.reload();
            }
        }, 1000);
        <?php endif; ?>
        
        // Focus first input on load
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (!$error): ?>
            document.getElementById('username').focus();
            <?php endif; ?>
        });
        
        // Add enter key handling
        document.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && !document.querySelector('.error-modal')) {
                document.querySelector('form').submit();
            }
        });
    </script>
</body>
</html>