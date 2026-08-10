<?php
session_start();
include '../config/config.php';

// Check if user is admin
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: ../auth/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['user_id'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if ($new_password !== $confirm_password) {
        header('Location: ../network/incoming_connections.php?error=Passwords do not match');
        exit;
    }

    try {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashed_password, $user_id]);

        header('Location: ../network/incoming_connections.php?message=Password reset successfully');
    } catch (Exception $e) {
        header('Location: ../network/incoming_connections.php?error=Failed to reset password');
    }
    exit;
}

header('Location: ../network/incoming_connections.php');
exit;
