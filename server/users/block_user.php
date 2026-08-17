<?php
session_start(); // Ensure this is at the very top

// Include the database connection
include '../config/config.php';

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

// Get the user ID from the query parameter
$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Block the user
$stmt = $pdo->prepare("UPDATE users SET blocked = 1 WHERE id = :id");
$stmt->bindParam(':id', $user_id);
$stmt->execute();

// Log the user out if they are currently logged in
if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $user_id) {
    session_destroy();
}

// Redirect back to the admin panel
header('Location: ../admin/admin_panel.php');
exit;
?>