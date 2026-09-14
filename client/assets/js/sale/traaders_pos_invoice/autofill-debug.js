/**
 * DEBUG HELPER: Check AutoFill Settings in Browser Console
 * Paste this in browser console to verify settings are saved
 */

// Quick check function - run this in browser console
function checkAutoFillSettings() {
    console.log('🔍 === AUTO-FILL SETTINGS DEBUG ===');
    
    // Check localStorage
    const settingsKey = 'invoiceDefaultFieldsSettings';
    const fieldsKey = 'invoiceDefaultFields';
    
    const settings = localStorage.getItem(settingsKey);
    const fields = localStorage.getItem(fieldsKey);
    
    console.log('📦 Settings in localStorage:', settingsKey);
    console.log('   Raw:', settings);
    console.log('   Parsed:', settings ? JSON.parse(settings) : 'NOT SET');
    
    console.log('');
    console.log('📦 Fields in localStorage:', fieldsKey);
    console.log('   Raw:', fields);
    console.log('   Parsed:', fields ? JSON.parse(fields) : 'NOT SET');
    
    // Check checkbox state
    const checkbox = document.getElementById('enableDefaultFieldsAutofill');
    console.log('');
    console.log('☑️  Checkbox state:', checkbox ? checkbox.checked : 'NOT FOUND');
    
    // Check defaultFieldsAutofill class
    console.log('');
    console.log('🔧 defaultFieldsAutofill object:', window.defaultFieldsAutofill ? 'EXISTS' : 'NOT FOUND');
    if (window.defaultFieldsAutofill) {
        console.log('   getSettings():', window.defaultFieldsAutofill.getSettings());
        console.log('   getSavedValues():', window.defaultFieldsAutofill.getSavedValues());
    }
    
    console.log('');
    console.log('✅ === END DEBUG ===');
}

// Save setting manually
function manualSaveSetting(enabled) {
    console.log('💾 Manually saving setting:', { enabled });
    const settings = { enabled: enabled === true };
    localStorage.setItem('invoiceDefaultFieldsSettings', JSON.stringify(settings));
    console.log('✅ Saved! Verify with: checkAutoFillSettings()');
}

// Clear all
function clearAllAutoFillData() {
    console.log('🗑️  Clearing all AutoFill data...');
    localStorage.removeItem('invoiceDefaultFieldsSettings');
    localStorage.removeItem('invoiceDefaultFields');
    console.log('✅ Cleared! Verify with: checkAutoFillSettings()');
}

console.log('✅ AutoFill Debug Helper Loaded');
console.log('Available commands:');
console.log('  checkAutoFillSettings()     - Check current settings');
console.log('  manualSaveSetting(true)     - Enable AutoFill');
console.log('  manualSaveSetting(false)    - Disable AutoFill');
console.log('  clearAllAutoFillData()      - Clear all data');
