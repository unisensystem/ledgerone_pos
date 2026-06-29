# Quick Setup Guide - Project Product Rates Module

## ⚡ 1-Minute Setup

### Step 1: Create Database Table
Open your database (phpMyAdmin or MySQL Workbench) and run:

```sql
CREATE TABLE IF NOT EXISTS `project_product_rates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `rate` decimal(12, 2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_project_product` (`project_id`, `product_id`),
  KEY `fk_project` (`project_id`),
  KEY `fk_product` (`product_id`),
  CONSTRAINT `fk_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Step 2: Files Created ✅

**Frontend:**
- ✅ `/client/pages/master_setup/project_product_rates/project-product-rate-add.php`
- ✅ `/client/pages/master_setup/project_product_rates/project-product-rate-list.php`

**API Endpoints:**
- ✅ `/server/api/master_setup/project_product_rates/add-rate.php`
- ✅ `/server/api/master_setup/project_product_rates/update-rate.php`
- ✅ `/server/api/master_setup/project_product_rates/delete-rate.php`
- ✅ `/server/api/master_setup/project_product_rates/get-rate.php`
- ✅ `/server/api/master_setup/project_product_rates/get-all-rates.php`
- ✅ `/server/api/master_setup/project_product_rates/get-projects.php`
- ✅ `/server/api/master_setup/project_product_rates/get-products.php`

### Step 3: Access the Module

**Add New Rate:**
```
http://your-domain.com/client/pages/master_setup/project_product_rates/project-product-rate-add.php
```

**View All Rates:**
```
http://your-domain.com/client/pages/master_setup/project_product_rates/project-product-rate-list.php
```

---

## ✨ Features

| Feature | Status |
|---------|--------|
| Create | ✅ Yes |
| Read | ✅ Yes |
| Update | ✅ Yes |
| Delete | ✅ Yes |
| Search | ✅ Yes |
| Validation | ✅ Yes |
| Error Handling | ✅ Yes |

---

## 🔧 Tech Stack

- **Frontend:** HTML5, CSS3, JavaScript (Vanilla)
- **Backend:** PHP 7.4+
- **Database:** MySQL/MariaDB
- **API:** RESTful JSON

---

## 📋 Fields

1. **Project** (Required, Searchable)
   - Pulls from `projects` table
   - Shows project_code + project_name

2. **Product Code / Name** (Required, Searchable)
   - Pulls from `products` table
   - Shows code + name

3. **Rate** (Required, Numeric)
   - Accepts decimals up to 2 places
   - Min value: 0
   - Example: 250.50

---

## 🎯 Usage Examples

### Example 1: Add a Rate
```javascript
// Project: Project Alpha (ID: 1)
// Product: Product A (ID: 5)
// Rate: 250.50

fetch('/server/api/master_setup/project_product_rates/add-rate.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    project_id: 1,
    product_id: 5,
    rate: 250.50
  })
})
.then(r => r.json())
.then(data => console.log(data.success ? 'Added!' : data.message));
```

### Example 2: Update a Rate
```javascript
fetch('/server/api/master_setup/project_product_rates/update-rate.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    id: 1,
    rate: 275.00
  })
})
.then(r => r.json())
.then(data => console.log(data.message));
```

### Example 3: Delete a Rate
```javascript
fetch('/server/api/master_setup/project_product_rates/delete-rate.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ id: 1 })
})
.then(r => r.json())
.then(data => console.log(data.message));
```

---

## 🚀 Demo Workflow

1. **User opens form**
   - `/project-product-rate-add.php`
   - JavaScript loads projects & products into dropdowns

2. **User selects Project**
   - Dropdown shows: "PRJ001 - Project Alpha"
   - Hidden input stores: `1`

3. **User selects Product**
   - Dropdown shows: "PROD001 - Product A"
   - Hidden input stores: `5`

4. **User enters Rate**
   - Input: `250.50`

5. **User clicks Save**
   - Validates form
   - Sends JSON to API
   - API inserts into database
   - Shows success message
   - Redirects to list page

6. **User views list**
   - See all rates in table format
   - Can search by project/product
   - Can edit rate (pencil icon)
   - Can delete rate (trash icon)

---

## ❌ Common Issues & Solutions

| Issue | Solution |
|-------|----------|
| "404 Not Found" on API calls | Check file paths and database connection |
| "No dropdown options" | Check if projects/products exist in database |
| "Rate not saving" | Check browser console for errors, verify database |
| "Can't delete" | Check if rate exists, verify permissions |
| Styling looks broken | Clear browser cache (Ctrl+Shift+Delete) |

---

## 📊 Database Verification

Check if table created correctly:

```sql
-- Check table structure
DESCRIBE project_product_rates;

-- Check sample data
SELECT * FROM project_product_rates;

-- Check relationships
SELECT 
  ppr.id,
  p.project_name,
  pr.name as product_name,
  ppr.rate
FROM project_product_rates ppr
LEFT JOIN projects p ON ppr.project_id = p.id
LEFT JOIN products pr ON ppr.product_id = pr.id;
```

---

## 🎓 Learning Path

1. Open `project-product-rate-add.php` → Understand form structure
2. Open `project-product-rate-list.php` → Understand list structure
3. Open `add-rate.php` → See how data is inserted
4. Open `get-all-rates.php` → See how data is retrieved
5. Test with your browser's DevTools Network tab

---

## 🔐 Security Features

✅ Input validation on frontend
✅ Input validation on backend
✅ SQL prepared statements (prevent SQL injection)
✅ Type casting for numeric values
✅ Unique constraint on database level
✅ Foreign key constraints

---

## 📞 Support

If something doesn't work:

1. Check browser console (F12) for JavaScript errors
2. Check Network tab in DevTools for API responses
3. Check database for table existence
4. Verify file paths match exactly
5. Clear browser cache and reload

---

**Ready to use!** 🎉

Start adding project product rates now!
