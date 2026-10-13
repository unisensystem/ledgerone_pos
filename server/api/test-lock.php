<?php
require_once '../../includes/connection.php';
require_once '../../includes/posting_lock.php';

$tenant_id = 2; // apna tenant_id yahan daalo (aapke row mein 2 hai)
$result = checkPostingDateAllowed($pdo, $tenant_id, 'receive_voucher', '2026-09-10');

header('Content-Type: application/json');
echo json_encode($result);