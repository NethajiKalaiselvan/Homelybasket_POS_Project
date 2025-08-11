<?php
require_once 'config/database.php';
require_once 'includes/session.php';
require_once 'includes/functions.php';

requireLogin();

// Get the export format from URL
$format = isset($_GET['format']) ? strtolower($_GET['format']) : 'pdf';

// Fetch all the required data
$summary = $db->single("SELECT COUNT(*) as total_bills, SUM(total_amount) as total_sales FROM bills WHERE payment_status = 'paid'");
$topProducts = getTopSellingProducts(5, 30);
$dailySales = getDailySales();
$monthlySales = getMonthlySales();
$lowStockProducts = getLowStockProducts(5);
$topCustomers = getTopCustomers(5);

if ($format === 'pdf') {
    // Set headers for PDF download using HTML
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: inline; filename="sales_report.pdf"');
    
    // Start HTML content with proper styling
    echo '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Sales Report</title>
        <style>
            body { font-family: Arial, sans-serif; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f5f5f5; }
            h2 { color: #333; margin-top: 20px; }
            @media print {
                body { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
            }
        </style>
    </head>
    <body>
        <h1>Sales Report</h1>
        <p>Generated on: ' . date('Y-m-d H:i:s') . '</p>';

    // Sales Summary
    echo '<h2>Sales Summary</h2>
    <table>
        <tr>
            <th>Total Sales</th>
            <td>' . formatCurrency($summary['total_sales']) . '</td>
        </tr>
        <tr>
            <th>Total Bills</th>
            <td>' . $summary['total_bills'] . '</td>
        </tr>
        <tr>
            <th>Today\'s Sales</th>
            <td>' . formatCurrency($dailySales['total_sales']) . '</td>
        </tr>
    </table>';

    // Top Products Table
    echo '<h2>Top Selling Products (Last 30 Days)</h2>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Product</th>
                <th>Sold</th>
                <th>Revenue</th>
            </tr>
        </thead>
        <tbody>';
    
    foreach ($topProducts as $i => $prod) {
        echo '<tr>
            <td>' . ($i + 1) . '</td>
            <td>' . htmlspecialchars($prod['name']) . '</td>
            <td>' . $prod['total_sold'] . '</td>
            <td>' . formatCurrency($prod['total_revenue']) . '</td>
        </tr>';
    }
    
    echo '</tbody>
    </table>';

    // Low Stock Products
    echo '<h2>Low Stock Products</h2>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Product</th>
                <th>Stock</th>
            </tr>
        </thead>
        <tbody>';
    
    foreach ($lowStockProducts as $i => $product) {
        echo '<tr>
            <td>' . ($i + 1) . '</td>
            <td>' . htmlspecialchars($product['name']) . '</td>
            <td>' . $product['stock_quantity'] . '</td>
        </tr>';
    }
    
    echo '</tbody>
    </table>';

    // Top Customers
    echo '<h2>Top Customers</h2>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Customer</th>
                <th>Total Purchases</th>
                <th>Total Spent</th>
            </tr>
        </thead>
        <tbody>';
    
    foreach ($topCustomers as $i => $customer) {
        echo '<tr>
            <td>' . ($i + 1) . '</td>
            <td>' . htmlspecialchars($customer['name']) . '</td>
            <td>' . $customer['total_purchases'] . '</td>
            <td>' . formatCurrency($customer['total_spent']) . '</td>
        </tr>';
    }
    
    echo '</tbody>
    </table>
    </body>
    </html>';
    
    // Add print script to automatically show print dialog
    echo '<script>window.print();</script>';

} elseif ($format === 'excel') {
    // Set headers for Excel download
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="sales_report.xls"');
    header('Cache-Control: max-age=0');
    
    // Start output buffering
    ob_start();
?>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
</head>
<body>
    <h2>Sales Summary</h2>
    <table border="1">
        <tr>
            <th>Total Sales</th>
            <td><?php echo formatCurrency($summary['total_sales']); ?></td>
        </tr>
        <tr>
            <th>Total Bills</th>
            <td><?php echo $summary['total_bills']; ?></td>
        </tr>
        <tr>
            <th>Today's Sales</th>
            <td><?php echo formatCurrency($dailySales['total_sales']); ?></td>
        </tr>
    </table>

    <h2>Top Selling Products (Last 30 Days)</h2>
    <table border="1">
        <tr>
            <th>#</th>
            <th>Product</th>
            <th>Sold</th>
            <th>Revenue</th>
        </tr>
        <?php foreach ($topProducts as $i => $prod): ?>
        <tr>
            <td><?php echo $i + 1; ?></td>
            <td><?php echo $prod['name']; ?></td>
            <td><?php echo $prod['total_sold']; ?></td>
            <td><?php echo formatCurrency($prod['total_revenue']); ?></td>
        </tr>
        <?php endforeach; ?>
    </table>

    <h2>Low Stock Products</h2>
    <table border="1">
        <tr>
            <th>#</th>
            <th>Product</th>
            <th>Stock</th>
        </tr>
        <?php foreach ($lowStockProducts as $i => $product): ?>
        <tr>
            <td><?php echo $i + 1; ?></td>
            <td><?php echo $product['name']; ?></td>
            <td><?php echo $product['stock_quantity']; ?></td>
        </tr>
        <?php endforeach; ?>
    </table>

    <h2>Top Customers</h2>
    <table border="1">
        <tr>
            <th>#</th>
            <th>Customer</th>
            <th>Total Purchases</th>
            <th>Total Spent</th>
        </tr>
        <?php foreach ($topCustomers as $i => $customer): ?>
        <tr>
            <td><?php echo $i + 1; ?></td>
            <td><?php echo $customer['name']; ?></td>
            <td><?php echo $customer['total_purchases']; ?></td>
            <td><?php echo formatCurrency($customer['total_spent']); ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>
<?php
    echo ob_get_clean();
} else {
    // Invalid format requested
    header("HTTP/1.0 400 Bad Request");
    echo "Invalid export format requested";
}
?>
