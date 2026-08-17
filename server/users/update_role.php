<?php
include '../config/config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_POST['user_id'];
    $role = $_POST['role'];

    $stmt = $conn->prepare("UPDATE users SET role = :role WHERE id = :user_id");
    $stmt->bindParam(':role', $role);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();

    // Log activity
    logActivity($user_id, "Role updated to $role");

    header('Location: ../admin/admin_panel.php');
}
?>