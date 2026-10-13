<?php
require_once '../../../../includes/dashboard.php';
if (session_status() == PHP_SESSION_NONE) session_start();

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header('Location: ../../auth/login.html');
    exit();
}

require_once '../../../../includes/connection.php';
$stmt = $pdo->prepare("SELECT c.symbol FROM tenant_currencies tc JOIN ledgerone_public.currencies c ON tc.currency_id = c.id WHERE tc.tenant_id = ? AND tc.is_base_currency = 1");
$stmt->execute([$_SESSION['tenant_id']]);
$currency = $stmt->fetch();
$currency_symbol = $currency['symbol'] ?? '$';

$userStmt = $pdo->prepare("SELECT full_name, employee_id FROM users WHERE id = ?");
$userStmt->execute([$user_id]);
$user = $userStmt->fetch();
$user_full_name = $user['full_name'] ?? 'Unknown';
$user_employee_id = $user['employee_id'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LedgerOne ERP - POS Return</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #1F7BFF;
            --primary-hover: #1A6CDC;
            --surface-0: #FFFFFF;
            --surface-1: #F7F9FC;
            --surface-2: #EFF2F7;
            --heading: #0E1A2B;
            --body: #2F3B4C;
            --subtext: #6B7280;
            --border-default: #E1E6EE;
            --border-strong: #C9CFDA;
            --success: #2FBF71;
            --warning: #E8B23F;
            --error: #E34F4F;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', system-ui, sans-serif; font-size: 13px; }
        body { background: var(--surface-1); color: var(--body); padding: 8px; }
        .container { display: grid; grid-template-columns: 320px 1fr; gap: 8px; max-height: calc(100vh - 20px); }
        .left-panel, .right-panel { display: flex; flex-direction: column; gap: 8px; }
        .card { background: white; padding: 12px; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .header-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; }
        .header-item { display: flex; flex-direction: column; }
        .header-label { font-size: 11px; color: var(--subtext); font-weight: 500; margin-bottom: 2px; }
        .header-value { font-weight: 600; color: var(--heading); font-size: 13px; }
        .return-no { font-size: 14px; color: var(--primary); font-weight: 700; }
        .form-group { display: flex; flex-direction: column; margin-bottom: 8px; }
        .form-label { font-size: 11px; color: var(--subtext); font-weight: 500; margin-bottom: 2px; }
        input, select { height: 32px; padding: 0 8px; border: 1.5px solid var(--border-strong); border-radius: 4px; background: #F4F8FF; font-size: 13px; width: 100%; color: var(--heading); font-weight: 700; }
        input:focus, select:focus { outline: none; border-color: var(--primary); background: white; box-shadow: 0 0 0 2px rgba(31,123,255,0.15); }
        .items-container { background: white; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); flex: 1; display: flex; flex-direction: column; overflow: hidden; }
        .items-header { padding: 8px 12px; background: var(--surface-1); border-bottom: 1px solid var(--border-default); display: grid; grid-template-columns: 30px 120px 60px 60px 70px 70px 70px 70px 70px 60px 80px 40px; gap: 4px; align-items: center; }
        .items-body { overflow-y: auto; max-height: calc(100vh - 400px); flex: 1; min-height: 200px; }
        .item-row { padding: 6px 12px; border-bottom: 1px solid var(--border-default); display: grid; grid-template-columns: 30px 120px 60px 60px 70px 70px 70px 70px 70px 60px 80px 40px; gap: 4px; align-items: center; transition: background 0.2s; }
        .item-row:hover { background: #F0F6FF; }
        .item-row input, .item-row select { height: 28px; padding: 0 4px; border: 1.5px solid var(--border-strong); border-radius: 3px; background: #F4F8FF; font-size: 12px; color: var(--heading); font-weight: 700; }
        .item-row input:focus, .item-row select:focus { background: white; border-color: var(--primary); }
        .item-row .readonly { background: var(--surface-2); border: none; padding: 4px; text-align: right; font-weight: 700; color: var(--heading); }
        .col-header { font-weight: 600; color: var(--heading); font-size: 11px; white-space: nowrap; }
        .quick-entry { background: white; padding: 8px 12px; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); display: grid; grid-template-columns: 1fr 80px 80px 40px; gap: 8px; align-items: end; }
        .summary-panel { background: white; padding: 12px; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; }
        .summary-item { display: flex; flex-direction: column; }
        .summary-label { font-size: 11px; color: var(--subtext); margin-bottom: 2px; }
        .summary-value { font-weight: 600; color: var(--heading); }
        .total-amount { color: var(--primary); font-size: 14px; }
        .action-buttons { display: grid; grid-template-columns: repeat(4, 1fr); gap: 4px; }
        .btn { height: 32px; border-radius: 4px; font-size: 12px; font-weight: 500; padding: 0 8px; cursor: pointer; border: none; display: flex; align-items: center; justify-content: center; gap: 4px; white-space: nowrap; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-hover); }
        .btn-secondary { background: var(--surface-2); color: var(--heading); border: 1px solid var(--border-default); }
        .btn-secondary:hover { background: #E4E8EF; }
        .btn-success { background: var(--success); color: white; }
        .btn-danger { background: var(--error); color: white; }
        .btn-micro { height: 24px; width: 24px; padding: 0; border-radius: 3px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .hidden { display: none; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: var(--surface-2); border-radius: 3px; }
        ::-webkit-scrollbar-thumb { background: var(--border-default); border-radius: 3px; }
        .dropdown-options { position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid var(--border-default); border-radius: 4px; max-height: 200px; overflow-y: auto; z-index: 1000; box-shadow: 0 4px 8px rgba(0,0,0,0.1); display: none; }
        .dropdown-option { padding: 8px 12px; cursor: pointer; border-bottom: 1px solid var(--border-default); }
        .dropdown-option:hover { background: var(--surface-1); }
        .suggestion-item { padding: 8px 12px; cursor: pointer; border-bottom: 1px solid var(--border-default); background: white; }
        .suggestion-item:hover, .suggestion-item.selected { background: var(--surface-1); }
    </style>
</head>
<body>
    <div class="container">
        <div class="left-panel">
            <div class="card">
                <div class="header-grid">
                    <div class="header-item">
                        <div class="header-label">RETURN NO</div>
                        <div class="header-value return-no" id="returnNo">SR-0001</div>
                    </div>
                    <div class="header-item">
                        <div class="header-label">DATE</div>
                        <input type="date" id="currentDate" style="font-weight: 600; border: 1px solid var(--border-default); border-radius: 4px; padding: 0 8px; height: 32px;">
                    </div>
                    <div class="header-item">
                        <div class="header-label">COMPANY <span style="color: var(--error);">*</span></div>
                        <select id="company" required style="font-weight: 600; border: 1px solid var(--border-default); border-radius: 4px; padding: 0 8px; height: 32px; width: 100%;">
                            <option value="">Select Company</option>
                        </select>
                    </div>
                    <div class="header-item">
                        <div class="header-label">PROJECT</div>
                        <div style="position: relative;">
                            <input type="text" id="projectSearch" placeholder="Search project..." autocomplete="off" style="font-weight: 600;">
                            <div id="projectOptions" class="dropdown-options"></div>
                            <input type="hidden" id="project">
                        </div>
                    </div>
                    <div class="header-item">
                        <div class="header-label">BRANCH</div>
                        <div style="position: relative;">
                            <input type="text" id="branchSearch" placeholder="Search branch..." autocomplete="off" style="font-weight: 600;">
                            <div id="branchOptions" class="dropdown-options"></div>
                            <input type="hidden" id="branch">
                        </div>
                    </div>
                    <div class="header-item">
                        <div class="header-label">SALES OFFICER</div>
                        <div style="position: relative;">
                            <input type="text" id="salesOfficerSearch" placeholder="Search officer..." autocomplete="off" style="font-weight: 600;">
                            <div id="salesOfficerOptions" class="dropdown-options"></div>
                            <input type="hidden" id="salesOfficer">
                        </div>
                    </div>
                    <div class="header-item">
                        <div class="header-label">SUPPLIER MAN</div>
                        <div style="position: relative;">
                            <input type="text" id="supplierManSearch" placeholder="Search supplier man..." autocomplete="off" style="font-weight: 600;">
                            <div id="supplierManOptions" class="dropdown-options"></div>
                            <input type="hidden" id="supplierMan">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="form-group">
                    <div class="form-label">PRICE TYPE</div>
                    <div style="display: flex; gap: 12px; height: 32px; align-items: center;">
                        <label style="display: flex; align-items: center; gap: 4px; cursor: pointer; margin: 0;">
                            <input type="radio" name="priceType" value="mrp" style="width: auto;">
                            <span style="font-size: 12px;">MRP</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 4px; cursor: pointer; margin: 0;">
                            <input type="radio" name="priceType" value="tp" checked style="width: auto;">
                            <span style="font-size: 12px;">TP</span>
                        </label>
                    </div>
                </div>
                <div class="form-group">
                    <div class="form-label">CUSTOMER</div>
                    <div style="position: relative;">
                        <input type="text" id="customerSearch" placeholder="Search by name, code or phone..." autocomplete="off" style="font-weight: 600;">
                        <div id="customerOptions" class="dropdown-options"></div>
                        <input type="hidden" id="customer">
                    </div>
                </div>
                <div class="form-group">
                    <div class="form-label">CUSTOMER PHONE</div>
                    <div style="position: relative;">
                        <input type="text" id="customerPhone" placeholder="Phone number — search to find by past invoice" autocomplete="off">
                        <div id="customerPhoneOptions" class="dropdown-options"></div>
                    </div>
                </div>
                <div class="form-group">
                    <div class="form-label">SUB ACCOUNT (Optional)</div>
                    <div style="position: relative;">
                        <input type="text" id="subAccountSearch" placeholder="Search sub account..." autocomplete="off">
                        <div id="subAccountOptions" class="dropdown-options"></div>
                        <input type="hidden" id="subAccount">
                    </div>
                </div>
                <div class="form-group">
                    <div class="form-label">SALE INVOICE (Optional)</div>
                    <div style="position: relative;">
                        <input type="text" id="invoiceSearch" placeholder="Search invoice..." autocomplete="off">
                        <div id="invoiceOptions" class="dropdown-options"></div>
                        <input type="hidden" id="saleInvoice">
                    </div>
                </div>
                <div class="form-group">
                    <div class="form-label">PAYMENT METHOD</div>
                    <select id="paymentMethod">
                        <option value="cash" selected>Cash</option>
                        <option value="bank_transfer">Bank Transfer</option>
                    </select>
                </div>
                <div class="form-group hidden" id="bankAccountContainer">
                    <div class="form-label">BANK ACCOUNT</div>
                    <select id="bankAccount"><option value="">Loading...</option></select>
                </div>
                <div class="form-group">
                    <div class="form-label">AMOUNT REFUNDED</div>
                    <input type="number" id="amountRefunded" value="0" step="0.01">
                </div>
            </div>
        </div>

        <div class="right-panel">
            <div class="quick-entry">
                <div class="form-group" style="position: relative; flex: 1; margin: 0;">
                    <div class="form-label">QUICK ADD PRODUCT (ENTER to add)</div>
                    <input type="text" id="quickProduct" placeholder="Scan barcode or type product name..." autofocus autocomplete="off" style="flex: 1;">
                    <div id="productSuggestions" class="dropdown-options"></div>
                </div>
                <div class="form-group" style="margin: 0;">
                    <div class="form-label">QTY</div>
                    <input type="number" id="quickQty" value="1" min="1" step="1">
                </div>
                <button class="btn btn-primary btn-micro" id="quickAddBtn" title="Add Item (ENTER)">
                    <i class="fas fa-plus"></i>
                </button>
                <label style="display: flex; align-items: center; gap: 4px; cursor: pointer; margin: 0; grid-column: 1 / -1; font-size: 11px; color: var(--subtext);">
                    <input type="checkbox" id="toggleBatchColumns" style="width: auto;">
                    Show Batch No / Expiry Date columns
                </label>
            </div>

            <div class="items-container">
                <div class="items-header" id="itemsHeader">
                    <div class="col-header text-center">#</div>
                    <div class="col-header">PRODUCT</div>
                    <!-- Dynamic unit columns will be inserted here -->
                    <div class="col-header text-right">PRICE</div>
                    <div class="col-header text-right">GROSS</div>
                    <div class="col-header text-right">DISC%</div>
                    <div class="col-header text-right">TAX%</div>
                    <div class="col-header text-right">TAX AMT</div>
                    <div class="col-header text-center">STATUS</div>
                    <div class="col-header text-right">T.O AMT</div>
                    <div class="col-header text-right">FOC QTY</div>
                    <div class="col-header text-right">NET</div>
                    <div class="col-header text-center">ACT</div>
                </div>
                <div class="items-body" id="itemsBody"></div>
                <div id="itemsFooter" style="padding: 8px 12px; background: var(--surface-2); border-top: 2px solid var(--border-strong); display: grid; gap: 4px; align-items: center; font-weight: 600;">
                    <div></div>
                    <div>TOTALS</div>
                    <!-- Dynamic unit totals will be inserted here -->
                    <div></div>
                    <div class="text-right" id="totalGross">0.00</div>
                    <div></div>
                    <div></div>
                    <div class="text-right" id="totalTaxPercent">0.00</div>
                    <div class="text-right" id="totalTaxAmount">0.00</div>
                    <div></div>
                    <div class="text-right" id="totalToAmt">0.00</div>
                    <div class="text-right" id="totalFocQty">0.00</div>
                    <div class="text-right" id="totalNet">0.00</div>
                    <div></div>
                </div>
            </div>

            <div class="summary-panel">
                <div class="summary-item">
                    <div class="summary-label">TOTAL BILL</div>
                    <div class="summary-value" id="totalBill">$0.00</div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">DISCOUNT</div>
                    <div class="summary-value" id="totalDiscount">$0.00</div>
                </div>
                <div class="summary-item" id="extraDiscount1Item" style="display:none;">
                    <div class="summary-label">EXTRA DISC 1</div>
                    <div class="summary-value" id="extraDiscount1Amt">$0.00</div>
                </div>
                <div class="summary-item" id="extraDiscount2Item" style="display:none;">
                    <div class="summary-label">EXTRA DISC 2</div>
                    <div class="summary-value" id="extraDiscount2Amt">$0.00</div>
                </div>
                <div class="summary-item" id="shippingFeesItem" style="display:none;">
                    <div class="summary-label">SHIPPING FEES</div>
                    <div class="summary-value" id="shippingFeesAmt">$0.00</div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">NET AMOUNT</div>
                    <div class="summary-value total-amount" id="netAmount">$0.00</div>
                </div>
                <!-- Invoice-level taxes populated dynamically from original sale invoice -->
                <div id="invoiceLevelTaxesContainer" style="display: contents;"></div>
                <div class="summary-item" id="netReceivableItem" style="display: none;">
                    <div class="summary-label">NET RECEIVABLE</div>
                    <div class="summary-value" style="color: var(--success);" id="netReceivable">$0.00</div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">REFUNDED</div>
                    <div class="summary-value" id="summaryRefunded">$0.00</div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">BALANCE</div>
                    <div class="summary-value" id="balanceAmount">$0.00</div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">ITEMS</div>
                    <div class="summary-value" id="itemCount">0</div>
                </div>
            </div>

            <div style="background: white; padding: 12px; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; align-items: end;">
                <div class="form-group" style="margin: 0;">
                    <div class="form-label">RETURN DISC %</div>
                    <input type="number" id="returnDiscountPercent" value="0" min="0" max="100" step="0.1">
                </div>
                <div class="form-group" style="margin: 0;">
                    <div class="form-label">RETURN DISC AMT</div>
                    <input type="number" id="returnDiscountAmount" value="0" step="0.01">
                </div>
            </div>

            <div class="action-buttons">
                <button class="btn btn-primary" id="saveReturnBtn">
                    <i class="fas fa-save"></i> SAVE (F10)
                </button>
                <button class="btn btn-secondary" id="clearBtn">
                    <i class="fas fa-trash-alt"></i> CLEAR
                </button>
                <button class="btn btn-secondary" id="shortcutsBtn">
                    <i class="fas fa-keyboard"></i> SHORTCUTS
                </button>
                <button class="btn btn-secondary" onclick="window.location.href='return-list.php'">
                    <i class="fas fa-list"></i> VIEW LIST
                </button>
                <button class="btn btn-danger" onclick="window.close()">
                    <i class="fas fa-times"></i> CLOSE
                </button>
            </div>
        </div>
    </div>

    <!-- Shortcuts Modal -->
    <div id="shortcutsModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000; align-items: center; justify-content: center;">
        <div style="background: white; padding: 24px; border-radius: 8px; max-width: 600px; width: 90%;">
            <h3 style="margin-bottom: 16px; color: var(--heading);">Keyboard Shortcuts</h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div>
                    <label style="font-size: 12px; color: var(--subtext);">Focus Product:</label>
                    <input type="text" id="shortcutProduct" value="F1" style="width: 100%; padding: 8px; border: 1px solid var(--border-default); border-radius: 4px;">
                </div>
                <div>
                    <label style="font-size: 12px; color: var(--subtext);">Focus Customer:</label>
                    <input type="text" id="shortcutCustomer" value="F2" style="width: 100%; padding: 8px; border: 1px solid var(--border-default); border-radius: 4px;">
                </div>
                <div>
                    <label style="font-size: 12px; color: var(--subtext);">Focus Branch:</label>
                    <input type="text" id="shortcutBranch" value="F3" style="width: 100%; padding: 8px; border: 1px solid var(--border-default); border-radius: 4px;">
                </div>
                <div>
                    <label style="font-size: 12px; color: var(--subtext);">Focus Quantity:</label>
                    <input type="text" id="shortcutQty" value="F4" style="width: 100%; padding: 8px; border: 1px solid var(--border-default); border-radius: 4px;">
                </div>
                <div>
                    <label style="font-size: 12px; color: var(--subtext);">Save Return:</label>
                    <input type="text" id="shortcutSave" value="F10" style="width: 100%; padding: 8px; border: 1px solid var(--border-default); border-radius: 4px;">
                </div>
                <div>
                    <label style="font-size: 12px; color: var(--subtext);">Clear Return:</label>
                    <input type="text" id="shortcutClear" value="F12" style="width: 100%; padding: 8px; border: 1px solid var(--border-default); border-radius: 4px;">
                </div>
            </div>
            <div style="margin-top: 16px; display: flex; gap: 8px; justify-content: flex-end;">
                <button class="btn btn-secondary" id="resetShortcutsBtn">Reset to Default</button>
                <button class="btn btn-secondary" id="closeShortcutsBtn">Close</button>
                <button class="btn btn-primary" id="saveShortcutsBtn">Save</button>
            </div>
        </div>
    </div>

    <script>const userEmployeeId = <?php echo json_encode($user_employee_id); ?>;</script>
    <script src="../../../assets/js/sale/sale_return/counter-return-uom.js?v=<?php echo time(); ?>"></script>
    <script src="../../../assets/js/sale/sale_return/counter-return.js?v=<?php echo time(); ?>"></script>
</body>
</html>