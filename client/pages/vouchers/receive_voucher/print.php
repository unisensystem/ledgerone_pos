<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    header('Location: ../../auth/login.html');
    exit();
}

$voucher_id = $_GET['id'] ?? null;
if (!$voucher_id) {
    header('Location: receive-list.php');
    exit();
}

// Fetch voucher data
require_once '../../../../includes/connection.php';

// Function to convert number to words
function numberToWords($number) {
    $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
             'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    if ($number == 0) return 'Zero';
    if ($number < 20) return $ones[$number];
    if ($number < 100) return $tens[intval($number / 10)] . ($number % 10 ? ' ' . $ones[$number % 10] : '');
    if ($number < 1000) return $ones[intval($number / 100)] . ' Hundred' . ($number % 100 ? ' ' . numberToWords($number % 100) : '');
    if ($number < 1000000) return numberToWords(intval($number / 1000)) . ' Thousand' . ($number % 1000 ? ' ' . numberToWords($number % 1000) : '');
    if ($number < 1000000000) return numberToWords(intval($number / 1000000)) . ' Million' . ($number % 1000000 ? ' ' . numberToWords($number % 1000000) : '');
    return numberToWords(intval($number / 1000000000)) . ' Billion' . ($number % 1000000000 ? ' ' . numberToWords($number % 1000000000) : '');
}

function amountInWords($amount, $main_unit, $sub_unit) {
    $amount    = round($amount, 2);
    $intPart   = intval($amount);
    $decPart   = round(($amount - $intPart) * 100);

    $words = numberToWords($intPart) . ' ' . ($main_unit ?: 'Only');
    if ($decPart > 0) {
        $words .= ' and ' . numberToWords($decPart) . ' ' . ($sub_unit ?: 'Cents');
    }
    return $words . ' Only';
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            rv.voucher_number,
            rv.voucher_date,
            rv.amount,
            rv.previous_balance,
            rv.bill_no,
            rv.description,
            rv.cheque_date,
            rv.cheque_no,
            rv.attachment,
            COALESCE(cu.customer_name, su.supplier_name) as customer_name,
            COALESCE(cu.customer_code, su.supplier_code) as customer_code,
            COALESCE(cu.address, su.address) as customer_address,
            a.name as payment_method,
            c.name as currency_name,
            c.symbol as currency_symbol,
            c.main_unit_name as currency_main_unit,
            c.sub_unit_name as currency_sub_unit,
            ba.bank_name,
            ba.account_number,
            co.company_name,
            co.legal_name,
            co.email,
            co.phone,
            co.address,
            co.city,
            co.state,
            co.zipcode,
            co.logo_url,
            p.project_code,
            p.project_name
        FROM receive_voucher rv
        LEFT JOIN customers cu ON rv.customer_id = cu.id
        LEFT JOIN suppliers su ON rv.supplier_id = su.id
        LEFT JOIN accounts a ON rv.payment_method_id = a.id
        LEFT JOIN ledgerone_public.currencies c ON rv.currency_id = c.id
        LEFT JOIN bank_accounts ba ON rv.bank_account_id = ba.id
        LEFT JOIN companies co ON rv.company_id = co.id
        LEFT JOIN projects p ON rv.project_id = p.id
        WHERE rv.id = ? AND rv.tenant_id = ?
    ");
    $stmt->execute([$voucher_id, $tenant_id]);
    $voucher = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$voucher) {
        header('Location: receive-list.php');
        exit();
    }
    
    // Fallback to first active company if no company in voucher
    if (empty($voucher['company_name'])) {
        $stmt = $pdo->prepare("SELECT company_name, address, phone, email, logo_url FROM companies WHERE tenant_id = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$tenant_id]);
        $fallback = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($fallback) {
            $voucher['company_name'] = $fallback['company_name'];
            $voucher['address'] = $fallback['address'];
            $voucher['phone'] = $fallback['phone'];
            $voucher['email'] = $fallback['email'];
            $voucher['logo_url'] = $fallback['logo_url'];
        }
    }
    
    // Keep company data for backward compatibility
    $company = [
        'company_name' => $voucher['company_name'],
        'address' => $voucher['address'],
        'phone' => $voucher['phone'],
        'email' => $voucher['email'],
        'logo_url' => $voucher['logo_url']
    ];
} catch (Exception $e) {
    header('Location: receive-list.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receive Voucher - <?php echo htmlspecialchars($voucher['voucher_number']); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            background: white;
        }
        
        .print-container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            border: 1px solid #ddd;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
        }
        
        .company-name {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .document-title {
            font-size: 18px;
            font-weight: bold;
            margin-top: 10px;
        }
        
        .voucher-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        
        .info-section {
            flex: 1;
        }
        
        .info-row {
            margin-bottom: 8px;
        }
        
        .label {
            font-weight: bold;
            display: inline-block;
            width: 120px;
        }
        
        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        .details-table th,
        .details-table td {
            border: 1px solid #333;
            padding: 8px;
            text-align: left;
        }
        
        .details-table th {
            background-color: #f5f5f5;
            font-weight: bold;
        }
        
        .amount-section {
            margin-top: 20px;
            text-align: right;
        }
        
        .total-amount {
            font-size: 16px;
            font-weight: bold;
            border: 2px solid #333;
            padding: 10px;
            display: inline-block;
            margin-top: 10px;
        }
        
        .signatures {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
        }
        
        .signature-box {
            text-align: center;
            width: 200px;
        }
        
        .signature-line {
            border-top: 1px solid #333;
            margin-top: 50px;
            padding-top: 5px;
        }
        
        .print-hidden {
            display: none !important;
        }

        @media print {
            body { margin: 0; }
            .print-container { margin: 0; border: none; box-shadow: none; }
            .no-print { display: none !important; }
            .modal-overlay { display: none !important; }
            .print-hidden { display: none !important; }
        }
        
        .print-btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            margin-bottom: 20px;
        }

        .customize-btn {
            background: #6c757d;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            margin-bottom: 20px;
            margin-left: 8px;
        }

        .excel-btn {
            background: #1d6f42;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            margin-bottom: 20px;
            margin-left: 8px;
        }

        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.45);
            z-index: 9998;
        }

        .modal-box-wrap {
            width: 100%;
            height: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .modal-box {
            background: #fff;
            border-radius: 8px;
            width: 420px;
            max-width: 95vw;
            max-height: 85vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 8px 32px rgba(0,0,0,0.2);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            border-bottom: 1px solid #eee;
        }

        .modal-header h4 {
            font-size: 15px;
            font-weight: bold;
            margin: 0;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: #999;
            line-height: 1;
        }

        .modal-body {
            padding: 16px 20px;
            overflow-y: auto;
            flex: 1;
        }

        .modal-body label {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 7px 0;
            font-size: 13px;
            cursor: pointer;
            border-bottom: 1px solid #f5f5f5;
        }

        .modal-body label:last-child {
            border-bottom: none;
        }

        .modal-body input[type=checkbox] {
            width: 16px;
            height: 16px;
            cursor: pointer;
        }

        .modal-footer {
            padding: 12px 20px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }

        .btn-save {
            background: #007bff;
            color: #fff;
            border: none;
            padding: 8px 22px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
        }

        .btn-cancel {
            background: #f0f0f0;
            color: #333;
            border: none;
            padding: 8px 18px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
        }
        
        .attachment-section {
            margin: 20px 0;
            page-break-inside: avoid;
        }
        
        .attachment-section h3 {
            margin-bottom: 10px;
            font-size: 14px;
            font-weight: bold;
        }
        
        .attachment-section img {
            max-width: 100%;
            height: auto;
            border: 1px solid #ddd;
            margin: 10px 0;
        }
        
        .attachment-section iframe {
            width: 100%;
            height: 600px;
            border: 1px solid #ddd;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <!-- Customize Modal -->
    <div class="modal-overlay" id="customizeModal" onclick="handleOverlayClick(event)">
        <div class="modal-box-wrap">
        <div class="modal-box">
            <div class="modal-header">
                <h4>&#9881; Customize Print</h4>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <label><input type="checkbox" data-target="sec-logo"> Company Logo</label>
                <label><input type="checkbox" data-target="sec-company-address"> Company Address</label>
                <label><input type="checkbox" data-target="sec-company-contact"> Phone / Email</label>
                <label><input type="checkbox" data-target="sec-voucher-left"> Voucher No &amp; Date</label>
                <label><input type="checkbox" data-target="sec-voucher-mid"> Payment Method &amp; Currency</label>
                <label><input type="checkbox" data-target="sec-voucher-right"> Previous &amp; Outstanding Balance</label>
                <label><input type="checkbox" data-target="sec-customer-address"> Customer Address Row</label>
                <label><input type="checkbox" data-target="sec-bill-no"> Bill No Row</label>
                <label><input type="checkbox" data-target="sec-project"> Project Row</label>
                <label><input type="checkbox" data-target="sec-bank"> Bank Account Row</label>
                <label><input type="checkbox" data-target="sec-cheque-date"> Cheque Date Row</label>
                <label><input type="checkbox" data-target="sec-cheque-no"> Cheque No Row</label>
                <label><input type="checkbox" data-target="sec-attachment-row"> Attachment Row</label>
                <label><input type="checkbox" data-target="sec-description"> Description Row</label>
                <label><input type="checkbox" data-target="sec-attachment-preview"> Attachment Preview</label>
                <label><input type="checkbox" data-target="sec-amount-words"> Amount in Words</label>
                <label><input type="checkbox" data-target="sec-signatures"> Signatures</label>
                <label><input type="checkbox" data-target="sec-footer"> Footer Text</label>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeModal()">Cancel</button>
                <button class="btn-save" onclick="saveSettings()">Save</button>
            </div>
        </div>
        </div>
    </div>

    <div class="print-container">
        <button class="print-btn no-print" onclick="window.print()">Print Voucher</button>
        <button class="print-btn no-print" style="background:#28a745;" onclick="downloadAsPdf()">Download PDF</button>
        <button class="excel-btn no-print" onclick="downloadAsExcel()">Export to Excel</button>
        <button class="customize-btn no-print" onclick="openModal()">&#9881; Customize Print</button>
        
        <div class="header">
            <?php if ($company['logo_url']): ?>
            <div id="sec-logo" style="margin-bottom: 10px;">
                <img src="../../../assets/uploads/company_logo/<?php echo htmlspecialchars($company['logo_url']); ?>" alt="Company Logo" style="max-height: 80px; max-width: 200px;">
            </div>
            <?php endif; ?>
            <div class="company-name"><?php echo htmlspecialchars($company['company_name'] ?? 'LedgerOne ERP'); ?></div>
            <?php if ($company['address']): ?>
            <div id="sec-company-address" style="font-size: 12px; margin: 5px 0;"><?php echo htmlspecialchars($company['address']); ?></div>
            <?php endif; ?>
            <?php if ($company['phone'] || $company['email']): ?>
            <div id="sec-company-contact" style="font-size: 12px; margin: 5px 0;">
                <?php if ($company['phone']): ?>Phone: <?php echo htmlspecialchars($company['phone']); ?><?php endif; ?>
                <?php if ($company['phone'] && $company['email']): ?> | <?php endif; ?>
                <?php if ($company['email']): ?>Email: <?php echo htmlspecialchars($company['email']); ?><?php endif; ?>
            </div>
            <?php endif; ?>
            <div class="document-title">RECEIVE VOUCHER</div>
        </div>
        
        <div class="voucher-info">
            <div class="info-section" id="sec-voucher-left">
                <div class="info-row">
                    <span class="label">Voucher No:</span>
                    <?php echo htmlspecialchars($voucher['voucher_number']); ?>
                </div>
                <div class="info-row">
                    <span class="label">Date:</span>
                    <?php echo date('d-M-Y', strtotime($voucher['voucher_date'])); ?>
                </div>
            </div>
            <div class="info-section" id="sec-voucher-mid">
                <div class="info-row">
                    <span class="label">Payment Method:</span>
                    <?php echo htmlspecialchars($voucher['payment_method']); ?>
                </div>
                <div class="info-row">
                    <span class="label">Currency:</span>
                    <?php echo htmlspecialchars($voucher['currency_name']); ?>
                </div>
            </div>
            <div class="info-section" id="sec-voucher-right">
                <div class="info-row">
                    <span class="label">Previous Balance:</span>
                    <?php echo $voucher['currency_symbol'] . number_format($voucher['previous_balance'], 2); ?>
                </div>
                <div class="info-row">
                    <span class="label">Outstanding Balance:</span>
                    <?php echo $voucher['currency_symbol'] . number_format($voucher['previous_balance'] - $voucher['amount'], 2); ?>
                </div>
            </div>
        </div>
        
        <table class="details-table">
            <tr>
                <th>Received From</th>
                <td><?php echo htmlspecialchars(($voucher['customer_code'] ?: 'N/A') . ' - ' . ($voucher['customer_name'] ?: 'N/A')); ?></td>
            </tr>
            <?php if ($voucher['customer_address']): ?>
            <tr id="sec-customer-address">
                <th>Address</th>
                <td><?php echo htmlspecialchars($voucher['customer_address']); ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($voucher['bill_no']): ?>
            <tr id="sec-bill-no">
                <th>Bill No</th>
                <td><?php echo htmlspecialchars($voucher['bill_no']); ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($voucher['project_code']): ?>
            <tr id="sec-project">
                <th>Project</th>
                <td><?php echo htmlspecialchars($voucher['project_code'] . ' - ' . $voucher['project_name']); ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($voucher['bank_name']): ?>
            <tr id="sec-bank">
                <th>Bank Account</th>
                <td><?php echo htmlspecialchars($voucher['bank_name'] . ' - ' . $voucher['account_number']); ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($voucher['cheque_date'] && $voucher['cheque_date'] !== '0000-00-00'): ?>
            <tr id="sec-cheque-date">
                <th>Cheque Date</th>
                <td><?php echo date('d-M-Y', strtotime($voucher['cheque_date'])); ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($voucher['cheque_no']): ?>
            <tr id="sec-cheque-no">
                <th>Cheque No</th>
                <td><?php echo htmlspecialchars($voucher['cheque_no']); ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($voucher['attachment']): ?>
            <tr id="sec-attachment-row">
                <th>Attachment</th>
                <td><?php echo htmlspecialchars($voucher['attachment']); ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($voucher['description']): ?>
            <tr id="sec-description">
                <th>Description</th>
                <td><?php echo htmlspecialchars($voucher['description'] ?: 'N/A'); ?></td>
            </tr>
            <?php endif; ?>
        </table>
        
        <?php if ($voucher['attachment']): ?>
        <div class="attachment-section" id="sec-attachment-preview">
            <h3>Attachment:</h3>
            <?php 
            $attachment_path = "../../../assets/uploads/receive_voucher/" . $voucher['attachment'];
            $file_extension = strtolower(pathinfo($voucher['attachment'], PATHINFO_EXTENSION));
            
            if ($file_extension === 'pdf'): ?>
                <iframe src="<?php echo $attachment_path; ?>" width="100%" height="600px" style="border: 1px solid #ddd; margin: 10px 0;"></iframe>
            <?php else: ?>
                <img src="<?php echo $attachment_path; ?>" alt="Attachment" style="max-width: 100%; height: auto; border: 1px solid #ddd; margin: 10px 0;">
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <div class="amount-section">
            <div class="total-amount">
                Total Amount: <?php echo $voucher['currency_symbol'] . number_format($voucher['amount'], 2); ?>
            </div>
            <div id="sec-amount-words" style="margin-top: 10px; font-style: italic;">
                Amount in Words: <?php echo amountInWords($voucher['amount'], $voucher['currency_main_unit'], $voucher['currency_sub_unit']); ?>
            </div>
        </div>
        
        <div class="signatures" id="sec-signatures">
            <div class="signature-box">
                <div class="signature-line">Prepared By</div>
            </div>
            <div class="signature-box">
                <div class="signature-line">Approved By</div>
            </div>
            <div class="signature-box">
                <div class="signature-line">Received By</div>
            </div>
        </div>
        
        <div id="sec-footer" style="text-align: center; margin-top: 30px; font-size: 10px; color: #666; border-top: 1px solid #ddd; padding-top: 10px;">
            <i>This is a System Generated Voucher</i>
        </div>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script>
        const STORAGE_KEY = 'rv_print_hidden';

        function getHidden() {
            try { return JSON.parse(localStorage.getItem(STORAGE_KEY)) || []; } catch(e) { return []; }
        }

        function applyHidden(hidden) {
            document.querySelectorAll('[data-target]').forEach(function(cb) {
                var id = cb.getAttribute('data-target');
                var el = document.getElementById(id);
                cb.checked = hidden.indexOf(id) === -1;
                if (el) el.classList.toggle('print-hidden', hidden.indexOf(id) !== -1);
            });
        }

        function openModal() {
            applyHidden(getHidden());
            document.getElementById('customizeModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('customizeModal').style.display = 'none';
        }

        function handleOverlayClick(e) {
            if (e.target === document.getElementById('customizeModal')) closeModal();
        }

        function saveSettings() {
            var hidden = [];
            document.querySelectorAll('[data-target]').forEach(function(cb) {
                var id = cb.getAttribute('data-target');
                var el = document.getElementById(id);
                if (!cb.checked) {
                    hidden.push(id);
                    if (el) el.classList.add('print-hidden');
                } else {
                    if (el) el.classList.remove('print-hidden');
                }
            });
            localStorage.setItem(STORAGE_KEY, JSON.stringify(hidden));
            closeModal();
        }

        document.addEventListener('DOMContentLoaded', function() {
            applyHidden(getHidden());
        });

        function downloadAsPdf() {
            const element = document.querySelector('.print-container');
            const voucherNo = '<?php echo addslashes($voucher['voucher_number']); ?>';
            html2pdf().set({
                margin: 0.3,
                filename: `receive-voucher-${voucherNo}.pdf`,
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true },
                jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' }
            }).from(element).save();
        }

        // Voucher data for Excel export (kept in sync with the printed fields above)
        const voucherExcelData = {
            voucherNo: <?php echo json_encode($voucher['voucher_number']); ?>,
            date: <?php echo json_encode(date('d-M-Y', strtotime($voucher['voucher_date']))); ?>,
            paymentMethod: <?php echo json_encode($voucher['payment_method']); ?>,
            currency: <?php echo json_encode($voucher['currency_name']); ?>,
            previousBalance: <?php echo json_encode($voucher['currency_symbol'] . number_format($voucher['previous_balance'], 2)); ?>,
            outstandingBalance: <?php echo json_encode($voucher['currency_symbol'] . number_format($voucher['previous_balance'] - $voucher['amount'], 2)); ?>,
            receivedFrom: <?php echo json_encode(($voucher['customer_code'] ?: 'N/A') . ' - ' . ($voucher['customer_name'] ?: 'N/A')); ?>,
            address: <?php echo json_encode($voucher['customer_address'] ?: ''); ?>,
            billNo: <?php echo json_encode($voucher['bill_no'] ?: ''); ?>,
            project: <?php echo json_encode($voucher['project_code'] ? ($voucher['project_code'] . ' - ' . $voucher['project_name']) : ''); ?>,
            bankAccount: <?php echo json_encode($voucher['bank_name'] ? ($voucher['bank_name'] . ' - ' . $voucher['account_number']) : ''); ?>,
            chequeDate: <?php echo json_encode(($voucher['cheque_date'] && $voucher['cheque_date'] !== '0000-00-00') ? date('d-M-Y', strtotime($voucher['cheque_date'])) : ''); ?>,
            chequeNo: <?php echo json_encode($voucher['cheque_no'] ?: ''); ?>,
            description: <?php echo json_encode($voucher['description'] ?: ''); ?>,
            amount: <?php echo json_encode($voucher['currency_symbol'] . number_format($voucher['amount'], 2)); ?>,
            amountInWords: <?php echo json_encode(amountInWords($voucher['amount'], $voucher['currency_main_unit'], $voucher['currency_sub_unit'])); ?>,
            companyName: <?php echo json_encode($company['company_name'] ?? 'LedgerOne ERP'); ?>
        };

        function downloadAsExcel() {
            // Build rows as an array-of-arrays; hidden sections (via Customize Print) are skipped
            const hidden = getHidden();
            const rows = [
                [voucherExcelData.companyName],
                ['RECEIVE VOUCHER'],
                []
            ];

            const addRow = (targetId, label, value) => {
                if (hidden.indexOf(targetId) !== -1) return;
                if (value === '' || value === null || value === undefined) return;
                rows.push([label, value]);
            };

            addRow('sec-voucher-left', 'Voucher No', voucherExcelData.voucherNo);
            addRow('sec-voucher-left', 'Date', voucherExcelData.date);
            addRow('sec-voucher-mid', 'Payment Method', voucherExcelData.paymentMethod);
            addRow('sec-voucher-mid', 'Currency', voucherExcelData.currency);
            addRow('sec-voucher-right', 'Previous Balance', voucherExcelData.previousBalance);
            addRow('sec-voucher-right', 'Outstanding Balance', voucherExcelData.outstandingBalance);
            rows.push(['Received From', voucherExcelData.receivedFrom]);
            addRow('sec-customer-address', 'Address', voucherExcelData.address);
            addRow('sec-bill-no', 'Bill No', voucherExcelData.billNo);
            addRow('sec-project', 'Project', voucherExcelData.project);
            addRow('sec-bank', 'Bank Account', voucherExcelData.bankAccount);
            addRow('sec-cheque-date', 'Cheque Date', voucherExcelData.chequeDate);
            addRow('sec-cheque-no', 'Cheque No', voucherExcelData.chequeNo);
            addRow('sec-description', 'Description', voucherExcelData.description);
            rows.push([]);
            rows.push(['Total Amount', voucherExcelData.amount]);
            addRow('sec-amount-words', 'Amount in Words', voucherExcelData.amountInWords);

            const worksheet = XLSX.utils.aoa_to_sheet(rows);
            worksheet['!cols'] = [{ wch: 22 }, { wch: 45 }];

            const workbook = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(workbook, worksheet, 'Receive Voucher');

            XLSX.writeFile(workbook, `receive-voucher-${voucherExcelData.voucherNo}.xlsx`);
        }

        <?php if (isset($_GET['pdf']) && $_GET['pdf'] === '1'): ?>
        window.addEventListener('DOMContentLoaded', function() {
            const element = document.querySelector('.print-container');
            const voucherNo = '<?php echo addslashes($voucher['voucher_number']); ?>';
            html2pdf().set({
                margin: 0.3,
                filename: `receive-voucher-${voucherNo}.pdf`,
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true },
                jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' }
            }).from(element).save().then(() => window.close());
        });
        <?php endif; ?>
    </script>
</body>
</html>