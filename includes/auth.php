<?php
// Session Management and Authentication Functions
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class Auth {
    private $db;
    private $maxAttempts = 3;
    private $lockoutTime = 30; // seconds
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    public function login($username, $password) {
        // Check if IP is locked out
        if ($this->isLockedOut()) {
            return ['success' => false, 'message' => 'Too many failed attempts. Please wait ' . $this->getRemainingLockoutTime() . ' seconds.', 'locked' => true];
        }
        
        $sql = "SELECT user_id, username, password, full_name, role, status 
                FROM users 
                WHERE username = ? AND status = 'active'";
        
        $user = $this->db->fetchOne($sql, [$username]);
        
        // For development: allow plain text passwords
        $passwordValid = false;
        if ($user) {
            // Check if it's a hashed password or plain text
            if (password_verify($password, $user['password'])) {
                // Bcrypt hash
                $passwordValid = true;
            } else if (md5($password) === $user['password']) {
                // MD5 hash (used by seeder)
                $passwordValid = true;
            } else if ($user['password'] === $password) {
                // For plain text passwords (development)
                $passwordValid = true;
            } else if (($username === 'admin' && $password === 'admin123') || 
                      ($username === 'staff1' && $password === 'admin123')) {
                // Hardcoded for development
                $passwordValid = true;
            }
        }
        
        if ($user && $passwordValid) {
            // Clear failed attempts on successful login
            $this->clearFailedAttempts();
            
            // Update last login
            $this->updateLastLogin($user['user_id']);
            
            // Set session variables
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['logged_in'] = true;
            
            return ['success' => true, 'message' => 'Login successful!'];
        }
        
        // Record failed attempt
        $this->recordFailedAttempt();
        $remainingAttempts = $this->maxAttempts - $this->getFailedAttempts();
        
        if ($remainingAttempts <= 0) {
            return ['success' => false, 'message' => 'Too many failed attempts. Please wait ' . $this->lockoutTime . ' seconds.', 'locked' => true];
        }
        
        return ['success' => false, 'message' => 'Invalid username or password. ' . $remainingAttempts . ' attempts remaining.', 'locked' => false];
    }
    
    private function getClientIP() {
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
    
    private function getFailedAttempts() {
        $key = 'failed_attempts_' . $this->getClientIP();
        return $_SESSION[$key] ?? 0;
    }
    
    private function recordFailedAttempt() {
        $key = 'failed_attempts_' . $this->getClientIP();
        $timeKey = 'first_attempt_time_' . $this->getClientIP();
        
        $_SESSION[$key] = ($this->getFailedAttempts()) + 1;
        
        if (!isset($_SESSION[$timeKey])) {
            $_SESSION[$timeKey] = time();
        }
    }
    
    private function clearFailedAttempts() {
        $key = 'failed_attempts_' . $this->getClientIP();
        $timeKey = 'first_attempt_time_' . $this->getClientIP();
        
        unset($_SESSION[$key]);
        unset($_SESSION[$timeKey]);
    }
    
    private function isLockedOut() {
        $attempts = $this->getFailedAttempts();
        $timeKey = 'first_attempt_time_' . $this->getClientIP();
        
        if ($attempts >= $this->maxAttempts) {
            $firstAttemptTime = $_SESSION[$timeKey] ?? 0;
            $currentTime = time();
            
            if (($currentTime - $firstAttemptTime) < $this->lockoutTime) {
                return true;
            } else {
                // Lockout time has passed, clear attempts
                $this->clearFailedAttempts();
                return false;
            }
        }
        
        return false;
    }
    
    private function getRemainingLockoutTime() {
        $timeKey = 'first_attempt_time_' . $this->getClientIP();
        $firstAttemptTime = $_SESSION[$timeKey] ?? 0;
        $currentTime = time();
        $elapsed = $currentTime - $firstAttemptTime;
        
        return max(0, $this->lockoutTime - $elapsed);
    }
    
    public function logout() {
        session_unset();
        session_destroy();
        return true;
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            // Determine the correct path to login.php based on the current location
            $scriptPath = $_SERVER['SCRIPT_NAME'];
            if (strpos($scriptPath, '/admin/') !== false || strpos($scriptPath, '/staff/') !== false) {
                header('Location: ../../login.php');
            } else {
                header('Location: login.php');
            }
            exit();
        }
    }
    
    public function requireAdmin() {
        $this->requireLogin();
        if ($_SESSION['role'] !== 'admin') {
            header('Location: ../login.php');
            exit();
        }
    }
    
    public function getCurrentUser() {
        if ($this->isLoggedIn()) {
            return [
                'user_id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'full_name' => $_SESSION['full_name'],
                'role' => $_SESSION['role']
            ];
        }
        return null;
    }
    
    private function updateLastLogin($userId) {
        $sql = "UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE user_id = ?";
        $this->db->query($sql, [$userId]);
    }
    
    public function changePassword($userId, $oldPassword, $newPassword) {
        // Verify old password
        $sql = "SELECT password FROM users WHERE user_id = ?";
        $user = $this->db->fetchOne($sql, [$userId]);
        
        if ($user && password_verify($oldPassword, $user['password'])) {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $updateSql = "UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?";
            $this->db->query($updateSql, [$hashedPassword, $userId]);
            return true;
        }
        
        return false;
    }
}

// Utility Functions
function sanitizeInput($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

function formatCurrency($amount) {
    return '₱' . number_format($amount, 2);
}

function formatDate($date) {
    return date('M d, Y', strtotime($date));
}

function formatDateTime($datetime) {
    return date('M d, Y h:i A', strtotime($datetime));
}

function generateTransactionNumber() {
    return 'TXN-' . date('Ymd') . '-' . str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
}

function redirectTo($location) {
    header("Location: $location");
    exit();
}

function showAlert($message, $type = 'info') {
    $_SESSION['alert'] = [
        'message' => $message,
        'type' => $type
    ];
}

function displayAlert() {
    if (isset($_SESSION['alert'])) {
        $alert = $_SESSION['alert'];
        $alertClass = '';
        
        switch($alert['type']) {
            case 'success':
                $alertClass = 'alert-success';
                break;
            case 'error':
                $alertClass = 'alert-danger';
                break;
            case 'warning':
                $alertClass = 'alert-warning';
                break;
            default:
                $alertClass = 'alert-info';
        }
        
        echo '<div class="alert ' . $alertClass . ' alert-dismissible fade show" role="alert">';
        echo htmlspecialchars($alert['message']);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        echo '</div>';
        
        unset($_SESSION['alert']);
    }
}