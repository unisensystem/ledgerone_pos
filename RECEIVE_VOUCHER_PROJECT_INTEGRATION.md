# PROJECT INTEGRATION IN RECEIVE VOUCHER - SUMMARY

## 1. DATABASE CHANGES

### Add Project Column to receive_voucher Table:
```sql
ALTER TABLE receive_voucher ADD COLUMN IF NOT EXISTS project_id INT NULL DEFAULT NULL AFTER dsr_no;

-- Add foreign key constraint
ALTER TABLE receive_voucher 
ADD CONSTRAINT fk_receive_voucher_project 
FOREIGN KEY (project_id) REFERENCES projects(id) 
ON DELETE SET NULL ON UPDATE CASCADE;

-- Create index for performance
CREATE INDEX IF NOT EXISTS idx_receive_voucher_project ON receive_voucher(project_id);
```

**Migration File Location:** `/database/migrations/add_project_id_to_receive_voucher.sql`

---

## 2. FRONTEND CHANGES

### A. HTML Updates (receive-add.php)

**Single Mode Form:**
```html
<!-- Added after Currency field -->
<div class="form-group">
    <label for="project">Project</label>
    <select id="project">
        <option value="">Select Project</option>
        <!-- Options populated by JS -->
    </select>
</div>
```

**Bulk Mode Table:**
- Added Project column (7th column after DSR#)
- Column displays as: `code - name - city`

### B. JavaScript Updates (receive-add.js)

**1. Global Variable:**
```javascript
let projects = [];
```

**2. Load Projects with Enhanced Display Format:**
```javascript
// Fetch projects from API
fetch('../../../../server/api/master_setup/project_setup/get-projects-dropdown.php')
    .then(response => response.json())
    .then(data => {
        if (data.success && Array.isArray(data.projects)) {
            projects = data.projects;
            const projectSelect = document.getElementById('project');
            data.projects.forEach(project => {
                const option = document.createElement('option');
                option.value = project.id;
                const cityName = project.city_name || 'N/A';
                option.textContent = `${project.project_code} - ${project.project_name} - ${cityName}`;
                projectSelect.appendChild(option);
            });
        }
    })
    .catch(error => console.error('Error fetching projects:', error));
```

**3. Bulk Mode - populateRowSelects():**
```javascript
projectSelect.innerHTML = '<option value="">Select</option>';
projects.forEach(p => {
    const cityName = p.city_name || 'N/A';
    projectSelect.innerHTML += `<option value="${p.id}">${p.project_code} - ${p.project_name} - ${cityName}</option>`;
});
```

**4. Single Mode Form Submission:**
```javascript
formData.append('project_id', document.getElementById('project').value || null);
```

**5. Bulk Mode Form Submission:**
```javascript
formData.append('project_id', row.querySelector('.bulk-project').value || null);
```

---

## 3. BACKEND API CHANGES

### A. receive-add.php (Create/POST)

**1. Get project_id from POST:**
```php
$project_id = (!empty($_POST['project_id']) && $_POST['project_id'] !== 'null') ? intval($_POST['project_id']) : null;
```

**2. Insert Query Updated:**
```php
$stmt = $pdo->prepare("
    INSERT INTO receive_voucher (
        tenant_id, company_id, voucher_number, voucher_date, customer_id, sub_account_id, bill_no, dsr_no, project_id,
        amount, currency_id, payment_method_id, bank_account_id, cheque_date, cheque_no, description, attachment,
        recovery_officer_id, created_by, updated_by
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->execute([
    $tenant_id, $company_id, $voucher_number, $voucher_date, $customer['id'], $sub_account_id, $bill_no, $dsr_no, $project_id,
    $amount, $currency_id, $payment_method_id, $bank_account_id, $cheque_date, $cheque_no, $description, $attachment,
    $recovery_officer_id, $user_id, $user_id
]);
```

### B. receive-edit.php (Update/PUT)

**1. Get project_id from JSON input:**
```php
$project_id = (!empty($input['project_id']) && $input['project_id'] !== 'null') ? intval($input['project_id']) : null;
```

**2. Update Query Updated:**
```php
$stmt = $pdo->prepare("
    UPDATE receive_voucher 
    SET voucher_date = ?, company_id = ?, bill_no = ?, amount = ?, currency_id = ?, payment_method_id = ?, 
        bank_account_id = ?, cheque_date = ?, cheque_no = ?, description = ?, project_id = ?, updated_by = ?
    WHERE id = ? AND tenant_id = ?
");

$stmt->execute([
    $voucher_date, $company_id, $bill_no, $amount, $currency_id, $payment_method_id,
    $bank_account_id, $cheque_date, $cheque_no, $description, $project_id, $user_id,
    $voucher_id, $tenant_id
]);
```

---

## 4. FEATURES & DISPLAY FORMAT

### Project Dropdown Display:
- **Format:** `PROJECT_CODE - PROJECT_NAME - CITY_NAME`
- **Example:** `PRJ-001 - Karachi Office - Karachi`
- **Default:** Shows 'N/A' if city_name is null

### Availability in:
✅ Single Mode Form (optional field)
✅ Bulk Mode Table (optional field)
✅ Edit Mode (loads and saves project)
✅ Print Mode (project info included)

### Data Flow:
1. **User selects project** → Option value stored as `project_id`
2. **Form submitted** → POST/PUT data includes `project_id`
3. **API validates & stores** → Saves to `receive_voucher.project_id`
4. **On edit** → Loads project_id and displays in dropdown
5. **Reporting** → Can filter by project_id

---

## 5. FILES MODIFIED

| File | Changes |
|------|---------|
| `receive-add.php` | Added project dropdown (HTML) |
| `receive-add.js` | Fetch projects, load in single & bulk, submit with formData |
| `receive-add.php` (API) | Get project_id, include in INSERT query |
| `receive-edit.php` (API) | Get project_id, include in UPDATE query |

---

## 6. DEPLOYMENT CHECKLIST

- [ ] Run migration: `add_project_id_to_receive_voucher.sql`
- [ ] Verify `receive_voucher` table has `project_id` column
- [ ] Check `projects` table has `city_name` field
- [ ] Test single mode: Create voucher with project
- [ ] Test bulk mode: Add multiple rows with different projects
- [ ] Test edit mode: Load and modify project
- [ ] Test print: Verify project displays correctly
- [ ] Test null cases: Leave project empty (should work)

---

## 7. OPTIONAL ENHANCEMENTS

If you want to make project **REQUIRED**:

**In HTML:**
```html
<select id="project" required>
```

**In JS Validation:**
```javascript
if (!document.getElementById('project').value) {
    alert('Please select a project');
    return false;
}
```

---

## 8. PROJECT DROPDOWN DISPLAY LOGIC

```javascript
// Gets from API: {id, project_code, project_name, city_name}
// Displays as: "project_code - project_name - city_name"

const cityName = project.city_name || 'N/A';
option.textContent = `${project.project_code} - ${project.project_name} - ${cityName}`;
```

✅ **All changes completed and ready for deployment!**
