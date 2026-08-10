<?php
require_once '../../../../includes/connection.php';
header('Content-Type: application/json');
session_start();
$user_id   = $_SESSION['user_id']   ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$user_id || !$tenant_id) { http_response_code(401); echo json_encode(['success'=>false,'message'=>'Unauthorized']); exit; }

try {
    $page   = max(1, (int)($_GET['page']  ?? 1));
    $limit  = max(1, (int)($_GET['limit'] ?? 10));
    $offset = ($page - 1) * $limit;

    $where  = ["dc.tenant_id = ?"];
    $params = [$tenant_id];

    if (!empty($_GET['dateFrom'])) { $where[] = "dc.delivery_date >= ?"; $params[] = $_GET['dateFrom']; }
    if (!empty($_GET['dateTo']))   { $where[] = "dc.delivery_date <= ?"; $params[] = $_GET['dateTo']; }
    if (!empty($_GET['customer'])) { $where[] = "c.customer_name = ?";  $params[] = $_GET['customer']; }
    if (!empty($_GET['search']))   {
        $where[] = "(dc.chalan_no LIKE ? OR c.customer_name LIKE ?)";
        $s = "%{$_GET['search']}%";
        $params[] = $s;
        $params[] = $s;
    }

    $wClause = implode(' AND ', $where);

    $countStmt = $pdo->prepare("SELECT COUNT(DISTINCT dc.id) FROM delivery_chalan dc LEFT JOIN customers c ON dc.customer_id = c.id WHERE $wClause");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $dataStmt = $pdo->prepare("
        SELECT dc.id,
               dc.chalan_no  AS bill_no,
               dc.delivery_date AS sale_date,
               dc.status,
               c.customer_name,
               COUNT(dci.id) AS item_count,
               0             AS net_amount,
               ''            AS currency_symbol
        FROM delivery_chalan dc
        LEFT JOIN customers c ON dc.customer_id = c.id
        LEFT JOIN delivery_chalan_items dci ON dc.id = dci.delivery_chalan_id
        WHERE $wClause
        GROUP BY dc.id
        ORDER BY dc.id DESC
        LIMIT $limit OFFSET $offset
    ");
    $dataStmt->execute($params);
    $invoices = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success'    => true,
        'invoices'   => $invoices,
        'pagination' => [
            'page'  => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => max(1, ceil($total / $limit))
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
