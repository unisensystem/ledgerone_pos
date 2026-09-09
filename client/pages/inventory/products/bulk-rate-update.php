<?php
require_once '../../../../includes/dashboard.php';
if (session_status() == PHP_SESSION_NONE) session_start();
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) { header('Location: ../../auth/login.html'); exit(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>LedgerOne ERP - Bulk Rate Update</title>
    <link rel="stylesheet" href="../../../assets/css/inventory/products/product-add.css">
    <style>
        .container { max-width: 1300px; }
.custom-dropdown .dropdown-item.active {
    background: #e3f2fd;
}

.rate-table input[type="text"].extra-input,
.rate-table input[type="date"].extra-input {
    width: 100%;
    padding: 7px 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 13px;
    font-family: inherit;
    box-sizing: border-box;
}

.rate-table input.extra-input.changed {
    border-color: #f59e0b;
    background: #fffbeb;
}
        .rate-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .rate-table thead tr {
            background: #f5f5f5;
            border-bottom: 2px solid #ddd;
        }

        .rate-table th {
            padding: 11px 12px;
            text-align: left;
            font-weight: 600;
            color: #333;
            white-space: nowrap;
            border-right: 1px solid #ddd;
        }

        .rate-table th:last-child { border-right: none; }

        .rate-table tbody tr { border-bottom: 1px solid #eee; }
        .rate-table tbody tr:hover { background: #fafafa; }

        .rate-table td {
            padding: 8px 10px;
            border-right: 1px solid #eee;
            vertical-align: middle;
        }

        .rate-table td:last-child { border-right: none; }

        .rate-table input[type="number"] {
            width: 100%;
            padding: 7px 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
            font-family: inherit;
            box-sizing: border-box;
        }

        .rate-table input[type="number"]:focus {
            outline: none;
            border-color: #1976d2;
            box-shadow: 0 0 0 2px rgba(25,118,210,0.1);
        }

        .rate-table input.changed {
            border-color: #f59e0b;
            background: #fffbeb;
        }

        .product-code { font-size: 11px; color: #999; margin-top: 2px; }

        /* product search dropdown */
        .custom-dropdown { position: relative; }

        .custom-dropdown input[type="text"] {
            width: 100%;
            padding: 7px 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
            font-family: inherit;
            box-sizing: border-box;
        }

        .custom-dropdown input[type="text"]:focus {
            outline: none;
            border-color: #1976d2;
        }

        .custom-dropdown .dropdown-list {
            position: absolute;
            top: 100%;
            left: 0;
            min-width: 100%;
            background: white;
            border: 1px solid #ddd;
            max-height: 220px;
            overflow-y: auto;
            z-index: 9999;
            display: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            border-radius: 0 0 4px 4px;
        }

        .custom-dropdown .dropdown-item {
            padding: 8px 10px;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
            font-size: 13px;
        }

        .custom-dropdown .dropdown-item:hover { background: #f5f5f5; }

        .remove-btn {
            background: #f44336;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }

        .remove-btn:hover { background: #d32f2f; }

        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: #aaa;
            font-size: 14px;
        }

        .action-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        #toast {
            display: none;
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 14px 20px;
            border-radius: 6px;
            color: white;
            font-size: 14px;
            z-index: 3000;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body>
<div class="container">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
        <h1 style="margin-bottom:0;">Bulk Rate Update</h1>
        <a href="product-list.php" class="btn btn-secondary" style="height:36px; line-height:36px; text-decoration:none; display:inline-flex; align-items:center;">
            ← Back to Products
        </a>
    </div>
    <p class="form-description">Search and add products to update their rates in bulk</p>

    <form id="bulkRateForm" class="card">

        <div style="overflow: visible;">
            <table class="rate-table" id="rateTable">
               <thead>
    <tr>
        <th style="width:40px;">#</th>
        <th style="min-width:220px;">Product</th>
        <th style="min-width:130px;">Purchase Price</th>
        <th style="min-width:130px;">Trade Price (TP)</th>
        <th style="min-width:130px;">Wholesale Price</th>
        <th style="min-width:130px;">MRP</th>
        <th style="min-width:120px;">Batch No</th>
        <th style="min-width:140px;">Expiry Date</th>
        <th style="width:80px;"></th>
    </tr>
</thead>
<tbody id="rateTableBody">
    <tr id="emptyRow">
        <td colspan="9" class="empty-state">Click "Add Row" to search and add products</td>
    </tr>
</tbody>
            </table>
        </div>

        <button type="button" class="btn btn-secondary" id="addRowBtn" style="margin-top:16px;">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-right:6px;">
                <path d="M8 3.33333V12.6667" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M3.33325 8H12.6666" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Add Row
        </button>

        <div class="action-buttons">
            <button type="button" class="btn btn-secondary" onclick="location.href='product-list.php'">Cancel</button>
            <button type="submit" class="btn btn-primary" id="saveBtn">Save Rates</button>
        </div>
    </form>
</div>

<div id="toast"></div>
<script src="../../../assets/js/inventory/products/bulk-rate-update.js"></script>
</body>
</html>
