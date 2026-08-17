<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$action = $_GET['action'] ?? '';
$nodeApiBase = 'http://localhost:3000';

function makeRequest($url, $method = 'GET', $data = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ['response' => $response, 'code' => $httpCode];
}

switch ($action) {
    case 'generate_qr':
        $clientId = $_GET['client_id'] ?? 'web_client_1';
        $result = makeRequest("$nodeApiBase/generate-qr?client_id=$clientId");
        echo $result['response'];
        break;
        
    case 'get_qr':
        $clientId = $_GET['client_id'] ?? 'web_client_1';
        $result = makeRequest("$nodeApiBase/get-qr?client_id=$clientId");
        echo $result['response'];
        break;
        
    case 'send_message':
        $input = json_decode(file_get_contents('php://input'), true);
        $result = makeRequest("$nodeApiBase/send-message", 'POST', $input);
        echo $result['response'];
        break;
        
    case 'health':
        $result = makeRequest("$nodeApiBase/health");
        echo $result['response'];
        break;
        
    case 'client_status':
        $clientId = $_GET['client_id'] ?? 'web_client_1';
        $result = makeRequest("$nodeApiBase/client-status?client_id=$clientId");
        echo $result['response'];
        break;
        
    case 'disconnect':
        $input = json_decode(file_get_contents('php://input'), true);
        $result = makeRequest("$nodeApiBase/disconnect", 'POST', $input);
        echo $result['response'];
        break;
        
    default:
        echo json_encode(['error' => 'Invalid action']);
}
?>