<?php
function encryptCustomerCode($customerId) {
    $key = str_pad('LedgerOne2024SecretKey', 32, '0');
    $customerId = (string)$customerId;
    $iv = substr(hash('sha256', $customerId . 'LedgerOne2024SecretKey'), 0, 32);
    $ivBinary = hex2bin($iv);
    $encrypted = openssl_encrypt($customerId, 'AES-256-CBC', $key, 0, $ivBinary);
    return $encrypted;
}

function decryptCustomerCode($encryptedCode, $pdo = null, $tenant_id = null) {
    $key = str_pad('LedgerOne2024SecretKey', 32, '0');
    
    if (!$pdo) {
        global $pdo;
    }
    if (!$tenant_id && isset($_SESSION['tenant_id'])) {
        $tenant_id = $_SESSION['tenant_id'];
    }
    
    if ($pdo && $tenant_id) {
        $stmt = $pdo->prepare("SELECT id FROM customers WHERE tenant_id = ? AND status = 'ACTIVE'");
        $stmt->execute([$tenant_id]);
        $customerIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($customerIds as $id) {
            $id = (string)$id;
            $iv = substr(hash('sha256', $id . 'LedgerOne2024SecretKey'), 0, 32);
            $ivBinary = hex2bin($iv);
            $decrypted = openssl_decrypt($encryptedCode, 'AES-256-CBC', $key, 0, $ivBinary);
            if ($decrypted !== false && $decrypted == $id) {
                return $decrypted;
            }
        }
    }
    
    return false;
}