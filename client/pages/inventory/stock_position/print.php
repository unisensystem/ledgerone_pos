<?php
session_start();

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;

if (!$user_id || !$tenant_id) {
    header('Location: ../../auth/login.html');
    exit();
}

// Get company data
require_once '../../../../includes/connection.php';
$company_sql = "SELECT company_name, address, phone, email, logo_url, inventory_valuation_method FROM companies WHERE tenant_id = ? AND is_active = 1 LIMIT 1";
$company_stmt = $pdo->prepare($company_sql);
$company_stmt->execute([$tenant_id]);
$company = $company_stmt->fetch(PDO::FETCH_ASSOC);

// Get user data
$user_sql = "SELECT full_name FROM users WHERE id = ?";
$user_stmt = $pdo->prepare($user_sql);
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Get filters from URL
$branch_id = $_GET['branch_id'] ?? null;
$product_id = $_GET['product_id'] ?? null;
$inventory_type_id = $_GET['inventory_type_id'] ?? null;
$stock_status = $_GET['stock_status'] ?? null;
$status = $_GET['status'] ?? null;
$stock_level = $_GET['stock_level'] ?? null;
$from_date = $_GET['from_date'] ?? null;
$to_date = $_GET['to_date'] ?? null;
$valuation_method = $_GET['valuation_method'] ?? 'AVCO';
$report_type = $_GET['type'] ?? 'position';

// Get company name if company_id is provided
$inventory_type_name = 'All Inventory Types';
if ($inventory_type_id) {
    $type_sql = "SELECT name FROM accounts WHERE id = ? AND tenant_id = ?";
    $type_stmt = $pdo->prepare($type_sql);
    $type_stmt->execute([$inventory_type_id, $tenant_id]);
    $type_result = $type_stmt->fetch(PDO::FETCH_ASSOC);
    if ($type_result) {
        $inventory_type_name = $type_result['name'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Report - Print View</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
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
        
        .company-name {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .report-title {
            font-size: 18px;
            margin-bottom: 10px;
        }
        
        .report-date {
            font-size: 12px;
            color: #666;
        }
        
        .filters {
            margin-bottom: 20px;
            font-size: 12px;
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
        
        .totals-row {
            font-weight: bold;
            background-color: #f9f9f9;
        }
        
        .toolbar {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }
        
        .print-btn {
            padding: 10px 20px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .excel-btn {
            padding: 10px 20px;
            background: #1e7e34;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .signature-section {
            margin-top: 0%;
            display: flex;
            justify-content: space-between;
            page-break-inside: avoid;
        }
        
        .signature-box {
            width: 180px;
            text-align: center;
            font-size: 12px;
        }
        
        .signature-line {
            border-bottom: 1px solid #333;
            height: 60px;
            margin-bottom: 8px;
            display: flex;
            align-items: flex-end;
        }
        
        .signature-box div:last-child {
            font-weight: bold;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="toolbar no-print">
        <button class="print-btn" onclick="window.print()">Print Report</button>
        <button class="excel-btn" onclick="exportToExcel()">Export to Excel</button>
    </div>
    
    <div class="header">
        <?php if ($company['logo_url']): ?>
            <img src="../../../assets/uploads/company_logo/<?= htmlspecialchars($company['logo_url']) ?>" alt="Company Logo" style="max-height: 60px; margin-bottom: 10px;">
        <?php endif; ?>
        <div class="company-name"><?= $company['company_name'] ?? 'LedgerOne ERP' ?></div>
        <?php if ($company['address']): ?>
            <div style="font-size: 12px; margin-bottom: 5px;"><?= htmlspecialchars($company['address']) ?></div>
        <?php endif; ?>
        <?php if ($company['phone'] || $company['email']): ?>
            <div style="font-size: 12px; margin-bottom: 10px;">
                <?= $company['phone'] ? 'Phone: ' . htmlspecialchars($company['phone']) : '' ?>
                <?= $company['phone'] && $company['email'] ? ' | ' : '' ?>
                <?= $company['email'] ? 'Email: ' . htmlspecialchars($company['email']) : '' ?>
            </div>
        <?php endif; ?>
        <div class="report-title">Stock Position Report</div>
        <div class="report-date">Generated on: <?= date('Y-m-d H:i:s') ?></div>
        <div class="report-date">Generated by: <?= $user['full_name'] ?? 'System User' ?></div>
    </div>
    
    <div class="filters">
        <strong>Filters Applied:</strong>
        Branch: <?= $branch_id ? 'Selected' : 'All Branches' ?> | 
        Product: <?= $product_id ? 'Selected' : 'All Products' ?> | 
        Inventory Type: <?= htmlspecialchars($inventory_type_name) ?> | 
        Stock Status: <?= $stock_status ? ucfirst($stock_status) : 'All' ?> | 
        Status: <?= $status ?: 'All Statuses' ?> | 
        Stock Level: <?= $stock_level === 'zero-or-less' ? 'Stock = 0 or Less' : ($stock_level === 'greater-than-zero' ? 'Stock > 0' : 'All Levels') ?> | 
        Period: <?= $from_date && $to_date ? date('M d, Y', strtotime($from_date)) . ' to ' . date('M d, Y', strtotime($to_date)) : 'All Dates' ?> | 
        Valuation Method: <?= $valuation_method === 'TRADE_PRICE' ? 'Market Value - Trade Price' : $valuation_method ?>
    </div>
    
    <?php if ($valuation_method === 'TRADE_PRICE'): ?>
    <div style="background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%); border-left: 4px solid #f39c12; padding: 16px; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(243, 156, 18, 0.15);">
        <div style="display: flex; align-items: start; gap: 12px;">
            <div style="color: #f39c12; font-size: 20px; margin-top: 2px;">ℹ️</div>
            <div>
                <div style="font-weight: 600; color: #856404; margin-bottom: 4px; font-size: 14px;">Market Value Estimation</div>
                <div style="color: #856404; font-size: 13px; line-height: 1.6;">
                    This report shows estimated market value based on trade price. It is not an accounting valuation and should not be used for financial reporting.
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <table id="reportTable">
        <thead>
            <tr>
                <th>Product</th>
                <th class="col-branch">Branch</th>
                <th class="col-opening-balance">Opening Balance</th>
                <th class="col-qty-in">Total Qty In</th>
                <th class="col-qty-out">Total Qty Out</th>
                <th class="col-current-stock">Current Stock</th>
                <th class="col-unit-cost">Unit Cost</th>
                <th class="col-stock-value">Stock Value</th>
                <th class="col-status">Status</th>
            </tr>
        </thead>
        <tbody>
        </tbody>
    </table>
    
    <div class="signature-section">
        <div class="signature-box">
            <div class="signature-line"></div>
            <div>Prepared By</div>
        </div>
        <div class="signature-box">
            <div class="signature-line"></div>
            <div>Reviewed By</div>
        </div>
        <div class="signature-box">
            <div class="signature-line"></div>
            <div>Approved By</div>
        </div>
    </div>
    
    <script>
        const API_BASE = '../../../../server/api/inventory/stock_position/stock-position.php';

        // Holds the data + currency currently rendered, so Export to Excel
        // exports exactly what's shown on screen (same filters, same columns).
        let currentPositions = [];
        let currentCurrency = '$';
        let currentColumnVisibility = {};
        
        // Load column visibility from localStorage
        function applyColumnVisibility() {
            const saved = localStorage.getItem('stockPositionColumnVisibility');
            if (saved) {
                currentColumnVisibility = JSON.parse(saved);
                
                const columns = {
                    'branch': document.querySelectorAll('.col-branch'),
                    'openingBalance': document.querySelectorAll('.col-opening-balance'),
                    'qtyIn': document.querySelectorAll('.col-qty-in'),
                    'qtyOut': document.querySelectorAll('.col-qty-out'),
                    'currentStock': document.querySelectorAll('.col-current-stock'),
                    'unitCost': document.querySelectorAll('.col-unit-cost'),
                    'stockValue': document.querySelectorAll('.col-stock-value'),
                    'status': document.querySelectorAll('.col-status')
                };
                
                Object.keys(columns).forEach(key => {
                    columns[key].forEach(col => {
                        col.style.display = currentColumnVisibility[key] ? '' : 'none';
                    });
                });
            }
        }
        
        async function loadPrintData() {
            try {
                const urlParams = new URLSearchParams(window.location.search);
                let url = `${API_BASE}?action=position`;
                
                if (urlParams.get('branch_id')) url += `&branch_id=${urlParams.get('branch_id')}`;
                if (urlParams.get('product_id')) url += `&product_id=${urlParams.get('product_id')}`;
                if (urlParams.get('inventory_type_id')) url += `&inventory_type_id=${urlParams.get('inventory_type_id')}`;
                if (urlParams.get('stock_status')) url += `&stock_status=${urlParams.get('stock_status')}`;
                if (urlParams.get('status')) url += `&status=${urlParams.get('status')}`;
                if (urlParams.get('from_date')) url += `&from_date=${urlParams.get('from_date')}`;
                if (urlParams.get('to_date')) url += `&to_date=${urlParams.get('to_date')}`;
                if (urlParams.get('valuation_method')) url += `&valuation_method=${urlParams.get('valuation_method')}`;
                
                const response = await fetch(url);
                const data = await response.json();
                
                if (data.success) {
                    let positions = data.data;
                    
                    // Apply stock level filter
                    const stockLevel = urlParams.get('stock_level');
                    if (stockLevel === 'zero-or-less') {
                        positions = positions.filter(pos => parseFloat(pos.current_stock) <= 0);
                    } else if (stockLevel === 'greater-than-zero') {
                        positions = positions.filter(pos => parseFloat(pos.current_stock) > 0);
                    }
                    
                    currentPositions = positions;
                    currentCurrency = data.currency || '$';
                    updatePrintTable(positions, currentCurrency);
                }
            } catch (error) {
                console.error('Error loading print data:', error);
            }
        }
        
        function numberToWords(num) {
            const ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine'];
            const teens = ['Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
            const tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
            const thousands = ['', 'Thousand', 'Million', 'Billion'];
            
            if (num === 0) return 'Zero';
            
            function convertHundreds(n) {
                let result = '';
                if (n >= 100) {
                    result += ones[Math.floor(n / 100)] + ' Hundred ';
                    n %= 100;
                }
                if (n >= 20) {
                    result += tens[Math.floor(n / 10)] + ' ';
                    n %= 10;
                } else if (n >= 10) {
                    result += teens[n - 10] + ' ';
                    n = 0;
                }
                if (n > 0) {
                    result += ones[n] + ' ';
                }
                return result;
            }
            
            // Split into dollars and cents
            const dollars = Math.floor(num);
            const cents = Math.round((num - dollars) * 100);
            
            let result = '';
            let tempNum = dollars;
            let thousandIndex = 0;
            
            if (dollars === 0) {
                result = 'Zero';
            } else {
                while (tempNum > 0) {
                    if (tempNum % 1000 !== 0) {
                        result = convertHundreds(tempNum % 1000) + thousands[thousandIndex] + ' ' + result;
                    }
                    tempNum = Math.floor(tempNum / 1000);
                    thousandIndex++;
                }
            }
            
            result = result.trim();
            
            if (cents > 0) {
                result += ' and ' + convertHundreds(cents).trim() + ' Cents';
            }
            
            return result;
        }
        
        function updatePrintTable(positions, currency = '$') {
            const tbody = document.querySelector('#reportTable tbody');
            
            // Calculate totals only for parent products or standalone products (exclude children)
            const totalValue = positions.reduce((sum, pos) => {
                if (!pos.parent_product_id) {
                    return sum + parseFloat(pos.stock_value);
                }
                return sum;
            }, 0);
            const amountInWords = numberToWords(totalValue);
            
            // Calculate unit-wise totals (only parent products or standalone products)
            const unitTotals = positions.reduce((totals, pos) => {
                if (!pos.parent_product_id) {
                    const unit = pos.unit_symbol || 'L';
                    totals[unit] = (totals[unit] || 0) + parseFloat(pos.current_stock);
                }
                return totals;
            }, {});
            
            const unitTotalsText = Object.entries(unitTotals)
                .map(([unit, total]) => `${total.toFixed(2)} ${unit}`)
                .join(', ');
            
            // Count only parent products or standalone products for entry count
            const totalEntries = positions.filter(pos => !pos.parent_product_id).length;
            
            tbody.innerHTML = positions.map(pos => {
                const isChild = pos.parent_product_id;
                const productDisplay = isChild ? `<span style="margin-left: 20px; color: #6b7280;">↳ ${pos.product_name}</span>` : pos.product_name;
                
                return `
                <tr style="${isChild ? 'background-color: #fafbfd;' : ''}">
                    <td>${productDisplay}</td>
                    <td class="col-branch">${pos.branch_display}</td>
                    <td class="col-opening-balance">${parseFloat(pos.opening_balance || 0).toFixed(2)} ${pos.unit_symbol || 'L'}</td>
                    <td class="col-qty-in">${parseFloat(pos.total_qty_in || 0).toFixed(2)} ${pos.unit_symbol || 'L'}</td>
                    <td class="col-qty-out">${parseFloat(pos.total_qty_out || 0).toFixed(2)} ${pos.unit_symbol || 'L'}</td>
                    <td class="col-current-stock">${parseFloat(pos.current_stock).toFixed(2)} ${pos.unit_symbol || 'L'}</td>
                    <td class="col-unit-cost">${currency}${parseFloat(pos.unit_cost).toFixed(2)}</td>
                    <td class="col-stock-value">${currency}${parseFloat(pos.stock_value).toFixed(2)}</td>
                    <td class="col-status">${pos.status.replace('-', ' ').replace(/\b\w/g, l => l.toUpperCase())}</td>
                </tr>
            `;
            }).join('') + `
                <tr class="totals-row">
                    <td colspan="2">Total (${totalEntries} entries)</td>
                    <td class="col-opening-balance">-</td>
                    <td class="col-qty-in">-</td>
                    <td class="col-qty-out">-</td>
                    <td class="col-current-stock">${unitTotalsText}</td>
                    <td class="col-unit-cost">-</td>
                    <td class="col-stock-value">${currency}${totalValue.toFixed(2)}</td>
                    <td class="col-status"></td>
                </tr>
            ` + (totalValue > 0 ? `
                <tr>
                    <td colspan="9" style="text-align: center; font-style: italic; padding: 10px;">Amount in Words: ${amountInWords} Only</td>
                </tr>
            ` : '');
            
            // Apply column visibility after rendering
            applyColumnVisibility();
        }

        // Export to Excel — mirrors exactly what's shown in the print preview
        // (same filtered rows, same visible columns) using the SheetJS library.
        function exportToExcel() {
            if (typeof XLSX === 'undefined') {
                alert('Excel export library failed to load. Please check your internet connection and reload the page.');
                return;
            }

            if (!currentPositions || currentPositions.length === 0) {
                alert('No data available to export.');
                return;
            }

            const cv = currentColumnVisibility;
            const rows = currentPositions.map(pos => {
                const unit = pos.unit_symbol || 'L';
                const row = { 'Product': pos.product_name };
                if (cv.branch !== false) row['Branch'] = pos.branch_display;
                if (cv.openingBalance !== false) row['Opening Balance'] = `${parseFloat(pos.opening_balance || 0).toFixed(2)} ${unit}`;
                if (cv.qtyIn !== false) row['Total Qty In'] = `${parseFloat(pos.total_qty_in || 0).toFixed(2)} ${unit}`;
                if (cv.qtyOut !== false) row['Total Qty Out'] = `${parseFloat(pos.total_qty_out || 0).toFixed(2)} ${unit}`;
                if (cv.currentStock !== false) row['Current Stock'] = `${parseFloat(pos.current_stock).toFixed(2)} ${unit}`;
                if (cv.unitCost !== false) row['Unit Cost'] = parseFloat(pos.unit_cost).toFixed(2);
                if (cv.stockValue !== false) row['Stock Value'] = parseFloat(pos.stock_value).toFixed(2);
                if (cv.status !== false) row['Status'] = pos.status.replace('-', ' ').replace(/\b\w/g, l => l.toUpperCase());
                return row;
            });

            const worksheet = XLSX.utils.json_to_sheet(rows);
            const workbook = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(workbook, worksheet, 'Stock Position');
            XLSX.writeFile(workbook, `stock_position_${new Date().toISOString().slice(0, 10)}.xlsx`);
        }
        
        document.addEventListener('DOMContentLoaded', () => {
            applyColumnVisibility();
            loadPrintData();
        });
    </script>
</body>
</html>