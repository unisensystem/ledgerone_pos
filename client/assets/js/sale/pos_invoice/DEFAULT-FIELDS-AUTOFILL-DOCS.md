# Default Fields AutoFill System - Documentation

## Overview

The Default Fields AutoFill system automatically saves and restores frequently used invoice fields across sessions. This eliminates repetitive data entry for fields that typically remain the same or change infrequently.

## Features

### ✓ Tracked Fields (6 Fields)
The following fields are automatically tracked and saved:
1. **Invoice Type** - Credit/Cash selection
2. **Branch** - Selected branch
3. **Area / City** - Delivery area/city
4. **Brand** - Product brand filter
5. **Sales Officer** - Assigned sales officer
6. **Supplier Man** - Supplier representative

### ✓ Automatic Saving
- Fields are saved to browser localStorage whenever they change
- No manual action required from user
- Persists across sessions and page refreshes

### ✓ Automatic Population
- On page load, previously saved values automatically populate the fields
- Works after soft reload (Ctrl+F5 or browser refresh)
- Works after printing and returning to invoice page

### ✓ User Control
- Settings in **Invoice Settings Modal** (⚙️ Icon)
- Toggle AutoFill on/off per user preference
- View currently saved values with status indicators
- Clear all saved values with one click

### ✓ Smart Display
- Invoice Settings shows current saved values
- Visual indicator (✓) for saved fields
- Circle (○) for fields not yet set
- Summary updates automatically

## How It Works

### 1. First-Time Setup
```
User loads POS Invoice page
↓
JavaScript checks localStorage for saved values
↓
AutoFill loads enabled by default
↓
User selects Invoice Type, Branch, Brand, etc.
↓
Values automatically saved to localStorage
```

### 2. Subsequent Sessions
```
User returns to POS Invoice page
↓
Page loads, scripts initialize
↓
AutoFill retrieves saved values from localStorage
↓
Fields auto-populate with previous values
↓
User can modify as needed
```

### 3. Invoice Settings
```
User clicks ⚙️ Invoice Settings button
↓
AutoFill Section appears showing:
   - Enable/Disable toggle
   - Tracked fields list
   - Currently saved values with status
   - Clear values button
↓
User can modify settings
↓
Click "Save Settings" to save preferences
```

## File Structure

### JavaScript Files

**1. `default-fields-autofill.js`** - Main Manager Class
- `DefaultFieldsAutofill` class handles all autofill logic
- Methods:
  - `saveFieldValue(fieldId)` - Save individual field
  - `autoPopulateFields()` - Load and populate on page load
  - `getSavedValues()` - Retrieve all saved values
  - `getSettings()` - Get autofill preferences
  - `saveSettings(settings)` - Save preferences
  - `clearSavedValues()` - Clear everything
  - `getSummary()` - Get display-friendly summary

**2. `default-fields-autofill-settings.js`** - Settings Handler
- Handles Invoice Settings modal interactions
- Methods:
  - `loadAutofillSettings()` - Load from localStorage
  - `saveAutofillSettings()` - Save to localStorage
  - `updateSummaryDisplay()` - Update UI display
  - `showNotification()` - Display user feedback

### HTML Elements

Added section in Invoice Settings Modal:
```html
<!-- AutoFill Settings Section -->
<div class="autofill-settings">
    <h4>Auto-Fill Default Values</h4>
    <input type="checkbox" id="enableDefaultFieldsAutofill">
    <div id="defaultFieldsSummary"><!-- Summary display --></div>
    <button id="clearDefaultFieldsBtn">Clear Saved Values</button>
</div>
```

## localStorage Structure

### Storage Key 1: `invoiceDefaultFields`
```json
{
  "invoiceType": "Credit",
  "branch": "Main Branch",
  "areaCity": "Karachi",
  "brand": "Brand A",
  "salesOfficer": "John Doe",
  "supplierMan": "Ahmed Khan"
}
```

### Storage Key 2: `invoiceDefaultFieldsSettings`
```json
{
  "enabled": true
}
```

## Usage Examples

### For End Users

**Enable AutoFill:**
1. Click ⚙️ Invoice Settings
2. Check "Enable Auto-Fill" (already enabled by default)
3. Click "Save Settings"

**View Saved Values:**
1. Click ⚙️ Invoice Settings
2. Scroll to "Auto-Fill Default Values" section
3. See summary with ✓ for saved and ○ for empty fields

**Clear Saved Values:**
1. Click ⚙️ Invoice Settings
2. Scroll to "Auto-Fill Default Values"
3. Click "Clear Saved Values" button
4. Confirm in dialog

**Use AutoFill:**
1. Just start using invoices normally
2. Select your frequent values
3. On next page load, values appear automatically

### For Developers

**Check if AutoFill is enabled:**
```javascript
const settings = window.defaultFieldsAutofill.getSettings();
if (settings.enabled) {
    // AutoFill is enabled
}
```

**Get saved values programmatically:**
```javascript
const saved = window.defaultFieldsAutofill.getSavedValues();
console.log(saved.branch); // Get saved branch
```

**Clear specific field:**
```javascript
window.defaultFieldsAutofill.clearField('brand');
```

**Update summary display:**
```javascript
window.autofillSettingsHandler.updateSummary();
```

## Technical Details

### Initialization Flow
1. `default-fields-autofill.js` loads first
2. Creates `DefaultFieldsAutofill` class instance
3. Sets up event listeners on tracked fields
4. Waits for document ready
5. `default-fields-autofill-settings.js` loads
6. Sets up modal handlers
7. Calls `autoPopulateFields()` after 500ms delay

### Data Persistence
- **Storage Location:** Browser localStorage
- **Scope:** Per domain (not affected by incognito/private browsing in some browsers)
- **Limitations:** 
  - ~5-10MB typical limit per domain
  - Cleared if user clears browser cache/cookies
  - Different per browser (not synced)

### Performance
- Minimal impact (localStorage is very fast)
- One-time initialization cost
- No API calls required
- No database overhead

## Browser Compatibility

Works in all modern browsers supporting:
- ES6 JavaScript (Promise, arrow functions, classes)
- localStorage API
- DOM manipulation (querySelector, addEventListener)

**Tested on:**
- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

## Integration with Invoice Save

**Important:** AutoFill values persist **independently** of invoice save:
- Invoice is saved to database via API
- AutoFill values are saved to browser localStorage
- On next visit, values auto-populate even if invoice was deleted
- When user prints invoice and returns, values remain auto-populated

## Troubleshooting

### Values Not Saving
1. Check if AutoFill is enabled in Invoice Settings
2. Verify browser isn't in private/incognito mode
3. Check if localStorage is enabled in browser
4. Clear browser cache and refresh

### Values Not Populating on Load
1. Check browser console for errors
2. Verify `default-fields-autofill.js` is loaded
3. Ensure DOM elements have correct IDs
4. Check if AutoFill is disabled in settings

### Losing Values After Refresh
1. Browser localStorage may have been cleared
2. Check browser privacy settings
3. Verify sufficient disk space
4. Try different browser

### Performance Issues
1. Should be negligible
2. If slow, check browser console for errors
3. Verify no conflicts with other scripts

## Future Enhancements

Possible improvements:
- [ ] Multiple saved profiles per user
- [ ] Sync across devices via cloud
- [ ] Time-based expiration of saved values
- [ ] Conditional auto-population based on customer
- [ ] Auto-fill based on last N invoices
- [ ] Per-customer default values
- [ ] Export/import settings

## Notes

- This is **client-side only** - data stays in browser
- Not synchronized across different devices
- Users can easily reset at any time
- No security/privacy concerns (local storage only)
- Complements existing invoice settings
