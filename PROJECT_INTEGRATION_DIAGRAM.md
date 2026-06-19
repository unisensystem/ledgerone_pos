# PROJECT INTEGRATION - DATA FLOW DIAGRAM

## SINGLE MODE FLOW

```
┌─────────────────────────────────────────────────────────────────┐
│  USER INTERFACE                                                  │
├─────────────────────────────────────────────────────────────────┤
│                                                                   │
│  Receive Voucher Form                                            │
│  ├─ Voucher Date      : [Date Input]                             │
│  ├─ Customer Code     : [Searchable Dropdown]                    │
│  ├─ Company           : [Select]                                 │
│  ├─ Sub Account       : [Select]                                 │
│  ├─ Bill No           : [Searchable Input]                       │
│  ├─ DSR#              : [Text Input]                             │
│  ├─ Currency          : [Select]                                 │
│  ├─ PROJECT           : [Select] ◄──── NEW FIELD                 │
│  │                     └─ Format: CODE - NAME - CITY            │
│  ├─ Amount            : [Currency Input]                         │
│  ├─ Payment Method    : [Select]                                 │
│  ├─ Bank Account      : [Select - Conditional]                   │
│  ├─ Cheque Date/No    : [Date/Text - Conditional]                │
│  └─ [Post Voucher Button]                                        │
│                                                                   │
└─────────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│  JAVASCRIPT (receive-add.js)                                     │
├─────────────────────────────────────────────────────────────────┤
│                                                                   │
│  1. Load Projects on Page Load:                                  │
│     fetch('/get-projects-dropdown.php')                          │
│     ├─ Response: {id, project_code, project_name, city_name}     │
│     └─ Populate select#project with:                             │
│        "${project_code} - ${project_name} - ${city_name}"        │
│                                                                   │
│  2. On Form Submit:                                              │
│     formData.append('project_id',                                │
│        document.getElementById('project').value)                 │
│                                                                   │
└─────────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│  API (receive-add.php) - POST Request                            │
├─────────────────────────────────────────────────────────────────┤
│                                                                   │
│  1. Get Data:                                                    │
│     $project_id = intval($_POST['project_id']) or null           │
│                                                                   │
│  2. Insert Query:                                                │
│     INSERT INTO receive_voucher (                                │
│       voucher_number, voucher_date, customer_id, ...,            │
│       project_id,          ◄──── NEW FIELD                       │
│       ...                                                         │
│     ) VALUES (?, ?, ?, ..., ?, ...)                              │
│                                                                   │
│  3. Response:                                                    │
│     {'success': true, 'voucher_id': 123}                         │
│                                                                   │
└─────────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│  DATABASE (receive_voucher table)                                │
├─────────────────────────────────────────────────────────────────┤
│                                                                   │
│  id  | voucher_no | customer_id | amount | project_id | ...     │
│  ──────────────────────────────────────────────────────────      │
│  123 | RV-2024-001| 45         | 5000   | 1          | ...      │
│  124 | RV-2024-002| 46         | 3000   | NULL       | ...      │
│  125 | RV-2024-003| 47         | 2500   | 2          | ...      │
│                                                                   │
│  Foreign Key: project_id → projects(id)                          │
│  ON DELETE: SET NULL                                             │
│                                                                   │
└─────────────────────────────────────────────────────────────────┘
```

---

## BULK MODE FLOW

```
┌─────────────────────────────────────────────────────────────────┐
│  BULK MODE TABLE                                                 │
├─────────────────────────────────────────────────────────────────┤
│                                                                   │
│  Date    | Customer | Bal    | DSR#  | PROJECT | Currency | ... │
│  ─────────────────────────────────────────────────────────────  │
│  01/15   | CUST-01  | 5000   | ---   | [▼] ◄── NEW COL       │
│          |          |        |       | CODE-NAME-CITY         │
│  01/16   | CUST-02  | 3000   | ---   | [▼]                    │
│  01/17   | CUST-03  | 2500   | ---   | [▼]                    │
│                                                                   │
│  [Add Row] [Post All Vouchers]                                   │
│                                                                   │
└─────────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│  JAVASCRIPT - Bulk Processing                                    │
├─────────────────────────────────────────────────────────────────┤
│                                                                   │
│  For Each Row:                                                   │
│    formData.append('project_id',                                 │
│      row.querySelector('.bulk-project').value)                   │
│                                                                   │
│  Send POST: /receive-add.php                                     │
│    Loop through all rows                                         │
│    Track: successCount, failCount                                │
│                                                                   │
└─────────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│  API Processing (Same as Single Mode)                            │
├─────────────────────────────────────────────────────────────────┤
│  Validate project_id → Save to DB → Return success              │
└─────────────────────────────────────────────────────────────────┘
```

---

## EDIT MODE FLOW

```
┌─────────────────────────────────────────────────────────────────┐
│  Load Receive Voucher for Edit                                   │
├─────────────────────────────────────────────────────────────────┤
│  Fetch: /receive-edit.php?id=123                                │
│  Response: {voucher_data, project_id: 1}                        │
│                                                                   │
└─────────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│  UI - Prepopulate Project Dropdown                              │
├─────────────────────────────────────────────────────────────────┤
│  Display all projects in dropdown                                │
│  Select the one with id matching project_id from DB              │
│                                                                   │
│  Example:                                                        │
│  - Project dropdown shows: PRJ-001, PRJ-002, PRJ-003             │
│  - Pre-select: PRJ-001 (id=1)                                    │
│                                                                   │
└─────────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│  User Updates Project                                            │
├─────────────────────────────────────────────────────────────────┤
│  Select different project or clear it                            │
│  Click Update/Save button                                        │
│                                                                   │
└─────────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│  API - receive-edit.php (PUT Request)                            │
├─────────────────────────────────────────────────────────────────┤
│                                                                   │
│  Get JSON: {project_id: 2, ...other fields}                     │
│                                                                   │
│  UPDATE receive_voucher SET                                      │
│    project_id = 2,                                               │
│    ...other fields...                                            │
│  WHERE id = 123                                                  │
│                                                                   │
└─────────────────────────────────────────────────────────────────┘
```

---

## PROJECT DROPDOWN DISPLAY OPTIONS

```
┌─────────────────────────────────────────────────────────────────┐
│  Projects from Database:                                        │
├─────────────────────────────────────────────────────────────────┤
│                                                                   │
│  With city_name populated:                                       │
│  ┌─────────────────────────────────────────────┐                │
│  │ Select Project                          ▼  │                │
│  ├─────────────────────────────────────────────┤                │
│  │ PRJ-001 - Karachi Office - Karachi          │                │
│  │ PRJ-002 - Lahore Warehouse - Lahore         │                │
│  │ PRJ-003 - Islamabad Branch - Islamabad      │                │
│  └─────────────────────────────────────────────┘                │
│                                                                   │
│  With NULL city_name:                                            │
│  ┌─────────────────────────────────────────────┐                │
│  │ Select Project                          ▼  │                │
│  ├─────────────────────────────────────────────┤                │
│  │ PRJ-004 - Demo Project - N/A                │                │
│  └─────────────────────────────────────────────┘                │
│                                                                   │
└─────────────────────────────────────────────────────────────────┘
```

---

## DATABASE SCHEMA

```
┌──────────────────────────────────────────────────────┐
│  receive_voucher (BEFORE)                            │
├──────────────────────────────────────────────────────┤
│ id              INT PRIMARY KEY                      │
│ tenant_id       INT                                  │
│ company_id      INT                                  │
│ voucher_number  VARCHAR(50)                          │
│ voucher_date    DATE                                 │
│ customer_id     INT                                  │
│ sub_account_id  INT                                  │
│ bill_no         VARCHAR(100)                         │
│ dsr_no          VARCHAR(50)                          │
│ amount          DECIMAL(12,2)                        │
│ currency_id     INT                                  │
│ payment_method_id INT                               │
│ bank_account_id INT                                  │
│ cheque_date     DATE                                 │
│ cheque_no       VARCHAR(50)                          │
│ description     TEXT                                 │
│ recovery_officer_id INT                              │
│ attachment      VARCHAR(255)                         │
│ created_by      INT                                  │
│ updated_by      INT                                  │
│ created_at      TIMESTAMP                            │
│ updated_at      TIMESTAMP                            │
└──────────────────────────────────────────────────────┘
                          │
                          │ ALTER TABLE ADD COLUMN
                          ▼
┌──────────────────────────────────────────────────────┐
│  receive_voucher (AFTER)                             │
├──────────────────────────────────────────────────────┤
│ [All previous columns...]                            │
│ dsr_no          VARCHAR(50)                          │
│ project_id      INT NULL ◄──── NEW COLUMN            │
│ amount          DECIMAL(12,2)                        │
│ [All remaining columns...]                           │
└──────────────────────────────────────────────────────┘
        │
        └─── Foreign Key: fk_receive_voucher_project_id
             References: projects(id)
             On Delete: SET NULL
             On Update: CASCADE
```

---

## PROJECT TABLE REFERENCE

```
┌────────────────────────────────────────────┐
│  projects                                  │
├────────────────────────────────────────────┤
│ id              INT PRIMARY KEY             │
│ tenant_id       INT                         │
│ project_code    VARCHAR(50)                 │
│ project_name    VARCHAR(255)                │
│ city_id         INT (nullable)              │
│ city_name       VARCHAR(100)                │
│ status          ENUM('active','inactive')   │
│ created_at      TIMESTAMP                   │
│ updated_at      TIMESTAMP                   │
└────────────────────────────────────────────┘
```

---

## API ENDPOINTS

```
GET /get-projects-dropdown.php
    ├─ Response:
    │  {
    │    "success": true,
    │    "projects": [
    │      {
    │        "id": 1,
    │        "project_code": "PRJ-001",
    │        "project_name": "Karachi Office",
    │        "city_name": "Karachi"
    │      },
    │      ...
    │    ]
    │  }
    └─ Used by: Single Mode, Bulk Mode

POST /receive-add.php
    ├─ Params: {..., project_id: 1}
    ├─ Saves: receive_voucher with project_id
    └─ Returns: {success: true, voucher_id: 123}

PUT /receive-edit.php?id=123
    ├─ Params: {..., project_id: 2}
    ├─ Updates: receive_voucher.project_id = 2
    └─ Returns: {success: true}
```

---

## COMPLETE ✅

This diagram shows the complete flow of project integration in the Receive Voucher system.
