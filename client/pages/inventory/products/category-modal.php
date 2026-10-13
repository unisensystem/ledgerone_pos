<!-- Category Modal -->
<div id="categoryModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="categoryModalTitle">Add New Category</h3>
            <button type="button" class="modal-close" id="closeCategoryModal">&times;</button>
        </div>
        <form id="categoryForm">
            <div class="modal-body">
                <div class="form-group">
                    <label class="required">Category Name</label>
                    <input type="text" id="categoryName" name="categoryName" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="cancelCategory">Cancel</button>
                <button type="submit" class="btn btn-primary" id="saveCategory">Save Category</button>
            </div>
        </form>
    </div>
</div>

<!-- Subcategory Modal -->
<div id="subcategoryModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="subcategoryModalTitle">Add New Subcategory</h3>
            <button type="button" class="modal-close" id="closeSubcategoryModal">&times;</button>
        </div>
        <form id="subcategoryForm">
            <div class="modal-body">
                <div class="form-group">
                    <label class="required">Parent Category</label>
                    <select id="parentCategory" name="parentCategory" required>
                        <option value="">Select Category</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="required">Subcategory Name</label>
                    <input type="text" id="subcategoryName" name="subcategoryName" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="cancelSubcategory">Cancel</button>
                <button type="submit" class="btn btn-primary" id="saveSubcategory">Save Subcategory</button>
            </div>
        </form>
    </div>
</div>