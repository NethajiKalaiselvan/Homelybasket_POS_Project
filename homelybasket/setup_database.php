<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database setup script for XAMPP
echo "<h2>Database Setup for Supermarket Billing System</h2>";

try {
    // Database configuration
    $host = 'localhost';
    $user = 'root';
    $pass = '';
    $dbname = 'supermarket_billing';
    
    echo "<p>Connecting to MySQL server...</p>";
    
    // Connect to MySQL without selecting a database first
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p style='color: green;'>✓ Connected to MySQL server successfully!</p>";
    
    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname`");
    echo "<p style='color: green;'>✓ Database '$dbname' created successfully!</p>";
    
    // Select the database
    $pdo->exec("USE `$dbname`");
    
    // Create tables
    echo "<p>Creating tables...</p>";
    
    // Users table
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        full_name VARCHAR(100) NOT NULL,
        email VARCHAR(100),
        role ENUM('admin', 'cashier') DEFAULT 'cashier',
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    // Categories table
    $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Brands table
    $pdo->exec("CREATE TABLE IF NOT EXISTS brands (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Products table
    $pdo->exec("CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(200) NOT NULL,
        barcode VARCHAR(50) UNIQUE,
        category_id INT,
        brand_id INT,
        unit_price DECIMAL(10,2) NOT NULL,
        cost_price DECIMAL(10,2),
        stock_quantity INT DEFAULT 0,
        min_stock_level INT DEFAULT 5,
        unit VARCHAR(20) DEFAULT 'piece',
        tax_rate DECIMAL(5,2) DEFAULT 0,
        description TEXT,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES categories(id),
        FOREIGN KEY (brand_id) REFERENCES brands(id)
    )");
    
    // Customers table
    $pdo->exec("CREATE TABLE IF NOT EXISTS customers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        phone VARCHAR(15) UNIQUE,
        email VARCHAR(100),
        address TEXT,
        city VARCHAR(50),
        total_purchases DECIMAL(12,2) DEFAULT 0,
        last_purchase DATE,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    // Bills table
    $pdo->exec("CREATE TABLE IF NOT EXISTS bills (
        id INT AUTO_INCREMENT PRIMARY KEY,
        bill_number VARCHAR(20) UNIQUE NOT NULL,
        customer_id INT,
        cashier_id INT NOT NULL,
        bill_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        subtotal DECIMAL(12,2) NOT NULL,
        tax_amount DECIMAL(12,2) DEFAULT 0,
        discount_amount DECIMAL(12,2) DEFAULT 0,
        total_amount DECIMAL(12,2) NOT NULL,
        payment_method ENUM('cash', 'card', 'upi', 'other') DEFAULT 'cash',
        payment_status ENUM('paid', 'pending', 'refunded') DEFAULT 'paid',
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (customer_id) REFERENCES customers(id),
        FOREIGN KEY (cashier_id) REFERENCES users(id)
    )");
    
    // Bill items table
    $pdo->exec("CREATE TABLE IF NOT EXISTS bill_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        bill_id INT NOT NULL,
        product_id INT NOT NULL,
        quantity INT NOT NULL,
        unit_price DECIMAL(10,2) NOT NULL,
        total_price DECIMAL(12,2) NOT NULL,
        tax_rate DECIMAL(5,2) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (bill_id) REFERENCES bills(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id)
    )");
    
    // Settings table
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value TEXT,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    echo "<p style='color: green;'>✓ All tables created successfully!</p>";
    
    // Insert default admin user
    $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT IGNORE INTO users (username, password, full_name, email, role) VALUES (:username, :password, :full_name, :email, :role)");
    $stmt->execute([
        ':username' => 'admin',
        ':password' => $hashedPassword,
        ':full_name' => 'System Administrator',
        ':email' => 'admin@supermarket.com',
        ':role' => 'admin'
    ]);
    
    echo "<p style='color: green;'>✓ Default admin user created!</p>";
    
    // Insert sample categories
    $categories = [
        ['Groceries', 'Daily grocery items'],
        ['Beverages', 'Drinks and beverages'],
        ['Snacks', 'Snacks and confectionery'],
        ['Personal Care', 'Personal hygiene products'],
        ['Household', 'Household items and cleaning supplies']
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO categories (name, description) VALUES (:name, :description)");
    foreach ($categories as $category) {
        $stmt->execute([':name' => $category[0], ':description' => $category[1]]);
    }
    
    // Insert sample brands
    $brands = [
        ['Local Brand', 'Local products'],
        ['Premium', 'Premium quality products'],
        ['Economy', 'Budget-friendly products']
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO brands (name, description) VALUES (:name, :description)");
    foreach ($brands as $brand) {
        $stmt->execute([':name' => $brand[0], ':description' => $brand[1]]);
    }
    
    // Insert default settings
    $settings = [
        ['company_name', 'SuperMart Store', 'Company name for invoices'],
        ['company_address', '123 Main Street, City, State 12345', 'Company address'],
        ['company_phone', '+1-234-567-8900', 'Company phone number'],
        ['tax_rate', '18', 'Default tax rate percentage'],
        ['currency_symbol', '$', 'Currency symbol'],
        ['bill_prefix', 'SM', 'Bill number prefix']
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO settings (setting_key, setting_value, description) VALUES (:key, :value, :description)");
    foreach ($settings as $setting) {
        $stmt->execute([':key' => $setting[0], ':value' => $setting[1], ':description' => $setting[2]]);
    }
    
    echo "<p style='color: green;'>✓ Sample data inserted successfully!</p>";
    
    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3 style='color: #155724;'>Setup Complete!</h3>";
    echo "<p><strong>Database:</strong> $dbname</p>";
    echo "<p><strong>Default Login:</strong></p>";
    echo "<ul>";
    echo "<li>Username: admin</li>";
    echo "<li>Password: admin123</li>";
    echo "</ul>";
    echo "<p><a href='login.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Login Page</a></p>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
    echo "<p>Please ensure:</p>";
    echo "<ul>";
    echo "<li>XAMPP MySQL service is running</li>";
    echo "<li>MySQL is accessible on localhost:3306</li>";
    echo "<li>Root user has proper permissions</li>";
    echo "</ul>";
}
?>