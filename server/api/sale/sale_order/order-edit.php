<?php
require_once '../../../../includes/connection.php';
require_once '../../../../includes/activity-logger.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT');
header('Access-Control-Allow-Headers: Content-Type');

session_start();
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $invoice_id = $_GET['id'] ?? null;

    if (!$invoice_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Order ID is required']);
        exit;
    }

    try {
        // Get invoice data
        $stmt = $pdo->prepare("
            SELECT 
                si.*,
                c.customer_name,
                c.customer_code,
                c.address as customer_address,
                c.primary_phone as customer_phone,
                c.email as customer_email,
                b.branch_name,
                b.branch_type,
                pb.branch_name as parent_branch_name,
                cur.symbol as currency_symbol,
                cur.code as currency_code,
                cur.name as currency_name,
                e.full_name as sales_officer_name,
                sm.full_name as supplier_man_name,
                p.project_name, p.project_code AS proj_code, ci.city_name AS project_city,
                COALESCE(rv.amount, 0) as amount_paid,
                CASE 
                    WHEN rv.payment_method_id = 1 THEN 'Cash'
                    WHEN rv.payment_method_id = 2 THEN 'Bank Transfer'
                    ELSE NULL
                END as payment_method
            FROM sale_order si
            LEFT JOIN customers c ON si.customer_id = c.id
            LEFT JOIN branches b ON si.branch_id = b.id
            LEFT JOIN branches pb ON b.parent_branch_id = pb.id
            LEFT JOIN ledgerone_public.currencies cur ON si.currency_id = cur.id
            LEFT JOIN employees e ON si.sale_officer_id = e.id
            LEFT JOIN employees sm ON si.supplier_man_id = sm.id
            LEFT JOIN projects p ON p.id = si.project_id
            LEFT JOIN cities ci ON ci.id = p.city_id
            LEFT JOIN receive_voucher rv ON si.bill_no = rv.bill_no AND si.tenant_id = rv.tenant_id
            WHERE si.id = ? AND si.tenant_id = ?
        ");
        $stmt->execute([$invoice_id, $tenant_id]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$invoice) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Order not found']);
            exit;
        }

        // Get invoice items grouped by product
        $itemStmt = $pdo->prepare("
            SELECT 
                sii.*,
                p.name as product_name,
                p.code as product_code,
                p.uom_type,
                p.uom_group_id,
                p.default_unit_id,
                u.uom_name
            FROM sale_order_items sii
            LEFT JOIN products p ON sii.product_id = p.id
            LEFT JOIN uom u ON sii.uom_id = u.id
            WHERE sii.sale_invoice_id = ? AND sii.tenant_id = ?
            ORDER BY sii.id
        ");
        $itemStmt->execute([$invoice_id, $tenant_id]);
        $rawItems = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

        // Group items by product_id
        $productGroups = [];
        foreach ($rawItems as $item) {
            $productGroups[$item['product_id']][] = $item;
        }

        $groupedItems = [];
        $itemIndex = 0;

        foreach ($productGroups as $productId => $items) {
            $uomMap = [];
            $firstItem = null;
            $totalGross = 0;
            $totalDiscount = 0;
            $totalTradeOffer = 0;
            $totalGst = 0;
            $totalFoc = 0;
            $totalNet = 0;

            foreach ($items as $item) {
                if (!$firstItem) $firstItem = $item;

                $uomId = $item['uom_id'];
                if (!isset($uomMap[$uomId])) {
                    $uomMap[$uomId] = [
                        'uom_id' => $uomId,
                        'uom_name' => $item['uom_name'],
                        'quantity' => 0
                    ];
                }
                $uomMap[$uomId]['quantity'] += floatval($item['quantity']);

                if (floatval($item['gross_amount']) > 0) {
                    $totalGross += floatval($item['gross_amount']);
                    $totalDiscount += floatval($item['discount_amount']);
                    $totalTradeOffer += floatval($item['trade_offer_amount']);
                    $totalGst += floatval($item['gst_amount']);
                    $totalFoc += floatval($item['foc_quantity']);
                    $totalNet += floatval($item['net_amount']);
                }
            }

            $itemIndex++;
            $groupedItems[$itemIndex] = [
                'product_id' => $productId,
                'product_name' => $firstItem['product_name'],
                'product_code' => $firstItem['product_code'],
                'uom_type' => $firstItem['uom_type'],
                'uom_group_id' => $firstItem['uom_group_id'],
                'default_unit_id' => $firstItem['default_unit_id'],
                'sale_price' => $firstItem['sale_price'],
                'scheme' => $firstItem['scheme'] ?? 'sale_on_tp',
                'gross_amount' => $totalGross,
                'discount_percent' => $firstItem['discount_percent'],
                'discount_amount' => $totalDiscount,
                'trade_offer_percent' => $firstItem['trade_offer_percent'],
                'trade_offer_amount' => $totalTradeOffer,
                'gst_percent' => $firstItem['gst_percent'],
                'gst_amount' => $totalGst,
                'foc_quantity' => $totalFoc,
                'net_amount' => $totalNet,
                'unit_entries' => array_values($uomMap)
            ];
        }

        $items = array_values($groupedItems);

        echo json_encode([
            'success' => true,
            'invoice' => $invoice,
            'items' => $items
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    $invoice_id = $input['invoice_id'] ?? null;

    if (!$invoice_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Order ID is required']);
        exit;
    }

    try {
        // Grab old values before anything changes, for the Activity Log diff
        $stmt = $pdo->prepare("SELECT bill_no, company_id, currency_id, sale_date, customer_id, branch_id, previous_balance, sale_officer_id, supplier_man_id, project_id, bilty_no, transport_name, total_bill, total_discount_percent, total_discount_amount, net_amount, remarks, status FROM sale_order WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$invoice_id, $tenant_id]);
        $oldOrder = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$oldOrder) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Order not found']);
            exit;
        }
        $stmt = $pdo->prepare("SELECT product_id, uom_id, quantity, sale_price, discount_percent, gst_percent FROM sale_order_items WHERE sale_invoice_id = ? AND tenant_id = ? ORDER BY product_id, uom_id, id");
        $stmt->execute([$invoice_id, $tenant_id]);
        $oldItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $pdo->beginTransaction();

        // Update sale invoice
        $stmt = $pdo->prepare("
            UPDATE sale_order SET
                company_id = ?, currency_id = ?, sale_date = ?, customer_id = ?, branch_id = ?,
                previous_balance = ?, sale_officer_id = ?, supplier_man_id = ?, project_id = ?, bilty_no = ?, transport_name = ?, total_bill = ?, total_discount_percent = ?,
                total_discount_amount = ?, net_amount = ?, remarks = ?, status = ?, updated_by = ?
            WHERE id = ? AND tenant_id = ?
        ");
        $stmt->execute([
            $input['companyId'] ?? null,
            $input['currencyId'],
            $input['saleDate'],
            $input['customerId'],
            $input['branchId'],
            extractBalanceAmount($input['previousBalance'] ?? '0.00'),
            $input['salesOfficerId'] ?? null,
            $input['supplierManId'] ?? null,
            !empty($input['projectId']) ? (int)$input['projectId'] : null,
            $input['biltyNo'] ?? null,
            $input['transportName'] ?? null,
            $input['totalBill'],
            $input['totalDiscountPercent'] ?? 0.00,
            $input['totalDiscountAmount'] ?? 0.00,
            $input['netAmount'],
            $input['remarks'] ?? null,
            $input['status'] ?? 'Posted',
            $user_id,
            $invoice_id,
            $tenant_id
        ]);

        // Delete existing items
        $pdo->prepare("DELETE FROM sale_order_items WHERE sale_invoice_id = ? AND tenant_id = ?")->execute([$invoice_id, $tenant_id]);

        // Insert new items with dynamic UOM support
        $item_stmt = $pdo->prepare("
            INSERT INTO sale_order_items (
                tenant_id, sale_invoice_id, product_id, uom_id,
                quantity, sale_price, gross_amount, discount_percent,
                discount_amount, trade_offer_percent, trade_offer_amount,
                gst_percent, gst_amount, foc_quantity, scheme, net_amount, created_by, updated_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($input['items'] as $item) {
            $isFirstEntry = true;
            foreach ($item['unitEntries'] as $unitEntry) {
                $item_stmt->execute([
                    $tenant_id,
                    $invoice_id,
                    $item['productId'],
                    $unitEntry['uomId'],
                    $unitEntry['quantity'],
                    $item['salePrice'],
                    $isFirstEntry ? $item['grossAmount'] : 0,
                    $item['discountPercent'] ?? 0.00,
                    $isFirstEntry ? $item['discountAmount'] : 0,
                    $item['tradeOfferPercent'] ?? 0.00,
                    $isFirstEntry ? $item['tradeOfferAmount'] : 0,
                    $item['gstPercent'] ?? 0.00,
                    $isFirstEntry ? $item['gstAmount'] : 0,
                    $item['focQty'] ?? 0,
                    $item['scheme'] ?? 'sale_on_tp',
                    $isFirstEntry ? $item['netAmount'] : 0,
                    $user_id,
                    $user_id
                ]);
                $isFirstEntry = false;
            }
        }

        $newOrder = [
            'bill_no' => $oldOrder['bill_no'],
            'company_id' => $input['companyId'] ?? null,
            'currency_id' => $input['currencyId'],
            'sale_date' => $input['saleDate'],
            'customer_id' => $input['customerId'],
            'branch_id' => $input['branchId'],
            'previous_balance' => extractBalanceAmount($input['previousBalance'] ?? '0.00'),
            'sale_officer_id' => $input['salesOfficerId'] ?? null,
            'supplier_man_id' => $input['supplierManId'] ?? null,
            'project_id' => !empty($input['projectId']) ? (int)$input['projectId'] : null,
            'bilty_no' => $input['biltyNo'] ?? null,
            'transport_name' => $input['transportName'] ?? null,
            'total_bill' => $input['totalBill'],
            'total_discount_percent' => $input['totalDiscountPercent'] ?? 0,
            'total_discount_amount' => $input['totalDiscountAmount'] ?? 0,
            'net_amount' => $input['netAmount'],
            'remarks' => $input['remarks'] ?? null,
            'status' => $input['status'] ?? 'Posted'
        ];
        $changeDescriptions = buildChangeDescription($oldOrder, $newOrder, [
            'sale_date' => 'Sale Date', 'customer_id' => 'Customer', 'branch_id' => 'Branch',
            'sale_officer_id' => 'Sales Officer', 'supplier_man_id' => 'Supplier Man', 'project_id' => 'Project',
            'bilty_no' => 'Bilty No', 'transport_name' => 'Transport', 'total_bill' => 'Total Bill',
            'total_discount_percent' => 'Discount %', 'total_discount_amount' => 'Discount Amount',
            'net_amount' => 'Net Amount', 'remarks' => 'Remarks', 'status' => 'Status'
        ]);
        // Flatten the nested (product -> unitEntries) submitted items into the same flat
        // shape as sale_order_items rows, for an accurate before/after comparison.
        $newItemsFlat = [];
        foreach ($input['items'] as $item) {
            foreach ($item['unitEntries'] as $unitEntry) {
                $newItemsFlat[] = [
                    'product_id' => $item['productId'], 'uom_id' => $unitEntry['uomId'], 'quantity' => $unitEntry['quantity'],
                    'sale_price' => $item['salePrice'], 'discount_percent' => $item['discountPercent'] ?? 0, 'gst_percent' => $item['gstPercent'] ?? 0
                ];
            }
        }
        if (itemsChanged($oldItems, $newItemsFlat, ['product_id', 'uom_id', 'quantity', 'sale_price', 'discount_percent', 'gst_percent'])) {
            $changeDescriptions[] = 'Items updated';
        }
        $editDescription = !empty($changeDescriptions)
            ? "Updated sale order \"{$oldOrder['bill_no']}\": " . implode('; ', $changeDescriptions)
            : "Updated sale order \"{$oldOrder['bill_no']}\" - no tracked field changes";
        logActivity($pdo, $tenant_id, $user_id, 'update', 'Sale Order', $invoice_id, $oldOrder['bill_no'], $editDescription, $oldOrder, $newOrder);

        $pdo->commit();

        echo json_encode(['success' => true, 'message' => 'Sale order updated successfully']);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// Function to extract numeric amount from balance string
function extractBalanceAmount($balanceString)
{
    if (empty($balanceString))
        return 0.00;
    $amount = preg_replace('/^(Dr|Cr)\s*/', '', $balanceString);
    return floatval($amount);
}

// Function to convert quantity to pieces based on UOM
function convertToPieces($quantity, $uomId, $pdo, $productId)
{
    $qty = floatval($quantity);
    $uomId = intval($uomId);

    // Piece (id: 9) - base unit
    if ($uomId === 9) {
        return $qty;
    }

    // Dozen (id: 10) - 12 pieces
    if ($uomId === 10) {
        return $qty * 12;
    }

    // Carton (id: 16) - get from product's carton_conversion
    if ($uomId === 16) {
        $stmt = $pdo->prepare("SELECT carton_conversion FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $cartonConversion = $stmt->fetchColumn();
        return $qty * intval($cartonConversion ?: 1);
    }

    // Other units - return as is
    return $qty;
}