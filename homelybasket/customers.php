<?php
require_once 'config/database.php';
require_once 'includes/session.php';
require_once 'includes/functions.php';

requireLogin();

// Handle delete customer
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $db->query("DELETE FROM customers WHERE id = :id", [':id' => $id]);
    header('Location: customers.php');
    exit();
}

// Handle edit customer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id']) && $_POST['edit_id']) {
    $id = (int)$_POST['edit_id'];
    $name = sanitize($_POST['name']);
    $phone = sanitize($_POST['phone'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $city = sanitize($_POST['city'] ?? '');
    $now = date('Y-m-d H:i:s');//  24 hrs format 
    $query = "UPDATE customers SET name = :name, phone = :phone, email = :email, address = :address, city = :city, updated_at = :updated_at WHERE id = :id";
    $db->query($query, [
        ':name' => $name,
        ':phone' => $phone,
        ':email' => $email,
        ':address' => $address,
        ':city' => $city,
        ':updated_at' => $now,
        ':id' => $id
    ]);
    header('Location: customers.php');
    exit();
}

// Handle add customer form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'])) {
    $name = sanitize($_POST['name']);
    $phone = sanitize($_POST['phone'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $address = sanitize($_POST['address'] ?? '');
    $city = sanitize($_POST['city'] ?? '');
    $status = 'active';
    $now = date('Y-m-d H:i:s');
    $query = "INSERT INTO customers (name, phone, email, address, city, status, created_at, updated_at) VALUES (:name, :phone, :email, :address, :city, :status, :created_at, :updated_at)";
    $db->query($query, [
        ':name' => $name,
        ':phone' => $phone,
        ':email' => $email,
        ':address' => $address,
        ':city' => $city,
        ':status' => $status,
        ':created_at' => $now,
        ':updated_at' => $now
    ]);
    header('Location: customers.php');
    exit();
}

// Fetch all customers
$customers = $db->resultset("SELECT * FROM customers ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers - Supermarket Billing System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>Customers</h2>
            <a href="#" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCustomerModal">Add Customer</a>
        </div>
        <table class="table table-bordered table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Address</th>
                    <th>City</th>
                    <th>Total Purchases</th>
                    <th>Last Purchase</th>
                    <th>Status</th>
                    <th>Created At</th>
                    <th>Updated At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($customers as $customer): ?>
                <tr>
                    <td><?= $customer['id'] ?></td>
                    <td><?= htmlspecialchars($customer['name']) ?></td>
                    <td><?= htmlspecialchars($customer['phone']) ?></td>
                    <td><?= htmlspecialchars($customer['email']) ?></td>
                    <td><?= htmlspecialchars($customer['address']) ?></td>
                    <td><?= htmlspecialchars($customer['city']) ?></td>
                    <td><?= number_format($customer['total_purchases'], 2) ?></td>
                    <td><?= $customer['last_purchase'] ?></td>
                    <td><?= ucfirst($customer['status']) ?></td>
                    <td><?= $customer['created_at'] ?></td>
                    <td><?= $customer['updated_at'] ?></td>
                    <td>
                        <button type="button" class="btn btn-sm btn-warning edit-btn"
                            data-id="<?= $customer['id'] ?>"
                            data-name="<?= htmlspecialchars($customer['name']) ?>"
                            data-phone="<?= htmlspecialchars($customer['phone']) ?>"
                            data-email="<?= htmlspecialchars($customer['email']) ?>"
                            data-address="<?= htmlspecialchars($customer['address']) ?>"
                            data-city="<?= htmlspecialchars($customer['city']) ?>"
                            data-bs-toggle="modal" data-bs-target="#addCustomerModal"
                        >Edit</button>
                        <a href="customers.php?delete=<?= $customer['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this customer?')">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <!-- Add Customer Modal -->
    <div class="modal fade" id="addCustomerModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" action="">
                    <input type="hidden" name="edit_id" id="edit_id">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTitle">Add Customer</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" id="name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" id="phone" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" id="email" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <input type="text" name="address" id="address" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">City</label>
                            <input type="text" name="city" id="city" class="form-control">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="submitBtn">Add Customer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
<script>
// Edit button fills modal with customer data
document.querySelectorAll('.edit-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('edit_id').value = this.dataset.id;
        document.getElementById('name').value = this.dataset.name;
        document.getElementById('phone').value = this.dataset.phone;
        document.getElementById('email').value = this.dataset.email;
        document.getElementById('address').value = this.dataset.address;
        document.getElementById('city').value = this.dataset.city;
        document.getElementById('modalTitle').textContent = 'Edit Customer';
        document.getElementById('submitBtn').textContent = 'Update Customer';
    });
});
// Reset modal for add
document.getElementById('addCustomerModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('edit_id').value = '';
    document.getElementById('name').value = '';
    document.getElementById('phone').value = '';
    document.getElementById('email').value = '';
    document.getElementById('address').value = '';
    document.getElementById('city').value = '';
    document.getElementById('modalTitle').textContent = 'Add Customer';
    document.getElementById('submitBtn').textContent = 'Add Customer';
});
</script>
</body>
</html>
