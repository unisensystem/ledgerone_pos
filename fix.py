php_file = r'c:\xampp\htdocs\ledgerone_erp\client\pages\sale\pos_invoice\pos-add.php'

with open(php_file, 'r', encoding='utf-8') as f:
    php = f.read()

old_modal = '''
<!-- Stock Violation Modal -->
<div id="stockViolationModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:var(--surface);border-radius:var(--radius);padding:24px;min-width:480px;max-width:90vw;box-shadow:0 8px 32px rgba(0,0,0,0.2);">
        <h3 style="margin:0 0 8px;color:var(--error);display:flex;align-items:center;gap:8px;">&#9888; Stock Kam Hai</h3>
        <p style="margin:0 0 16px;color:var(--body);font-size:13px;">Neeche diye gaye products ki required quantity available stock se zyada hai:</p>
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead>
                <tr style="background:var(--surface-2);">
                    <th style="padding:6px 10px;text-align:left;border-bottom:2px solid var(--border-default);">Product</th>
                    <th style="padding:6px 10px;text-align:center;border-bottom:2px solid var(--border-default);">Required</th>
                    <th style="padding:6px 10px;text-align:center;border-bottom:2px solid var(--border-default);">Available</th>
                </tr>
            </thead>
            <tbody id="stockViolationBody"></tbody>
        </table>
        <div style="display:flex;gap:12px;justify-content:flex-end;margin-top:20px;">
            <button id="stockViolationCancel" class="btn btn-secondary" type="button">Qty Kam Karo</button>
            <button id="stockViolationConfirm" class="btn btn-primary" type="button">Phir Bhi Save Karo</button>
        </div>
    </div>
</div>'''

new_modal = '''
    <!-- Stock Violation Modal -->
    <div class="modal" id="stockViolationModal" style="display:none;">
        <div class="modal-content" style="max-width: 560px;">
            <h3 class="modal-title" style="color: var(--error);"><i class="fas fa-exclamation-triangle" style="margin-right: 8px;"></i>Insufficient Stock</h3>
            <p style="margin: 0 0 16px; color: var(--body); font-size: 13px;">The following products exceed available stock. You can reduce the quantity or save anyway.</p>
            <div class="table-container" style="max-height: 260px; overflow-y: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                    <thead>
                        <tr>
                            <th style="padding: 8px 10px; text-align: left; border-bottom: 1px solid var(--border-default);">Product</th>
                            <th style="padding: 8px 10px; text-align: center; border-bottom: 1px solid var(--border-default); color: var(--error);">Required</th>
                            <th style="padding: 8px 10px; text-align: center; border-bottom: 1px solid var(--border-default); color: var(--success);">Available</th>
                        </tr>
                    </thead>
                    <tbody id="stockViolationBody"></tbody>
                </table>
            </div>
            <div class="modal-actions" style="margin-top: 20px;">
                <button id="stockViolationCancel" class="btn btn-secondary" type="button">Reduce Quantity</button>
                <button id="stockViolationConfirm" class="btn btn-primary" type="button"><i class="fas fa-save"></i> Save Anyway</button>
            </div>
        </div>
    </div>'''

if old_modal in php:
    php = php.replace(old_modal, new_modal)
    print('Modal replaced')
else:
    print('ERROR: old modal not found')

with open(php_file, 'w', encoding='utf-8') as f:
    f.write(php)
print('Done')
