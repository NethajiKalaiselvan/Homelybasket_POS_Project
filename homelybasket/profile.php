<?php
require_once 'includes/session.php';
require_once 'includes/functions.php';
require_once 'config/database.php';

// Initialize database connection
$db = new Database();
$conn = $db->getConnection();

function validateUsername($username, $currentUserId, $db) {
    $sql = "SELECT id FROM users WHERE username = :username AND id != :id";
    $result = $db->single($sql, [':username' => $username, ':id' => $currentUserId]);
    return $result === false;
}

function validateEmail($email, $currentUserId, $db) {
    if (empty($email)) return true;
    $sql = "SELECT id FROM users WHERE email = :email AND id != :id";
    $result = $db->single($sql, [':email' => $email, ':id' => $currentUserId]);
    return $result === false;
}

// Check if user is logged in
if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Only admins can create/edit/delete users
$isAdmin = isAdmin();

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'update_profile') {
            $userId = $_SESSION['user_id'];
            $username = trim($_POST['username']);
            $full_name = trim($_POST['full_name']);
            $email = trim($_POST['email']);
            $password = trim($_POST['password']);

            // Validate input
            if (empty($username) || empty($full_name)) {
                $error_message = "Username and Full Name are required!";
            } elseif (!validateUsername($username, $userId, $db)) {
                $error_message = "Username already exists!";
            } elseif (!validateEmail($email, $userId, $db)) {
                $error_message = "Email already exists!";
            } else {
                try {
                    // Start with basic update
                    $sql = "UPDATE users SET username = :username, full_name = :full_name, email = :email";
                    $params = [
                        ':username' => $username,
                        ':full_name' => $full_name,
                        ':email' => $email,
                        ':id' => $userId
                    ];

                    // If password is provided, update it too
                    if (!empty($password)) {
                        $sql .= ", password = :password";
                        $params[':password'] = password_hash($password, PASSWORD_DEFAULT);
                    }

                    $sql .= " WHERE id = :id";
                    
                    if ($db->query($sql, $params)) {
                        $success_message = "Profile updated successfully!";
                        // Update session variables
                        $_SESSION['username'] = $username;
                    } else {
                        $error_message = "Error updating profile!";
                    }
                } catch (Exception $e) {
                    $error_message = "Error updating profile: " . $e->getMessage();
                }
            }
        } elseif ($_POST['action'] === 'create') {
            $username = trim($_POST['username']);
            $password = trim($_POST['password']);
            $full_name = trim($_POST['full_name']);
            $role = trim($_POST['role']);
            $email = trim($_POST['email']);

            // Validate input
            if (empty($username) || empty($password) || empty($full_name) || empty($role)) {
                $error_message = "All fields are required!";
            } elseif (!validateUsername($username, 0, $db)) {
                $error_message = "Username already exists!";
            } elseif (!empty($email) && !validateEmail($email, 0, $db)) {
                $error_message = "Email already exists!";
            } elseif (strlen($password) < 6) {
                $error_message = "Password must be at least 6 characters long!";
            } else {
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert new user
                try {
                    $sql = "INSERT INTO users (username, password, full_name, role, email) VALUES (:username, :password, :full_name, :role, :email)";
                    $params = [
                        ':username' => $username,
                        ':password' => $hashed_password,
                        ':full_name' => $full_name,
                        ':role' => $role,
                        ':email' => $email
                    ];
                    
                    if ($db->query($sql, $params)) {
                        $success_message = "User created successfully!";
                    } else {
                        $error_message = "Error creating user!";
                    }
                } catch (Exception $e) {
                    $error_message = "Error creating user: " . $e->getMessage();
                }
            }
        }
    }
}

// Fetch all users
$sql = "SELECT id, username, full_name, role, email FROM users WHERE role != 'admin'";
$users = $db->resultset($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Management - SuperMarket Billing</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <h2><i class="fas fa-users-cog me-2"></i>Profile Management</h2>
                <hr>

                <?php if ($success_message): ?>
                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>

                <?php if ($isAdmin): ?>
                <!-- Create User Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h4>Create New User</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="createUserForm">
                            <input type="hidden" name="action" value="create">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="username" class="form-label">Username</label>
                                    <input type="text" class="form-control" id="username" name="username" 
                                           pattern="[a-zA-Z0-9_]{3,}" title="Username must be at least 3 characters and can only contain letters, numbers, and underscore" required>
                                    <div class="form-text">Minimum 3 characters, letters, numbers, and underscore only</div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="password" class="form-label">Password</label>
                                    <input type="password" class="form-control" id="password" name="password" 
                                           minlength="6" required>
                                    <div class="form-text">Minimum 6 characters</div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="confirm_password" class="form-label">Confirm Password</label>
                                    <input type="password" class="form-control" id="confirm_password" 
                                           minlength="6" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="full_name" class="form-label">Full Name</label>
                                    <input type="text" class="form-control" id="full_name" name="full_name" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="role" class="form-label">Role</label>
                                    <select class="form-select" id="role" name="role" required>
                                        <option value="">Select Role</option>
                                        <option value="cashier">Cashier</option>
                                        <option value="manager">Manager</option>
                                    </select>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-user-plus me-2"></i>Create User
                            </button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Users List -->
                <div class="card">
                    <div class="card-header">
                        <h4><?php echo $isAdmin ? 'Existing Users' : 'My Profile'; ?></h4>
                    </div>
                    <div class="card-body">
                        <?php if ($isAdmin): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Full Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                                            <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td><span class="badge bg-info"><?php echo ucfirst(htmlspecialchars($user['role'])); ?></span></td>
                                            <td>
                                                <button class="btn btn-sm btn-warning" onclick="editUser(<?php echo $user['id']; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger" onclick="deleteUser(<?php echo $user['id']; ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                        <?php else: ?>
                            <?php
                            // Fetch current user's information
                            $userId = $_SESSION['user_id'];
                            $sql = "SELECT username, full_name, email, role FROM users WHERE id = :id";
                            $user = $db->single($sql, [':id' => $userId]);
                            ?>
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table">
                                        <tr>
                                            <th>Username:</th>
                                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Full Name:</th>
                                            <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Email:</th>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        </tr>
                                        <tr>
                                            <th>Role:</th>
                                            <td><span class="badge bg-info"><?php echo ucfirst(htmlspecialchars($user['role'])); ?></span></td>
                                        </tr>
                                    </table>
                                    <button class="btn btn-primary" onclick="editProfile()">
                                        <i class="fas fa-edit me-2"></i>Edit Profile
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Profile Modal -->
    <div class="modal fade" id="editProfileModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="" id="editProfileForm">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-user-edit me-2"></i>Edit Profile
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="edit_username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="edit_username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_full_name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="edit_full_name" name="full_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="edit_email" name="email">
                        </div>
                        <div class="mb-3">
                            <label for="edit_password" class="form-label">New Password (leave empty to keep current)</label>
                            <input type="password" class="form-control" id="edit_password" name="password">
                        </div>
                        <div class="mb-3">
                            <label for="edit_confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="edit_confirm_password" name="confirm_password">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Create User Form Validation
        document.getElementById('createUserForm')?.addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const username = document.getElementById('username').value;
            
            // Validate password match
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return;
            }
            
            // Validate username format
            if (!/^[a-zA-Z0-9_]{3,}$/.test(username)) {
                e.preventDefault();
                alert('Username must be at least 3 characters and can only contain letters, numbers, and underscore');
                return;
            }
        });

        function editUser(userId) {
            // TODO: Implement edit functionality
            alert('Edit functionality will be implemented soon!');
        }

        function deleteUser(userId) {
            if (confirm('Are you sure you want to delete this user?')) {
                // TODO: Implement delete functionality
                alert('Delete functionality will be implemented soon!');
            }
        }

        function editProfile() {
            // Fill the form with current user data
            document.getElementById('edit_username').value = '<?php echo htmlspecialchars($user['username']); ?>';
            document.getElementById('edit_full_name').value = '<?php echo htmlspecialchars($user['full_name']); ?>';
            document.getElementById('edit_email').value = '<?php echo htmlspecialchars($user['email']); ?>';
            
            // Show the modal
            const modal = new bootstrap.Modal(document.getElementById('editProfileModal'));
            modal.show();
        }

        // Password validation
        document.getElementById('editProfileForm').addEventListener('submit', function(e) {
            const password = document.getElementById('edit_password').value;
            const confirmPassword = document.getElementById('edit_confirm_password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
            }
        });
    </script>
</body>
</html>
