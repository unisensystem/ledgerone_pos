<?php
function getPostingLockDate($pdo, $tenant_id, $module_key) {
    $stmt = $pdo->prepare("SELECT lock_date FROM posting_lock_dates WHERE tenant_id = ? AND module_key = ? LIMIT 1");
    $stmt->execute([$tenant_id, $module_key]);
    return $stmt->fetchColumn() ?: null;
}

function checkPostingDateAllowed($pdo, $tenant_id, $module_key, $voucher_date) {
    $lockDate = getPostingLockDate($pdo, $tenant_id, $module_key);

    if (!$lockDate || !$voucher_date) {
        return ['allowed' => true, 'message' => ''];
    }

    if (strtotime($voucher_date) > strtotime($lockDate)) {
        return [
            'allowed' => false,
            'message' => "Posting is locked. Entries after $lockDate cannot be posted."
        ];
    }

    return ['allowed' => true, 'message' => ''];
}