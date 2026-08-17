<?php
require_once '../../../../includes/connection.php';

session_start();
$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    header('Location: ../../auth/login.html');
    exit();
}

// Get filters from URL
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$type = $_GET['type'] ?? '';
$status = $_GET['status'] ?? '';

// Fetch products with same logic as product-list API
$sql = "
    SELECT p.id, p.code, p.name, p.product_type, p.mrp, p.is_active,
           c.category_name, sc.subcategory_name,
           COALESCE(SUM(sl.qty_in) - SUM(sl.qty_out), 0) as current_stock,
           CASE WHEN COUNT(sl.id) = 0 THEN 1 ELSE 0 END as no_stock_records,
           curr.symbol as currency_symbol
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN subcategories sc ON p.subcategory_id = sc.id
    LEFT JOIN stock_ledger sl ON p.id = sl.product_id AND sl.tenant_id = p.tenant_id
    LEFT JOIN tenant_currencies tc ON p.tenant_id = tc.tenant_id AND tc.is_base_currency = 1
    LEFT JOIN ledgerone_public.currencies curr ON tc.currency_id = curr.id
    WHERE p.tenant_id = ?
";

$params = [$tenant_id];

if ($search) {
    $sql .= " AND (p.name LIKE ? OR p.code LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category) {
    $sql .= " AND p.category_id = ?";
    $params[] = $category;
}

if ($type) {
    $sql .= " AND p.product_type = ?";
    $params[] = $type;
}

if ($status) {
    $active = ($status === 'active') ? 1 : 0;
    $sql .= " AND p.is_active = ?";
    $params[] = $active;
}

$sql .= " GROUP BY p.id ORDER BY p.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products List - Print View</title>
    <style>
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
        
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            color: #333;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }
        
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: bold;
        }
        
        .header p {
            margin: 5px 0;
            color: #666;
        }
        
        .filters {
            margin-bottom: 20px;
            font-size: 12px;
            color: #666;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
            font-size: 12px;
        }
        
        th {
            background-color: #f5f5f5;
            font-weight: bold;
        }
        
        .status-active { color: #28a745; }
        .status-inactive { color: #dc3545; }
        .type-product { color: #007bff; }
        .type-service { color: #6c757d; }
        
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
        
        .print-btn {
            margin-bottom: 20px;
            padding: 10px 20px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <button class="print-btn no-print" onclick="window.print()">Print</button>
    
    <div class="header">
        <h1>Products List</h1>
        <p>LedgerOne ERP - Inventory Management</p>
        <p>Generated on: <?php echo date('F j, Y, g:i a'); ?></p>
    </div>
    
    <?php if ($search || $category || $type || $status): ?>
    <div class="filters">
        <strong>Applied Filters:</strong>
        <?php if ($search): ?>Search: "<?php echo htmlspecialchars($search); ?>" <?php endif; ?>
        <?php if ($category): ?>Category: <?php echo htmlspecialchars($category); ?> <?php endif; ?>
        <?php if ($type): ?>Type: <?php echo ucfirst($type); ?> <?php endif; ?>
        <?php if ($status): ?>Status: <?php echo ucfirst($status); ?> <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <table>
        <thead>
            <tr>
                <th>Code</th>
                <th>Name</th>
                <th>Type</th>
                <th>Category</th>
                <th>Current Stock</th>
                <th>Price</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($products as $product): ?>
            <tr>
                <td><?php echo htmlspecialchars($product['code']); ?></td>
                <td>
                    <?php echo htmlspecialchars($product['name']); ?>
                    <?php if ($product['subcategory_name']): ?>
                        <br><small><?php echo htmlspecialchars($product['subcategory_name']); ?></small>
                    <?php endif; ?>
                </td>
                <td class="type-<?php echo $product['product_type']; ?>">
                    <?php echo ucfirst($product['product_type']); ?>
                </td>
                <td><?php echo htmlspecialchars($product['category_name'] ?: 'Uncategorized'); ?></td>
                <td>
                    <?php 
                    if ($product['product_type'] === 'service' || $product['no_stock_records']) {
                        echo 'N/A';
                    } else {
                        echo number_format($product['current_stock'], 2);
                    }
                    ?>
                </td>
                <td>
                    <?php 
                    if ($product['mrp'] > 0) {
                        echo ($product['currency_symbol'] ?: '₹') . number_format($product['mrp'], 2);
                    } else {
                        echo 'Free';
                    }
                    ?>
                </td>
                <td class="status-<?php echo $product['is_active'] ? 'active' : 'inactive'; ?>">
                    <?php echo $product['is_active'] ? 'Active' : 'Inactive'; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <div class="footer">
        <p>Total Products: <?php echo count($products); ?></p>
        <p>Printed by: <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></p>
    </div>
</body>
</html>