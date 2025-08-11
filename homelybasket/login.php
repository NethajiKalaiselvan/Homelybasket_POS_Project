<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include required files
require_once 'config/database.php';
require_once 'includes/session.php';

// Check if already logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';
$db = null;

// Initialize database connection
try {
    $db = new Database();
    
    // Check if users table exists and create default admin if needed
    try {
        $checkUser = $db->single("SELECT COUNT(*) as count FROM users WHERE username = 'admin'");
        if ($checkUser['count'] == 0) {
            // Create default admin user
            $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
            $db->query("INSERT INTO users (username, password, full_name, email, role) VALUES (?, ?, ?, ?, ?)", 
                      ['admin', $hashedPassword, 'System Administrator', 'admin@supermarket.com', 'admin']);
            $success = 'Default admin user created successfully!';
        }
    } catch (Exception $e) {
        // Tables might not exist yet
        $error = 'Database tables not found. Please run setup first.';
    }
    
} catch (Exception $e) {
    $error = 'Database connection failed: ' . $e->getMessage();
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $db && !$error) {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        try {
            $query = "SELECT * FROM users WHERE username = ? AND status = 'active'";
            $user = $db->single($query, [$username]);
            
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $query = "SELECT * FROM users WHERE username = :username AND status = 'active'";
                $user = $db->single($query, [':username' => $username]);
                
                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'Invalid username or password.';
            }
        } catch (Exception $e) {
            $error = 'Login error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Supermarket Billing System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            max-width: 450px;
            width: 100%;
        }
        .login-header {
            background: linear-gradient(135deg, #2563eb, #1e40af);
            color: white;
            border-radius: 20px 20px 0 0;
            padding: 2rem;
            text-align: center;
        }
        .form-control {
            border-radius: 10px;
            border: 2px solid #e5e7eb;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 0.2rem rgba(37, 99, 235, 0.25);
        }
        .btn-primary {
            background: linear-gradient(135deg, #2563eb, #1e40af);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(37, 99, 235, 0.4);
        }
        .alert {
            border-radius: 10px;
            border: none;
        }
        .input-group-text {
            background: #f8fafc;
            border: 2px solid #e5e7eb;
            border-radius: 10px 0 0 10px;
        }
        .form-control:not(:first-child) {
            border-radius: 0 10px 10px 0;
        }
        .setup-notice {
            background: rgba(255, 193, 7, 0.1);
            border: 1px solid #ffc107;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .debug-info {
            background: rgba(0, 123, 255, 0.1);
            border: 1px solid #007bff;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-md-6 col-lg-5">
                <div class="login-card mx-auto">
                    <div class="login-header">
                        <i class="fas fa-shopping-cart fa-3x mb-3"></i>
                        <h3 class="mb-0">SuperMarket Billing</h3>
                        <p class="mb-0 opacity-75">Point of Sale System</p>
                    </div>
                    <div class="card-body p-4">
                        <!-- Debug Information -->
                        <div class="debug-info">
                            <strong>System Status:</strong><br>
                            PHP Version: <?php echo phpversion(); ?><br>
                            Database: <?php echo $db ? 'Connected' : 'Not Connected'; ?><br>
                            Session: <?php echo session_status() === PHP_SESSION_ACTIVE ? 'Active' : 'Inactive'; ?>
                        </div>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>
                                <?php echo htmlspecialchars($success); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!$db): ?>
                            <div class="setup-notice">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Setup Required:</strong> Database connection failed. Please ensure:
                                <ul class="mb-0 mt-2">
                                    <li>XAMPP MySQL service is running</li>
                                    <li>Database credentials are correct</li>
                                    <li>Run <a href="setup_database.php" class="text-decoration-none">setup_database.php</a> to initialize</li>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="login.php">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Username</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-user"></i>
                                    </span>
                                    <input type="text" class="form-control" name="username" 
                                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : 'admin'; ?>" 
                                           required autofocus placeholder="Enter username">
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label fw-semibold">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-lock"></i>
                                    </span>
                                    <input type="password" class="form-control" name="password" 
                                           value="admin123" required placeholder="Enter password">
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-sign-in-alt me-2"></i>
                                Sign In
                            </button>
                        </form>
                        
                        <div class="mt-4 pt-3 border-top text-center">
                            <small class="text-muted">
                                <strong>Demo Credentials:</strong><br>
                                Username: admin | Password: admin123
                            </small>
                        </div>
                        
                        <div class="mt-3 text-center">
                            <small class="text-muted">
                                <a href="test_php.php" class="text-decoration-none">Test PHP</a> | 
                                <a href="setup_database.php" class="text-decoration-none">Setup Database</a>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>