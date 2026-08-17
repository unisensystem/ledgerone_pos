<?php
require_once'../../server/config/config.php';

// Get employee details
if (!isset($_GET['id'])) {
    die('Employee ID not provided');
}

try {
    $stmt = $pdo->prepare("SELECT e.*, d.dept_name as department_name FROM employees e 
                           LEFT JOIN departments d ON e.department_id = d.id 
                           WHERE e.id = ?");
    $stmt->execute([$_GET['id']]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$employee) {
        die('Employee not found');
    }

    // Get company details
    $stmt = $pdo->prepare("SELECT * FROM company_settings LIMIT 1");
    $stmt->execute();
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Calculate default dates
$defaultIssueDate = date('Y-m-d');
$defaultExpiryDate = date('Y-m-d', strtotime('+2 years'));

// Get custom dates if provided
$issueDate = $_GET['issue_date'] ?? $defaultIssueDate;
$expiryDate = $_GET['expiry_date'] ?? $defaultExpiryDate;

// Handle image paths
$companyLogoPath = !empty($company['company_logo']) ? '../../uploads/company/' . $company['company_logo'] : '../../assets/images/default-company-logo.png';
$employeePhotoPath = !empty($employee['photo_path']) ? '../../uploads/employees/' . $employee['photo_path'] : '../../assets/images/default-profile.png';
?>

<!DOCTYPE html>
<html>
<head>
    <title><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?> - ID Card</title>
    <style>
        /* Add these at the top of your style section */
        * {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            color-adjust: exact !important;
        }

        @page {
            size: 86mm 54mm;
            margin: 0;
        }
        
        body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f0f2f5;
        }

        .id-card {
            width: 86mm;
            height: 54mm;
            margin: 20px auto;
            background: white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            overflow: hidden;
            page-break-after: always;
            display: flex;
            flex-direction: column;
        }

        /* Front side styles */
        .card-front {
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .card-header {
            background: linear-gradient(to right, #001f3f, #003366);
            color: white;
            padding: 12px;
            text-align: center;
            position: relative;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .company-logo {
            position: absolute;
            left: 12px;
            width: 32px;
            height: 32px;
            object-fit: contain;
            background: white;
            padding: 2px;
            border-radius: 4px;
        }
        
        .company-name {
            font-size: 14px; /* Changed from 16px */
            font-weight: 600;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .employee-section {
            padding: 10px 15px;
            display: flex;
            gap: 15px;
            margin-top: -5px;
        }

        .photo-container {
            width: 100px;
            height: 100px;
            border: 2px solid #001f3f;
            border-radius: 5px;
            overflow: hidden;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .employee-photo {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .photo-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
            color: #6c757d;
            font-size: 14px;
            text-align: center;
            border: 2px dashed #dee2e6;
        }

        .photo-placeholder span {
            line-height: 1.3;
        }

        .employee-details {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            padding-top: 5px;
        }

        .employee-name {
            font-size: 16px;
            font-weight: 600;
            color: #001f3f;
            margin-bottom: 6px;
        }

        .detail-row {
            font-size: 12px;
            margin-bottom: 3px;
            color: #444;
            display: flex;
            gap: 5px;
            line-height: 1.3;
        }

        .detail-label {
            font-weight: 600;
            min-width: 80px;
        }

        .barcode-section {
            text-align: center;
            padding: 5px 8px;
            background: #f8f9fa;
            border-top: 1px solid #eee;
            margin-top: auto;
        }

        .barcode-section svg {
            max-width: 100%;
            height: 35px;
        }

        /* Back side styles */
        .card-back {
            padding: 0;
            background: white;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .back-header {
            background: linear-gradient(to right, #001f3f, #003366);
            color: white;
            padding: 12px;
            text-align: center;
            font-size: 14px;
            font-weight: 600;
            letter-spacing: 0.5px;
            margin-bottom: 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .back-content {
            padding: 12px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }

        .validity-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 11px;
            background: #f8f9fa;
            padding: 8px 12px;
            border-radius: 4px;
            border: 1px solid #eee;
        }

        .terms-conditions {
            font-size: 10px;
            color: #444;
            margin-bottom: 12px;
            background: white;
            padding: 8px;
            border-radius: 4px;
        }

        .terms-conditions ol {
            margin: 5px 0 0 0;
            padding-left: 20px;
        }

        .terms-conditions li {
            margin-bottom: 3px;
            line-height: 1.3;
        }

        .company-info {
            font-size: 10px;
            text-align: center;
            color: #666;
            margin-top: auto;
            padding: 8px;
            background: #f8f9fa;
            border-radius: 4px;
            border-top: 1px solid #eee;
        }

        .company-info div {
            margin-bottom: 2px;
            line-height: 1.4;
        }

        /* Print settings */
        @media print {
            body {
                background: none;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color-adjust: exact !important;
            }
            
            .id-card {
                margin: 0;
                box-shadow: none;
                background-color: white !important;
            }

            .card-header {
                background: linear-gradient(to right, #001f3f, #003366) !important;
                color: white !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .back-header {
                background: linear-gradient(to right, #001f3f, #003366) !important;
                color: white !important;
            }

            .no-print {
                display: none !important;
            }
        }

        /* Date input styles */
        .date-inputs {
            max-width: 800px;
            width: 98%;
            margin: 20px auto;
            padding: 15px 30px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
            box-sizing: border-box;
        }

        .date-form {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 35px;
        }

        .date-group {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
        }

        .date-inputs label {
            font-weight: 600;
            color: #001f3f;
            white-space: nowrap;
            font-size: 14px;
            min-width: 85px;
            text-align: right;
        }

        .date-inputs input {
            padding: 6px 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-family: inherit;
            width: 150px;
            font-size: 14px;
        }

        .date-inputs button {
            padding: 6px 30px;
            background: linear-gradient(to right, #001f3f, #003366);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            height: 34px;
            white-space: nowrap;
            font-size: 14px;
            min-width: 140px;
            flex-shrink: 0;
        }

        .date-inputs button:hover {
            background: linear-gradient(to right, #002b50, #004080);
            transform: translateY(-1px);
        }

        /* Button container styles */
        .button-container {
            text-align: center;
            margin: 20px auto;
            max-width: 800px;
            width: 98%;
            display: flex;
            justify-content: center;
            gap: 15px;
        }

        /* Common button styles */
        .action-button {
            padding: 8px 30px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            height: 40px;
            white-space: nowrap;
            font-size: 14px;
            min-width: 140px;
            font-family: inherit;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        /* Print button specific styles */
        .print-button {
            background: linear-gradient(to right, #001f3f, #003366);
            color: white;
        }

        .print-button:hover {
            background: linear-gradient(to right, #002b50, #004080);
            transform: translateY(-1px);
            box-shadow: 0 3px 6px rgba(0,0,0,0.15);
        }

        /* Back button specific styles */
        .back-button {
            background: white;
            color: #001f3f;
            border: 1px solid #001f3f;
        }

        .back-button:hover {
            background: #f8f9fa;
            transform: translateY(-1px);
            box-shadow: 0 3px 6px rgba(0,0,0,0.15);
        }

        /* Active state for all buttons */
        .action-button:active {
            transform: translateY(0);
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }

        .company-logo-placeholder {
            position: absolute;
            left: 12px;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
            color: #6c757d;
            font-size: 8px;
            text-align: center;
            border: 1px dashed #dee2e6;
            border-radius: 4px;
            padding: 2px;
        }

        .company-logo-placeholder span {
            line-height: 1.2;
        }
    </style>
    <!-- Add meta tags to control printing -->
    <meta name="format-detection" content="telephone=no">
    <meta name="robots" content="noindex">
    <!-- Force Chrome to hide URL -->
    <meta name="chrome" content="print">
</head>
<body>
    <!-- Date Input Form -->
    <div class="date-inputs no-print">
        <form id="dateForm" class="date-form">
            <div class="date-group">
                <label for="issue_date">Issue Date:</label>
                <input type="date" id="issue_date" name="issue_date" value="<?php echo $issueDate; ?>" required>
            </div>
            
            <div class="date-group">
                <label for="expiry_date">Expiry Date:</label>
                <input type="date" id="expiry_date" name="expiry_date" value="<?php echo $expiryDate; ?>" required>
            </div>
            
            <button type="submit">Update Dates</button>
        </form>
    </div>

    <!-- Front Side -->
    <div class="id-card">
        <div class="card-front">
            <div class="card-header">
                <?php if (!empty($company['company_logo']) && file_exists('../../uploads/company/' . $company['company_logo'])): ?>
                    <img src="<?php echo htmlspecialchars($companyLogoPath); ?>" alt="Company Logo" class="company-logo">
                <?php else: ?>
                    <div class="company-logo-placeholder">
                        <span>Logo Not<br>Available</span>
                    </div>
                <?php endif; ?>
                <h1 class="company-name"><?php echo htmlspecialchars($company['company_name'] ?? 'InnovaTech'); ?></h1>
            </div>

            <div class="employee-section">
                <div class="photo-container">
                    <?php if (!empty($employee['photo_path']) && file_exists('../../uploads/employees/' . $employee['photo_path'])): ?>
                        <img src="<?php echo htmlspecialchars($employeePhotoPath); ?>" alt="Employee Photo" class="employee-photo">
                    <?php else: ?>
                        <div class="photo-placeholder">
                            <span>Photo Not<br>Available</span>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="employee-details">
                    <h2 class="employee-name">
                        <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                    </h2>
                    
                    <div class="detail-row">
                        <span class="detail-label">Position:</span>
                        <span><?php echo htmlspecialchars($employee['position']); ?></span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="detail-label">Department:</span>
                        <span><?php echo htmlspecialchars($employee['department_name']); ?></span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="detail-label">Employee ID:</span>
                        <span><?php echo htmlspecialchars($employee['employee_id']); ?></span>
                    </div>
                </div>
            </div>

            <div class="barcode-section">
                <svg id="barcode"></svg>
            </div>
        </div>
    </div>

    <!-- Back Side -->
    <div class="id-card">
        <div class="card-back">
            <div class="back-header">
                EMPLOYEE IDENTIFICATION CARD
            </div>
            
            <div class="back-content">
                <div class="validity-info">
                    <div><strong>Issue Date:</strong> <?php echo $issueDate; ?></div>
                    <div><strong>Expiry Date:</strong> <?php echo $expiryDate; ?></div>
                </div>
                
                <div class="terms-conditions">
                    <strong>Terms & Conditions:</strong>
                    <ol>
                        <li>This ID card is the property of <?php echo htmlspecialchars($company['company_name'] ?? 'the Company'); ?>.</li>
                        <li>The holder of this card is an employee of the company.</li>
                        <li>This card must be worn visibly while on company premises.</li>
                        <li>Loss of this card must be reported immediately.</li>
                        <li>This card is non-transferable.</li>
                    </ol>
                </div>
                
                <div class="company-info">
                    <?php if ($company): ?>
                        <div><strong><?php echo htmlspecialchars($company['company_name'] ?? ''); ?></strong></div>
                        <div><?php echo htmlspecialchars($company['address_line1'] ?? ''); ?></div>
                        <?php if (!empty($company['address_line2'])): ?>
                            <div><?php echo htmlspecialchars($company['address_line2']); ?></div>
                        <?php endif; ?>
                        <div>
                            <?php 
                            $location = array_filter([
                                $company['city'] ?? '',
                                $company['state'] ?? '',
                                $company['postal_code'] ?? '',
                                $company['country'] ?? ''
                            ]);
                            echo htmlspecialchars(implode(', ', $location));
                            ?>
                        </div>
                        <?php if (!empty($company['phone_1'])): ?>
                            <div><strong>Tel:</strong> <?php echo htmlspecialchars($company['phone_1']); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($company['email'])): ?>
                            <div><strong>Email:</strong> <?php echo htmlspecialchars($company['email']); ?></div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="button-container no-print">
        <button onclick="window.location.href='entry/employees/manage_employees.php'" class="action-button back-button">Back to Employee Form</button>
        <button onclick="printCard()" class="action-button print-button">Print ID Card</button>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <script>
        // Print function with custom settings
        function printCard() {
            // Set print settings
            const style = document.createElement('style');
            style.textContent = `
                @page {
                    size: 86mm 54mm;
                    margin: 0;
                }
                @media print {
                    @page { margin: 0; }
                    body { margin: 0; }
                }
            `;
            document.head.appendChild(style);

            // Hide URL from print
            const urlStyle = document.createElement('style');
            urlStyle.textContent = `
                @media print {
                    @page { margin: 0; }
                    body { margin: 0; }
                    .page-info { display: none !important; }
                }
            `;
            document.head.appendChild(urlStyle);

            window.print();
        }

        // Set document title dynamically
        document.title = "<?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?> - ID Card";
        
        // Handle date form submission
        document.getElementById('dateForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const issueDate = document.getElementById('issue_date').value;
            const expiryDate = document.getElementById('expiry_date').value;
            
            // Validate dates
            if (new Date(issueDate) >= new Date(expiryDate)) {
                alert('Expiry date must be after issue date');
                return;
            }
            
            // Update URL with new dates and reload
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('issue_date', issueDate);
            currentUrl.searchParams.set('expiry_date', expiryDate);
            window.location.href = currentUrl.toString();
        });

        // Generate barcode
        JsBarcode("#barcode", "<?php echo $employee['employee_id']; ?>", {
            format: "CODE128",
            width: 1.5,
            height: 40,
            displayValue: true
        });
    </script>
</body>
</html>
