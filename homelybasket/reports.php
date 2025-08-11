<?php
require_once 'config/database.php';
require_once 'includes/session.php';
require_once 'includes/functions.php';

requireLogin();

// Get sales summary
$summary = $db->single("SELECT COUNT(*) as total_bills, SUM(total_amount) as total_sales FROM bills WHERE payment_status = 'paid'");

// Get top selling products
$topProducts = getTopSellingProducts(5, 30);
$dailySales = getDailySales();
$monthlySales = getMonthlySales();

// Get low stock products
$lowStockProducts = $db->resultset(
    "SELECT p.id, p.name, p.stock_quantity as stock, p.min_stock_level, p.unit 
     FROM products p 
     WHERE p.status = 'active' 
     AND p.stock_quantity <= p.min_stock_level 
     ORDER BY (p.min_stock_level - p.stock_quantity) DESC 
     LIMIT 5"
);

// Get top customers
$topCustomers = getTopCustomers(5);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Supermarket Billing System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    <div class="container mt-4">
        <h2>Reports</h2>
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Total Sales</h5>
                        <p class="card-text h4">₹<?= number_format($summary['total_sales'] ?? 0, 2) ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Total Bills</h5>
                        <p class="card-text h4"><?= $summary['total_bills'] ?? 0 ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Today's Sales</h5>
                        <p class="card-text h4">₹<?= number_format($dailySales['total_sales'] ?? 0, 2) ?></p>
                    </div>
                </div>
            </div>
        </div>
        <div class="mb-4">
            <form method="GET" action="reports.php">
                <div class="row">
                    <div class="col-md-4">
                        <label for="startDate" class="form-label">Start Date</label>
                        <input type="date" id="startDate" name="start_date" class="form-control" value="<?= htmlspecialchars($_GET['start_date'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="endDate" class="form-label">End Date</label>
                        <input type="date" id="endDate" name="end_date" class="form-control" value="<?= htmlspecialchars($_GET['end_date'] ?? '') ?>">
                    </div>
                    <div class="col-md-4 align-self-end">
                        <button type="submit" class="btn btn-primary">Filter</button>
                    </div>
                </div>
            </form>
        </div>
        <div class="card mb-4">
            <div class="card-header">Top Selling Products (30 Days)</div>
            <div class="card-body">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Product</th>
                            <th>Sold</th>
                            <th>Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topProducts as $i => $prod): ?>
                        <tr>
                            <td><?= $i+1 ?></td>
                            <td><?= htmlspecialchars($prod['name']) ?></td>
                            <td><?= $prod['total_sold'] ?></td>
                            <td>₹<?= number_format($prod['total_revenue'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card mb-4">
            <div class="card-header">Monthly Sales Chart</div>
            <div class="card-body">
                <canvas id="monthlySalesChart"></canvas>
            </div>
        </div>
        <div class="card mb-4">
            <div class="card-header">Low Stock Products</div>
            <div class="card-body">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Product</th>
                            <th>Stock</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($lowStockProducts)): ?>
                        <tr>
                            <td colspan="3" class="text-center">No low stock products found</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($lowStockProducts as $i => $product): ?>
                            <tr>
                                <td><?= $i+1 ?></td>
                                <td><?= htmlspecialchars($product['name']) ?></td>
                                <td>
                                    <span class="badge bg-<?= $product['stock'] == 0 ? 'danger' : 'warning' ?>">
                                        <?= $product['stock'] ?> <?= htmlspecialchars($product['unit']) ?>
                                        <small class="text-white">(Min: <?= $product['min_stock_level'] ?>)</small>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card mb-4">
            <div class="card-header">Top Customers</div>
            <div class="card-body">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Customer</th>
                            <th>Total Purchases</th>
                            <th>Total Spent</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topCustomers as $i => $customer): ?>
                        <tr>
                            <td><?= $i+1 ?></td>
                            <td><?= htmlspecialchars($customer['name']) ?></td>
                            <td><?= $customer['total_purchases'] ?></td>
                            <td>₹<?= number_format($customer['total_spent'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mb-4">
            <button class="btn btn-primary" onclick="exportReport('pdf')">Export as PDF</button>
            <button class="btn btn-secondary" onclick="exportReport('excel')">Export as Excel</button>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const ctx = document.getElementById('monthlySalesChart').getContext('2d');
        const monthlySalesChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($monthlySales['labels'] ?? []) ?>,
                datasets: [{
                    label: 'Sales',
                    data: <?= json_encode($monthlySales['data'] ?? []) ?>,
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 2,
                    fill: false
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    title: {
                        display: true,
                        text: 'Monthly Sales'
                    }
                }
            }
        });

        function exportReport(format) {
            window.location.href = `export_report.php?format=${format}`;
        }
    </script>
</body>
</html>
