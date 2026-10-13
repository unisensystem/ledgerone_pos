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
$status = $_GET['status'] ?? '';
$type = $_GET['type'] ?? '';

// Build query with filters
$sql = "SELECT branch_code, branch_name, branch_type, city, state, phone, email, is_active FROM branches WHERE tenant_id = ?";
$params = [$tenant_id];

if ($search) {
    $sql .= " AND (branch_code LIKE ? OR branch_name LIKE ? OR city LIKE ? OR email LIKE ?)";
    $searchParam = "%$search%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
}

if ($status) {
    $sql .= " AND is_active = ?";
    $params[] = ($status === 'active') ? 1 : 0;
}

if ($type) {
    $sql .= " AND branch_type = ?";
    $params[] = $type;
}

$sql .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

$typeMap = [
    'head_office' => 'Head Office',
    'regional_office' => 'Regional Office',
    'branch_office' => 'Branch Office',
    'warehouse' => 'Warehouse',
    'distribution_center' => 'Distribution Center',
    'retail_store' => 'Retail Store',
    'service_center' => 'Service Center',
    'manufacturing_unit' => 'Manufacturing Unit'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Branches List - Print</title>
    <style>
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
        
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 20px;
            color: #333;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: bold;
        }
        
        .header p {
            margin: 5px 0 0 0;
            color: #666;
        }
        
        .print-info {
            margin-bottom: 20px;
            font-size: 11px;
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
        }
        
        th {
            background-color: #f5f5f5;
            font-weight: bold;
        }
        
        .status-active {
            color: #2fbf71;
            font-weight: bold;
        }
        
        .status-inactive {
            color: #e34f4f;
            font-weight: bold;
        }
        
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
        
        .no-print {
            margin-bottom: 20px;
        }
        
        .btn {
            padding: 10px 20px;
            background-color: #1f7bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin-right: 10px;
        }
        
        .btn:hover {
            background-color: #1a6cdc;
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="btn" onclick="window.print()">Print</button>
        <button class="btn" onclick="window.close()">Close</button>
    </div>
    
    <div class="header">
        <h1>LedgerOne ERP - Branches List</h1>
        <p>Generated on <?php echo date('F j, Y \a\t g:i A'); ?></p>
    </div>
    
    <div class="print-info">
        <strong>Total Branches:</strong> <?php echo count($branches); ?>
        <?php if ($search || $status || $type): ?>
            <br><strong>Filters Applied:</strong>
            <?php if ($search): ?> Search: "<?php echo htmlspecialchars($search); ?>" <?php endif; ?>
            <?php if ($status): ?> Status: <?php echo ucfirst($status); ?> <?php endif; ?>
            <?php if ($type): ?> Type: <?php echo $typeMap[$type] ?? $type; ?> <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Branch Code</th>
                <th>Branch Name</th>
                <th>Type</th>
                <th>Location</th>
                <th>Phone</th>
                <th>Email</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($branches as $branch): ?>
                <tr>
                    <td><?php echo htmlspecialchars($branch['branch_code']); ?></td>
                    <td><?php echo htmlspecialchars($branch['branch_name']); ?></td>
                    <td><?php echo $typeMap[$branch['branch_type']] ?? $branch['branch_type']; ?></td>
                    <td><?php echo htmlspecialchars($branch['city'] . ', ' . $branch['state']); ?></td>
                    <td><?php echo htmlspecialchars($branch['phone'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($branch['email'] ?? 'N/A'); ?></td>
                    <td class="<?php echo $branch['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                        <?php echo $branch['is_active'] ? 'Active' : 'Inactive'; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <div class="footer">
        <p>This report was generated by LedgerOne ERP System</p>
        <p>Page 1 of 1</p>
    </div>
</body>
</html>