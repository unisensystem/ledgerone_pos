<!-- UOM Group Modal -->
<div id="uomGroupModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h3 id="uomGroupModalTitle">Manage UOM Groups</h3>
            <button type="button" class="modal-close" id="closeUomGroupModal">&times;</button>
        </div>
        <form id="uomGroupForm">
            <div class="modal-body">
                <div class="form-group">
                    <label class="required">Group Name</label>
                    <input type="text" id="uomGroupName" name="uomGroupName" required>
                </div>
                <div class="form-group">
                    <label class="required">Select Units</label>
                    <div id="unitDropdownContainer"></div>
                    <div class="helper-text">Add multiple units to this group</div>
                </div>
                <div style="margin-top: 16px;">
                    <button type="submit" class="btn btn-primary" id="saveUomGroup" style="width: 100%;">Save UOM Group</button>
                </div>
                
                <hr style="margin: 24px 0; border: none; border-top: 1px solid var(--border-default);">
                
                <div>
                    <h4 style="font-size: 16px; font-weight: 600; color: var(--heading); margin-bottom: 12px;">Existing UOM Groups</h4>
                    <div id="uomGroupList" style="max-height: 300px; overflow-y: auto;">
                        <p style="color: var(--subtext); text-align: center; padding: 20px;">Loading...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="cancelUomGroup">Close</button>
            </div>
        </form>
    </div>
</div>
