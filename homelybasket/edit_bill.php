<?php
require_once 'config/database.php';
require_once 'includes/session.php';
require_once 'includes/functions.php';

requireLogin();

$db = new Database();

$bill_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->getConnection()->beginTransaction();

        // Update bill details
        $query = "UPDATE bills SET 
                    payment_status = :payment_status,
                    payment_method = :payment_method,
                    notes = :notes,
                    updated_at = CURRENT_TIMESTAMP
                 WHERE id = :bill_id";

        $db->query($query, [
            ':payment_status' => $_POST['payment_status'],
            ':payment_method' => $_POST['payment_method'],
            ':notes' => $_POST['notes'],
            ':bill_id' => $bill_id
        ]);

        $db->getConnection()->commit();
        $_SESSION['success'] = 'Bill updated successfully';
        header('Location: bill_history.php');
        exit();
    } catch (Exception $e) {
        $db->getConnection()->rollBack();
        $_SESSION['error'] = 'Error updating bill: ' . $e->getMessage();
    }
}

// Fetch bill details
$query = "SELECT b.*, c.name as customer_name, u.username as cashier_name 
          FROM bills b 
          LEFT JOIN customers c ON b.customer_id = c.id 
          LEFT JOIN users u ON b.cashier_id = u.id 
          WHERE b.id = :bill_id";
$bill = $db->single($query, [':bill_id' => $bill_id]);

if (!$bill) {
    $_SESSION['error'] = 'Bill not found';
    header('Location: bill_history.php');
    exit();
}

// Fetch bill items
$query = "SELECT bi.*, p.name as product_name 
          FROM bill_items bi 
          INNER JOIN products p ON bi.product_id = p.id 
          WHERE bi.bill_id = :bill_id";
$items = $db->resultset($query, [':bill_id' => $bill_id]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Bill - Supermarket Billing System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-edit"></i> Edit Bill</h2>
            <div>
                <a href="bill_history.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to History
                </a>
                <a href="invoice.php?invoice=<?= urlencode($bill['bill_number']) ?>" class="btn btn-success" target="_blank">
                    <i class="fas fa-print"></i> Print Invoice
                </a>
            </div>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $_SESSION['error'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="row">
            <!-- Bill Details -->
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Bill Details</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Bill Number</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($bill['bill_number']) ?>" readonly>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Date</label>
                                <input type="text" class="form-control" value="<?= date('Y-m-d H:i', strtotime($bill['bill_date'])) ?>" readonly>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Customer</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($bill['customer_name'] ?? 'Walk-in Customer') ?>" readonly>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Cashier</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($bill['cashier_name']) ?>" readonly>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Payment Method</label>
                                <select name="payment_method" class="form-select">
                                    <option value="cash" <?= $bill['payment_method'] === 'cash' ? 'selected' : '' ?>>Cash</option>
                                    <option value="card" <?= $bill['payment_method'] === 'card' ? 'selected' : '' ?>>Credit/Debit Card</option>
                                    <option value="upi" <?= $bill['payment_method'] === 'upi' ? 'selected' : '' ?>>UPI</option>
                                    <option value="other" <?= $bill['payment_method'] === 'other' ? 'selected' : '' ?>>Other</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Payment Status</label>
                                <select name="payment_status" class="form-select">
                                    <option value="paid" <?= $bill['payment_status'] === 'paid' ? 'selected' : '' ?>>Paid</option>
                                    <option value="pending" <?= $bill['payment_status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="refunded" <?= $bill['payment_status'] === 'refunded' ? 'selected' : '' ?>>Refunded</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Notes</label>
                                <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($bill['notes'] ?? '') ?></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Bill Items -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Bill Items</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Product</th>
                                        <th>Quantity</th>
                                        <th>Unit Price</th>
                                        <th>Tax Rate</th>
                                        <th class="text-end">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $index => $item): ?>
                                    <tr>
                                        <td><?= $index + 1 ?></td>
                                        <td><?= htmlspecialchars($item['product_name']) ?></td>
                                        <td><?= $item['quantity'] ?></td>
                                        <td>₹<?= number_format($item['unit_price'], 2) ?></td>
                                        <td><?= $item['tax_rate'] ?>%</td>
                                        <td class="text-end">₹<?= number_format($item['total_price'], 2) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <td colspan="5" class="text-end"><strong>Subtotal:</strong></td>
                                        <td class="text-end">₹<?= number_format($bill['subtotal'], 2) ?></td>
                                    </tr>
                                    <tr>
                                        <td colspan="5" class="text-end"><strong>Tax Amount:</strong></td>
                                        <td class="text-end">₹<?= number_format($bill['tax_amount'], 2) ?></td>
                                    </tr>
                                    <tr>
                                        <td colspan="5" class="text-end"><strong>Discount:</strong></td>
                                        <td class="text-end">₹<?= number_format($bill['discount_amount'], 2) ?></td>
                                    </tr>
                                    <tr>
                                        <td colspan="5" class="text-end"><strong>Total:</strong></td>
                                        <td class="text-end"><strong>₹<?= number_format($bill['total_amount'], 2) ?></strong></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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
