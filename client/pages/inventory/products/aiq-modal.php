<!-- Batch/Lot Label Modal -->
<div id="aiqModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3>Print Labels</h3>
            <button type="button" class="modal-close" id="closeAiqModal">&times;</button>
        </div>
        <div class="modal-body" style="text-align: left;">
            <div class="form-group" style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 6px; font-weight: 500;">Manufacturing Date</label>
                <input type="date" id="aiqMfgDate" style="width: 100%; padding: 10px 12px; border: 1.5px solid var(--border-default); border-radius: 6px; font-size: 14px;">
            </div>
            
            <div class="form-group" style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 6px; font-weight: 500;">Expiry Date</label>
                <input type="date" id="aiqExpDate" style="width: 100%; padding: 10px 12px; border: 1.5px solid var(--border-default); border-radius: 6px; font-size: 14px;">
            </div>
            
            <div class="form-group">
                <label style="display: block; margin-bottom: 6px; font-weight: 500;">Copies</label>
                <input type="number" id="aiqCopies" value="1" min="1" max="100" style="width: 100%; padding: 10px 12px; border: 1.5px solid var(--border-default); border-radius: 6px; font-size: 14px;">
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" id="cancelAiq">Cancel</button>
            <button type="button" class="btn btn-primary" id="confirmAiqPrint">Print</button>
        </div>
    </div>
</div>
