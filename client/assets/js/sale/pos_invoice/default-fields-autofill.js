/**
 * Default Fields AutoFill Manager
 * Handles automatic population of frequently used fields from localStorage
 * Fields: Invoice Type, Branch, Area/City, Brand, Sales Officer, Supplier Man
 */

class DefaultFieldsAutofill {
    constructor() {
        this.storageKey = 'invoiceDefaultFields';
        this.settingsKey = 'invoiceDefaultFieldsSettings';
        this.trackedFieldsKey = 'invoiceTrackedFields';
        this.allFields = ['company', 'invoiceType', 'branch', 'areaCity', 'brand', 'salesOfficer', 'supplierMan'];
        this.fieldsToTrack = this.getTrackedFields();
        this.initializeListeners();
        this.setupPageVisibilityListener();
    }

    /**
     * Get tracked fields from localStorage (user-selected)
     */
    getTrackedFields() {
        try {
            const saved = localStorage.getItem(this.trackedFieldsKey);
            return saved ? JSON.parse(saved) : this.allFields;
        } catch(e) {
            return this.allFields;
        }
    }

    /**
     * Update tracked fields dynamically (called when checkboxes change)
     */
    updateTrackedFields(fields) {
        this.fieldsToTrack = fields;
        this.initializeListeners();
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
        this.fieldsToTrack.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field && !field._autofillListener) {
                field.addEventListener('change', () => this.saveFieldValue(fieldId));
                field._autofillListener = true;
            }
        });

        this.setupSearchableDropdownListeners();

        const reinitListener = setInterval(() => {
            let allFound = true;
            this.fieldsToTrack.forEach(fieldId => {
                if (!document.getElementById(fieldId)) allFound = false;
            });
            if (allFound) {
                clearInterval(reinitListener);
                this.fieldsToTrack.forEach(fieldId => {
                    const field = document.getElementById(fieldId);
                    if (field && !field._autofillListener) {
                        field.addEventListener('change', () => this.saveFieldValue(fieldId));
                        field._autofillListener = true;
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
     * Save a field value to localStorage (saves both id and label)
     */
    saveFieldValue(fieldId) {
        const field = document.getElementById(fieldId);
        if (!field) return;

        let id = field.value;
        let label = id;

        if (fieldId === 'company' || fieldId === 'branch' || fieldId === 'areaCity') {
            const searchInput = document.getElementById(`${fieldId}Search`);
            if (searchInput && searchInput.value) label = searchInput.value;
        } else if (field.tagName === 'SELECT') {
            const selected = field.options[field.selectedIndex];
            if (selected) label = selected.text;
        }

        if (!id) return;

        let savedValues = this.getSavedValues();
        savedValues[fieldId] = { id, label };
        localStorage.setItem(this.storageKey, JSON.stringify(savedValues));
        console.log(`✓ Saved ${fieldId}:`, { id, label });
    }

    /**
     * Get saved field values from localStorage
     */
    getSavedValues() {
        try {
            const saved = localStorage.getItem(this.storageKey);
            if (!saved) return {};
            const parsed = JSON.parse(saved);
            // Migrate old flat string format to {id, label} format
            let migrated = false;
            Object.keys(parsed).forEach(key => {
                if (typeof parsed[key] === 'string') {
                    parsed[key] = { id: parsed[key], label: parsed[key] };
                    migrated = true;
                }
            });
            if (migrated) localStorage.setItem(this.storageKey, JSON.stringify(parsed));
            return parsed;
        } catch (error) {
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
            const entry = savedValues[fieldId];
            if (entry) {
                const id = typeof entry === 'object' ? entry.id : entry;
                const label = typeof entry === 'object' ? entry.label : entry;
                this.setFieldValue(fieldId, id, label);
            }
        });

        console.log('✅ Auto-populated default fields');
        return true;
    }

    /**
     * Set field value dynamically
     */
    setFieldValue(fieldId, value, label) {
        const field = document.getElementById(fieldId);
        if (!field) {
            console.warn(`Field ${fieldId} not found`);
            return;
        }

        if (fieldId === 'company' || fieldId === 'branch' || fieldId === 'areaCity') {
            this.setSearchableDropdownValue(fieldId, value, label);
        } else {
            field.value = value;
            field.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    /**
     * Set searchable dropdown value and trigger search
     */
    setSearchableDropdownValue(fieldId, value, label) {
        const field = document.getElementById(fieldId);
        const searchInput = document.getElementById(`${fieldId}Search`);
        const dropdownOptions = document.getElementById(`${fieldId}Options`);

        if (!field || !searchInput || !dropdownOptions) {
            console.warn(`Dropdown elements not found for ${fieldId}`);
            return;
        }

        field.value = value;

        const trySetValue = (attempts = 0) => {
            const options = dropdownOptions.querySelectorAll('.dropdown-option');
            
            if (options.length === 0 && attempts < 10) {
                setTimeout(() => trySetValue(attempts + 1), 100);
                return;
            }

            let matchedText = label || value;
            let matchedOption = null;
            options.forEach(option => {
                if (option.getAttribute('data-value') === value) {
                    matchedText = option.textContent.trim();
                    matchedOption = option;
                }
            });

            searchInput.value = matchedText;
            field.dispatchEvent(new Event('change', { bubbles: true }));

            if (fieldId === 'areaCity' && matchedOption && typeof window.filterCustomersByAreaCity === 'function') {
                window.filterCustomersByAreaCity(
                    matchedOption.getAttribute('data-filter-type'),
                    matchedOption.getAttribute('data-city-id'),
                    matchedOption.getAttribute('data-area-id')
                );
            }
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
        this.allFields.forEach(fieldId => {
            const entry = savedValues[fieldId];
            if (entry && typeof entry === 'object') {
                summary[fieldId] = entry.label || entry.id || 'Not set';
            } else {
                summary[fieldId] = entry || 'Not set';
            }
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
