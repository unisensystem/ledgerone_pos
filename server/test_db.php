<?php
// Test database connection
define('DB_HOST', 'localhost');
define('DB_USER', 'fuelingsys_tenant'); // Replace with your actual DB username
define('DB_PASS', 'M36VQg42Uv)PF&RCm%*@'); // Replace with your actual DB password
define('DB_TENANT', 'fuelingsys_tenant');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_TENANT . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "Database connection successful!";
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
?>