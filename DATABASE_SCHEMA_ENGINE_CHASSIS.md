# Engine No & Chassis No - Database Schema & Implementation Guide

## Database Migration

**File:** `database/migrations/add_engine_chassis_to_stock_transfer_items.sql`

```sql
-- Add columns to stock_transfer_items table
ALTER TABLE `stock_transfer_items` ADD COLUMN `engine_no` VARCHAR(100) NULL AFTER `stock_value`;
ALTER TABLE `stock_transfer_items` ADD COLUMN `chassis_no` VARCHAR(100) NULL AFTER `engine_no`;

-- Create indexes for performance
CREATE INDEX idx_stock_transfer_items_engine ON stock_transfer_items(engine_no);
CREATE INDEX idx_stock_transfer_items_chassis ON stock_transfer_items(chassis_no);
```

### Table Structure After Migration:
```
stock_transfer_items table:
├── id (PK)
├── tenant_id
├── transfer_id (FK)
├── product_id (FK)
├── qty
├── rate
├── stock_value
├── engine_no          ← NEW
├── chassis_no         ← NEW
└── timestamps
```

---

## API Implementation

### 1. **Save New Transfer** (`transfer-add.php`)

**INSERT Statement:**
```php
INSERT INTO stock_transfer_items (tenant_id, transfer_id, product_id, qty, rate, stock_value, engine_no, chassis_no) 
VALUES (?, ?, ?, ?, ?, ?, ?, ?)
```

**Data Flow:**
```
Frontend (transfer-add.js)
    ↓
POST /server/api/inventory/stock_transfer/transfer-add.php
    ↓
Extract: engine_no, chassis_no from items array
    ↓
INSERT into stock_transfer_items with both fields
    ↓
Success Response: transfer_id, transfer_code
```

---

### 2. **Update Existing Transfer** (`transfer-update.php`)

**Process:**
1. DELETE old stock_ledger entries
2. DELETE old stock_transfer_items
3. INSERT new items with engine_no, chassis_no

**INSERT Statement:**
```php
INSERT INTO stock_transfer_items (tenant_id, transfer_id, product_id, qty, rate, stock_value, engine_no, chassis_no) 
VALUES (?, ?, ?, ?, ?, ?, ?, ?)
```

---

### 3. **Load Transfer for Editing** (`transfer-get.php`)

**Query:**
```sql
SELECT sti.*, p.default_unit_id 
FROM stock_transfer_items sti
LEFT JOIN products p ON sti.product_id = p.id
WHERE sti.tenant_id = ? AND sti.transfer_id = ?
```

**Returns:**
```json
{
  "items": [
    {
      "id": 1,
      "product_id": 123,
      "qty": 5,
      "rate": 1000,
      "stock_value": 5000,
      "engine_no": "ENG-2024-001",      ← RETURNED
      "chassis_no": "CHG-2024-001",      ← RETURNED
      "default_unit_id": 2
    }
  ]
}
```

---

## Frontend Implementation

### JavaScript Data Structure

**When Posting/Updating:**
```javascript
const items = [
  {
    product_id: 123,
    unit_id: 2,
    quantity: 5,
    rate: 1000,
    stock_value: 5000,
    engine_no: "ENG-2024-001",    ← SENT
    chassis_no: "CHG-2024-001"     ← SENT
  }
];
```

**When Loading (Edit Mode):**
```javascript
row.querySelector('.engine-no').value = item.engine_no || '';
row.querySelector('.chassis-no').value = item.chassis_no || '';
```

---

## Running the Migration

**Steps to execute:**

1. **Connect to MySQL:**
   ```bash
   mysql -u root -p ledgerone_erp
   ```

2. **Run migration:**
   ```sql
   source database/migrations/add_engine_chassis_to_stock_transfer_items.sql;
   ```

3. **Or run via script:**
   ```bash
   # If you have a migration runner
   php scripts/run-migration.php
   ```

4. **Verify columns were added:**
   ```sql
   DESCRIBE stock_transfer_items;
   ```

---

## Files Modified/Created

| File | Action | Purpose |
|------|--------|---------|
| `database/migrations/add_engine_chassis_to_stock_transfer_items.sql` | CREATE | Migration file |
| `client/pages/inventory/stock_transfer/transfer-add.php` | UPDATE | Added columns to table header |
| `client/assets/js/inventory/stock_transfer/transfer-add.js` | UPDATE | Handle engine_no, chassis_no input/save |
| `client/assets/css/inventory/stock_transfer/transfer-add.css` | UPDATE | Responsive styling |
| `server/api/inventory/stock_transfer/transfer-add.php` | UPDATE | Save engine_no, chassis_no to DB |
| `server/api/inventory/stock_transfer/transfer-update.php` | UPDATE | Update engine_no, chassis_no in DB |
| `server/api/inventory/stock_transfer/transfer-get.php` | NO CHANGE | Already returns all columns |

---

## Testing the Feature

### Test Case 1: Add New Transfer
```
1. Open /client/pages/inventory/stock_transfer/transfer-add.php
2. Fill in: Company, From/To Branch, Date
3. Add Product with Engine No: "ENG-123" and Chassis No: "CHASS-456"
4. Click "Post Transfer"
5. Check database:
   SELECT * FROM stock_transfer_items WHERE transfer_id = <id>;
   → Should show engine_no and chassis_no values
```

### Test Case 2: Edit Transfer
```
1. Open transfer in edit mode with ?edit=<id>
2. Engine No and Chassis No should be pre-filled
3. Modify values
4. Click "Update Transfer"
5. Check database - values should be updated
```

### Test Case 3: Query Data
```sql
SELECT 
  st.transfer_code,
  p.name as product,
  sti.qty,
  sti.engine_no,
  sti.chassis_no,
  sti.rate,
  sti.stock_value
FROM stock_transfer st
JOIN stock_transfer_items sti ON st.id = sti.transfer_id
JOIN products p ON sti.product_id = p.id
WHERE st.tenant_id = <tenant_id>
ORDER BY st.date DESC;
```

---

## Data Validation

- **Engine No:** VARCHAR(100), allows NULL
- **Chassis No:** VARCHAR(100), allows NULL
- Both fields are optional (nullable)
- Can be updated when editing transfer
- Indexed for better search performance

---

## Next Steps

1. Run the migration on your database
2. Test with sample data
3. Verify data appears in MySQL
4. Check edit mode loads the values correctly
