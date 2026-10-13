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

$employee_id = $_GET['id'] ?? null;

if (!$employee_id) {
    header('Location: employee-list.php');
    exit();
}

// Fetch employee data
$stmt = $pdo->prepare("
    SELECT e.*, d.department_name, p.position_title 
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.id
    LEFT JOIN positions p ON e.position_id = p.id
    WHERE e.id = ? AND e.tenant_id = ? AND e.deleted_at IS NULL
");
$stmt->execute([$employee_id, $tenant_id]);
$employee = $stmt->fetch();

if (!$employee) {
    header('Location: employee-list.php');
    exit();
}

// Get company info
$stmt = $pdo->prepare("SELECT company_name, phone, email, address, city, state, country, website, logo_url FROM companies WHERE tenant_id = ? AND is_active = 1 LIMIT 1");
$stmt->execute([$tenant_id]);
$tenant = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Card - <?php echo htmlspecialchars($employee['full_name']); ?></title>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            background: #f0f0f0;
            padding: 40px 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        
        .card-container {
            display: flex;
            gap: 40px;
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .id-card {
            width: 350px;
            height: 550px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
            position: relative;
        }
        
        .card-front .card-header {
            background: linear-gradient(135deg, #1f7bff 0%, #1a6cdc 100%);
            padding: 20px 15px;
            text-align: center;
            color: white;
            position: relative;
        }
        
        .card-front .company-logo {
            width: 50px;
            height: 50px;
            object-fit: contain;
            position: absolute;
            top: 10px;
            left: 15px;
            background: white;
            border-radius: 8px;
            padding: 5px;
        }
        
        .card-front .company-name {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 3px;
        }
        
        .card-front .card-title {
            font-size: 10px;
            opacity: 0.9;
        }
        
        .card-front .photo-section {
            text-align: center;
            padding: 20px 15px 15px;
        }
        
        .card-front .employee-photo {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            border: 4px solid #1f7bff;
            object-fit: cover;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            font-size: 40px;
            color: #6b7280;
        }
        
        .card-front .employee-name {
            font-size: 18px;
            font-weight: bold;
            color: #0e1a2b;
            margin-top: 10px;
        }
        
        .card-front .employee-position {
            font-size: 12px;
            color: #6b7280;
            margin-top: 3px;
        }
        
        .card-front .employee-id {
            font-size: 14px;
            color: #1f7bff;
            font-weight: bold;
            margin-top: 8px;
        }
        
        .card-front .info-section {
            padding: 15px;
        }
        
        .card-front .info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e1e6ee;
        }
        
        .card-front .info-label {
            font-size: 11px;
            color: #6b7280;
        }
        
        .card-front .info-value {
            font-size: 11px;
            color: #0e1a2b;
            font-weight: 500;
        }
        
        .card-back {
            background: linear-gradient(135deg, #2f3b4c 0%, #1a2332 100%);
            color: white;
            padding: 20px 15px;
        }
        
        .card-back .back-header {
            text-align: center;
            margin-bottom: 15px;
        }
        
        .card-back .back-title {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 8px;
        }
        
        .card-back .barcode-section {
            background: white;
            padding: 12px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 15px;
        }
        
        .card-back .barcode-section svg {
            width: 100%;
            height: 50px;
        }
        
        .card-back .barcode-text {
            color: #0e1a2b;
            font-size: 12px;
            font-weight: bold;
            margin-top: 8px;
        }
        
        .card-back .emergency-section {
            background: rgba(255,255,255,0.1);
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 12px;
        }
        
        .card-back .section-title {
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 6px;
            color: #e8b23f;
        }
        
        .card-back .contact-info {
            font-size: 10px;
            line-height: 1.4;
        }
        
        .card-back .footer-text {
            text-align: center;
            font-size: 9px;
            opacity: 0.7;
            margin-top: 15px;
        }
        
        .controls {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .controls label {
            display: block;
            font-size: 12px;
            margin-bottom: 5px;
            color: #2f3b4c;
        }
        
        .controls input {
            width: 140px;
            padding: 6px;
            margin-bottom: 10px;
            border: 1px solid #d6dbe4;
            border-radius: 4px;
            font-size: 13px;
        }
        
        .print-btn {
            width: 100%;
            padding: 10px;
            background: #1f7bff;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }
        
        .print-btn:hover {
            background: #1a6cdc;
        }
        
        @media print {
            body {
                background: white;
                padding: 0;
            }
            
            .controls {
                display: none;
            }
            
            .card-container {
                gap: 20px;
                page-break-after: always;
            }
            
            .id-card {
                box-shadow: none;
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="controls">
        <label>Valid From:</label>
        <input type="date" id="cardValidFrom" value="<?php echo $employee['hire_date']; ?>" onchange="updateCardDisplay()">
        
        <label>Valid Till:</label>
        <input type="date" id="cardValidTill" value="" onchange="updateCardDisplay()">
        
        <button class="print-btn" onclick="window.print()">
            <i class="fas fa-print"></i> Print Card
        </button>
    </div>
    
    <div class="card-container">
        <!-- Front Side -->
        <div class="id-card card-front">
            <div class="card-header">
                <?php if ($tenant['logo_url']): ?>
                    <img src="../../../assets/uploads/company_logo/<?php echo htmlspecialchars($tenant['logo_url']); ?>" alt="Company Logo" class="company-logo">
                <?php endif; ?>
                <div class="company-name"><?php echo htmlspecialchars($tenant['company_name'] ?? 'LedgerOne ERP'); ?></div>
                <div class="card-title">EMPLOYEE IDENTIFICATION CARD</div>
            </div>
            
            <div class="photo-section">
                <?php if ($employee['profile_photo_url']): ?>
                    <img src="<?php echo htmlspecialchars($employee['profile_photo_url']); ?>" alt="Employee Photo" class="employee-photo">
                <?php else: ?>
                    <div class="employee-photo">👤</div>
                <?php endif; ?>
                
                <div class="employee-name"><?php echo htmlspecialchars($employee['full_name']); ?></div>
                <div class="employee-position"><?php echo htmlspecialchars($employee['position_title'] ?? 'N/A'); ?></div>
                <div class="employee-id"><?php echo htmlspecialchars($employee['employee_id']); ?></div>
            </div>
            
            <div class="info-section">
                <div class="info-row">
                    <span class="info-label">Department:</span>
                    <span class="info-value"><?php echo htmlspecialchars($employee['department_name'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email:</span>
                    <span class="info-value"><?php echo htmlspecialchars($employee['email'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Phone:</span>
                    <span class="info-value"><?php echo htmlspecialchars($employee['phone_number'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Hire Date:</span>
                    <span class="info-value"><?php echo date('M d, Y', strtotime($employee['hire_date'])); ?></span>
                </div>
            </div>
        </div>
        
        <!-- Back Side -->
        <div class="id-card card-back">
            <div class="back-header">
                <div class="back-title">EMPLOYEE CARD</div>
            </div>
            
            <div class="barcode-section">
                <svg id="barcode"></svg>
                <div class="barcode-text"><?php echo htmlspecialchars($employee['employee_id']); ?></div>
            </div>
            
            <div class="emergency-section">
                <div class="section-title">Emergency Contact</div>
                <div class="contact-info">
                    <?php if ($employee['emergency_contact_name']): ?>
                        <div><strong>Name:</strong> <?php echo htmlspecialchars($employee['emergency_contact_name']); ?></div>
                    <?php else: ?>
                        <div>No emergency contact provided</div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="emergency-section">
                <div class="section-title">Company Contact</div>
                <div class="contact-info">
                    <?php if ($tenant['phone']): ?>
                        <div><strong>Phone:</strong> <?php echo htmlspecialchars($tenant['phone']); ?></div>
                    <?php endif; ?>
                    <?php if ($tenant['email']): ?>
                        <div><strong>Email:</strong> <?php echo htmlspecialchars($tenant['email']); ?></div>
                    <?php endif; ?>
                    <?php if ($tenant['address']): ?>
                        <div><strong>Address:</strong> <?php echo htmlspecialchars($tenant['address']); ?></div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="emergency-section">
                <div class="section-title">Important Notice</div>
                <div class="contact-info">
                    This card is property of <?php echo htmlspecialchars($tenant['company_name'] ?? 'LedgerOne ERP'); ?>. 
                    If found, please return to HR Department.
                </div>
            </div>
            
            <div class="footer-text">
                Valid from: <span id="displayValidFrom"><?php echo date('d M Y', strtotime($employee['hire_date'])); ?></span> | 
                Valid till: <span id="displayValidTill">Permanent</span><br>
                Card issued: <?php echo date('M d, Y'); ?>
            </div>
        </div>
    </div>
    
    <script>
        // Generate barcode
        JsBarcode("#barcode", "<?php echo $employee['employee_id']; ?>", {
            format: "CODE128",
            width: 2,
            height: 60,
            displayValue: false,
            background: "transparent",
            lineColor: "#0E1A2B"
        });
        
        // Update card display in real-time
        function updateCardDisplay() {
            const validFrom = document.getElementById('cardValidFrom').value;
            const validTill = document.getElementById('cardValidTill').value;
            
            if (validFrom) {
                const fromDate = new Date(validFrom);
                document.getElementById('displayValidFrom').textContent = fromDate.toLocaleDateString('en-US', { day: 'numeric', month: 'short', year: 'numeric' });
            }
            
            if (validTill) {
                const tillDate = new Date(validTill);
                document.getElementById('displayValidTill').textContent = tillDate.toLocaleDateString('en-US', { day: 'numeric', month: 'short', year: 'numeric' });
            } else {
                document.getElementById('displayValidTill').textContent = 'Permanent';
            }
            
        }
    </script>
</body>
</html>
