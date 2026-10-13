// Field configuration - mark required fields
const FIELD_CONFIG = {
  company: { label: 'Company', required: true, groupId: 'companyGroup' },
  billNo: { label: 'Bill No', required: true, groupId: 'billNoGroup' },
  saleDate: { label: 'Sale Date', required: true, groupId: null },
  customerCode: { label: 'Customer Code', required: true, groupId: null },
  customerAddress: { label: 'Customer Address', required: false, groupId: 'customerAddressGroup' },
  branch: { label: 'Branch', required: true, groupId: 'branchGroup' },
  currency: { label: 'Currency', required: false, groupId: 'currencyGroup' },
  salesOfficer: { label: 'Sales Officer', required: false, groupId: 'salesOfficerGroup' },
  supplierMan: { label: 'Supplier Man', required: false, groupId: 'supplierManGroup' },
  biltyNo: { label: 'Bilty No', required: false, groupId: 'biltyNoGroup' },
  transportName: { label: 'Transport Name', required: false, groupId: 'transportNameGroup' },
  remarks: { label: 'Remarks', required: false, groupId: 'remarksGroup' },
  previousBalance: { label: "Customer's Previous Balance", required: false, groupId: 'previousBalanceGroup' }
};

const STORAGE_KEY = 'orderFormFieldsSettings';

// Initialize fields settings modal
function initializeFieldsSettings() {
  const fieldsSettingsBtn = document.getElementById('fieldsSettingsBtn');
  const closeFieldsSettingsBtn = document.getElementById('closeFieldsSettingsBtn');
  const saveFieldsSettingsBtn = document.getElementById('saveFieldsSettingsBtn');
  const fieldsSettingsModal = document.getElementById('fieldsSettingsModal');

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
    const element = config.groupId 
      ? document.getElementById(config.groupId)
      : document.getElementById(fieldId)?.closest('.form-group');

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
