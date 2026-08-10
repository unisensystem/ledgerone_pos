<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header('Location: ../../auth/login.html');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Petrol Daily Sale Sheet - Print</title>
    <style>
        @media print {
            @page { margin: 0.5cm; }
            body { margin: 0; }
            .no-print { display: none; }
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: white;
        }
        
        .print-header {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }
        
        .print-header h1 {
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .print-header p {
            font-size: 14px;
            color: #666;
        }
        
        .print-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .print-table th,
        .print-table td {
            border: 1px solid #000;
            padding: 8px;
            text-align: left;
            font-size: 12px;
        }
        
        .print-table th {
            background-color: #f0f0f0;
            font-weight: bold;
        }
        
        .print-table td {
            text-align: center;
        }
        
        .print-footer {
            margin-top: 30px;
            border-top: 2px solid #000;
            padding-top: 15px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 14px;
        }
        
        .summary-row strong {
            font-weight: bold;
        }
        
        .no-print {
            margin-bottom: 20px;
        }
        
        .btn {
            padding: 10px 20px;
            margin-right: 10px;
            cursor: pointer;
            border: none;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .btn-primary {
            background-color: #1f7bff;
            color: white;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="btn btn-primary" onclick="window.print()">Print</button>
        <button class="btn btn-secondary" onclick="window.close()">Close</button>
    </div>
    
    <div class="print-header">
        <h1>Diesel Daily Sale Sheet</h1>
        <p>Daily meter readings for diesel sales tracking (Combined for all stations)</p>
        <p>Print Date: <span id="printDate"></span></p>
    </div>
    
    <table class="print-table" id="printTable">
        <thead>
            <tr>
                <th style="width: 50px;">Sr#</th>
                <th style="width: 100px;">Date</th>
                <th style="width: 120px;">Opening Meter</th>
                <th style="width: 120px;">Closing Meter</th>
                <th style="width: 100px;">Ltr</th>
                <th style="width: 100px;">Rate</th>
                <th style="width: 120px;">Amount</th>
            </tr>
        </thead>
        <tbody id="printTableBody">
            <!-- Rows will be populated by JavaScript -->
        </tbody>
    </table>
    
    <div class="print-footer">
        <div class="summary-row">
            <span>Total Liters:</span>
            <strong id="totalLiters">0.00</strong>
        </div>
        <div class="summary-row">
            <span>Total Amount:</span>
            <strong id="totalAmount">Rs 0.00</strong>
        </div>
        <div class="summary-row">
            <span>Average Rate:</span>
            <strong id="avgRate">Rs 0.00</strong>
        </div>
    </div>
    
    <script>
        window.HARDCODED_PRODUCT_ID = 5;
        window.HARDCODED_BRANCH_ID = 4;
        window.HARDCODED_UNIT_ID = 7;
        window.HARDCODED_STATION_ID = 3;
        
        document.addEventListener('DOMContentLoaded', async function() {
            // Set print date
            document.getElementById('printDate').textContent = new Date().toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            
            // Fetch and display data
            await fetchAndDisplayData();
        });
        
        async function fetchAndDisplayData() {
            try {
                const response = await fetch(`../../../../server/api/sale/meter_invoice/get-worksheet-data.php?product_id=${window.HARDCODED_PRODUCT_ID}&branch_id=${window.HARDCODED_BRANCH_ID}&unit_id=${window.HARDCODED_UNIT_ID}&station_id=${window.HARDCODED_STATION_ID}`);
                const result = await response.json();
                
                if (result.success && result.data.length > 0) {
                    const tbody = document.getElementById('printTableBody');
                    let totalLiters = 0;
                    let totalAmount = 0;
                    let totalRate = 0;
                    let rateCount = 0;
                    
                    result.data.forEach((row, index) => {
                        const liters = row.closing_reading ? (parseFloat(row.closing_reading) - parseFloat(row.opening_reading)).toFixed(2) : '';
                        const amount = liters && row.rate ? (parseFloat(liters) * parseFloat(row.rate)).toFixed(2) : '';
                        
                        if (liters) totalLiters += parseFloat(liters);
                        if (amount) totalAmount += parseFloat(amount);
                        if (row.rate) {
                            totalRate += parseFloat(row.rate);
                            rateCount++;
                        }
                        
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td>${index + 1}</td>
                            <td>${row.usage_date}</td>
                            <td>${parseFloat(row.opening_reading).toFixed(2)}</td>
                            <td>${row.closing_reading ? parseFloat(row.closing_reading).toFixed(2) : '-'}</td>
                            <td>${liters || '-'}</td>
                            <td>${row.rate ? parseFloat(row.rate).toFixed(2) : '-'}</td>
                            <td>${amount || '-'}</td>
                        `;
                        tbody.appendChild(tr);
                    });
                    
                    document.getElementById('totalLiters').textContent = totalLiters.toFixed(2);
                    document.getElementById('totalAmount').textContent = `Rs ${totalAmount.toFixed(2)}`;
                    document.getElementById('avgRate').textContent = rateCount > 0 ? `Rs ${(totalRate / rateCount).toFixed(2)}` : 'Rs 0.00';
                } else {
                    document.getElementById('printTableBody').innerHTML = '<tr><td colspan="7">No data available</td></tr>';
                }
            } catch (error) {
                console.error('Error fetching data:', error);
                document.getElementById('printTableBody').innerHTML = '<tr><td colspan="7">Error loading data</td></tr>';
            }
        }
    </script>
</body>
</html>
