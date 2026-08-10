/**
 * Default Fields AutoFill - Developer Console Reference
 * 
 * You can run these commands in browser console (F12 → Console tab)
 * to test, debug, and manage the AutoFill system
 */

// ============================================================================
// BASIC OPERATIONS
// ============================================================================

// 1. Check if AutoFill is initialized
window.defaultFieldsAutofill
// Output: DefaultFieldsAutofill instance

// 2. Check current settings
window.defaultFieldsAutofill.getSettings()
// Output: {enabled: true}

// 3. Get all saved values
window.defaultFieldsAutofill.getSavedValues()
// Output: {invoiceType: "Credit", branch: "Main", ...}

// 4. Get summary (for display)
window.defaultFieldsAutofill.getSummary()
// Output: {invoiceType: "Credit", branch: "Main", ...}


// ============================================================================
// SAVE/LOAD OPERATIONS
// ============================================================================

// 5. Manually save a field
window.defaultFieldsAutofill.saveFieldValue('branch')

// 6. Manually set field value
window.defaultFieldsAutofill.setFieldValue('invoiceType', 'Cash')

// 7. Auto-populate all fields now
window.defaultFieldsAutofill.autoPopulateFields()

// 8. Save custom settings
window.defaultFieldsAutofill.saveSettings({enabled: false})
window.defaultFieldsAutofill.saveSettings({enabled: true})


// ============================================================================
// CLEAR/DELETE OPERATIONS
// ============================================================================

// 9. Clear all saved values
window.defaultFieldsAutofill.clearSavedValues()

// 10. Clear specific field
window.defaultFieldsAutofill.clearField('brand')

// 11. Clear specific fields (multiple)
window.defaultFieldsAutofill.clearField('salesOfficer')
window.defaultFieldsAutofill.clearField('supplierMan')


// ============================================================================
// SETTINGS MANAGEMENT
// ============================================================================

// 12. Load settings from modal
window.autofillSettingsHandler.loadSettings()

// 13. Save settings from modal
window.autofillSettingsHandler.saveSettings()

// 14. Update summary display
window.autofillSettingsHandler.updateSummary()


// ============================================================================
// DEBUGGING / TESTING
// ============================================================================

// 15. Check localStorage directly
localStorage.getItem('invoiceDefaultFields')
// Output: '{"invoiceType":"Credit","branch":"Main",...}'

localStorage.getItem('invoiceDefaultFieldsSettings')
// Output: '{"enabled":true}'

// 16. Check invoice form fields exist
document.getElementById('invoiceType')
document.getElementById('branch')
document.getElementById('areaCity')
document.getElementById('brand')
document.getElementById('salesOfficer')
document.getElementById('supplierMan')

// 17. Check if values are set in form
document.getElementById('invoiceType').value
document.getElementById('branch').value

// 18. Enable console logging
localStorage.setItem('autoFillDebug', 'true')
// Then refresh page to see detailed logs


// ============================================================================
// EXPORT/IMPORT (BACKUP & RESTORE)
// ============================================================================

// 19. Export all data
const backup = window.defaultFieldsAutofill.exportData()
console.log(JSON.stringify(backup))

// 20. Import previously exported data
const dataToRestore = {
  "settings": {"enabled": true},
  "values": {"invoiceType": "Credit", "branch": "Main"}
}
window.defaultFieldsAutofill.importData(dataToRestore)


// ============================================================================
// COMMON TESTING SCENARIOS
// ============================================================================

// Test 1: Verify AutoFill Works
function testAutoFill() {
  console.log('Starting AutoFill Test...')
  
  // Check if initialized
  if (!window.defaultFieldsAutofill) {
    console.error('AutoFill not initialized!')
    return false
  }
  
  // Get current values
  const values = window.defaultFieldsAutofill.getSavedValues()
  console.log('Current saved values:', values)
  
  // Check settings
  const settings = window.defaultFieldsAutofill.getSettings()
  console.log('Current settings:', settings)
  
  // Try to populate
  window.defaultFieldsAutofill.autoPopulateFields()
  console.log('✓ AutoFill test completed')
  return true
}

testAutoFill()


// Test 2: Simulate First-Time User
function testFirstTimeUser() {
  console.log('Simulating first-time user...')
  
  // Clear everything
  localStorage.removeItem('invoiceDefaultFields')
  localStorage.removeItem('invoiceDefaultFieldsSettings')
  
  // Refresh page to simulate fresh load
  console.log('Clear completed. Refresh page manually (Ctrl+F5)')
}


// Test 3: Check All Form Fields
function checkFormFields() {
  const fields = {
    invoiceType: document.getElementById('invoiceType'),
    branch: document.getElementById('branch'),
    areaCity: document.getElementById('areaCity'),
    brand: document.getElementById('brand'),
    salesOfficer: document.getElementById('salesOfficer'),
    supplierMan: document.getElementById('supplierMan')
  }
  
  const result = {}
  for (let [key, element] of Object.entries(fields)) {
    result[key] = {
      found: !!element,
      value: element ? element.value : 'N/A',
      type: element ? element.tagName : 'N/A'
    }
  }
  
  console.table(result)
  return result
}

checkFormFields()


// ============================================================================
// UTILITIES
// ============================================================================

// Function: Reset to Default Settings
function resetToDefaults() {
  if (confirm('Reset AutoFill to defaults?')) {
    window.defaultFieldsAutofill.clearSavedValues()
    window.defaultFieldsAutofill.saveSettings({enabled: true})
    window.autofillSettingsHandler.updateSummary()
    console.log('✓ Reset to defaults completed')
  }
}


// Function: Show Current State
function showCurrentState() {
  console.group('AutoFill Current State')
  
  console.log('Settings:', window.defaultFieldsAutofill.getSettings())
  console.log('Saved Values:', window.defaultFieldsAutofill.getSavedValues())
  console.log('Summary:', window.defaultFieldsAutofill.getSummary())
  
  const fieldValues = {}
  const fields = ['invoiceType', 'branch', 'areaCity', 'brand', 'salesOfficer', 'supplierMan']
  fields.forEach(field => {
    const el = document.getElementById(field)
    fieldValues[field] = el ? el.value : 'NOT FOUND'
  })
  
  console.log('Form Values:', fieldValues)
  console.groupEnd()
}


// Function: Quick Set Values
function quickSet(invoiceType, branch, areaCity, brand, salesOfficer, supplierMan) {
  const values = {
    invoiceType: invoiceType || '',
    branch: branch || '',
    areaCity: areaCity || '',
    brand: brand || '',
    salesOfficer: salesOfficer || '',
    supplierMan: supplierMan || ''
  }
  
  localStorage.setItem('invoiceDefaultFields', JSON.stringify(values))
  window.defaultFieldsAutofill.autoPopulateFields()
  
  console.log('✓ Values set:', values)
}

// Usage: quickSet('Credit', 'Main Branch', 'Karachi', 'Brand A', 'John', 'Ahmed')


// ============================================================================
// PERFORMANCE MONITORING
// ============================================================================

// Measure AutoFill load time
console.time('AutoFill Load')
window.defaultFieldsAutofill.autoPopulateFields()
console.timeEnd('AutoFill Load')


// ============================================================================
// COPY-PASTE READY COMMANDS
// ============================================================================

/*
COPY THESE ENTIRE COMMANDS AND PASTE IN CONSOLE:

// View everything
showCurrentState()

// Reset everything  
resetToDefaults()

// Check form fields
checkFormFields()

// Set test values
quickSet('Credit', 'Main Branch', 'Karachi', 'Brand A', 'John Doe', 'Ahmed Khan')

// Clear and test
window.defaultFieldsAutofill.clearSavedValues(); window.autofillSettingsHandler.updateSummary();

*/

