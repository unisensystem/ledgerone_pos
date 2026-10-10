<?php
// $enableCartonPriceEntry is set in product-add.php (this file is included there)
$enableCartonPriceEntry = $enableCartonPriceEntry ?? false;

$fields = [
    'code'               => 'Code',
    'company'            => 'Company',
    'category'           => 'Category',
    'subcategory'        => 'Sub-category',
    'inventoryAccount'   => 'Inventory Account',
    'vendor'             => 'Vendor',
    'parentProduct'      => 'Parent Product',
    'description'        => 'Description',
    'itemCompany'        => 'Item Company',
    'weight'             => 'Weight (per base unit)',
    'qrCode'             => 'QR Code',
    'barcode'            => 'Barcode',
    'photo'              => 'Photo',
    'status'             => 'Status',
    'stockAffects'       => 'Stock Affects',
    'invoiceAffects'     => 'Invoice Affects',
    'purchasePrice'      => 'Purchase Price',
    'wholesalePrice'     => 'Wholesale Price',
    'defaultDiscount'    => 'Default Discount',
    'taxation'           => 'Taxation Section',
    'stock'              => 'Stock Section',
    'minStock'           => 'Min Stock Level',
    'maxStock'           => 'Max Stock Level',
    'batchNo'            => 'Batch No',
    'batchExpiry'        => 'Batch Expiry Date',
    'additionalInfo'     => 'Additional Info Section',
    'manufacturingDate'  => 'Manufacturing Date',
    'expiryDate'         => 'Expiry Date',
];
?>
<!-- Customize Fields Modal -->
<div id="customizeFieldsModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3>Customize Visible Fields</h3>
            <button type="button" class="modal-close" id="closeCustomizeModal">&times;</button>
        </div>
        <div class="modal-body" style="max-height: 60vh; overflow-y: auto;">
            <div style="margin-bottom: 16px; padding-bottom: 16px; border-bottom: 1px solid var(--border, #dee2e6);">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" id="enableCartonPriceEntryToggle" <?= $enableCartonPriceEntry ? 'checked' : '' ?>>
                    Enter Trade Price / MRP by Carton/Dozen (instead of Piece)
                </label>
                <div class="helper-text" style="margin-top: 4px; margin-left: 24px;">Business-wide setting — applies to every product added by this account, not just this browser.</div>
            </div>
            <div style="margin-bottom: 16px;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" id="selectAllFields">
                    <strong>Select All</strong>
                </label>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <?php foreach ($fields as $key => $label): ?>
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" class="field-toggle" data-field="<?= htmlspecialchars($key) ?>" checked>
                    <?= htmlspecialchars($label) ?>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" id="cancelCustomize">Cancel</button>
            <button type="button" class="btn btn-primary" id="saveCustomize">Save Preferences</button>
        </div>
    </div>
</div>