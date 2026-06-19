# Sale Return - Project Dropdown Integration

## Summary
Add project dropdown field to Sale Return module (counter-return.php) with add, edit, and print support.

## Changes Required

### 1. counter-return.js - Line 17 (DOMContentLoaded)
**CHANGE:**
```javascript
await Promise.all([loadProducts(), loadCustomers(), loadBranches(), loadCurrencies(), loadBankAccounts(), loadUOM(), loadSaleInvoices(), loadEmployees(), loadCompanies()]);
```

**TO:**
```javascript
await Promise.all([loadProducts(), loadCustomers(), loadBranches(), loadCurrencies(), loadBankAccounts(), loadUOM(), loadSaleInvoices(), loadEmployees(), loadCompanies(), loadProjects()]);
```

---

### 2. counter-return.js - After Line 47 (after initDropdown for supplierMan)
**ADD:**
```javascript
initDropdown('projectSearch', 'projectOptions', 'project', projectsData, (p) => `${p.project_code} - ${p.project_name}${p.city_name ? ' - ' + p.city_name : ''}`, (id) => { currentReturn.project = parseInt(id); localStorage.setItem('lastSelectedProject', id); });

const savedProjectId = localStorage.getItem('lastSelectedProject');
if (savedProjectId && projectsData.find(p => p.id == savedProjectId)) {
    const project = projectsData.find(p => p.id == savedProjectId);
    document.getElementById('projectSearch').value = `${project.project_code} - ${project.project_name}${project.city_name ? ' - ' + project.city_name : ''}`;
    document.getElementById('project').value = savedProjectId;
    currentReturn.project = parseInt(savedProjectId);
}
```

---

### 3. counter-return.js - Add click handler for projectOptions
**IN:** `document.addEventListener('click', (e) => {...})` (around line 140)

**ADD:**
```javascript
if (!e.target.closest('#projectSearch') && !e.target.closest('#projectOptions')) document.getElementById('projectOptions').style.display = 'none';
```

---

### 4. counter-return.js - End of file (after filterInvoicesByCustomer function)
**ADD:**
```javascript
async function loadProjects() {
    const response = await fetch('../../../../server/api/master_setup/project_setup/get-projects-dropdown.php');
    const data = await response.json();
    if (data.success) projectsData = data.projects;
}
```

---

### 5. return-add.php - INSERT statement (around line 66)
**CHANGE:**
```php
INSERT INTO sale_return (
    tenant_id, company_id, currency_id, bill_no, sale_date, customer_id, branch_id,
    ...
```

**TO:**
```php
INSERT INTO sale_return (
    tenant_id, company_id, project_id, currency_id, bill_no, sale_date, customer_id, branch_id,
    ...
```

**AND add parameter** in execute() array:
```php
$input['projectId'] ?? null,
```

---

### 6. return-edit.php - UPDATE statement (around line 92)
**CHANGE:**
```php
UPDATE sale_return SET
    company_id = ?, currency_id = ?, sale_date = ?, ...
```

**TO:**
```php
UPDATE sale_return SET
    company_id = ?, project_id = ?, currency_id = ?, sale_date = ?, ...
```

**AND add parameter** in execute() array:
```php
$input['projectId'] ?? null,
```

---

### 7. return-edit.php - loadEditData function (after company section)
**ADD:**
```php
// Project
if (inv.project_id) {
    const project = projectsData.find(p => p.id == inv.project_id);
    if (project) {
        document.getElementById('projectSearch').value = `${project.project_code} - ${project.project_name}${project.city_name ? ' - ' + project.city_name : ''}`;
        document.getElementById('project').value = inv.project_id;
        currentReturn.project = parseInt(inv.project_id);
    }
}
```

---

### 8. thermal-print.php - Add project display
**IN:** `<div class="info-row">` section (after Sale Invoice #)

**ADD:**
```html
<div class="info-row">
    <span>Project:</span>
    <span id="projectName">-</span>
</div>
```

**IN:** `populateInvoiceData` function

**ADD:**
```javascript
document.getElementById('projectName').textContent = invoice.project_name ? `${invoice.project_code} - ${invoice.project_name}` : '-';
```

---

## Files Modified
1. ✅ counter-return.php (already has project dropdown HTML)
2. ✅ counter-return.js (needs updates above)
3. ✅ return-add.php (needs project_id in INSERT)
4. ✅ return-edit.php (needs project_id in UPDATE and loadEditData)
5. ✅ thermal-print.php (needs project display)

## Database
- sale_return table already has project_id column OR needs:
  ```sql
  ALTER TABLE sale_return ADD COLUMN project_id INT UNSIGNED NULL;
  ALTER TABLE sale_return ADD FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL;
  ```
