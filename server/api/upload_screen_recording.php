<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$uploadDir = '../../client/assets/uploads/screen_recordings/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if (!isset($_POST['sessionId'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing session ID']);
    exit;
}

$sessionId = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['sessionId']);
$userId = $_SESSION['user_id'];
$uploaded = [];

if (isset($_FILES['screen'])) {
    $screenFile = "{$userId}_{$sessionId}_screen.webm";
    if (move_uploaded_file($_FILES['screen']['tmp_name'], $uploadDir . $screenFile)) {
        $uploaded[] = 'screen';
    }
}

if (isset($_FILES['webcam'])) {
    $webcamFile = "{$userId}_{$sessionId}_webcam.webm";
    if (move_uploaded_file($_FILES['webcam']['tmp_name'], $uploadDir . $webcamFile)) {
        $uploaded[] = 'webcam';
    }
}

if (count($uploaded) > 0) {
    echo json_encode(['success' => true, 'uploaded' => $uploaded]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Upload failed']);
}
