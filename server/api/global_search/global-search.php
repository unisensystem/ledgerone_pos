<?php
require_once '../../../includes/connection.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$tenant_id) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$q = trim($_GET['q'] ?? '');
if ($q === '' || strlen($q) < 1) {
    echo json_encode(['success' => true, 'data' => []]);
    exit();
}

$modules = [
    'quotation' => [
        'label' => 'Quotation', 'table' => 'quotations', 'id_col' => 'id',
        'number_col' => 'quotation_number', 'amount_col' => 'grand_total', 'date_col' => 'quotation_date',
        'party_table' => 'customers', 'party_col' => 'customer_id', 'party_name_col' => 'customer_name',
        'list_url' => '../sale/Quotation/quotation-list.php',
    ],
    'sale_order' => [
        'label' => 'Sale Order', 'table' => 'sale_order', 'id_col' => 'id',
        'number_col' => 'bill_no', 'amount_col' => 'net_amount', 'date_col' => 'sale_date',
        'party_table' => 'customers', 'party_col' => 'customer_id', 'party_name_col' => 'customer_name',
        'list_url' => '../sale/sale_order/order-list.php',
    ],
    'sale_invoice' => [
        'label' => 'Sale Invoice', 'table' => 'sale_invoice', 'id_col' => 'id',
        'number_col' => 'bill_no', 'amount_col' => 'net_amount', 'date_col' => 'sale_date',
        'party_table' => 'customers', 'party_col' => 'customer_id', 'party_name_col' => 'customer_name',
        'list_url' => '../sale/pos_invoice/pos-list.php',
    ],
    'delivery_chalan' => [
        'label' => 'Delivery Challan', 'table' => 'delivery_chalan', 'id_col' => 'id',
        'number_col' => 'chalan_no', 'amount_col' => null, 'date_col' => 'delivery_date',
        'party_table' => 'customers', 'party_col' => 'customer_id', 'party_name_col' => 'customer_name',
        'list_url' => '../sale/delivery_chalan/pos-list.php',
    ],
    'sale_return' => [
        'label' => 'Sale Return', 'table' => 'sale_return', 'id_col' => 'id',
        'number_col' => 'bill_no', 'amount_col' => 'net_amount', 'date_col' => 'sale_date',
        'party_table' => 'customers', 'party_col' => 'customer_id', 'party_name_col' => 'customer_name',
        'list_url' => '../sale/sale_return/return-list.php',
    ],
    'purchase_order' => [
        'label' => 'Purchase Order', 'table' => 'purchase_order', 'id_col' => 'id',
        'number_col' => 'bill_no', 'amount_col' => 'net_amount', 'date_col' => 'purchase_date',
        'party_table' => 'suppliers', 'party_col' => 'supplier_id', 'party_name_col' => 'supplier_name',
        'list_url' => '../purchase/purchase_order/order-list.php',
    ],
    'purchase_invoice' => [
        'label' => 'Purchase Invoice', 'table' => 'purchase_invoice', 'id_col' => 'id',
        'number_col' => 'bill_no', 'amount_col' => 'net_amount', 'date_col' => 'purchase_date',
        'party_table' => 'suppliers', 'party_col' => 'supplier_id', 'party_name_col' => 'supplier_name',
        'list_url' => '../purchase/purchase_invoice/purchase-list.php',
    ],
    'purchase_return' => [
        'label' => 'Purchase Return', 'table' => 'purchase_return', 'id_col' => 'id',
        'number_col' => 'bill_no', 'amount_col' => 'net_amount', 'date_col' => 'purchase_date',
        'party_table' => 'suppliers', 'party_col' => 'supplier_id', 'party_name_col' => 'supplier_name',
        'list_url' => '../purchase/purchase_return/return-list.php',
    ],
    'stock_adjustment' => [
        'label' => 'Stock Adjustment', 'table' => 'stock_adjustment', 'id_col' => 'id',
        'number_col' => 'adjustment_code', 'amount_col' => 'total_amount', 'date_col' => 'date',
        'party_table' => null, 'party_col' => null, 'party_name_col' => null,
        'list_url' => '../inventory/stock_adjustment/adjustment-list.php',
    ],
    'stock_transfer' => [
        'label' => 'Stock Transfer', 'table' => 'stock_transfer', 'id_col' => 'id',
        'number_col' => 'transfer_code', 'amount_col' => 'total_amount', 'date_col' => 'date',
        'party_table' => null, 'party_col' => null, 'party_name_col' => null,
        'list_url' => '../inventory/stock_transfer/transfer-list.php',
    ],
    'inward_gatepass' => [
        'label' => 'Inward Gatepass', 'table' => 'inward_gatepass', 'id_col' => 'id',
        'number_col' => 'gatepass_code', 'amount_col' => null, 'date_col' => 'date',
        'party_table' => 'suppliers', 'party_col' => 'supplier_id', 'party_name_col' => 'supplier_name',
        'list_url' => '../inventory/inward_gatepass/gatepass-list.php',
    ],
    'outward_gatepass' => [
        'label' => 'Outward Gatepass', 'table' => 'outward_gatepass', 'id_col' => 'id',
        'number_col' => 'gatepass_code', 'amount_col' => null, 'date_col' => 'date',
        'party_table' => 'suppliers', 'party_col' => 'supplier_id', 'party_name_col' => 'supplier_name',
        'list_url' => '../inventory/outward_gatepass/gatepass-list.php',
    ],
    'receive_voucher' => [
        'label' => 'Receive Voucher', 'table' => 'receive_voucher', 'id_col' => 'id',
        'number_col' => 'voucher_number', 'amount_col' => 'amount', 'date_col' => 'voucher_date',
        'party_table' => 'customers', 'party_col' => 'customer_id', 'party_name_col' => 'customer_name',
        'list_url' => '../vouchers/receive_voucher/receive-list.php',
    ],
    'payment_voucher' => [
        'label' => 'Payment Voucher', 'table' => 'payment_voucher', 'id_col' => 'id',
        'number_col' => 'voucher_number', 'amount_col' => 'amount', 'date_col' => 'voucher_date',
        'party_table' => 'suppliers', 'party_col' => 'supplier_id', 'party_name_col' => 'supplier_name',
        'list_url' => '../vouchers/payment_voucher/payment-list.php',
    ],
    'transfer_voucher' => [
        'label' => 'Transfer Voucher', 'table' => 'transfer_voucher', 'id_col' => 'id',
        'number_col' => 'voucher_number', 'amount_col' => 'amount', 'date_col' => 'voucher_date',
        'party_table' => null, 'party_col' => null, 'party_name_col' => null,
        'list_url' => '../vouchers/transfer_voucher/transfer-list.php',
    ],
    'expense_voucher' => [
        'label' => 'Expense Voucher', 'table' => 'expense_voucher', 'id_col' => 'id',
        'number_col' => 'voucher_no', 'amount_col' => 'total_amount', 'date_col' => 'date',
        'party_table' => null, 'party_col' => null, 'party_name_col' => null,
        'list_url' => '../vouchers/expense_voucher/expense-list.php',
    ],
    'journal_voucher' => [
        'label' => 'Journal Voucher', 'table' => 'journal_voucher', 'id_col' => 'id',
        'number_col' => 'voucher_number', 'amount_col' => 'total_debit', 'date_col' => 'voucher_date',
        'party_table' => null, 'party_col' => null, 'party_name_col' => null,
        'list_url' => '../vouchers/journal_voucher/journal-list.php',
    ],
    'bank_account' => [
        'label' => 'Bank Account', 'table' => 'bank_accounts', 'id_col' => 'id',
        'number_col' => 'account_number', 'amount_col' => 'opening_balance', 'date_col' => 'as_of_date',
        'party_table' => null, 'party_col' => null, 'party_name_col' => null,
        'list_url' => '../banking/bank/bank-list.php',
    ],
    'pdc' => [
        'label' => 'Post Dated Cheque', 'table' => 'post_dated_cheques', 'id_col' => 'id',
        'number_col' => 'cheque_no', 'amount_col' => 'amount', 'date_col' => 'cheque_date',
        'party_table' => null, 'party_col' => null, 'party_name_col' => null,
        'list_url' => '../banking/post_dated_cheques/post-dated-cheques.php',
    ],

    // ---- New: Item / Product ----
    // Search matches product `code` (number_col) OR product `name` (extra_search_col).
    'item' => [
        'label' => 'Item', 'table' => 'products', 'id_col' => 'id',
        'number_col' => 'code', 'amount_col' => 'mrp', 'date_col' => 'created_at',
        'party_table' => null, 'party_col' => null, 'party_name_col' => null,
        'extra_search_col' => 'name',
        'list_url' => '../inventory/products/product-list.php',
    ],

    // ---- New: Employee ----
    // Search matches `employee_id` (number_col) OR `full_name` (extra_search_col).
    'employee' => [
        'label' => 'Employee', 'table' => 'employees', 'id_col' => 'id',
        'number_col' => 'employee_id', 'amount_col' => 'base_salary', 'date_col' => 'hire_date',
        'party_table' => null, 'party_col' => null, 'party_name_col' => null,
        'extra_search_col' => 'full_name',
        'list_url' => '../hrm/employees/employee-list.php',
    ],

    // ---- New: Payroll ----
    // Search matches `payroll_code`; amount = exact match on total_amount.
    'payroll' => [
        'label' => 'Payroll', 'table' => 'payroll_entries', 'id_col' => 'id',
        'number_col' => 'payroll_code', 'amount_col' => 'total_amount', 'date_col' => 'payroll_date',
        'party_table' => null, 'party_col' => null, 'party_name_col' => null,
        'list_url' => '../hrm/payroll/payroll-list.php',
    ],
];

$results = [];

foreach ($modules as $cfg) {
    $conditions = ["t.`{$cfg['number_col']}` LIKE ?"];
    $params = [$tenant_id, "%$q%"];

    // Extra self-table search column (e.g. product name, employee full name)
    if (!empty($cfg['extra_search_col'])) {
        $conditions[] = "t.`{$cfg['extra_search_col']}` LIKE ?";
        $params[] = "%$q%";
    }

    // Amount: EXACT match only — "600" won't match 6000 or 5600
    if ($cfg['amount_col'] && is_numeric($q)) {
        $conditions[] = "t.`{$cfg['amount_col']}` = ?";
        $params[] = $q;
    }

    // Party name search — "ali" matches Ali/Ali Raza, "ali raza" matches only Ali Raza
    if ($cfg['party_table']) {
        $conditions[] = "p.`{$cfg['party_name_col']}` LIKE ?";
        $params[] = "%$q%";
    }

    $whereExtra = '(' . implode(' OR ', $conditions) . ')';

    $select = "t.`{$cfg['id_col']}` AS id, t.`{$cfg['number_col']}` AS number_val";
    $select .= $cfg['amount_col'] ? ", t.`{$cfg['amount_col']}` AS amount_val" : ", NULL AS amount_val";
    $select .= $cfg['date_col'] ? ", t.`{$cfg['date_col']}` AS date_val" : ", NULL AS date_val";

    $join = '';
    if ($cfg['party_table']) {
        $join = "LEFT JOIN `{$cfg['party_table']}` p ON t.`{$cfg['party_col']}` = p.id";
        $select .= ", p.`{$cfg['party_name_col']}` AS party_name";
    } elseif (!empty($cfg['extra_search_col'])) {
        // Show the self-table name (product name / employee full name) as the "party" column
        $select .= ", t.`{$cfg['extra_search_col']}` AS party_name";
    } else {
        $select .= ", NULL AS party_name";
    }

    $sql = "SELECT $select FROM `{$cfg['table']}` t $join
            WHERE t.tenant_id = ? AND $whereExtra
            ORDER BY t.`{$cfg['id_col']}` DESC LIMIT 5";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $results[] = [
                'module' => $cfg['label'],
                'id' => $row['id'],
                'number' => $row['number_val'],
                'amount' => $row['amount_val'],
                'date' => $row['date_val'],
                'party' => $row['party_name'],
                'url' => $cfg['list_url'] . '?search=' . urlencode($row['number_val']),
            ];
        }
    } catch (PDOException $e) {
        continue;
    }
}

echo json_encode(['success' => true, 'data' => $results]);