<!-- Unit Modal -->
<div id="unitModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="unitModalTitle">Add New Unit</h3>
            <button type="button" class="modal-close" id="closeUnitModal">&times;</button>
        </div>
        <form id="unitForm">
            <div class="modal-body">
                <div class="help-box" style="background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 6px; padding: 12px; margin-bottom: 16px; font-size: 13px; color: #0c4a6e;">
                    <strong>💡 Quick Guide:</strong><br>
                    <strong>Base Unit:</strong> The main unit you measure in (e.g., Kilogram, Liter, Piece).<br>
                    <strong>Derived Unit:</strong> A unit based on the base unit (e.g., Gram is derived from Kilogram).<br>
                    <strong>Conversion Factor:</strong> How many derived units equal 1 base unit (e.g., 1 Kilogram = 1000 Grams, so factor is 1000).
                </div>
                <div class="form-group">
                    <label class="required">Unit Name</label>
                    <input type="text" id="unitName" name="unitName" required>
                </div>
                <div class="form-group">
                    <label class="required">Unit Scope</label>
                    <div class="radio-group" style="margin-top: 6px;">
                        <div class="radio-option">
                            <input type="radio" id="perProduct" name="unitScope" value="per_product" required>
                            <label for="perProduct">Per Product</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" id="universal" name="unitScope" value="universal" checked required>
                            <label for="universal">Universal</label>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label class="required">Unit Type</label>
                    <select id="unitType" name="unitType" required>
                        <option value="count">Count</option>
                        <option value="weight">Weight</option>
                        <option value="volume">Volume</option>
                        <option value="length">Length</option>
                        <option value="area">Area</option>
                    </select>
                </div>
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="isBaseUnit" name="isBaseUnit">
                        <label for="isBaseUnit">Is Base Unit</label>
                    </div>
                </div>
                <div class="form-group" id="baseUnitGroup">
                    <label class="required">Base Unit</label>
                    <select id="baseUnit" name="baseUnit" required>
                        <option value="">Select Base Unit</option>
                    </select>
                </div>
                <div class="form-group" id="conversionFactorGroup">
                    <label class="required">Conversion Factor</label>
                    <input type="number" id="conversionFactor" name="conversionFactor" step="0.000001" min="0" placeholder="e.g., 1000 (for kg to g)" value="1" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="cancelUnit">Cancel</button>
                <button type="submit" class="btn btn-primary" id="saveUnit">Save Unit</button>
            </div>
        </form>
    </div>
</div>