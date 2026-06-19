# Project Dropdown Implementation Guide

## Summary of Changes

Project dropdown functionality has been added to all four purchase modules:
1. **Purchase Order** (order-add.php)
2. **Purchase Invoice** (purchase-add.php)  
3. **Purchase Tax Invoice** (purchase-add.php in purchase_tax_invoice folder)
4. **Purchase Return** (return-add.php)

---

## Database Changes Required

### Files to Execute:
- `c:\xampp\htdocs\ledgerone_erp\DATABASE_MIGRATION_PROJECT_ID.sql`
- `c:\xampp\htdocs\ledgerone_erp\server\migrations\add_project_id_to_purchase_tables.sql`

### SQL Statements to Execute:

```sql
-- Add project_id to purchase_invoice table
ALTER TABLE purchase_invoice 
ADD COLUMN project_id INT NULL AFTER sub_account_id,
ADD FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL;

-- Add project_id to purchase_tax_invoice table
ALTER TABLE purchase_tax_invoice 
ADD COLUMN project_id INT NULL AFTER sub_account_id,
ADD FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL;

-- Add project_id to purchase_order table
ALTER TABLE purchase_order 
ADD COLUMN project_id INT NULL AFTER currency_id,
ADD FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL;

-- Add project_id to purchase_return table
ALTER TABLE purchase_return 
ADD COLUMN project_id INT NULL AFTER sub_account_id,
ADD FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL;
```

---

## How to Execute the Migration

### Method 1: Using phpMyAdmin
1. Open phpMyAdmin
2. Select your database (ledgerone_erp)
3. Click on "SQL" tab
4. Copy and paste the SQL statements above
5. Click "Go" to execute

### Method 2: Using MySQL Command Line
```bash
mysql -u username -p database_name < DATABASE_MIGRATION_PROJECT_ID.sql
```

### Method 3: Using MySQL Workbench
1. Open MySQL Workbench
2. Connect to your database
3. Open File → Open SQL Script
4. Navigate to `DATABASE_MIGRATION_PROJECT_ID.sql`
5. Execute the script

---

## Code Implementation Details

### 1. HTML Changes (Form Fields)
Added project dropdown field between Currency and Remarks fields in:
- `client/pages/purchase/purchase_order/order-add.php`
- `client/pages/purchase/purchase_invoice/purchase-add.php`
- `client/pages/purchase/purchase_tax_invoice/purchase-add.php`
- `client/pages/purchase/purchase_return/return-add.php`

```html
<div class="form-group">
    <label for="project">Project</label>
    <select id="project" tabindex="-1">
        <option value="">Select Project</option>
        <!-- Options loaded dynamically -->
    </select>
</div>
```

### 2. JavaScript Changes (Frontend Logic)

#### Added to all `*-add.js` files:
1. Added `loadProjects()` to Promise.all() array in DOMContentLoaded
2. Created `loadProjects()` function to fetch from API
3. Updated form data collection to include projectId
4. Updated edit mode to load and set projectId

```javascript
// In Promise.all() - Added loadProjects()
Promise.all([loadSuppliers(), loadBranches(), loadProducts(), loadUOM(), loadCurrencies(), loadCompanies(), loadProjects()]).then(() => {
    // ... rest of code
});

// Load projects function
async function loadProjects() {
    try {
        const response = await fetch('../../../../server/api/master_setup/project_setup/get-projects-dropdown.php');
        const data = await response.json();
        
        if (data.success && Array.isArray(data.projects) && data.projects.length > 0) {
            const projectSelect = document.getElementById('project');
            projectSelect.innerHTML = '<option value="">Select Project</option>';
            
            data.projects.forEach(project => {
                const option = document.createElement('option');
                option.value = project.id;
                option.textContent = `${project.project_code} - ${project.project_name} (${project.city_name})`;
                projectSelect.appendChild(option);
            });
            
            if (data.projects.length === 1) {
                projectSelect.value = data.projects[0].id;
            }
        }
    } catch (error) {
        console.error('Error loading projects:', error);
    }
}

// In form data collection - Added projectId
projectId: document.getElementById('project')?.value || null,

// In edit mode - Set project value
document.getElementById('project').value = returnData.project_id || '';
```

### 3. API Changes (Backend)

#### All APIs Updated:
- `server/api/purchase/purchase_order/order-add.php`
- `server/api/purchase/purchase_invoice/purchase-add.php`
- `server/api/purchase/purchase_tax_invoice/purchase-add.php`
- `server/api/purchase/purchase_return/return-add.php`

**INSERT Statement Changes:**
```php
INSERT INTO purchase_invoice (
    // ... other columns
    project_id,
    created_by, updated_by
) VALUES (?, ?, ?, ...);

// In execute array:
$input['projectId'] ?? null,
```

#### Edit/Update APIs Updated:
- `server/api/purchase/purchase_order/order-edit.php`
- `server/api/purchase/purchase_invoice/purchase-edit.php`
- `server/api/purchase/purchase_tax_invoice/purchase-edit.php`
- `server/api/purchase/purchase_return/return-edit.php`

**SELECT Query Changes:**
```php
SELECT 
    pi.*,
    // ... other joins
    pr.project_code,
    pr.project_name
FROM purchase_invoice pi
// ... other joins
LEFT JOIN projects pr ON pi.project_id = pr.id
WHERE pi.id = ? AND pi.tenant_id = ?
```

**UPDATE Statement Changes:**
```php
UPDATE purchase_invoice SET
    // ... other columns
    project_id = ?,
    updated_by = ?
WHERE id = ? AND tenant_id = ?

// In execute array:
$input['projectId'] ?? null,
```

### 4. Print Changes

#### Files Updated:
- `client/pages/purchase/purchase_order/invoice-print.php`
- `client/pages/purchase/purchase_invoice/invoice-print.php`
- `client/pages/purchase/purchase_tax_invoice/invoice-print.php`
- `client/pages/purchase/purchase_return/invoice-print.php`

**HTML Added:**
```html
<p><strong>Project:</strong> <span id="projectName">-</span></p>
```

**JavaScript Added:**
```javascript
document.getElementById('projectName').textContent = invoice.project_code ? 
    `${invoice.project_code} - ${invoice.project_name}` : '-';
```

---

## Project Dropdown Features

✅ **Auto-Select**: If only one project exists, it auto-selects  
✅ **Optional Field**: Project is not mandatory (allows NULL)  
✅ **Edit Support**: Project value loads when editing existing records  
✅ **Print Support**: Project displays on printed documents  
✅ **Database Constraint**: Foreign key on projects table with ON DELETE SET NULL  

---

## Column Placement in Database

| Table | Column Position | After Column |
|-------|-----------------|--------------|
| purchase_invoice | After sub_account_id | sub_account_id |
| purchase_tax_invoice | After sub_account_id | sub_account_id |
| purchase_order | After currency_id | currency_id |
| purchase_return | After sub_account_id | sub_account_id |

---

## Testing Checklist

- [ ] Execute SQL migration successfully
- [ ] Refresh browser and verify project dropdown appears
- [ ] Create new purchase record with project selected
- [ ] Verify project_id saves to database
- [ ] Edit existing record and verify project loads
- [ ] Update project and verify it saves
- [ ] Print document and verify project displays
- [ ] Test with no project selected (should save as NULL)
- [ ] Test auto-select when only one project exists

---

## Troubleshooting

### Project dropdown not showing
1. Verify `get-projects-dropdown.php` is accessible
2. Check browser console for fetch errors
3. Verify projects exist in database with active status

### Project not saving
1. Check database migration was executed
2. Verify project_id column exists in table
3. Check API response in browser network tab

### Edit not loading project
1. Verify project_id is in SELECT query
2. Check LEFT JOIN projects is correct
3. Verify project code/name fields are selected

---

## Files Modified Summary

### Frontend Files (HTML/JS)
- ✅ order-add.php (Purchase Order form)
- ✅ order-add.js (Purchase Order JS)
- ✅ purchase-add.php (Purchase Invoice form)
- ✅ purchase-add.js (Purchase Invoice JS)
- ✅ purchase-add.php (Purchase Tax Invoice form - in purchase_tax_invoice folder)
- ✅ purchase-add.js (Purchase Tax Invoice JS - in purchase_tax_invoice folder)
- ✅ return-add.php (Purchase Return form)
- ✅ return-add.js (Purchase Return JS)

### API Files (Backend)
- ✅ order-add.php (Purchase Order API)
- ✅ order-edit.php (Purchase Order Edit API)
- ✅ purchase-add.php (Purchase Invoice API)
- ✅ purchase-edit.php (Purchase Invoice Edit API)
- ✅ purchase-add.php (Purchase Tax Invoice API - in purchase_tax_invoice folder)
- ✅ purchase-edit.php (Purchase Tax Invoice Edit API - in purchase_tax_invoice folder)
- ✅ return-add.php (Purchase Return API)
- ✅ return-edit.php (Purchase Return Edit API)

### Print Files
- ✅ invoice-print.php (Purchase Order print)
- ✅ invoice-print.php (Purchase Invoice print)
- ✅ invoice-print.php (Purchase Tax Invoice print - in purchase_tax_invoice folder)
- ✅ invoice-print.php (Purchase Return print - in purchase_return folder)

### Migration Files
- ✅ DATABASE_MIGRATION_PROJECT_ID.sql
- ✅ add_project_id_to_purchase_tables.sql (in migrations folder)
