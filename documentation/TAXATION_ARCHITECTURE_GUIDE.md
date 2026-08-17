# POS Invoice Taxation Architecture Guide

## Overview
The POS invoice taxation system has two independent tax application levels:
1. **Item-Level Taxation** - Tax applied on individual line items
2. **Invoice-Level Taxation** - Tax applied on the entire invoice's net amount

---

## 1. Core Components

### Files Involved
```
├── client/pages/sale/pos_invoice/
│   └── pos-add.php                              (Main HTML form)
│
├── client/assets/js/sale/pos_invoice/
│   ├── pos-tax-calculation.js                   (Core tax calculations)
│   ├── tax-integration.js                       (Auto-loading mechanism)
│   ├── invoice-level-taxes-dynamic.js           (Invoice-level taxes)
│   ├── collect-taxes.js                         (Tax collection for saving)
│   ├── withholding-tax.js                       (AIT/Withholding tax)
│   ├── pos-add-scheme.js                        (Scheme management)
│   └── pos-add.js                               (Main form logic)
│
└── server/api/sale/pos_invoice/
    ├── calculate-tax.php                        (Tax fetching API)
    ├── get-invoice-level-taxes.php              (Invoice-level tax API)
    └── get-invoice-taxes.php                    (Alternative tax API)
```

---

## 2. Key Data Structures

### Tax Metadata (Stored in row.dataset)
```javascript
row.dataset.taxRate = "17.00"                           // Tax percentage
row.dataset.basePrice = "1000"                          // Trade price or MRP
row.dataset.formulaTemplate = "{trade_price} * ({rate} / 100)"  // Template
row.dataset.applicationLevel = "item"                   // 'item' or 'invoice'
row.dataset.formulaTemplate = "{trade_price} * ({rate} / 100)"  // Tax formula
```

### Invoice-Level Tax Regime Object
```javascript
{
  id: 1,
  regime_name: "Sales Tax",
  rate_percentage: "17.00",
  application_level: "invoice"
}
```

### Tax Collection Format (When Saving)
```javascript
{
  taxRegimeId: 1,
  taxRateId: null,
  taxName: "Sales Tax",
  ratePercentage: 17.00,
  baseAmount: 5000,
  taxAmount: 850
}
```

---

## 3. Core Functions

### 3.1 Tax Fetching Functions

#### `calculateProductTax(customerId, productId, salePriceSetting = 'trade_price')`
**Location**: pos-tax-calculation.js  
**Purpose**: Fetch tax rate and formula for a customer-product combination

```javascript
const result = await calculateProductTax(customerId, productId, 'trade_price');
// Returns:
{
  success: true,
  tax_rate: 17,
  tax_amount: 170,
  formula_template: "{trade_price} * ({rate} / 100)",
  base_price: 1000,
  application_level: "item",
  mismatch: false,  // Warning if true
  message: "Success"
}
```

**Flow**:
1. Calls `/api/calculate-tax.php?customer_id={id}&product_id={id}`
2. Returns tax metadata with formula template
3. Shows notification if mismatch detected

---

#### `loadTaxRatesForCustomer(customerId)`
**Location**: pos-tax-calculation.js  
**Purpose**: Reload tax for ALL rows when customer changes

```javascript
await loadTaxRatesForCustomer(123);
```

**Flow**:
1. Gets all rows from items table
2. For each row with a product:
   - Calls `calculateProductTax()`
   - Updates row dataset
   - Calls `calculateRowAmounts()` to recalculate
3. Calls `loadInvoiceLevelTaxRegimes()` to load customer-specific invoice taxes
4. Fully automated on customer selection

---

#### `loadTaxForNewProduct(row, customerId, productId)`
**Location**: pos-tax-calculation.js  
**Purpose**: Load tax for a single row when product is selected

```javascript
await loadTaxForNewProduct(row, customerId, productId);
```

**Sets**:
- `row.dataset.taxRate`
- `row.dataset.basePrice`
- `row.dataset.formulaTemplate`
- `row.dataset.applicationLevel`

**Handles**:
- Item-level taxes: Tax % input is editable
- Invoice-level taxes: Tax % input shows "0.00" and is read-only (grayed out)

---

### 3.2 Formula Evaluation

#### `evaluateFormula(formula, basePrice, rate)`
**Location**: pos-tax-calculation.js  
**Purpose**: Evaluate tax formula with actual values

```javascript
const taxAmount = evaluateFormula(
  "{trade_price} * ({rate} / 100)",
  1000,          // basePrice
  17             // rate (%)
);
// Returns: 170
```

**Tokens Replaced**:
- `{trade_price}` → basePrice value
- `{mrp}` → basePrice value (alternative)
- `{rate}` → tax rate percentage

**Security**:
- Validates formula contains only: `[0-9+\-*\/().]`
- Uses `Function()` constructor for safe evaluation
- Returns 0 if validation fails

---

### 3.3 Item-Level Tax Application

#### `applyTaxToRow(row, taxRate, formula, basePrice, applicationLevel = 'item')`
**Location**: pos-tax-calculation.js  
**Purpose**: Apply calculated tax to a row

```javascript
applyTaxToRow(row, 17, "{trade_price} * ({rate} / 100)", 1000, "item");
```

**For Item-Level Taxes**:
- Tax % input: editable, shows actual rate
- Tax Amount input: shows calculated amount
- User can override tax % if needed

**For Invoice-Level Taxes**:
- Tax % input: shows "0.00", read-only (grayed background, cursor: not-allowed)
- Tax Amount input: shows "0.00"
- User cannot modify (tax calculated at invoice level)

---

### 3.4 Row Amount Calculation

#### `calculateRowAmounts(row, totalQty, price)`
**Location**: pos-add.js (main logic)  
**Purpose**: Calculate all amounts for a row including tax

**Formula**:
```
Gross Amount = Total Qty × Price
Discount Amount = Gross Amount × (Discount % / 100)
Trade Offer Amount = (Based on scheme)
Taxable Amount = Gross Amount - Discount - Trade Offer
Tax Amount = evaluateFormula(formula, Taxable Amount, tax_rate)
Net Amount = Taxable Amount + Tax Amount
```

---

### 3.5 Invoice-Level Tax Functions

#### `loadInvoiceLevelTaxRegimes(customerId)`
**Location**: invoice-level-taxes-dynamic.js  
**Purpose**: Fetch invoice-level tax regimes for a customer

```javascript
await loadInvoiceLevelTaxRegimes(123);
// Fetches from: /api/get-invoice-level-taxes.php?customer_id=123
```

**Stores**: `window.invoiceLevelTaxRegimes = [{id, regime_name, rate_percentage}, ...]`

**Automatically Called When**:
- Customer is selected
- Page loads in edit mode

---

#### `renderInvoiceLevelTaxColumns()`
**Location**: invoice-level-taxes-dynamic.js  
**Purpose**: Create dynamic HTML for invoice-level taxes

**Creates** for each regime:
- `<div id="invoiceTax_{id}_percent">` - Shows tax rate %
- `<div id="invoiceTax_{id}_amount">` - Shows calculated tax amount

**Location**: Inside `#invoiceLevelTaxesContainer`

---

#### `calculateInvoiceLevelTaxes()`
**Location**: invoice-level-taxes-dynamic.js  
**Purpose**: Calculate tax amounts for all invoice-level regimes

```javascript
calculateInvoiceLevelTaxes();
```

**Formula** (for each regime):
```
Tax Amount = Net Amount × (Rate Percentage / 100)
```

**Updates**:
1. Each regime's % and amount display elements
2. `#netReceivable = Net Amount + Total Invoice Taxes`

**Triggered When**:
- Invoice summary changes (discount, shipping, etc.)
- Customer changes
- Items total changes

---

### 3.6 Tax Collection & Saving

#### `collectInvoiceTaxes()`
**Location**: collect-taxes.js  
**Purpose**: Collect all invoice-level taxes before saving

```javascript
const taxes = collectInvoiceTaxes();
// Returns:
[
  {
    taxRegimeId: 1,
    taxRateId: null,
    taxName: "Sales Tax",
    ratePercentage: 17,
    baseAmount: 5000,
    taxAmount: 850
  },
  // ... more regimes
]
```

**Process**:
1. Calls `calculateInvoiceLevelTaxes()` to ensure latest values
2. Reads DOM elements for each regime
3. Returns only taxes with amount > 0 or rate > 0
4. Used when submitting form

---

### 3.7 Withholding Tax (AIT)

#### `updateInvoiceSummaryWithAIT()`
**Location**: withholding-tax.js  
**Purpose**: Update net receivable with AIT deduction

```javascript
updateInvoiceSummaryWithAIT();
```

**Formula**:
```
Withholding Tax Amount = Net Amount × (AIT % / 100)
Net Receivable = Net Amount - Withholding Tax Amount
```

**Triggered When**:
- Customer selected (loads AIT % from customer)
- Net Amount changes

---

#### `handleCustomerTaxInfo(customer)`
**Location**: withholding-tax.js  
**Purpose**: Load customer tax properties

```javascript
handleCustomerTaxInfo(customerObject);
```

**Reads from Customer**:
- `advance_income_tax_percentage` - AIT rate
- `is_filer` - 0 = non-filer (higher tax rate)
- `is_sales_tax_registered` - Sales tax registration status
- `ntn`, `strn` - Tax registration numbers

**Actions**:
- Sets withholding tax percentage
- Shows warning if customer is non-filer
- Logs tax registration details

---

## 4. Automatic Tax Loading (tax-integration.js)

### MutationObserver Setup
```javascript
// Monitors for:
// 1. New rows added to items table
// 2. Product code changes in existing rows
// 3. Customer selection changes
```

**Triggers**:
- Row added → `setupRowTaxLoading(row)`
- Product selected → `loadTaxForProductSelection(row)`
- Customer changed → `loadTaxRatesForCustomer(customerId)`

**NO Manual Coding Needed** - Fully automated!

---

## 5. Application Levels Explained

### ITEM-LEVEL TAXATION
```
When: application_level = "item"
Effect: Tax calculated on individual line items
Table Column: [Tax %, Tax Amt] both editable
Formula: Tax = evaluateFormula(formula_template, base_price, tax_rate)
Scope: Each row independent
```

### INVOICE-LEVEL TAXATION
```
When: application_level = "invoice"
Effect: Tax calculated once on net invoice amount
Table Column: [Tax %, Tax Amt] both show "0.00" (read-only, grayed)
Formula: Tax = Net Amount × (Rate % / 100)
Scope: Applied to entire invoice
```

---

## 6. Usage Flow - From User Action to Tax Calculation

### Scenario 1: User Selects Customer
```
1. User selects customer from dropdown
   ↓
2. Dropdown click detected in tax-integration.js
   ↓
3. loadTaxRatesForCustomer(customerId) called
   ↓
4. For each row with product:
   - calculateProductTax(customerId, productId) → API call
   - applyTaxToRow() → Updates row with tax metadata
   - calculateRowAmounts() → Recalculates with new tax
   ↓
5. loadInvoiceLevelTaxRegimes(customerId) called
   ↓
6. renderInvoiceLevelTaxColumns() → Creates invoice tax fields
   ↓
7. calculateInvoiceLevelTaxes() → Calculates invoice taxes
   ↓
8. Net Receivable updated
```

### Scenario 2: User Adds Product to Row
```
1. User selects product in row
   ↓
2. MutationObserver detects product code change
   ↓
3. loadTaxForProductSelection(row) called
   ↓
4. calculateProductTax(customerId, productId) → API call
   ↓
5. loadTaxForNewProduct(row, ...) → Sets row.dataset
   ↓
6. calculateRowAmounts() triggered
   ↓
7. evaluateFormula() → Calculates tax for this row
   ↓
8. Row displays: Tax %, Tax Amt based on application_level
   ↓
9. If application_level = "invoice":
   - calculateInvoiceLevelTaxes() auto-triggers
   - Net Receivable updates
```

### Scenario 3: User Saves Invoice
```
1. User clicks Save button
   ↓
2. Form validates
   ↓
3. Before POST: collectInvoiceTaxes() called
   ↓
4. Collects all invoice-level taxes from DOM
   ↓
5. POST includes:
   - Item-level tax data (in items array)
   - Invoice-level tax data (separate array)
   ↓
6. Server saves invoice with all tax details
```

---

## 7. Key Datasets & Variables

### Global Variables (in pos-add.js)
```javascript
window.invoiceLevelTaxRegimes = []      // Current customer's tax regimes
window.currentNetAmount = 0              // Latest net amount for calculations
```

### Row-Level Data Storage
```javascript
row.dataset.taxRate                     // Tax rate percentage
row.dataset.basePrice                   // Base amount for tax calculation
row.dataset.formulaTemplate             // Tax formula template
row.dataset.applicationLevel            // 'item' or 'invoice'
```

### LocalStorage (Settings)
```javascript
localStorage.getItem('salePriceSetting') // 'trade_price' or 'mrp'
localStorage.getItem('enableTaxation')  // Tax feature toggle
```

---

## 8. Error Handling

### Tax Mismatch Warning
```javascript
if (taxResult.mismatch) {
  showNotification(`Tax Mismatch: ${message}`, 'warning');
  taxPercentInput.disabled = true;
}
```

### Non-Filer Customer Warning
```javascript
if (customer.is_filer == 0 && withholdingTaxPercent > 0) {
  showNotification('Customer is non-filer - Higher AIT rate applied', 'warning');
}
```

### Formula Validation
```javascript
if (!/^[0-9+\-*\/().]+$/.test(formula)) {
  return 0;  // Invalid formula returns 0
}
```

---

## 9. Quick Reference: Function Call Map

```
Customer Selected
  └─ loadTaxRatesForCustomer(customerId)
      ├─ calculateProductTax() × n rows
      ├─ applyTaxToRow() × n rows
      ├─ calculateRowAmounts() × n rows
      └─ loadInvoiceLevelTaxRegimes()
          ├─ renderInvoiceLevelTaxColumns()
          └─ calculateInvoiceLevelTaxes()

Product Selected
  └─ loadTaxForProductSelection(row)
      └─ loadTaxForNewProduct()
          ├─ evaluateFormula()
          └─ calculateRowAmounts()

Qty/Price Changed
  └─ calculateRowAmounts()
      ├─ evaluateFormula()
      └─ (auto-triggers) calculateInvoiceLevelTaxes()

Save Form
  └─ collectInvoiceTaxes()
      └─ calculateInvoiceLevelTaxes() [recalculate]
```

---

## 10. Debugging Tips

### Check Tax Metadata in Console
```javascript
// Find a row element (e.g., first data row)
const row = document.querySelector('tbody tr');
console.log(row.dataset);
// Shows: taxRate, basePrice, formulaTemplate, applicationLevel
```

### Check Invoice-Level Tax Regimes
```javascript
console.log(window.invoiceLevelTaxRegimes);
// Shows array of current customer's tax regimes
```

### Verify Formula Evaluation
```javascript
// Manually test formula
evaluateFormula("{trade_price} * ({rate} / 100)", 1000, 17);
// Should return: 170
```

### Check Tax Collection
```javascript
// Before save, verify what's being collected
console.log(collectInvoiceTaxes());
// Shows all taxes that will be saved
```

---

## 11. Integration Points

### API Endpoints Called
- `POST /server/api/sale/pos_invoice/calculate-tax.php`
  - Params: `customer_id`, `product_id`, `sale_price_setting`
  - Returns: Tax rate, formula, base price, application level

- `GET /server/api/sale/pos_invoice/get-invoice-level-taxes.php`
  - Params: `customer_id`
  - Returns: Array of invoice-level tax regimes

### Form Data Structure (When Saving)
```javascript
{
  // ... invoice details ...
  items: [
    {
      // ... item data ...
      tax_percent: 17,        // Item-level tax %
      tax_amount: 170,        // Item-level tax amount
      // ... other fields ...
    }
  ],
  invoiceTaxes: [             // Invoice-level taxes
    {
      taxRegimeId: 1,
      ratePercentage: 17,
      taxAmount: 850
    }
  ]
}
```

---

## Summary

**Two Tax Levels**:
- **Item-Level**: Applied per line item, user-editable
- **Invoice-Level**: Applied to entire invoice, read-only at item level

**Automatic Triggers**:
- Customer change → Reload all taxes
- Product selection → Load tax for row
- Quantity/Price change → Recalculate amounts with tax

**Key Functions**:
1. `loadTaxRatesForCustomer()` - Bulk tax loading
2. `calculateProductTax()` - Fetch tax from API
3. `evaluateFormula()` - Calculate tax with formula
4. `loadInvoiceLevelTaxRegimes()` - Fetch invoice taxes
5. `calculateInvoiceLevelTaxes()` - Calculate invoice-level amounts
6. `collectInvoiceTaxes()` - Prepare taxes for saving

**No Manual Intervention Needed** - MutationObserver automatically detects changes and triggers tax calculations.
