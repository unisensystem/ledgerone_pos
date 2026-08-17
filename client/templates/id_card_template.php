<?php
function generateIdCard($employee, $company, $barcode_image) {
?>
<!DOCTYPE html>
<html>
<head>
    <title>Employee ID Card</title>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <style>
        /* Hide URL from print */
        @page {
            margin: 0;
            size: auto;
        }
        @media print {
            body {
                margin: 0;
                padding: 0;
            }
            .id-card {
                box-shadow: none;
                margin: 0;
                page-break-inside: avoid;
            }
            .no-print {
                display: none;
            }
        }

        .id-card {
            width: 3.375in;
            height: 2.125in;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            overflow: hidden;
            position: relative;
            margin: 20px auto;
            font-family: Arial, sans-serif;
            page-break-inside: avoid;
        }

        .header {
            background: #002147;
            color: white;
            padding: 10px;
            text-align: center;
        }

        .company-logo {
            width: 50px;
            height: auto;
            margin: 5px auto;
        }

        .company-name {
            font-size: 14px;
            font-weight: bold;
            margin: 5px 0;
        }

        .card-content {
            display: flex;
            padding: 10px;
        }

        .photo-section {
            width: 30%;
            padding: 5px;
        }

        .photo-container {
            width: 80px;
            height: 80px;
            margin: 0 auto;
            border: 2px solid #002147;
            overflow: hidden;
            border-radius: 5px;
        }

        .photo-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .info-section {
            width: 70%;
            padding: 5px;
        }

        .employee-name {
            font-size: 14px;
            font-weight: bold;
            margin: 5px 0;
            color: #002147;
        }

        .info-row {
            font-size: 10px;
            margin: 3px 0;
            color: #333;
        }

        .info-label {
            font-weight: bold;
            display: inline-block;
            width: 70px;
        }

        .barcode-section {
            text-align: center;
            margin-top: 5px;
            padding: 5px;
        }

        .barcode-section svg {
            max-width: 90%;
            height: 30px;
        }

        .id-number {
            font-size: 10px;
            margin-top: 2px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="id-card">
        <div class="header">
            <?php if (!empty($company['logo_path'])): ?>
            <img src="<?php echo htmlspecialchars($company['logo_path']); ?>" alt="Company Logo" class="company-logo">
            <?php endif; ?>
            <div class="company-name"><?php echo htmlspecialchars($company['company_name']); ?></div>
        </div>
        
        <div class="card-content">
            <div class="photo-section">
                <div class="photo-container">
                    <?php if (!empty($employee['photo_path']) && file_exists($employee['photo_path'])): ?>
                    <img src="<?php echo htmlspecialchars($employee['photo_path']); ?>" alt="Employee Photo">
                    <?php else: ?>
                    <img src="images/default-avatar.png" alt="Default Photo">
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="info-section">
                <div class="employee-name">
                    <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Position:</span>
                    <?php echo htmlspecialchars($employee['position']); ?>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Department:</span>
                    <?php echo htmlspecialchars($employee['dept_name']); ?>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Employee ID:</span>
                    <?php echo htmlspecialchars($employee['employee_id']); ?>
                </div>
            </div>
        </div>
        
        <div class="barcode-section">
            <svg id="barcode"></svg>
            <div class="id-number"><?php echo htmlspecialchars($employee['employee_id']); ?></div>
        </div>
    </div>
    
    <div class="no-print" style="text-align: center; margin: 20px;">
        <button onclick="window.print()">Print ID Card</button>
    </div>

    <script>
        // Generate barcode using JsBarcode
        JsBarcode("#barcode", "<?php echo htmlspecialchars($employee['employee_id']); ?>", {
            format: "CODE128",
            width: 2,
            height: 50,
            displayValue: false
        });
    </script>
</body>
</html>
<?php
}
?>
