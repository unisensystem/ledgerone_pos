<?php
require_once '../../../../includes/dashboard.php';
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Get user_id from session
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    // Redirect to login if no user_id in session
    header('Location: ../../auth/login.html');
    exit();
}

// Tenant-scoped feature flag: "Enter Trade Price / MRP By [unit]" — only enabled
// for the one client that needs Carton-based price entry, everyone else keeps
// the original Piece-only Financial Details form untouched.
$enableCartonPriceEntry = false;
$tenant_id = $_SESSION['tenant_id'] ?? null;
if ($tenant_id) {
    require_once '../../../../includes/connection.php';
    $flagStmt = $pdo->prepare("SELECT enabled FROM tenant_feature_flags WHERE tenant_id = ? AND feature_key = 'carton_price_entry'");
    $flagStmt->execute([$tenant_id]);
    $enableCartonPriceEntry = (bool) $flagStmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>LedgerOne ERP - Products Entry</title>
    <link rel="stylesheet" href="../../../assets/css/inventory/products/product-add.css?v=<?= filemtime(__DIR__ . '/../../../assets/css/inventory/products/product-add.css') ?>">
    <link rel="stylesheet" href="../../../assets/css/inventory/products/unit-modal.css">
    <script src="https://cdn.jsdelivr.net/npm/qrious@4.0.2/dist/qrious.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jsbarcode/3.11.5/JsBarcode.all.min.js"></script>
</head>
<body>
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
            <h1 style="margin-bottom: 0;">Product Entry</h1>
            <button type="button" class="btn btn-secondary" id="customizeFieldsBtn" style="height: 36px;">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-right: 4px;">
                    <path d="M1 8C1 8 3.5 3 8 3C12.5 3 15 8 15 8C15 8 12.5 13 8 13C3.5 13 1 8 1 8Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    <circle cx="8" cy="8" r="2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Customize Fields
            </button>
        </div>
        <p class="form-description">Add a new product or service to the system</p>
        
        <form id="productForm" class="card">
            <!-- Basic Information Section -->
            <div class="form-section">
                <h2 class="section-title">Basic Information</h2>
                
                <div class="form-row">
                    <div class="form-group full-width">
                        <label class="required">Product Type</label>
                        <div class="radio-group">
                            <div class="radio-option">
                                <input type="radio" id="physical" name="productType" value="physical" checked required>
                                <label for="physical">Physical Product</label>
                            </div>
                            <div class="radio-option">
                                <input type="radio" id="service" name="productType" value="service" required>
                                <label for="service">Service</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group" data-field="code">
                        <label>Code</label>
                        <input type="text" id="code" name="code" readonly placeholder="Auto-generated on save" class="optional-field">
                        <div class="helper-text">Product code will be generated automatically</div>
                    </div>
                    
                    <div class="form-group">
                        <label class="required">Name</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    
                    <div class="form-group optional-field" data-field="company">
                        <label>Company</label>
                        <select id="company" name="company">
                            <option value="">Select Company</option>
                        </select>
                    </div>
                    
                    <div class="form-group full-width">
                        <label class="required">UOM Type</label>
                        <div class="radio-group">
                            <div class="radio-option">
                                <input type="radio" id="uomTypeUnit" name="uomType" value="unit" checked required>
                                <label for="uomTypeUnit">Default Unit</label>
                            </div>
                            <div class="radio-option">
                                <input type="radio" id="uomTypeGroup" name="uomType" value="group" required>
                                <label for="uomTypeGroup">UOM Group</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group" id="defaultUnitGroup">
                        <label class="required">Default Unit</label>
                        <div class="input-with-buttons">
                            <select id="defaultUnit" name="defaultUnit" required>
                                <option value="">Select Unit</option>
                            </select>
                            <div class="input-buttons">
                                <button type="button" class="input-button" id="addUnit">+</button>
                                <button type="button" class="input-button" id="removeUnit">-</button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group" id="uomGroupField" style="display: none;">
                        <label class="required">UOM Group</label>
                        <div class="input-with-buttons">
                            <select id="uomGroup" name="uomGroup">
                                <option value="">Select UOM Group</option>
                            </select>
                            <div class="input-buttons">
                                <button type="button" class="input-button" id="addUomGroup">+</button>
                                <button type="button" class="input-button" id="removeUomGroup">-</button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group" id="productConversionFactorGroup" style="display: none;">
                        <label class="required">Conversion Factor</label>
                        <input type="number" id="productConversionFactor" name="productConversionFactor" step="0.000001" min="0" placeholder="e.g., 1000">
                        <div class="helper-text">Product-specific conversion factor for this unit</div>
                    </div>
                    
                    <div class="form-group optional-field" data-field="category">
                        <label>Assigned Category</label>
                        <div class="input-with-buttons">
                            <select id="category" name="category">
                                <option value="">Select Category</option>
                            </select>
                            <div class="input-buttons">
                                <button type="button" class="input-button" id="addCategory">+</button>
                                <button type="button" class="input-button" id="removeCategory">-</button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group optional-field" data-field="subcategory">
                        <label>Assigned Sub-category</label>
                        <div class="input-with-buttons">
                            <select id="subcategory" name="subcategory">
                                <option value="">Select Sub-category</option>
                            </select>
                            <div class="input-buttons">
                                <button type="button" class="input-button" id="addSubcategory">+</button>
                                <button type="button" class="input-button" id="removeSubcategory">-</button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group optional-field" data-field="inventoryAccount">
                        <label>Inventory Account Type</label>
                        <select id="inventoryAccount" name="inventoryAccount">
                            <option value="">Select Account</option>
                        </select>
                    </div>
                    
                    <div class="form-group optional-field" data-field="vendor">
                        <label>Vendor</label>
                        <select id="vendor" name="vendor">
                            <option value="">Select Vendor</option>
                        </select>
                    </div>
                    
                    <div class="form-group optional-field" data-field="parentProduct">
                        <label>Parent Product</label>
                        <div class="custom-dropdown">
                            <input type="text" id="parentProductSearch" class="parent-product-search" placeholder="Search parent products..." autocomplete="off">
                            <input type="hidden" id="parentProduct" name="parentProduct">
                            <div class="dropdown-list" id="parentProductDropdown" style="display: none;"></div>
                        </div>
                    </div>
                    
                    <div class="form-group full-width optional-field" data-field="description">
                        <label>Description</label>
                        <textarea id="description" name="description"></textarea>
                    </div>
                    
                    <div class="form-group full-width optional-field" id="qrCodeGroup" data-field="qrCode">
                        <label>QR Code</label>
                        <div class="form-row">
                            <div class="form-group">
                                <input type="text" id="qrInput" placeholder="Enter QR code value">
                                <input type="hidden" id="qrCodeHidden" name="qrCode">
                            </div>
                            <div class="form-group">
                                <div class="code-generation">
                                    <button type="button" class="btn btn-secondary" id="manualQR">Generate</button>
                                    <button type="button" class="btn btn-secondary" id="autoQR">Auto Generate</button>
                                </div>
                            </div>
                        </div>
                        <div class="code-display" id="qrDisplay" style="display: none;">
                            <div class="code-preview">
                                <canvas id="qrCanvas" width="150" height="150"></canvas>
                                <div class="code-value" id="qrValue"></div>
                            </div>
                            <button type="button" class="btn btn-ghost" id="clearQR">Clear</button>
                        </div>
                    </div>
                    
                    <div class="form-group full-width optional-field" id="barcodeGroup" data-field="barcode">
                        <label>Barcode</label>
                        <div class="form-row">
                            <div class="form-group">
                                <input type="text" id="barcodeInput" placeholder="Enter barcode value">
                                <input type="hidden" id="barcodeHidden" name="barcode">
                            </div>
                            <div class="form-group">
                                <div class="code-generation">
                                    <button type="button" class="btn btn-secondary" id="manualBarcode">Generate</button>
                                    <button type="button" class="btn btn-secondary" id="autoBarcode">Auto Generate</button>
                                </div>
                            </div>
                        </div>
                        <div class="code-display" id="barcodeDisplay" style="display: none;">
                            <div class="code-preview">
                                <canvas id="barcodeCanvas" width="200" height="80"></canvas>
                                <div class="code-value" id="barcodeValue"></div>
                            </div>
                            <button type="button" class="btn btn-ghost" id="clearBarcode">Clear</button>
                        </div>
                    </div>
                    
                    <div class="form-group full-width optional-field" data-field="photo">
                        <label>Attached Photo</label>
                        <div class="photo-upload" id="photoUpload">
                            <p>Click to upload product photo (Max 5MB)</p>
                            <input type="file" id="photo" name="photo" accept=".png,.jpg,.jpeg,.gif,.pdf,.webp,.avif" style="display: none;">
                            <img id="photoPreview" class="photo-preview" alt="Product photo preview">
                            <button type="button" class="btn btn-danger" id="removePhoto" style="display: none; position: absolute; top: 5px; right: 5px; width: 30px; height: 30px; padding: 0; border-radius: 50%;">×</button>
                        </div>
                    </div>
                    
                    <div class="form-group optional-field" data-field="status">
                        <label>Status</label>
                        <div class="checkbox-group">
                            <input type="checkbox" id="active" name="active" checked>
                            <label for="active">Active</label>
                        </div>
                    </div>
                    
                    <div class="form-group optional-field" data-field="stockAffects">
                        <label>Stock Affects?</label>
                        <div class="checkbox-group">
                            <input type="checkbox" id="stockAffects" name="stockAffects" checked>
                            <label for="stockAffects">Yes</label>
                        </div>
                    </div>
                    
                    <div class="form-group optional-field" data-field="invoiceAffects">
                        <label>Invoice Affects?</label>
                        <div class="checkbox-group">
                            <input type="checkbox" id="invoiceAffects" name="invoiceAffects" checked>
                            <label for="invoiceAffects">Yes</label>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Financial Details Section -->
            <div class="form-section">
                <h2 class="section-title">Financial Details</h2>
                
                <div class="form-row">
                    <div class="form-group optional-field" id="purchasePriceGroup" data-field="purchasePrice">
                        <label>Purchase Price / Selected Unit</label>
                        <input type="number" id="purchasePrice" name="purchasePrice" step="0.001" min="0">
                    </div>
                    
                    <div class="form-group" id="priceEntryUnitGroup" style="display: none;">
                        <label>Enter Trade Price / MRP By</label>
                        <select id="priceEntryUnit">
                            <option value="">Select Unit</option>
                        </select>
                        <div class="helper-text">System will store the equivalent Piece rate automatically.</div>
                    </div>

                    <div class="form-group">
                        <label id="tradePriceLabel">Trade Price (TP) / Selected Unit</label>
                        <input type="number" id="tradePrice" name="tradePrice" step="0.001" min="0">
                    </div>

                    <div class="form-group optional-field" data-field="wholesalePrice">
                        <label>Wholesale Price / Selected Unit</label>
                        <input type="number" id="wholesalePrice" name="wholesalePrice" step="0.001" min="0">
                    </div>

                    <div class="form-group">
                        <label id="mrpLabel">Maximum Retail Price (MRP) / Selected Unit</label>
                        <input type="number" id="mrp" name="mrp" step="0.001" min="0">
                    </div>
                    
                    <div class="form-group optional-field" data-field="defaultDiscount">
                        <label>Default Discount (%)</label>
                        <input type="number" id="defaultDiscount" name="defaultDiscount" step="0.01" min="0" max="100">
                    </div>
                    


                </div>
            </div>
            
            <!-- Taxation Section -->
            <div class="form-section optional-field" data-field="taxation">
                <h2 class="section-title">Taxation</h2>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Sales Tax Type</label>
                        <div class="custom-dropdown">
                            <input type="text" id="salesTaxTypeSearch" class="tax-regime-search" placeholder="Search tax types..." autocomplete="off">
                            <input type="hidden" id="salesTaxType" name="salesTaxType">
                            <div class="dropdown-list" id="taxRegimeDropdown" style="display: none;"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Stock Section -->
            <div class="form-section optional-field" id="stockSection" data-field="stock">
                <h2 class="section-title">Stock</h2>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Min Stock Level</label>
                        <input type="number" id="minStock" name="minStock" step="0.01" min="0">
                    </div>
                    
                    <div class="form-group">
                        <label>Max Stock Level</label>
                        <input type="number" id="maxStock" name="maxStock" step="0.01" min="0">
                    </div>
                </div>
                
                <div class="stock-opening-section">
                    <h3 class="subsection-title">Branch-wise Opening Stock</h3>
                  <div class="stock-entries" id="stockEntries">
    <div class="stock-entry">
        <div class="form-row">
            <div class="form-group">
                <label>Branch</label>
                <div class="custom-dropdown">
                    <input type="text" class="branch-search" placeholder="Search branches..." autocomplete="off">
                    <input type="hidden" name="branch[]">
                    <div class="dropdown-list" style="display: none;"></div>
                </div>
            </div>
            <div class="form-group">
                <label>Opening Qty</label>
                <input type="number" name="openingQty[]" step="0.01" min="0">
            </div>
            <div class="form-group">
                <label>Opening Price / Unit</label>
                <input type="number" name="openingPrice[]" step="0.01" min="0">
            </div>
            <div class="form-group">
                <label>Batch No</label>
                <input type="text" name="batchNo[]" placeholder="e.g., B-2026-001">
            </div>
            <div class="form-group">
                <label>Batch Expiry Date</label>
                <input type="date" name="batchExpiryDate[]">
            </div>
            <div class="form-group">
                <button type="button" class="btn btn-danger remove-stock-entry" style="margin-top: 24px;">Remove</button>
            </div>
        </div>
    </div>
</div>
                    <button type="button" class="btn btn-secondary" id="addStockEntry">Add Another Branch</button>
                    
                    <div class="total-stock-summary">
                        <h4 class="summary-title">Total Stock Opening</h4>
                        <div class="summary-row">
                            <div class="summary-item">
                                <label>Total Quantity</label>
                                <div class="summary-value" id="totalQty">0.00</div>
                            </div>
                            <div class="summary-item">
                                <label>Weighted Avg Price</label>
                                <div class="summary-value" id="avgPrice">0.00</div>
                            </div>
                            <div class="summary-item">
                                <label>Total Value</label>
                                <div class="summary-value" id="totalValue">0.00</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Additional Information Section -->
            <div class="form-section optional-field" id="additionalInfoSection" data-field="additionalInfo">
                <h2 class="section-title">Additional Information</h2>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Manufacturing Date</label>
                        <input type="date" id="manufacturingDate" name="manufacturingDate">
                    </div>
                    
                    <div class="form-group">
                        <label>Expiry Date</label>
                        <input type="date" id="expiryDate" name="expiryDate">
                    </div>
                </div>
            </div>
            
            <!-- Form Actions -->
            <div class="form-section">
                <div class="button-group">
                    <button type="button" class="btn btn-secondary" id="cancelBtn">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">Save Product</button>
                </div>
            </div>
        </form>
    </div>

    <?php include 'unit-modal.php'; ?>
    <?php include 'uom-group-modal.php'; ?>
    <?php include 'category-modal.php'; ?>
    <?php include 'confirm-modal.php'; ?>
    <?php include 'customize-fields-modal.php'; ?>
    <?php include 'scheme-modal.php'; ?>

    <script>window.ENABLE_CARTON_PRICE_ENTRY = <?= $enableCartonPriceEntry ? 'true' : 'false' ?>;</script>
    <script src="../../../assets/js/inventory/products/scheme-functions.js"></script>
    <script src="../../../assets/js/inventory/products/product-add.js?v=<?= filemtime(__DIR__ . '/../../../assets/js/inventory/products/product-add.js') ?>"></script>
</body>
</html>