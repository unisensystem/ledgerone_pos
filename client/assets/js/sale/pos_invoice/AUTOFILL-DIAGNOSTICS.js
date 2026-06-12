/**
 * Diagnostic Script for Default Fields AutoFill
 * Run this in the browser console to diagnose issues
 */

function runAutofillDiagnostics() {
    console.group('%c=== AutoFill Diagnostics ===', 'color: blue; font-weight: bold; font-size: 14px;');

    // 1. Check if autofill manager is initialized
    console.group('1. Manager Initialization');
    if (window.defaultFieldsAutofill) {
        console.log('✓ DefaultFieldsAutofill manager found');
    } else {
        console.error('✗ DefaultFieldsAutofill manager NOT found');
    }
    console.groupEnd();

    // 2. Check localStorage
    console.group('2. LocalStorage Content');
    const values = localStorage.getItem('invoiceDefaultFields');
    const settings = localStorage.getItem('invoiceDefaultFieldsSettings');
    
    console.log('Values Key:', values ? JSON.parse(values) : 'EMPTY');
    console.log('Settings Key:', settings ? JSON.parse(settings) : 'EMPTY');
    console.groupEnd();

    // 3. Check HTML elements
    console.group('3. HTML Elements');
    const fields = {
        invoiceType: document.getElementById('invoiceType'),
        branch: document.getElementById('branch'),
        branchSearch: document.getElementById('branchSearch'),
        areaCity: document.getElementById('areaCity'),
        areaCitySearch: document.getElementById('areaCitySearch'),
        brand: document.getElementById('brand'),
        salesOfficer: document.getElementById('salesOfficer'),
        supplierMan: document.getElementById('supplierMan')
    };

    for (const [name, el] of Object.entries(fields)) {
        console.log(`${name}: ${el ? '✓ Found' : '✗ NOT FOUND'}`, el);
    }
    console.groupEnd();

    // 4. Check field values
    console.group('4. Current Field Values');
    console.log('invoiceType:', document.getElementById('invoiceType')?.value);
    console.log('branch:', document.getElementById('branch')?.value);
    console.log('branchSearch display:', document.getElementById('branchSearch')?.value);
    console.log('areaCity:', document.getElementById('areaCity')?.value);
    console.log('areaCitySearch display:', document.getElementById('areaCitySearch')?.value);
    console.log('brand:', document.getElementById('brand')?.value);
    console.log('salesOfficer:', document.getElementById('salesOfficer')?.value);
    console.log('supplierMan:', document.getElementById('supplierMan')?.value);
    console.groupEnd();

    // 5. Check settings modal elements
    console.group('5. Settings Modal Elements');
    const enableCheckbox = document.getElementById('enableDefaultFieldsAutofill');
    const clearBtn = document.getElementById('clearDefaultFieldsBtn');
    const summaryDiv = document.getElementById('defaultFieldsSummary');
    const saveBtn = document.getElementById('saveInvoiceSettingsBtn');

    console.log('enableCheckbox:', enableCheckbox ? '✓ Found' : '✗ NOT FOUND');
    console.log('enableCheckbox.checked:', enableCheckbox?.checked);
    console.log('clearBtn:', clearBtn ? '✓ Found' : '✗ NOT FOUND');
    console.log('summaryDiv:', summaryDiv ? '✓ Found' : '✗ NOT FOUND');
    console.log('saveBtn:', saveBtn ? '✓ Found' : '✗ NOT FOUND');
    console.groupEnd();

    // 6. Test Functions
    console.group('6. Available Functions');
    console.log('loadAutofillSettings:', typeof loadAutofillSettings === 'function' ? '✓' : '✗');
    console.log('saveAutofillSettings:', typeof saveAutofillSettings === 'function' ? '✓' : '✗');
    console.log('updateSummaryDisplay:', typeof updateSummaryDisplay === 'function' ? '✓' : '✗');
    console.log('showNotification:', typeof showNotification === 'function' ? '✓' : '✗');
    console.groupEnd();

    // 7. Try to populate fields
    console.group('7. Test Auto-Populate');
    console.log('Attempting to auto-populate...');
    if (window.defaultFieldsAutofill) {
        window.defaultFieldsAutofill.autoPopulateFields();
        console.log('✓ auto-populate called');
    } else {
        console.error('✗ Cannot call auto-populate - manager not found');
    }
    console.groupEnd();

    console.groupEnd();
    console.log('%cDiagnostics Complete', 'color: green; font-weight: bold;');
}

// Quick test function
function testAutoPopulate() {
    console.log('%cTesting AutoPopulate...', 'color: orange; font-weight: bold;');
    
    if (!window.defaultFieldsAutofill) {
        console.error('AutoFill manager not found!');
        return;
    }

    const before = {
        invoiceType: document.getElementById('invoiceType')?.value,
        branch: document.getElementById('branch')?.value,
        areaCity: document.getElementById('areaCity')?.value,
    };

    console.log('Before:', before);

    window.defaultFieldsAutofill.autoPopulateFields();

    setTimeout(() => {
        const after = {
            invoiceType: document.getElementById('invoiceType')?.value,
            branch: document.getElementById('branch')?.value,
            areaCity: document.getElementById('areaCity')?.value,
        };

        console.log('After:', after);
        console.log('Changed:', before !== JSON.stringify(after) ? '✓ Yes' : '✗ No');
    }, 500);
}

// Make it easy to run
console.log('%cRun: runAutofillDiagnostics()', 'color: green; font-size: 12px;');
console.log('%cRun: testAutoPopulate()', 'color: green; font-size: 12px;');
