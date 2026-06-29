# 🏗️ Architecture & Workflow Diagram

## System Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                        USER BROWSER                             │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │   Frontend Forms (HTML + CSS + JavaScript)               │  │
│  │                                                          │  │
│  │  ├─ project-product-rate-add.php (Form)                │  │
│  │  └─ project-product-rate-list.php (List)               │  │
│  └──────────────────────────────────────────────────────────┘  │
└──────────────────────┬──────────────────────────────────────────┘
                       │ HTTP Requests
                       │ (JSON)
                       ▼
┌─────────────────────────────────────────────────────────────────┐
│                    WEB SERVER (PHP)                             │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │         API Endpoints (RESTful)                          │  │
│  │                                                          │  │
│  │  ├─ POST   add-rate.php       (Create)                 │  │
│  │  ├─ POST   update-rate.php    (Update)                 │  │
│  │  ├─ POST   delete-rate.php    (Delete)                 │  │
│  │  ├─ GET    get-rate.php       (Read single)            │  │
│  │  ├─ GET    get-all-rates.php  (Read all)               │  │
│  │  ├─ GET    get-projects.php   (Dropdown)               │  │
│  │  └─ GET    get-products.php   (Dropdown)               │  │
│  └──────────────────────────────────────────────────────────┘  │
└──────────────────────┬──────────────────────────────────────────┘
                       │ SQL Queries
                       │ (Prepared Statements)
                       ▼
┌─────────────────────────────────────────────────────────────────┐
│                    MYSQL DATABASE                               │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │   project_product_rates (Main Table)                     │  │
│  │   ├─ id (PK)                                             │  │
│  │   ├─ project_id (FK → projects)                          │  │
│  │   ├─ product_id (FK → products)                          │  │
│  │   ├─ rate (DECIMAL)                                      │  │
│  │   ├─ created_at                                          │  │
│  │   └─ updated_at                                          │  │
│  └──────────────────────────────────────────────────────────┘  │
│                                                                  │
│  References:                                                     │
│  ├─ projects (id, project_code, project_name, ...)            │
│  └─ products (id, code, name, ...)                            │
└─────────────────────────────────────────────────────────────────┘
```

---

## Request/Response Flow Diagram

### Add New Rate Flow
```
User Opens Form
    │
    ├─ Load Dropdown Data (get-projects.php + get-products.php)
    │
User Fills Form
    ├─ Project: "PRJ001 - Project Alpha"
    ├─ Product: "PROD001 - Product A"
    └─ Rate: 250.50
    │
User Clicks Save
    │
Frontend Validation ✓
    │
Sends JSON to API (POST add-rate.php)
    │
    {
      "project_id": 1,
      "product_id": 5,
      "rate": 250.50
    }
    │
Backend Validation ✓
    │
Check Unique Constraint ✓
    │
Insert into Database
    │
API Returns Success ✓
    │
    {
      "success": true,
      "message": "Rate added successfully",
      "id": 1
    }
    │
Show Success Message
    │
Redirect to List Page
```

---

### Update Rate Flow
```
User Opens List Page
    │
    ├─ Fetch All Rates (get-all-rates.php)
    │
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
      ]
    }
    │
Display Rates in Table ✓
    │
User Clicks Edit Icon
    │
    ├─ Redirect to: project-product-rate-add.php?edit=1
    │
Fetch Rate Data (get-rate.php?id=1)
    │
    {
      "success": true,
      "rate": {
        "id": 1,
        "project_id": 1,
        "product_id": 5,
        "rate": "250.50"
      }
    }
    │
Populate Form with Data ✓
    │
User Changes Rate to 275.00
    │
User Clicks Save
    │
Send JSON to API (POST update-rate.php)
    │
    {
      "id": 1,
      "project_id": 1,
      "product_id": 5,
      "rate": 275.00
    }
    │
API Updates Database
    │
Show Success Message ✓
    │
Redirect to List
```

---

### Delete Rate Flow
```
User Views List
    │
    ├─ See Rate: "PRJ001 | PROD001 | 250.50"
    │
User Clicks Delete Icon (🗑)
    │
Show Confirmation Modal
    │
    "Are you sure you want to delete this rate?
     This action cannot be undone."
    │
User Clicks Confirm
    │
Send JSON to API (POST delete-rate.php)
    │
    {
      "id": 1
    }
    │
Backend: Check if exists ✓
    │
Backend: Delete from database ✓
    │
API Returns Success
    │
    {
      "success": true,
      "message": "Rate deleted successfully"
    }
    │
Show Success Message
    │
Reload List (get-all-rates.php)
    │
Rate removed from table ✓
```

---

## Data Flow Diagram

```
                    Database Tables
                           │
            ┌──────────────┼──────────────┐
            │              │              │
        projects       products    project_product_rates
        ┌─────┐      ┌────────┐    ┌──────────────┐
        │ id  │──┐   │ id     │─┐  │ id           │
        │ ... │  │   │ code   │ │  │ project_id ──┼──→ projects.id
        └─────┘  │   │ name   │ │  │ product_id ──┼──→ products.id
                 │   │ ...    │ │  │ rate         │
                 │   └────────┘ │  │ created_at   │
                 │              │  │ updated_at   │
                 └──────────────┘  └──────────────┘
                                          │
                                   Unique Constraint:
                                   (project_id, product_id)
                                   Cannot have duplicates
```

---

## File Organization

```
ledgerone_erp/
│
├─ client/
│  └─ pages/
│     └─ master_setup/
│        └─ project_product_rates/          ⭐ NEW
│           ├─ project-product-rate-add.php
│           └─ project-product-rate-list.php
│
├─ server/
│  └─ api/
│     └─ master_setup/
│        └─ project_product_rates/          ⭐ NEW
│           ├─ add-rate.php
│           ├─ update-rate.php
│           ├─ delete-rate.php
│           ├─ get-rate.php
│           ├─ get-all-rates.php
│           ├─ get-projects.php
│           └─ get-products.php
│
├─ server/
│  └─ migrations/
│     └─ create_project_product_rates_table.sql  ⭐ NEW
│
├─ README_PROJECT_PRODUCT_RATES.md          ⭐ NEW
├─ QUICKSTART_PROJECT_PRODUCT_RATES.md      ⭐ NEW
└─ PROJECT_PRODUCT_RATES_SUMMARY.md         ⭐ NEW
```

---

## Component Interaction Diagram

```
┌──────────────────────────────────────────────────────────┐
│                   Form Component                         │
│  (project-product-rate-add.php)                         │
│                                                         │
│  ┌──────────┐      ┌──────────┐      ┌──────────┐     │
│  │ Project  │      │ Product  │      │  Rate    │     │
│  │Dropdown  │      │ Dropdown │      │ Input    │     │
│  └─────┬────┘      └────┬─────┘      └────┬─────┘     │
│        │                │                  │           │
│        └────────────┬───┴──────────────────┘           │
│                    │                                   │
│            ┌───────▼─────────┐                        │
│            │  Validation     │                        │
│            │  - All filled?  │                        │
│            │  - Valid data?  │                        │
│            └────────┬────────┘                        │
│                    │ Valid                            │
│            ┌───────▼─────────────┐                   │
│            │  JSON Serializer    │                   │
│            │  {                  │                   │
│            │    project_id: 1,   │                   │
│            │    product_id: 5,   │                   │
│            │    rate: 250.50     │                   │
│            │  }                  │                   │
│            └────────┬────────────┘                   │
│                    │                                │
└────────────────────┼────────────────────────────────┘
                    │
                    │ HTTP POST
                    │
┌────────────────────▼─────────────────────────────────┐
│          API Endpoint                                │
│  (add-rate.php / update-rate.php)                   │
│                                                     │
│  ┌──────────────────────────────────────────────┐  │
│  │ 1. Parse JSON Request                        │  │
│  │ 2. Backend Validation                        │  │
│  │ 3. Check Unique Constraint                   │  │
│  │ 4. Prepare SQL Statement                     │  │
│  │ 5. Bind Parameters                           │  │
│  │ 6. Execute Query                             │  │
│  │ 7. Return JSON Response                      │  │
│  └──────────────────┬───────────────────────────┘  │
│                    │                               │
│         ┌──────────▼──────────┐                   │
│         │  Error Handling     │                   │
│         │  Try-Catch Block    │                   │
│         └─────────┬──────────┘                    │
│                  │                                │
└──────────────────┼────────────────────────────────┘
                  │
                  │ {success: bool, message: string}
                  │
┌─────────────────▼──────────────────────────────────┐
│        Database Layer                              │
│  (MySQL/MariaDB)                                   │
│                                                    │
│  ┌────────────────────────────────────────────┐  │
│  │  INSERT/UPDATE/DELETE Operations          │  │
│  │  Using Prepared Statements (Security)     │  │
│  │                                            │  │
│  │  Tables:                                   │  │
│  │  - project_product_rates (Main)           │  │
│  │  - projects (Reference)                   │  │
│  │  - products (Reference)                   │  │
│  └────────────────────────────────────────────┘  │
│                    │                              │
└────────────────────┼──────────────────────────────┘
                    │
                    │ Success/Failure
                    │
                    ▼
            Response to User
            - Success Message ✓
            - Redirect to List
            OR
            - Error Message ✗
            - Stay on Form
```

---

## Technology Stack Diagram

```
Frontend Layer
    ├─ HTML5 (Semantic Structure)
    ├─ CSS3 (Responsive Styling)
    │  ├─ Flexbox Layout
    │  ├─ CSS Variables
    │  └─ Animations
    └─ JavaScript (Vanilla - No Framework)
       ├─ DOM Manipulation
       ├─ Event Listeners
       ├─ Fetch API
       └─ Local Data Validation

Application Layer
    ├─ PHP 7.4+ (Backend Logic)
    │  ├─ OOP Principles
    │  ├─ Error Handling
    │  └─ Security Best Practices
    └─ RESTful API Design
       ├─ Standard HTTP Methods
       ├─ JSON Request/Response
       └─ Proper HTTP Status Codes

Database Layer
    ├─ MySQL 5.7+ / MariaDB 10.3+
    ├─ Structured Query Language (SQL)
    ├─ Prepared Statements (Security)
    ├─ Indexes & Constraints
    └─ Foreign Key Relationships

Infrastructure
    ├─ Apache Web Server
    ├─ PHP-FPM
    └─ MySQL Server
```

---

## State Machine Diagram

```
                    [INITIAL]
                        │
                        ▼
                  [FORM_LOADING]
                 /      |      \
                /       |       \
              ▼        ▼        ▼
        [PROJECTS] [PRODUCTS] [RATE]
        LOADED     LOADED     EMPTY
              \       |       /
                \     |     /
                  ▼   ▼   ▼
            [READY_FOR_INPUT]
                    │
        ┌───────────┼───────────┐
        │           │           │
        ▼           ▼           ▼
    [EDITING]   [VALIDATING] [ERROR]
        │           │           │
        └───────────┼───────────┘
                    │
                    ▼
            [FORM_VALID]
                    │
                    ▼
            [SUBMITTING]
                    │
        ┌───────────┴───────────┐
        │                       │
        ▼                       ▼
    [SUCCESS]              [ERROR]
        │                    │
        ▼                    ▼
    [REDIRECT]           [SHOW_ERROR]
        │                    │
        ▼                    ▼
    [LIST_PAGE]         [STAY_ON_FORM]
```

---

## Security Architecture

```
Input → Frontend Validation → JSON → HTTP POST
         (Type checking)
                           │
                           ▼
                        API Layer
                           │
        ┌──────────────────┼──────────────────┐
        │                  │                  │
        ▼                  ▼                  ▼
    Parse JSON      Validate Input      Prepare Statement
         │               │                    │
         ├─ Type Check   ├─ Not Empty      ├─ Parameter Binding
         ├─ Required     ├─ Valid Format   ├─ SQL Injection Prevention
         └─ Format       └─ Range Check    └─ Safe Execution
                           │
                           ▼
                      Database Level
                           │
        ┌──────────────────┼──────────────────┐
        │                  │                  │
        ▼                  ▼                  ▼
    Foreign Keys     Unique Constraint    Type Validation
         │                  │                    │
         ├─ Referential     ├─ No Duplicates   ├─ DECIMAL(12,2)
         └─ Integrity       └─ Data Integrity  └─ NOT NULL
```

---

## Performance Optimization Points

```
1. INDEXING
   ├─ Primary Key (id): Auto-indexed
   ├─ Foreign Keys (project_id, product_id): Indexed
   └─ Unique Constraint: Indexed

2. QUERY OPTIMIZATION
   ├─ Use SELECT specific columns (not *)
   ├─ Use JOIN instead of subqueries
   ├─ Limit result set when needed
   └─ Use PREPARED STATEMENTS

3. FRONTEND OPTIMIZATION
   ├─ Lazy load dropdowns
   ├─ Debounce search input
   ├─ Cache data in memory
   └─ Minimize DOM operations

4. CACHING (Future Enhancement)
   ├─ Cache projects list
   ├─ Cache products list
   └─ Invalidate on changes
```

---

## Error Handling Flow

```
Error Occurs
    │
    ├─ Frontend Validation Error
    │  ├─ Show inline error message
    │  ├─ Highlight invalid field
    │  └─ Don't send to server
    │
    ├─ API Validation Error
    │  ├─ Return HTTP 400
    │  ├─ JSON: {success: false, message: "..."}
    │  └─ Show error in modal
    │
    ├─ Database Error
    │  ├─ Catch exception
    │  ├─ Log error (secure way)
    │  ├─ Return generic message (security)
    │  └─ Return HTTP 500
    │
    └─ Network Error
       ├─ No response from server
       ├─ Timeout error
       └─ Show network error message
```

---

This complete architecture ensures:
✅ Clean separation of concerns
✅ Security at every layer
✅ Scalability for growth
✅ Maintainability
✅ Performance
✅ User experience
