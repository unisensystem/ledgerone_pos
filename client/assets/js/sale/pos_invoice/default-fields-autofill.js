/**
 * Default Fields AutoFill Manager
 * Handles automatic population of frequently used fields from localStorage
 * Fields: Invoice Type, Branch, Area/City, Brand, Sales Officer, Supplier Man
 */

class DefaultFieldsAutofill {
    constructor() {
        this.storageKey = 'invoiceDefaultFields';
        this.settingsKey = 'invoiceDefaultFieldsSettings';
        this.fieldsToTrack = ['company', 'invoiceType', 'branch', 'areaCity', 'brand', 'salesOfficer', 'supplierMan'];
        this.initializeListeners();
        this.setupPageVisibilityListener();
    }

    /**
     * Setup listener for page visibility changes (when coming back from print)
     */
    setupPageVisibilityListener() {
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                // Check if enabled before re-populating
                const settings = this.getSettings();
                if (settings.enabled === true) {
                    console.log('✓ Page visible again - re-populating fields');
                    setTimeout(() => this.autoPopulateFields(), 100);
                } else {
                    console.log('✗ Page visible but AutoFill disabled - skipping re-population');
                }
            }
        });

        // Also listen for focus event
        window.addEventListener('focus', () => {
            // Check if enabled before re-populating
            const settings = this.getSettings();
            if (settings.enabled === true) {
                console.log('✓ Window focused - re-populating fields');
                setTimeout(() => this.autoPopulateFields(), 100);
            } else {
                console.log('✗ Window focused but AutoFill disabled - skipping re-population');
            }
        });
    }

    /**
     * Initialize listeners on tracked fields
     */
    initializeListeners() {
        // Listen for changes on tracked fields
        this.fieldsToTrack.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) {
                field.addEventListener('change', () => this.saveFieldValue(fieldId));
            }
        });

        // Special handling for searchable dropdowns (branch, areaCity)
        this.setupSearchableDropdownListeners();

        // Re-initialize listeners after fields are loaded dynamically
        const reinitListener = setInterval(() => {
            let allFound = true;
            this.fieldsToTrack.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (!field) {
                    allFound = false;
                }
            });
            
            if (allFound) {
                clearInterval(reinitListener);
                this.fieldsToTrack.forEach(fieldId => {
                    const field = document.getElementById(fieldId);
                    if (field && !field.hasListener) {
                        field.addEventListener('change', () => this.saveFieldValue(fieldId));
                        field.hasListener = true;
                    }
                });
                this.setupSearchableDropdownListeners();
            }
        }, 200);
    }

    /**
     * Setup special listeners for searchable dropdowns
     */
    setupSearchableDropdownListeners() {
        ['company', 'branch', 'areaCity'].forEach(fieldId => {
            const hiddenField = document.getElementById(fieldId);
            const searchInput = document.getElementById(`${fieldId}Search`);
            const optionsContainer = document.getElementById(`${fieldId}Options`);

            if (hiddenField && optionsContainer) {
                // Override the click handler on options
                const originalHandler = optionsContainer.onclick;
                optionsContainer.addEventListener('click', (e) => {
                    const opt = e.target.closest('.dropdown-option');
                    if (opt) {
                        // Wait for the value to be set, then save
                        setTimeout(() => {
                            if (hiddenField.value) {
                                this.saveFieldValue(fieldId);
                            }
                        }, 50);
                    }
                });
            }
        });
    }

    /**
     * Save a field value to localStorage
     */
    saveFieldValue(fieldId) {
        const field = document.getElementById(fieldId);
        if (!field) return;

        // For searchable dropdowns, also get the display text
        let valueToSave = field.value;
        
        if (fieldId === 'company' || fieldId === 'branch' || fieldId === 'areaCity') {
            const searchInput = document.getElementById(`${fieldId}Search`);
            if (searchInput && searchInput.value) {
                valueToSave = field.value;
            }
        }

        if (!valueToSave) return; // Don't save empty values

        // Get current values
        let savedValues = this.getSavedValues();

        // Update the field value
        savedValues[fieldId] = valueToSave;

        // Save back to localStorage
        localStorage.setItem(this.storageKey, JSON.stringify(savedValues));

        console.log(`✓ Saved ${fieldId}: ${valueToSave}`);
    }

    /**
     * Get saved field values from localStorage
     */
    getSavedValues() {
        try {
            const saved = localStorage.getItem(this.storageKey);
            return saved ? JSON.parse(saved) : {};
        } catch (error) {
            console.error('Error reading saved values:', error);
            return {};
        }
    }

    /**
     * Get autofill settings
     */
    getSettings() {
        try {
            // Try new settings key first
            let settings = localStorage.getItem('invoiceDefaultFieldsSettings');
            if (settings) {
                const parsed = JSON.parse(settings);
                if (typeof parsed.enabled === 'boolean') {
                    return parsed;
                }
            }
            
            // Fallback to old key for backward compatibility
            settings = localStorage.getItem(this.settingsKey);
            if (settings) {
                const parsed = JSON.parse(settings);
                if (typeof parsed.enabled === 'boolean') {
                    return parsed;
                }
            }
            
            // Default: disabled
            return { enabled: false };
        } catch (error) {
            console.error('Error reading settings:', error);
            return { enabled: false };
        }
    }

    /**
     * Save autofill settings with verification
     */
    saveSettings(settings) {
        // Ensure enabled is explicitly a boolean
        const cleanSettings = {
            enabled: settings.enabled === true
        };
        
        // Save to localStorage
        localStorage.setItem(this.settingsKey, JSON.stringify(cleanSettings));
        
        // Verify it was saved
        const verify = localStorage.getItem(this.settingsKey);
        if (verify) {
            const verifyParsed = JSON.parse(verify);
            console.log('✓ AutoFill setting saved to localStorage:', cleanSettings);
            console.log('✓ Verified saved value:', verifyParsed);
            return true;
        } else {
            console.error('✗ Failed to save AutoFill setting');
            return false;
        }
    }

    /**
     * Auto-populate fields on page load
     */
    autoPopulateFields() {
        const settings = this.getSettings();
        const isEnabled = settings.enabled === true;
        
        console.log('🔍 AutoFill status check:', { isEnabled, enabled: settings.enabled });
        
        // CRITICAL: Return immediately if not explicitly enabled
        if (isEnabled === false || settings.enabled === false) {
            console.log('❌ AutoFill DISABLED - auto-population BLOCKED');
            return false;
        }
        
        if (isEnabled !== true) {
            console.log('❌ AutoFill NOT ENABLED - auto-population BLOCKED');
            return false;
        }
        
        console.log('✅ AutoFill ENABLED - proceeding with auto-population');

        const savedValues = this.getSavedValues();

        this.fieldsToTrack.forEach(fieldId => {
            if (savedValues[fieldId]) {
                this.setFieldValue(fieldId, savedValues[fieldId]);
            }
        });

        console.log('✅ Auto-populated default fields');
        return true;
    }

    /**
     * Set field value dynamically
     */
    setFieldValue(fieldId, value) {
        const field = document.getElementById(fieldId);
        if (!field) {
            console.warn(`Field ${fieldId} not found`);
            return;
        }

        // For searchable dropdowns (company, branch, areaCity)
        if (fieldId === 'company' || fieldId === 'branch' || fieldId === 'areaCity') {
            this.setSearchableDropdownValue(fieldId, value);
        } else {
            // For regular select fields
            field.value = value;
            field.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    /**
     * Set searchable dropdown value and trigger search
     */
    setSearchableDropdownValue(fieldId, value) {
        const field = document.getElementById(fieldId);
        const searchInput = document.getElementById(`${fieldId}Search`);
        const dropdownOptions = document.getElementById(`${fieldId}Options`);

        if (!field || !searchInput || !dropdownOptions) {
            console.warn(`Dropdown elements not found for ${fieldId}`);
            return;
        }

        // Set the hidden value
        field.value = value;

        // Wait for options to be populated if needed
        const trySetValue = (attempts = 0) => {
            const options = dropdownOptions.querySelectorAll('.dropdown-option');
            
            if (options.length === 0 && attempts < 10) {
                // Options not loaded yet, try again
                setTimeout(() => trySetValue(attempts + 1), 100);
                return;
            }

            // Find matching option text
            let matchedText = value;
            let matchedOption = null;
            options.forEach(option => {
                const optionValue = option.getAttribute('data-value');
                if (optionValue === value) {
                    matchedText = option.textContent.trim();
                    matchedOption = option;
                }
            });

            // Set search input to display the value
            searchInput.value = matchedText;

            // Trigger change event
            field.dispatchEvent(new Event('change', { bubbles: true }));

            // If areaCity, trigger customer filter using option's data attributes
            if (fieldId === 'areaCity' && matchedOption && typeof window.filterCustomersByAreaCity === 'function') {
                const filterType = matchedOption.getAttribute('data-filter-type');
                const cityId     = matchedOption.getAttribute('data-city-id');
                const areaId     = matchedOption.getAttribute('data-area-id');
                window.filterCustomersByAreaCity(filterType, cityId, areaId);
            }

            console.log(`✓ Set searchable dropdown ${fieldId} to: ${value} (${matchedText})`);
        };

        trySetValue();
    }

    /**
     * Clear all saved values
     */
    clearSavedValues() {
        localStorage.removeItem(this.storageKey);
        console.log('✓ Cleared all saved field values');
    }

    /**
     * Clear specific field
     */
    clearField(fieldId) {
        let savedValues = this.getSavedValues();
        delete savedValues[fieldId];
        localStorage.setItem(this.storageKey, JSON.stringify(savedValues));
        console.log(`✓ Cleared saved value for ${fieldId}`);
    }

    /**
     * Get summary of saved values for display in settings
     */
    getSummary() {
        const savedValues = this.getSavedValues();
        const summary = {};

        this.fieldsToTrack.forEach(fieldId => {
            summary[fieldId] = savedValues[fieldId] || 'Not set';
        });

        return summary;
    }

    /**
     * Export settings and values
     */
    exportData() {
        return {
            settings: this.getSettings(),
            values: this.getSavedValues()
        };
    }

    /**
     * Import settings and values
     */
    importData(data) {
        if (data.settings) {
            this.saveSettings(data.settings);
        }
        if (data.values) {
            localStorage.setItem(this.storageKey, JSON.stringify(data.values));
        }
    }
}

// Initialize on document ready
let defaultFieldsAutofill;

document.addEventListener('DOMContentLoaded', function () {
    defaultFieldsAutofill = new DefaultFieldsAutofill();
    
    // Auto-populate fields when page loads - with multiple attempts to ensure data is ready
    const attempts = [300, 800, 1500, 3000];
    let attemptIndex = 0;

    const tryPopulate = () => {
        defaultFieldsAutofill.autoPopulateFields();
        
        if (attemptIndex < attempts.length - 1) {
            attemptIndex++;
            setTimeout(tryPopulate, attempts[attemptIndex] - attempts[attemptIndex - 1]);
        }
    };

    setTimeout(tryPopulate, attempts[0]);
});

// Also trigger autopopulate when custom data load event fires
document.addEventListener('invoiceDataLoaded', function () {
    console.log('Invoice data loaded event fired - re-populating fields');
    if (window.defaultFieldsAutofill) {
        setTimeout(() => {
            window.defaultFieldsAutofill.autoPopulateFields();
        }, 100);
    }
});

// Export for use in other scripts
window.defaultFieldsAutofill = defaultFieldsAutofill;
