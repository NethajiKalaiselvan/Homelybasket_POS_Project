<?php
require_once 'config/database.php';
require_once 'includes/session.php';
require_once 'includes/functions.php';

requireLogin();

$db = new Database();

// Handle bill deletion
if (isset($_POST['action']) && $_POST['action'] === 'delete_bill') {
    $bill_id = (int)$_POST['bill_id'];
    try {
        $db->getConnection()->beginTransaction();
        
        // Delete bill items first (due to foreign key constraint)
        $db->query("DELETE FROM bill_items WHERE bill_id = :bill_id", [':bill_id' => $bill_id]);
        
        // Then delete the bill
        $db->query("DELETE FROM bills WHERE id = :bill_id", [':bill_id' => $bill_id]);
        
        $db->getConnection()->commit();
        $_SESSION['success'] = 'Bill deleted successfully';
    } catch (Exception $e) {
        $db->getConnection()->rollBack();
        $_SESSION['error'] = 'Error deleting bill: ' . $e->getMessage();
    }
    header('Location: bill_history.php');
    exit();
}

// Get search parameters
$search = $_GET['search'] ?? '';
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Build the query
$query = "SELECT b.*, c.name as customer_name, u.username as cashier_name 
          FROM bills b 
          LEFT JOIN customers c ON b.customer_id = c.id 
          LEFT JOIN users u ON b.cashier_id = u.id 
          WHERE 1=1";
$params = [];

if ($search) {
    $query .= " AND (b.bill_number LIKE :search OR c.name LIKE :search)";
    $params[':search'] = "%$search%";
}

if ($start_date) {
    $query .= " AND DATE(b.bill_date) >= :start_date";
    $params[':start_date'] = $start_date;
}

if ($end_date) {
    $query .= " AND DATE(b.bill_date) <= :end_date";
    $params[':end_date'] = $end_date;
}

$query .= " ORDER BY b.bill_date DESC";

// Execute query
$bills = $db->resultset($query, $params);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bill History - Supermarket Billing System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-history"></i> Bill History</h2>
            <a href="billing.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> New Bill
            </a>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $_SESSION['success'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $_SESSION['error'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Search and Filter Form -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Bill number or customer name" value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($start_date) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($end_date) ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search"></i> Search
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Bills Table -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Bill Number</th>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>Cashier</th>
                                <th>Total Amount</th>
                                <th>Payment Method</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bills as $bill): ?>
                            <tr>
                                <td><?= htmlspecialchars($bill['bill_number']) ?></td>
                                <td><?= date('Y-m-d H:i', strtotime($bill['bill_date'])) ?></td>
                                <td><?= htmlspecialchars($bill['customer_name'] ?? 'Walk-in Customer') ?></td>
                                <td><?= htmlspecialchars($bill['cashier_name']) ?></td>
                                <td>₹<?= number_format($bill['total_amount'], 2) ?></td>
                                <td>
                                    <span class="badge bg-<?= $bill['payment_method'] === 'cash' ? 'success' : 
                                        ($bill['payment_method'] === 'card' ? 'primary' : 
                                        ($bill['payment_method'] === 'upi' ? 'info' : 'secondary')) ?>">
                                        <?= ucfirst($bill['payment_method']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $bill['payment_status'] === 'paid' ? 'success' : 
                                        ($bill['payment_status'] === 'pending' ? 'warning' : 'danger') ?>">
                                        <?= ucfirst($bill['payment_status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="invoice.php?invoice=<?= urlencode($bill['bill_number']) ?>" 
                                           class="btn btn-sm btn-info" title="View Invoice">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit_bill.php?id=<?= $bill['id'] ?>" 
                                           class="btn btn-sm btn-primary" title="Edit Bill">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="invoice.php?invoice=<?= urlencode($bill['bill_number']) ?>" 
                                           class="btn btn-sm btn-success" title="Print Invoice"
                                           onclick="window.print(); return false;">
                                            <i class="fas fa-print"></i>
                                        </a>
                                        <button type="button" 
                                                class="btn btn-sm btn-danger" 
                                                title="Delete Bill"
                                                onclick="confirmDelete('<?= $bill['id'] ?>', '<?= $bill['bill_number'] ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($bills)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <i class="fas fa-receipt fa-3x text-muted mb-3 d-block"></i>
                                    No bills found
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete bill <span id="deleteBillNumber"></span>?
                    This action cannot be undone.
                </div>
                <div class="modal-footer">
                    <form method="POST">
                        <input type="hidden" name="action" value="delete_bill">
                        <input type="hidden" name="bill_id" id="deleteBillId">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmDelete(billId, billNumber) {
            document.getElementById('deleteBillId').value = billId;
            document.getElementById('deleteBillNumber').textContent = billNumber;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
        
        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(function(alert) {
                    bootstrap.Alert.getOrCreateInstance(alert).close();
                });
            }, 5000);
        });
    </script>
</body>
</html>
