/**
 * AutoFill Enable/Disable Testing Script
 * Use this in browser console (F12 → Console) to test the feature
 */

// TEST 1: Check current setting
function testCurrentSetting() {
    console.group('%cTEST 1: Check Current Setting', 'color: blue; font-weight: bold;');
    
    const setting = localStorage.getItem('invoiceDefaultFieldsSettings');
    console.log('Raw localStorage value:', setting);
    
    if (setting) {
        const parsed = JSON.parse(setting);
        console.log('Parsed setting:', parsed);
        console.log('enabled value:', parsed.enabled);
        console.log('Type of enabled:', typeof parsed.enabled);
    } else {
        console.log('No setting saved yet (will default to enabled)');
    }
    
    if (window.defaultFieldsAutofill) {
        const settings = window.defaultFieldsAutofill.getSettings();
        console.log('getSettings() returns:', settings);
    }
    
    console.groupEnd();
}

// TEST 2: Disable AutoFill
function testDisableAutoFill() {
    console.group('%cTEST 2: Disable AutoFill', 'color: blue; font-weight: bold;');
    
    if (!window.defaultFieldsAutofill) {
        console.error('AutoFill manager not found');
        return;
    }
    
    console.log('Before save:');
    console.log(localStorage.getItem('invoiceDefaultFieldsSettings'));
    
    console.log('Calling saveSettings with enabled=false...');
    window.defaultFieldsAutofill.saveSettings({ enabled: false });
    
    console.log('After save:');
    const saved = localStorage.getItem('invoiceDefaultFieldsSettings');
    console.log(saved);
    
    const verified = window.defaultFieldsAutofill.getSettings();
    console.log('Verified via getSettings():', verified);
    
    if (verified.enabled === false) {
        console.log('%c✓ SUCCESS: AutoFill is DISABLED', 'color: green; font-weight: bold;');
    } else {
        console.log('%c✗ FAILED: AutoFill is NOT disabled', 'color: red; font-weight: bold;');
    }
    
    console.groupEnd();
}

// TEST 3: Enable AutoFill
function testEnableAutoFill() {
    console.group('%cTEST 3: Enable AutoFill', 'color: blue; font-weight: bold;');
    
    if (!window.defaultFieldsAutofill) {
        console.error('AutoFill manager not found');
        return;
    }
    
    console.log('Before save:');
    console.log(localStorage.getItem('invoiceDefaultFieldsSettings'));
    
    console.log('Calling saveSettings with enabled=true...');
    window.defaultFieldsAutofill.saveSettings({ enabled: true });
    
    console.log('After save:');
    const saved = localStorage.getItem('invoiceDefaultFieldsSettings');
    console.log(saved);
    
    const verified = window.defaultFieldsAutofill.getSettings();
    console.log('Verified via getSettings():', verified);
    
    if (verified.enabled === true) {
        console.log('%c✓ SUCCESS: AutoFill is ENABLED', 'color: green; font-weight: bold;');
    } else {
        console.log('%c✗ FAILED: AutoFill is NOT enabled', 'color: red; font-weight: bold;');
    }
    
    console.groupEnd();
}

// TEST 4: Test via checkbox in UI
function testViaCheckbox() {
    console.group('%cTEST 4: Test via Checkbox UI', 'color: blue; font-weight: bold;');
    
    const checkbox = document.getElementById('enableDefaultFieldsAutofill');
    if (!checkbox) {
        console.error('Checkbox not found - open Invoice Settings first');
        console.groupEnd();
        return;
    }
    
    console.log('Current checkbox state:', checkbox.checked);
    
    // Uncheck it
    console.log('Unchecking checkbox...');
    checkbox.checked = false;
    checkbox.dispatchEvent(new Event('change', { bubbles: true }));
    
    setTimeout(() => {
        console.log('After uncheck:');
        const setting = JSON.parse(localStorage.getItem('invoiceDefaultFieldsSettings'));
        console.log('Saved setting:', setting);
        console.log('✓ Check browser console for "AutoFill DISABLED" notification');
    }, 500);
    
    console.groupEnd();
}

// TEST 5: Test refresh persistence
function testPersistenceAfterRefresh() {
    console.group('%cTEST 5: Persistence After Refresh', 'color: blue; font-weight: bold;');
    
    if (!window.defaultFieldsAutofill) {
        console.error('AutoFill manager not found');
        return;
    }
    
    const setting = window.defaultFieldsAutofill.getSettings();
    console.log('Current setting:', setting);
    
    if (setting.enabled === false) {
        console.log('%c✓ AutoFill is DISABLED and persisted!', 'color: green; font-weight: bold;');
    } else {
        console.log('%c✓ AutoFill is ENABLED', 'color: green; font-weight: bold;');
    }
    
    console.log('If you want to test refresh persistence:');
    console.log('1. Run testDisableAutoFill()');
    console.log('2. Refresh page (Ctrl+F5)');
    console.log('3. Run testCurrentSetting()');
    console.log('4. Should show enabled: false');
    
    console.groupEnd();
}

// COMPREHENSIVE TEST
function runAllTests() {
    console.clear();
    console.log('%c╔════════════════════════════════════════╗', 'color: cyan; font-weight: bold;');
    console.log('%c║     AutoFill Enable/Disable Tests     ║', 'color: cyan; font-weight: bold;');
    console.log('%c╚════════════════════════════════════════╝', 'color: cyan; font-weight: bold;');
    
    testCurrentSetting();
    testEnableAutoFill();
    testDisableAutoFill();
    testPersistenceAfterRefresh();
}

// Print quick reference
console.log('%c╔ AutoFill Test Commands ╗', 'color: yellow; font-weight: bold; font-size: 12px;');
console.log('%crunAllTests()\n\nIndividual tests:\ntestCurrentSetting()\ntestEnableAutoFill()\ntestDisableAutoFill()\ntestViaCheckbox()\ntestPersistenceAfterRefresh()', 'color: yellow; font-size: 11px;');
console.log('%c╚════════════════════════╝', 'color: yellow; font-weight: bold; font-size: 12px;');
