<!-- Customize Fields Modal -->
<div id="customizeFieldsModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h3>Customize Visible Fields</h3>
            <button type="button" class="modal-close" id="closeCustomizeModal">&times;</button>
        </div>
        <div class="modal-body" style="max-height: 60vh; overflow-y: auto;">
            <div style="margin-bottom: 16px;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" id="selectAllFields">
                    <strong>Select All</strong>
                </label>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" class="field-toggle" data-field="code" checked>
                    Code
                </label>
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" class="field-toggle" data-field="category" checked>
                    Category
                </label>
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" class="field-toggle" data-field="subcategory" checked>
                    Sub-category
                </label>
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" class="field-toggle" data-field="inventoryAccount" checked>
                    Inventory Account
                </label>
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" class="field-toggle" data-field="vendor" checked>
                    Vendor
                </label>
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" class="field-toggle" data-field="parentProduct" checked>
                    Parent Product
                </label>
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" class="field-toggle" data-field="description" checked>
                    Description
                </label>
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" class="field-toggle" data-field="qrCode" checked>
                    QR Code
                </label>
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" class="field-toggle" data-field="barcode" checked>
                    Barcode
                </label>
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" class="field-toggle" data-field="photo" checked>
                    Photo
                </label>
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" class="field-toggle" data-field="status" checked>
                    Status
                </label>
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" class="field-toggle" data-field="stockAffects" checked>
                    Stock Affects
                </label>
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" class="field-toggle" data-field="invoiceAffects" checked>
                    Invoice Affects
                </label>
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" class="field-toggle" data-field="purchasePrice" checked>
                    Purchase Price
                </label>
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" class="field-toggle" data-field="wholesalePrice" checked>
                    Wholesale Price
                </label>
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" class="field-toggle" data-field="defaultDiscount" checked>
                    Default Discount
                </label>
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" class="field-toggle" data-field="tradeOfferDiscount" checked>
                    Trade Offer Discount
                </label>
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" class="field-toggle" data-field="defaultFoc" checked>
                    Default FOC
                </label>
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" class="field-toggle" data-field="cartonConversion" checked>
                    Carton Conversion
                </label>
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" class="field-toggle" data-field="taxation" checked>
                    Taxation Section
                </label>
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" class="field-toggle" data-field="stock" checked>
                    Stock Section
                </label>
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" class="field-toggle" data-field="additionalInfo" checked>
                    Additional Info Section
                </label>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" id="cancelCustomize">Cancel</button>
            <button type="button" class="btn btn-primary" id="saveCustomize">Save Preferences</button>
        </div>
    </div>
</div>
