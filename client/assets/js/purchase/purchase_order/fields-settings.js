// Field configuration for Purchase Order - mark required fields
const FIELD_CONFIG = {
  billNo: { label: 'Order No', required: true, groupId: null },
  purchaseDate: { label: 'Order Date', required: true, groupId: null },
  company: { label: 'Company', required: true, groupId: null },
  supplierCode: { label: 'Supplier Code', required: true, groupId: null },
  branch: { label: 'Branch', required: true, groupId: null },
  currency: { label: 'Currency', required: false, groupId: null },
  supplierInvoiceNo: { label: 'Supplier Invoice No', required: false, groupId: null },
  supplierInvoiceDate: { label: 'Supplier Invoice Date', required: false, groupId: null },
  biltyNo: { label: 'Bilty No', required: false, groupId: null },
  transportName: { label: 'Transport Name', required: false, groupId: null },
  previousBalance: { label: "Supplier's Previous Balance", required: false, groupId: null },
  remarks: { label: 'Remarks', required: false, groupId: null }
};

const STORAGE_KEY = 'purchaseOrderFieldsSettings';

// Initialize fields settings modal
function initializeFieldsSettings() {
  const fieldsSettingsBtn = document.getElementById('fieldsSettingsBtn');
  const closeFieldsSettingsBtn = document.getElementById('closeFieldsSettingsBtn');
  const saveFieldsSettingsBtn = document.getElementById('saveFieldsSettingsBtn');
  const fieldsSettingsModal = document.getElementById('fieldsSettingsModal');

  if (!fieldsSettingsBtn) return; // Exit if button doesn't exist

  fieldsSettingsBtn.addEventListener('click', () => {
    renderFieldsSettings();
    fieldsSettingsModal.style.display = 'flex';
  });

  closeFieldsSettingsBtn.addEventListener('click', () => {
    fieldsSettingsModal.style.display = 'none';
  });

  saveFieldsSettingsBtn.addEventListener('click', () => {
    saveFieldsSettings();
    fieldsSettingsModal.style.display = 'none';
  });

  fieldsSettingsModal.addEventListener('click', (e) => {
    if (e.target === fieldsSettingsModal) {
      fieldsSettingsModal.style.display = 'none';
    }
  });

  // Load saved settings on page load
  loadFieldsSettings();
}

// Render fields in settings modal
function renderFieldsSettings() {
  const container = document.getElementById('fieldsSettingsContainer');
  container.innerHTML = '';

  const settings = getFieldsSettings();

  Object.entries(FIELD_CONFIG).forEach(([fieldId, config]) => {
    // Skip required fields - don't show them in modal
    if (config.required) {
      return;
    }

    const isChecked = settings[fieldId] !== false; // Default true for new settings

    const itemDiv = document.createElement('div');
    itemDiv.className = 'fields-setting-item';

    const checkbox = document.createElement('input');
    checkbox.type = 'checkbox';
    checkbox.id = `fieldToggle_${fieldId}`;
    checkbox.checked = isChecked;
    checkbox.value = fieldId;

    const label = document.createElement('label');
    label.htmlFor = `fieldToggle_${fieldId}`;
    label.textContent = config.label;

    itemDiv.appendChild(checkbox);
    itemDiv.appendChild(label);
    container.appendChild(itemDiv);
  });
}

// Get current settings from localStorage
function getFieldsSettings() {
  const saved = localStorage.getItem(STORAGE_KEY);
  if (saved) {
    return JSON.parse(saved);
  }
  // Initialize all as visible
  const defaultSettings = {};
  Object.keys(FIELD_CONFIG).forEach(fieldId => {
    defaultSettings[fieldId] = true;
  });
  return defaultSettings;
}

// Save fields settings to localStorage
function saveFieldsSettings() {
  const settings = {};
  const checkboxes = document.querySelectorAll('#fieldsSettingsContainer input[type="checkbox"]');

  checkboxes.forEach(checkbox => {
    const fieldId = checkbox.value;
    settings[fieldId] = checkbox.checked;
  });

  localStorage.setItem(STORAGE_KEY, JSON.stringify(settings));
  applyFieldsSettings();
}

// Apply saved settings to form
function applyFieldsSettings() {
  const settings = getFieldsSettings();

  Object.entries(FIELD_CONFIG).forEach(([fieldId, config]) => {
    const isVisible = settings[fieldId] !== false;
    
    // Try to find element by ID or by looking for parent form-group
    let element = document.getElementById(fieldId);
    
    if (element) {
      // Find the closest form-group parent
      element = element.closest('.form-group');
    }
    
    if (element) {
      element.style.display = isVisible ? '' : 'none';
    }
  });
}

// Load and apply settings on page load
function loadFieldsSettings() {
  setTimeout(() => {
    applyFieldsSettings();
  }, 500);
}

// Initialize on document ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initializeFieldsSettings);
} else {
  initializeFieldsSettings();
}
