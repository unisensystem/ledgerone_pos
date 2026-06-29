# Project Product Rates - Complete CRUD System

## Overview
This module manages project-specific product rates. It has 4 core fields:
- **Project** - Searchable dropdown
- **Product Code / Name** - Searchable dropdown  
- **Rate** - Numeric input
- **Auto-generated** - ID, timestamps

## Features
✅ Complete CRUD Operations (Create, Read, Update, Delete)
✅ Searchable dropdowns for Project & Product
✅ Search/Filter functionality on list page
✅ Edit existing rates
✅ Delete with confirmation modal
✅ Clean, modern UI
✅ Input validation
✅ Success/Error messages

---

## File Structure

### Frontend Files
```
/client/pages/master_setup/project_product_rates/
├── project-product-rate-add.php     (Form for add/edit)
└── project-product-rate-list.php    (List view with search)
```

### API Endpoints
```
/server/api/master_setup/project_product_rates/
├── add-rate.php           (POST - Create new rate)
├── update-rate.php        (POST - Update existing rate)
├── delete-rate.php        (POST - Delete rate)
├── get-rate.php           (GET - Fetch single rate by ID)
├── get-all-rates.php      (GET - Fetch all rates with project/product details)
├── get-projects.php       (GET - Fetch all active projects)
└── get-products.php       (GET - Fetch all active products)
```

### Database
```
/server/migrations/
└── create_project_product_rates_table.sql
```

---

## Database Schema

Table: `project_product_rates`

| Column | Type | Description |
|--------|------|-------------|
| id | INT | Primary key, auto-increment |
| project_id | INT | Foreign key to projects table |
| product_id | INT | Foreign key to products table |
| rate | DECIMAL(12,2) | Product rate for this project |
| created_at | TIMESTAMP | Record creation time |
| updated_at | TIMESTAMP | Last update time |

**Constraints:**
- Unique combination of project_id + product_id (no duplicate rates)
- Foreign keys with CASCADE delete

---

## Installation Steps

### 1. Create Database Table
Execute the SQL migration:
```sql
-- Copy content from: /server/migrations/create_project_product_rates_table.sql
-- Run in your database
```

OR use phpMyAdmin:
```
Database > SQL > Paste the migration SQL > Execute
```

### 2. Access the Module
- **Add New Rate**: `/client/pages/master_setup/project_product_rates/project-product-rate-add.php`
- **View List**: `/client/pages/master_setup/project_product_rates/project-product-rate-list.php`

---

## API Endpoints Reference

### 1. Add New Rate
**Endpoint:** `POST /server/api/master_setup/project_product_rates/add-rate.php`

**Request:**
```json
{
  "project_id": 1,
  "product_id": 5,
  "rate": 250.50
}
```

**Response:**
```json
{
  "success": true,
  "message": "Rate added successfully",
  "id": 1
}
```

---

### 2. Update Rate
**Endpoint:** `POST /server/api/master_setup/project_product_rates/update-rate.php`

**Request:**
```json
{
  "id": 1,
  "project_id": 1,
  "product_id": 5,
  "rate": 275.00
}
```

**Response:**
```json
{
  "success": true,
  "message": "Rate updated successfully"
}
```

---

### 3. Delete Rate
**Endpoint:** `POST /server/api/master_setup/project_product_rates/delete-rate.php`

**Request:**
```json
{
  "id": 1
}
```

**Response:**
```json
{
  "success": true,
  "message": "Rate deleted successfully"
}
```

---

### 4. Get Single Rate
**Endpoint:** `GET /server/api/master_setup/project_product_rates/get-rate.php?id=1`

**Response:**
```json
{
  "success": true,
  "rate": {
    "id": 1,
    "project_id": 1,
    "product_id": 5,
    "rate": "250.50"
  }
}
```

---

### 5. Get All Rates
**Endpoint:** `GET /server/api/master_setup/project_product_rates/get-all-rates.php`

**Response:**
```json
{
  "success": true,
  "rates": [
    {
      "id": 1,
      "project_id": 1,
      "product_id": 5,
      "rate": "250.50",
      "project_code": "PRJ001",
      "project_name": "Project Alpha",
      "product_code": "PROD001",
      "product_name": "Product A"
    }
  ],
  "total": 1
}
```

---

### 6. Get Projects Dropdown
**Endpoint:** `GET /server/api/master_setup/project_product_rates/get-projects.php`

**Response:**
```json
{
  "success": true,
  "projects": [
    {
      "id": 1,
      "project_code": "PRJ001",
      "project_name": "Project Alpha"
    }
  ]
}
```

---

### 7. Get Products Dropdown
**Endpoint:** `GET /server/api/master_setup/project_product_rates/get-products.php`

**Response:**
```json
{
  "success": true,
  "products": [
    {
      "id": 1,
      "code": "PROD001",
      "name": "Product Alpha"
    }
  ]
}
```

---

## Usage Guide

### Adding a New Rate
1. Go to: `/client/pages/master_setup/project_product_rates/project-product-rate-add.php`
2. Select Project (searchable)
3. Select Product (searchable)
4. Enter Rate
5. Click "Save"

### Editing a Rate
1. Go to List page
2. Click the edit icon (✏️)
3. Modify the rate
4. Click "Save"

### Deleting a Rate
1. Go to List page
2. Click the delete icon (🗑️)
3. Confirm in modal
4. Rate is deleted

### Searching
1. Go to List page
2. Type in search box (searches project name/code or product name/code)
3. Results filter in real-time

---

## Validation Rules

| Field | Rule |
|-------|------|
| Project | Required, must exist |
| Product | Required, must exist |
| Rate | Required, numeric, min 0, 2 decimal places |
| Unique Constraint | Same project + product can have only 1 rate |

---

## Error Handling

| Error | Cause | Solution |
|-------|-------|----------|
| "Missing required fields" | Form not filled completely | Fill all 3 fields |
| "A rate already exists..." | Duplicate project-product combo | Delete old rate or use different product |
| "Rate not found" | Trying to edit/delete non-existent rate | Refresh page |
| Database connection error | DB not running | Check database connection |

---

## Customization

### Change Table Columns
Edit the relevant API endpoint and SQL migration

### Change Styling
Edit CSS in `.php` files (in `<style>` tags)

### Add More Fields
1. Add column to database table
2. Update SQL migration
3. Add form field in `project-product-rate-add.php`
4. Update API endpoints

---

## Support

For issues or feature requests, check:
- Database table existence and structure
- Database user permissions
- API endpoint paths
- Browser console for JavaScript errors

---

**Created:** 2024
**Version:** 1.0
**Status:** Production Ready
