<?php
session_start();
include '../server/config/config.php'; // Include database connection

// Redirect to login page if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

// Check if the user is an admin
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    echo "Access denied. You are not authorized to view this page.";
    exit;
}

// Fetch all users
$sql = "SELECT id, username, first_name, last_name, is_active FROM users";
$result = $conn->query($sql);

if (!$result) {
    echo "Error fetching users: " . $conn->$error;
    exit;
}

// Set headers to download the file
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=users.csv');
$output = fopen('php://output', 'w');
fputcsv($output, array('ID', 'Username', 'First Name', 'Last Name', 'Status'));

while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($output, $row);
}

fclose($output);
?>