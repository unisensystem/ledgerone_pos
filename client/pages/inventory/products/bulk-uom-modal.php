<!-- Bulk UOM Assignment Modal -->
<div id="bulkUomModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 520px;">
        <div class="modal-header">
            <h2 id="bulkUomModalTitle">Assign UOM to Products</h2>
            <button type="button" class="close-btn" id="closeBulkUomModal">&times;</button>
        </div>
        <form id="bulkUomForm">
            <div class="modal-body" style="padding: 24px;">
                <div class="form-group" id="selectedProductsInfo" style="display: none; margin-bottom: 20px;">
                    <div style="padding: 16px; background: #f0f7ff; border-radius: 8px; border: 1px solid #d0e7ff;">
                        <div style="font-size: 13px; color: #666; margin-bottom: 6px;">Selected Products</div>
                        <div style="font-size: 20px; font-weight: 600; color: #1f7bff;"><span id="bulkSelectedCount">0</span> product(s)</div>
                    </div>
                </div>
                
                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="required" style="display: block; margin-bottom: 8px; font-weight: 500;">UOM Type</label>
                    <div class="radio-group" style="display: flex; gap: 16px;">
                        <div class="radio-option" style="display: flex; align-items: center; gap: 6px;">
                            <input type="radio" id="bulkUomTypeUnit" name="bulkUomType" value="unit" checked required>
                            <label for="bulkUomTypeUnit" style="margin: 0; font-weight: normal; cursor: pointer;">Default Unit</label>
                        </div>
                        <div class="radio-option" style="display: flex; align-items: center; gap: 6px;">
                            <input type="radio" id="bulkUomTypeGroup" name="bulkUomType" value="group" required>
                            <label for="bulkUomTypeGroup" style="margin: 0; font-weight: normal; cursor: pointer;">UOM Group</label>
                        </div>
                    </div>
                </div>
                
                <div class="form-group" id="bulkDefaultUnitGroup" style="margin-bottom: 20px;">
                    <label class="required" style="display: block; margin-bottom: 8px; font-weight: 500;">Default Unit</label>
                    <select id="bulkDefaultUnit" name="bulkDefaultUnit" required style="width: 100%; padding: 10px 12px; border: 1.5px solid #ddd; border-radius: 6px; font-size: 14px;">
                        <option value="">Select Unit</option>
                    </select>
                </div>
                
                <div class="form-group" id="bulkUomGroupField" style="display: none; margin-bottom: 20px;">
                    <label class="required" style="display: block; margin-bottom: 8px; font-weight: 500;">UOM Group</label>
                    <select id="bulkUomGroup" name="bulkUomGroup" style="width: 100%; padding: 10px 12px; border: 1.5px solid #ddd; border-radius: 6px; font-size: 14px;">
                        <option value="">Select UOM Group</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer" style="padding: 16px 24px; border-top: 1px solid #eee; display: flex; justify-content: flex-end; gap: 12px;">
                <button type="button" class="btn btn-secondary" id="cancelBulkUom">Cancel</button>
                <button type="submit" class="btn btn-primary" id="saveBulkUom">Assign UOM</button>
            </div>
        </form>
    </div>
</div>
