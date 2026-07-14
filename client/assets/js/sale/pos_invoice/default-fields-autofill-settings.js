/**
 * Handler for Default Fields AutoFill Settings in Invoice Settings Modal
 * Manages loading, displaying, and saving autofill preferences
 */

const SETTINGS_KEY = 'invoiceDefaultFieldsSettings';

// Wait for page to load and defaultFieldsAutofill to be ready
document.addEventListener('DOMContentLoaded', function () {
    setTimeout(() => {
        initAutofillSettings();
    }, 500);
});

function initAutofillSettings() {
    const enableCheckbox = document.getElementById('enableDefaultFieldsAutofill');
    const clearBtn = document.getElementById('clearDefaultFieldsBtn');

    if (!enableCheckbox || !clearBtn) {
        console.warn('❌ AutoFill settings elements not found');
        return;
    }

    console.log('✅ AutoFill settings initialized');

    // Load current setting into checkbox
    loadCurrentSetting();

    // Save immediately when checkbox changes
    enableCheckbox.addEventListener('change', function (e) {
        const isChecked = this.checked;
        console.log('🔄 Checkbox changed:', { isChecked });
        saveSetting(isChecked);
    });

    // Clear saved field values
    clearBtn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        
        if (confirm('Are you sure you want to clear all saved default field values? This action cannot be undone.')) {
            if (window.defaultFieldsAutofill) {
                window.defaultFieldsAutofill.clearSavedValues();
            }
            updateSummaryDisplay();
            showNotification('✅ Saved values cleared', 'success');
        }
    });

    // Load settings when modal opens
    const invoiceSettingsBtn = document.getElementById('invoiceSettingsBtn');
    if (invoiceSettingsBtn) {
        invoiceSettingsBtn.addEventListener('click', function () {
            setTimeout(() => {
                loadCurrentSetting();
                updateSummaryDisplay();
            }, 100);
        });
    }

    // Update summary display
    updateSummaryDisplay();
}

/**
 * Load current setting from localStorage and update checkbox
 */
function loadCurrentSetting() {
    const enableCheckbox = document.getElementById('enableDefaultFieldsAutofill');
    if (!enableCheckbox) return;

    try {
        const saved = localStorage.getItem(SETTINGS_KEY);
        const settings = saved ? JSON.parse(saved) : { enabled: false };
        const isEnabled = settings.enabled === true;
        
        enableCheckbox.checked = isEnabled;
        console.log('📖 Loaded setting from localStorage:', { isEnabled, fullSettings: settings });
    } catch (error) {
        console.error('❌ Error loading settings:', error);
        enableCheckbox.checked = false;
    }
}

/**
 * Save setting to localStorage immediately
 */
function saveSetting(isEnabled) {
    try {
        const settings = { enabled: isEnabled === true };
        localStorage.setItem(SETTINGS_KEY, JSON.stringify(settings));
        
        // Verify it was saved
        const verify = localStorage.getItem(SETTINGS_KEY);
        const verifyParsed = JSON.parse(verify);
        
        console.log('💾 Saved to localStorage:', settings);
        console.log('✅ Verified saved value:', verifyParsed);

        const statusMsg = isEnabled 
            ? '✅ AutoFill ENABLED - fields will auto-populate' 
            : '✅ AutoFill DISABLED - fields will NOT auto-populate';
        showNotification(statusMsg, 'success');
        
        return true;
    } catch (error) {
        console.error('❌ Error saving settings:', error);
        showNotification('❌ Failed to save setting', 'error');
        return false;
    }
}

/**
 * Update the summary display with currently saved field values
 */
function updateSummaryDisplay() {
    if (!window.defaultFieldsAutofill) {
        console.warn('⏳ defaultFieldsAutofill not ready yet');
        return;
    }

    const summaryDiv = document.getElementById('defaultFieldsSummary');
    if (!summaryDiv) {
        console.warn('⏳ summaryDiv not found');
        return;
    }

    const summary = window.defaultFieldsAutofill.getSummary();
    const savedValues = window.defaultFieldsAutofill.getSavedValues();
    
    console.log('🔄 Updating summary:', { summary, savedValues });

    let html = '';

    const fieldLabels = {
        company: 'Company',
        invoiceType: 'Invoice Type',
        branch: 'Branch',
        areaCity: 'Area / City',
        brand: 'Brand',
        salesOfficer: 'Sales Officer',
        supplierMan: 'Supplier Man'
    };

    for (const [fieldId, label] of Object.entries(fieldLabels)) {
        const value = summary[fieldId] || 'Not set';
        const isSaved = savedValues[fieldId] ? true : false;
        const statusIcon = isSaved ? '✓' : '○';
        const statusColor = isSaved ? '#4caf50' : '#999';

        html += `
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 4px 0;">
                <span style="display: flex; align-items: center; gap: 6px;">
                    <span style="color: ${statusColor}; font-weight: bold;">${statusIcon}</span>
                    <span>${label}:</span>
                </span>
                <span style="color: var(--subtext); font-size: 11px; max-width: 200px; text-align: right;">
                    ${value}
                </span>
            </div>
        `;
    }

    summaryDiv.innerHTML = html || '<span>No saved values yet</span>';
}

/**
 * Show notification popup
 */
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 12px 20px;
        background: ${type === 'success' ? '#4caf50' : type === 'error' ? '#f44336' : '#2196f3'};
        color: white;
        border-radius: 4px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        z-index: 9999;
        font-size: 13px;
        animation: slideIn 0.3s ease;
        max-width: 300px;
    `;
    notification.textContent = message;

    // Add animation if not exists
    if (!document.getElementById('notificationStyles')) {
        const style = document.createElement('style');
        style.id = 'notificationStyles';
        style.textContent = `
            @keyframes slideIn {
                from {
                    transform: translateX(400px);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
        `;
        document.head.appendChild(style);
    }

    document.body.appendChild(notification);

    setTimeout(() => {
        notification.remove();
    }, 3000);
}

// Export
window.autofillSettingsHandler = {
    loadSetting: loadCurrentSetting,
    saveSetting: saveSetting,
    updateSummary: updateSummaryDisplay
};
