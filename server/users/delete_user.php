<?php
session_start();

include '../config/config.php';

// Check if the user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !$_SESSION['is_admin']) {
    header('Location: ../auth/login.php');
    exit;
}

// Get user ID from query parameter
$user_id = $_GET['id'];

try {
    $pdo->beginTransaction();

    // Delete user's company setting assignments first
    $dist_stmt = $pdo->prepare("DELETE FROM distribution_users WHERE user_id = :id");
    $dist_stmt->bindParam(':id', $user_id);
    $dist_stmt->execute();

    // Delete user
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
    $stmt->bindParam(':id', $user_id);
    $stmt->execute();

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error deleting user: " . $e->getMessage());
    // Could redirect with error message
    header('Location: ../admin/admin_panel.php?error=Failed to delete user');
    exit;
}

// Redirect back to the admin panel
header('Location: ../admin/admin_panel.php');
exit;
?>