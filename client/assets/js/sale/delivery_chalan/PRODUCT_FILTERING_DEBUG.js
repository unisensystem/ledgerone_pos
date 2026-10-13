// PRODUCT FILTERING - TESTING & DEBUGGING GUIDE
// ================================================

/*
FEATURE OVERVIEW:
- Product filtering based on Sales Officer selection
- Two modes: "Show All Products" (default) and "Sales Officer Product Filtering"
- Settings persist in localStorage
- Auto-populates Sales Officer from Customer selection
- Real-time filtering on new rows

TESTING CHECKLIST:
==================

1. PAGE LOAD TEST
   ✓ Open pos-add.php
   ✓ Check browser console for "Product filter script ready"
   ✓ Verify "Product Filtering Mode: showAll" in console
   ✓ Check Invoice Settings modal - "Show All Products" should be selected

2. CUSTOMER SELECTION TEST
   ✓ Select a customer with associated Sales Officer
   ✓ Check console: "Customer selected: [ID] | Supplier Man: [ID]"
   ✓ Verify Sales Officer field auto-populates
   ✓ Check console: "Auto-populated Sales Officer: [ID]"

3. FILTERING MODE TOGGLE TEST
   ✓ Open Invoice Settings modal
   ✓ Select "Sales Officer Product Filtering"
   ✓ Check console: "Filtering mode changed to: salesOfficerFilter"
   ✓ Verify localStorage has "productFilteringMode: salesOfficerFilter"
   ✓ Check console: "Loaded [X] filtered products"
   ✓ Product dropdown should show only filtered products

4. PRODUCT DROPDOWN TEST
   ✓ Click on Product Code/Name field
   ✓ Verify dropdown shows correct products based on mode
   ✓ If filtering enabled: should show only Sales Officer's products
   ✓ If filtering disabled: should show all products

5. NEW ROW TEST
   ✓ Click "Add Item" button
   ✓ New row should have correct product list
   ✓ If filtering enabled: filtered products
   ✓ If filtering disabled: all products

6. RELOAD TEST
   ✓ Set filtering mode to "Sales Officer Product Filtering"
   ✓ Select a Sales Officer
   ✓ Reload page (F5)
   ✓ Verify filtering mode persists
   ✓ Verify Sales Officer is still selected
   ✓ Verify products are still filtered

7. SWITCHING SALES OFFICER TEST
   ✓ Select Sales Officer A
   ✓ Check products shown
   ✓ Change to Sales Officer B
   ✓ Check console: "Sales Officer changed to: [B's ID]"
   ✓ Verify products update to Sales Officer B's products

CONSOLE LOGS TO LOOK FOR:
=========================

On Page Load:
  ✓ "Product filter script ready"
  ✓ "DOMContentLoaded - Initializing product filtering"
  ✓ "Starting product filtering initialization..."
  ✓ "✓ Loaded filtering mode: [mode]"
  ✓ "Found X filtering radio buttons"

On Customer Selection:
  ✓ "👥 Customer selected: [ID] | Supplier Man: [ID]"
  ✓ "✓ Auto-populated Sales Officer: [ID]"
  ✓ "👤 Sales Officer changed to: [ID]"

On Filtering Mode Change:
  ✓ "🔄 Filtering mode changed to: [mode]"
  ✓ "📋 Applying filtering - Mode: [mode] | Sales Officer: [ID]"
  ✓ "🔗 Fetching products for sales officer: [ID]"
  ✓ "✓ Loaded [X] filtered products"

On New Row:
  ✓ "🔄 Updating all dropdowns with [X] products"
  ✓ "✓ Updated [X] row dropdowns"

TROUBLESHOOTING:
================

ISSUE: Products not filtering
FIX:
  1. Check console for errors
  2. Verify API endpoint: get-products-by-sales-officer.php
  3. Check Sales Officer ID is valid
  4. Verify filtering mode is "salesOfficerFilter"
  5. Check localStorage: localStorage.getItem('productFilteringMode')

ISSUE: Sales Officer not auto-populating
FIX:
  1. Check customer has data-supplier-man attribute
  2. Verify customer data is loaded correctly
  3. Check console for "Auto-populated" message
  4. Verify Sales Officer select element exists

ISSUE: New rows not getting filtered products
FIX:
  1. Check addRowDynamic function is being called
  2. Verify dropdownOptions element exists on new row
  3. Check console for "New row:" messages
  4. Verify filteredProductsData is populated

ISSUE: Settings not persisting after reload
FIX:
  1. Check localStorage is enabled in browser
  2. Verify localStorage.setItem is being called
  3. Check browser's localStorage in DevTools
  4. Clear localStorage and try again

DEBUGGING COMMANDS (Run in Console):
====================================

// Check current filtering mode
console.log('Current mode:', productFilteringMode);

// Check localStorage
console.log('Stored mode:', localStorage.getItem('productFilteringMode'));

// Check filtered products
console.log('Filtered products:', filteredProductsData);

// Check all products
console.log('All products:', productsData);

// Check current sales officer
console.log('Current Sales Officer ID:', currentSalesOfficerId);

// Manually trigger filtering
applyProductFilteringNow(123); // Replace 123 with Sales Officer ID

// Check radio button state
console.log('Radio checked:', document.querySelector('input[name="productFilteringMode"]:checked').value);

// Force update all dropdowns
updateAllProductDropdowns(productFilteringMode === 'salesOfficerFilter' ? filteredProductsData : productsData);

API ENDPOINT:
=============
GET /server/api/sale/pos_invoice/get-products-by-sales-officer.php?sales_officer_id=[ID]

Expected Response:
{
  "success": true,
  "products": [
    {
      "id": 1,
      "code": "PROD001",
      "name": "Product Name",
      "mrp": 100,
      "trade_price": 80,
      "default_unit_id": 1,
      "photo": "photo.jpg",
      "qr_code": "QR123",
      "barcode": "BAR123"
    }
  ]
}

FEATURE FLOW:
=============

1. Page Load
   ↓
2. Load filtering mode from localStorage (default: 'showAll')
   ↓
3. Setup event listeners for:
   - Invoice Settings radio buttons
   - Sales Officer select change
   - Customer dropdown selection
   ↓
4. When Customer Selected
   ↓
5. Auto-populate Sales Officer from customer data
   ↓
6. Trigger Sales Officer change event
   ↓
7. Check filtering mode
   ├─ If 'showAll': Show all products
   └─ If 'salesOfficerFilter': Fetch and show filtered products
   ↓
8. Update all product dropdowns in table
   ↓
9. When new row added
   ↓
10. Update new row's dropdown with current product list
    ├─ If filtering enabled: Use filtered products
    └─ If filtering disabled: Use all products

*/

// Quick test function - run in console
function testProductFiltering() {
    console.log('=== PRODUCT FILTERING TEST ===');
    console.log('Filtering Mode:', productFilteringMode);
    console.log('Current Sales Officer ID:', currentSalesOfficerId);
    console.log('All Products Count:', productsData?.length || 0);
    console.log('Filtered Products Count:', filteredProductsData?.length || 0);
    console.log('localStorage Mode:', localStorage.getItem('productFilteringMode'));
    
    const radio = document.querySelector('input[name="productFilteringMode"]:checked');
    console.log('Radio Button Selected:', radio?.value || 'None');
    
    const salesOfficerSelect = document.getElementById('salesOfficer');
    console.log('Sales Officer Select Value:', salesOfficerSelect?.value || 'None');
    
    const itemsTable = document.getElementById('itemsTable');
    const tbody = itemsTable?.getElementsByTagName('tbody')[0];
    console.log('Table Rows:', tbody?.rows?.length || 0);
    
    console.log('=== TEST COMPLETE ===');
}

// Run test
// testProductFiltering();
