<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

$input = json_decode(file_get_contents('php://input'), true);
echo json_encode([
    'success' => true,
    'projectId_received' => $input['projectId'] ?? 'KEY_NOT_FOUND',
    'projectId_type' => gettype($input['projectId'] ?? null),
    'all_keys' => array_keys($input ?? [])
]);
