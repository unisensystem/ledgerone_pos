<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <h3>Delete Product</h3>
            <button type="button" class="modal-close" id="closeDeleteModal">&times;</button>
        </div>
        <div class="modal-body">
            <p id="deleteModalMessage">Are you sure you want to delete this product? This action cannot be undone.</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" id="cancelDelete">Cancel</button>
            <button type="button" class="btn btn-danger" id="confirmDelete">Delete</button>
        </div>
    </div>
</div>