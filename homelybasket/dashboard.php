<?php
require_once 'config/database.php';
require_once 'includes/session.php';
require_once 'includes/functions.php';

requireLogin();

// Get the selected date from GET parameter, default to today
$selectedDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Validate date format
if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $selectedDate)) {
    $selectedDate = date('Y-m-d');
}

$dailySales = getDailySales($selectedDate);
$lowStockProducts = getLowStockProducts();
$topProducts = getTopSellingProducts(5, 30);

// Get some statistics
$db = new Database();
$totalProducts = $db->single("SELECT COUNT(*) as count FROM products WHERE status = 'active'")['count'];
$totalCustomers = $db->single("SELECT COUNT(*) as count FROM customers WHERE status = 'active'")['count'];
$todayBills = $db->single("SELECT COUNT(*) as count FROM bills WHERE DATE(bill_date) = CURDATE()")['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Supermarket Billing System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="row">
       
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-tachometer-alt me-2"></i>
                        Dashboard
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <input type="date" 
                                   id="dashboardDate" 
                                   class="form-control form-control-sm" 
                                   value="<?php echo $selectedDate; ?>"
                                   max="<?php echo date('Y-m-d'); ?>">
                            <button type="button" class="btn btn-sm btn-outline-primary" id="today-btn">
                                <i class="fas fa-calendar-day me-1"></i>
                                Today
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="prev-day">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="next-day">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Today's Sales
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo formatCurrency($dailySales['total_sales'] ?? 0); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Today's Bills
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $todayBills; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-receipt fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Total Products
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $totalProducts; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-boxes fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Total Customers
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $totalCustomers; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-users fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Low Stock Alert -->
                    <div class="col-lg-6">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-danger">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    Low Stock Alert
                                </h6>
                            </div>
                            <div class="card-body">
                                <?php if (empty($lowStockProducts)): ?>
                                    <div class="text-center text-muted">
                                        <i class="fas fa-check-circle fa-3x mb-3"></i>
                                        <p>All products are well stocked!</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Product</th>
                                                    <th>Stock</th>
                                                    <th>Min Level</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach (array_slice($lowStockProducts, 0, 5) as $product): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                                                        <td>
                                                            <span class="badge bg-danger">
                                                                <?php echo $product['stock_quantity']; ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo $product['min_stock_level']; ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Top Selling Products -->
                    <div class="col-lg-6">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                                <h6 class="m-0 font-weight-bold text-success">
                                    <i class="fas fa-chart-line me-2"></i>
                                    Top Selling Products (30 Days)
                                </h6>
                            </div>
                            <div class="card-body">
                                <?php if (empty($topProducts)): ?>
                                    <div class="text-center text-muted">
                                        <i class="fas fa-chart-line fa-3x mb-3"></i>
                                        <p>No sales data available</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Product</th>
                                                    <th>Sold</th>
                                                    <th>Revenue</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($topProducts as $product): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                                                        <td>
                                                            <span class="badge bg-info">
                                                                <?php echo $product['total_sold']; ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo formatCurrency($product['total_revenue']); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="row">
                    <div class="col-12">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-bolt me-2"></i>
                                    Quick Actions
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3 mb-3">
                                        <a href="billing.php" class="btn btn-primary btn-lg w-100">
                                            <i class="fas fa-cash-register fa-2x mb-2 d-block"></i>
                                            New Sale
                                        </a>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <a href="products.php" class="btn btn-success btn-lg w-100">
                                            <i class="fas fa-plus fa-2x mb-2 d-block"></i>
                                            Add Product
                                        </a>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <a href="customers.php" class="btn btn-info btn-lg w-100">
                                            <i class="fas fa-user-plus fa-2x mb-2 d-block"></i>
                                            Add Customer
                                        </a>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <a href="reports.php" class="btn btn-warning btn-lg w-100">
                                            <i class="fas fa-chart-bar fa-2x mb-2 d-block"></i>
                                            View Reports
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const dateInput = document.getElementById('dashboardDate');
            const todayBtn = document.getElementById('today-btn');
            const prevBtn = document.getElementById('prev-day');
            const nextBtn = document.getElementById('next-day');
            const today = new Date().toISOString().split('T')[0];

            // Update dashboard when date changes
            function updateDashboard(date) {
                window.location.href = `dashboard.php?date=${date}`;
            }

            // Date input change handler
            dateInput.addEventListener('change', function() {
                updateDashboard(this.value);
            });

            // Today button handler
            todayBtn.addEventListener('click', function() {
                updateDashboard(today);
            });

            // Previous day handler
            prevBtn.addEventListener('click', function() {
                const currentDate = new Date(dateInput.value);
                currentDate.setDate(currentDate.getDate() - 1);
                updateDashboard(currentDate.toISOString().split('T')[0]);
            });

            // Next day handler
            nextBtn.addEventListener('click', function() {
                const currentDate = new Date(dateInput.value);
                const maxDate = new Date(today);
                
                currentDate.setDate(currentDate.getDate() + 1);
                
                // Don't allow going beyond today
                if (currentDate <= maxDate) {
                    updateDashboard(currentDate.toISOString().split('T')[0]);
                }
            });
        });
    </script>
</body>
</html>