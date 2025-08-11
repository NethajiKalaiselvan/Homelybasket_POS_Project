<?php
// Database configuration for XAMPP
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', ''); // Default XAMPP password is empty
define('DB_NAME', 'supermarket_billing');

class Database {
    private $host = DB_HOST;
    private $user = DB_USER;
    private $pass = DB_PASS;
    private $dbname = DB_NAME;
    private $dbh;
    private $error;

    public function __construct() {
        // First try to connect without database to create it if needed
        try {
            $dsn = 'mysql:host=' . $this->host . ';charset=utf8';
            $options = [
                PDO::ATTR_PERSISTENT => false,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ];
            
            $tempConnection = new PDO($dsn, $this->user, $this->pass, $options);
            
            // Create database if it doesn't exist
            $tempConnection->exec("CREATE DATABASE IF NOT EXISTS `{$this->dbname}`");
            
            // Now connect to the specific database
            $dsn = 'mysql:host=' . $this->host . ';dbname=' . $this->dbname . ';charset=utf8';
            $this->dbh = new PDO($dsn, $this->user, $this->pass, $options);
            
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            throw new Exception('Database connection failed: ' . $this->error);
        }
    }

    public function getConnection() {
        return $this->dbh;
    }

    public function query($query, $params = []) {
        $stmt = $this->dbh->prepare($query);
        
        if (!empty($params)) {
            // Handle both named parameters (:param) and positional parameters (?)
            if (is_array($params) && !empty($params)) {
                // Check if we have named parameters (associative array with : keys)
                $hasNamedParams = false;
                foreach (array_keys($params) as $key) {
                    if (is_string($key) && strpos($key, ':') === 0) {
                        $hasNamedParams = true;
                        break;
                    }
                }
                
                if ($hasNamedParams) {
                    // Named parameters
                    foreach ($params as $key => $value) {
                        $stmt->bindValue($key, $value);
                    }
                } else {
                    // Positional parameters - PDO expects 1-based indexing
                    $index = 1;
                    foreach ($params as $value) {
                        $stmt->bindValue($index, $value);
                        $index++;
                    }
                }
            }
        }
        
        $stmt->execute();
        return $stmt;
    }

    public function single($query, $params = []) {
        $stmt = $this->query($query, $params);
        return $stmt->fetch();
    }

    public function resultset($query, $params = []) {
        $stmt = $this->query($query, $params);
        return $stmt->fetchAll();
    }

    public function lastInsertId() {
        return $this->dbh->lastInsertId();
    }

    public function rowCount($query, $params = []) {
        $stmt = $this->query($query, $params);
        return $stmt->rowCount();
    }
}
if (!function_exists('sanitize')) {
    function sanitize($input) {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
}

?>