<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once '../../../../includes/connection.php';

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    header('Location: ../../auth/login.html');
    exit();
}

// Get filter parameters
$search = $_GET['search'] ?? '';
$department = $_GET['department'] ?? '';
$position = $_GET['position'] ?? '';
$status = $_GET['status'] ?? '';

// Build query with filters
$sql = "SELECT e.employee_id, e.full_name, e.email, e.phone_number, 
           e.current_status, e.hire_date, e.employment_type,
           d.department_name, p.position_title
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.id
    LEFT JOIN positions p ON e.position_id = p.id
    WHERE e.tenant_id = ? AND e.deleted_at IS NULL";

$params = [$tenant_id];

if ($search) {
    $sql .= " AND (e.full_name LIKE ? OR e.employee_id LIKE ? OR d.department_name LIKE ? OR p.position_title LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

if ($department) {
    $sql .= " AND d.department_name = ?";
    $params[] = $department;
}

if ($position) {
    $sql .= " AND p.position_title = ?";
    $params[] = $position;
}

if ($status) {
    $sql .= " AND e.current_status = ?";
    $params[] = $status;
}

$sql .= " ORDER BY e.full_name";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$employees = $stmt->fetchAll();

// Get company info
$stmt = $pdo->prepare("SELECT company_name, phone, email, address, city, state, country, logo_url FROM companies WHERE tenant_id = ? AND is_active = 1 LIMIT 1");
$stmt->execute([$tenant_id]);
$tenant = $stmt->fetch();

// Get user info
$stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee List - Print</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            color: #000;
        }
        
        .print-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #000;
            padding-bottom: 15px;
        }
        
        .print-header h1 {
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .company-logo {
            width: 80px;
            height: 80px;
            object-fit: contain;
            margin: 0 auto 10px;
        }
        
        .print-header h2 {
            font-size: 18px;
            font-weight: normal;
            margin-bottom: 10px;
        }
        
        .print-info {
            font-size: 12px;
            color: #666;
            margin-top: 10px;
        }
        
        .company-info {
            font-size: 11px;
            color: #666;
            margin-top: 5px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        th, td {
            border: 1px solid #000;
            padding: 4px 6px;
            text-align: left;
            font-size: 10px;
        }
        
        th {
            background-color: #f0f0f0;
            font-weight: bold;
        }
        
        .status-active { color: #2fbf71; font-weight: bold; }
        .status-inactive { color: #6b7280; }
        .status-on-leave { color: #e8b23f; font-weight: bold; }
        .status-suspended { color: #e34f4f; font-weight: bold; }
        .status-terminated { color: #6b7280; text-decoration: line-through; }
        
        .print-footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #666;
        }
        
        @media print {
            body {
                padding: 0;
            }
            
            .no-print {
                display: none;
            }
            
            @page {
                margin: 1cm;
            }
            
            th, td {
                padding: 3px 4px;
                font-size: 9px;
            }
        }
        
        .print-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background: #1f7bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .print-btn:hover {
            background: #1a6cdc;
        }
    </style>
</head>
<body>
    <button class="print-btn no-print" onclick="window.print()">
        <i class="fas fa-print"></i> Print
    </button>
    
    <div class="print-header">
        <?php if ($tenant['logo_url']): ?>
            <img src="../../../assets/uploads/company_logo/<?php echo htmlspecialchars($tenant['logo_url']); ?>" alt="Company Logo" class="company-logo">
        <?php endif; ?>
        <h1><?php echo htmlspecialchars($tenant['company_name'] ?? 'LedgerOne ERP'); ?></h1>
        <div class="company-info">
            <?php if ($tenant['phone']): ?>Phone: <?php echo htmlspecialchars($tenant['phone']); ?> | <?php endif; ?>
            <?php if ($tenant['email']): ?>Email: <?php echo htmlspecialchars($tenant['email']); ?><?php endif; ?>
            <?php if ($tenant['address']): ?><br>Address: <?php echo htmlspecialchars($tenant['address']); ?><?php if ($tenant['city']): ?>, <?php echo htmlspecialchars($tenant['city']); ?><?php endif; ?><?php if ($tenant['state']): ?>, <?php echo htmlspecialchars($tenant['state']); ?><?php endif; ?><?php if ($tenant['country']): ?>, <?php echo htmlspecialchars($tenant['country']); ?><?php endif; ?><?php endif; ?>
        </div>
        <div class="print-info">
            Generated on: <?php echo date('F d, Y h:i A'); ?> | 
            Generated by: <?php echo htmlspecialchars($user['full_name'] ?? 'Admin'); ?> | 
            Total Employees: <?php echo count($employees); ?>
        </div>
        <h2>Employee List Report</h2>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Employee ID</th>
                <th>Full Name</th>
                <th>Department</th>
                <th>Position</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Status</th>
                <th>Hire Date</th>
            </tr>
        </thead>
        <tbody>
            <?php $counter = 1; ?>
            <?php foreach ($employees as $emp): ?>
            <tr>
                <td><?php echo $counter++; ?></td>
                <td><?php echo htmlspecialchars($emp['employee_id']); ?></td>
                <td><?php echo htmlspecialchars($emp['full_name']); ?></td>
                <td><?php echo htmlspecialchars($emp['department_name'] ?? 'N/A'); ?></td>
                <td><?php echo htmlspecialchars($emp['position_title'] ?? 'N/A'); ?></td>
                <td><?php echo htmlspecialchars($emp['email'] ?? 'N/A'); ?></td>
                <td><?php echo htmlspecialchars($emp['phone_number'] ?? 'N/A'); ?></td>
                <td class="status-<?php echo $emp['current_status']; ?>">
                    <?php echo ucwords(str_replace('-', ' ', $emp['current_status'])); ?>
                </td>
                <td><?php echo date('M d, Y', strtotime($emp['hire_date'])); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <div class="print-footer">
        <p>This is a computer-generated document. No signature is required.</p>
    </div>
</body>
</html>
