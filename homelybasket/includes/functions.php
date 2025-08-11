<?php
require_once 'config/database.php';

$db = new Database();

if (!function_exists('sanitize')) {
    function sanitize($input) {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
}

function generateBillNumber() {
    global $db;
    $prefix = getSetting('bill_prefix');
    $date = date('Ymd');
    
    // Get last bill number for today
    $query = "SELECT bill_number FROM bills WHERE DATE(bill_date) = CURDATE() ORDER BY id DESC LIMIT 1";
    $lastBill = $db->single($query);
    
    if ($lastBill) {
        $lastNumber = (int)substr($lastBill['bill_number'], -4);
        $newNumber = $lastNumber + 1;
    } else {
        $newNumber = 1;
    }
    
    return $prefix . $date . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
}

function getSetting($key) {
    global $db;
    $query = "SELECT setting_value FROM settings WHERE setting_key = :key";
    $result = $db->single($query, [':key' => $key]);
    return $result ? $result['setting_value'] : '';
}

function updateStock($productId, $quantity, $type = 'out', $referenceType = 'sale', $referenceId = null) {
    global $db;
    
    // Update product stock
    if ($type === 'out') {
        $query = "UPDATE products SET stock_quantity = stock_quantity - :quantity WHERE id = :id";
    } else {
        $query = "UPDATE products SET stock_quantity = stock_quantity + :quantity WHERE id = :id";
    }
    
    $db->query($query, [':quantity' => $quantity, ':id' => $productId]);
    
    // Record stock movement
    $query = "INSERT INTO stock_movements (product_id, movement_type, quantity, reference_type, reference_id, user_id) 
              VALUES (:product_id, :movement_type, :quantity, :reference_type, :reference_id, :user_id)";
    
    $db->query($query, [
        ':product_id' => $productId,
        ':movement_type' => $type,
        ':quantity' => $quantity,
        ':reference_type' => $referenceType,
        ':reference_id' => $referenceId,
        ':user_id' => getUserId()
    ]);
}

function getLowStockProducts() {
    global $db;
    $query = "SELECT p.*, c.name as category_name, b.name as brand_name 
              FROM products p 
              LEFT JOIN categories c ON p.category_id = c.id 
              LEFT JOIN brands b ON p.brand_id = b.id 
              WHERE p.stock_quantity <= p.min_stock_level AND p.status = 'active'
              ORDER BY p.stock_quantity ASC";
    return $db->resultset($query);
}

function formatCurrency($amount) {
    $symbol = getSetting('currency_symbol');
    return $symbol . number_format($amount, 2);
}

function getTopSellingProducts($limit = 5, $days = 30) {
    global $db;
    $limit = intval($limit); // Ensure integer
    $query = "SELECT p.name, p.unit_price, SUM(bi.quantity) as total_sold, 
                     SUM(bi.total_price) as total_revenue
              FROM bill_items bi 
              INNER JOIN products p ON bi.product_id = p.id 
              INNER JOIN bills b ON bi.bill_id = b.id 
              WHERE b.bill_date >= DATE_SUB(NOW(), INTERVAL :days DAY)
              GROUP BY p.id, p.name, p.unit_price
              ORDER BY total_sold DESC 
              LIMIT $limit";
    return $db->resultset($query, [':days' => $days]);
}

function getDailySales($date = null) {
    global $db;
    if (!$date) $date = date('Y-m-d');
    
    $query = "SELECT COUNT(*) as total_bills, SUM(total_amount) as total_sales, 
                     SUM(tax_amount) as total_tax, AVG(total_amount) as avg_bill
              FROM bills 
              WHERE DATE(bill_date) = :date AND payment_status = 'paid'";
    return $db->single($query, [':date' => $date]);
}

function getMonthlySales() {
    global $db;
    $query = "SELECT DATE_FORMAT(bill_date, '%Y-%m') as month, SUM(total_amount) as total_sales 
              FROM bills 
              WHERE payment_status = 'paid' 
              GROUP BY DATE_FORMAT(bill_date, '%Y-%m') 
              ORDER BY month ASC";
    $results = $db->resultset($query);

    $labels = array_map(fn($row) => $row['month'], $results);
    $data = array_map(fn($row) => $row['total_sales'], $results);

    return ['labels' => $labels, 'data' => $data];
}

function getTopCustomers($limit = 5) {
    global $db;
    $query = "SELECT c.name, COUNT(b.id) as total_purchases, SUM(b.total_amount) as total_spent 
              FROM customers c 
              JOIN bills b ON c.id = b.customer_id 
              WHERE b.payment_status = 'paid' 
              GROUP BY c.id 
              ORDER BY total_spent DESC 
              LIMIT $limit"; // Embed the limit value directly
    return $db->resultset($query);
}
?>