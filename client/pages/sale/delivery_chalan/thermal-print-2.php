<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thermal Print 2 - Delivery Chalan</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        @media print {
            body { margin: 0; padding: 0; background: #fff; }
            .no-print { display: none; }
        }

        body {
            font-family: 'Courier New', monospace;
            font-size: 14px;
            line-height: 1.4;
            color: #000;
            background: #f5f5f5;
            padding: 10px;
            font-weight: bold;
        }

        .thermal-receipt {
            width: 80mm;
            max-width: 302px;
            margin: 0 auto;
            background: white;
            padding: 10px 12px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }

        .text-center { text-align: center; }
        .text-right { text-align: right; }

        .company-logo {
            max-width: 70px;
            max-height: 70px;
            margin: 6px auto;
            display: block;
        }

        .company-name {
            font-size: 20px;
            font-weight: bold;
            letter-spacing: 2px;
            margin-bottom: 4px;
        }

        .company-info {
            font-size: 12px;
            margin-bottom: 2px;
        }

        .doc-title {
            font-size: 17px;
            font-weight: bold;
            margin: 8px 0 6px;
            border-bottom: 1px solid #000;
            padding-bottom: 6px;
        }

        .info-block {
            border-bottom: 1px solid #000;
            padding-bottom: 6px;
            margin-bottom: 6px;
        }

        .info-row {
            display: flex;
            font-size: 13px;
            margin-bottom: 2px;
        }

        .info-row .label { flex-shrink: 0; }
        .info-row .value { margin-left: 4px; }

        /* "Invoice #" and "Date" share one line, like the reference receipt */
        .info-row.split {
            justify-content: space-between;
        }

        .items-table {
            width: 100%;
            font-size: 13px;
            border-collapse: collapse;
        }

        .items-table th {
            border-bottom: 1px solid #000;
            padding: 3px 2px 5px;
            text-align: left;
        }

        .items-table th.col-ctn,
        .items-table th.col-pcs,
        .items-table td.col-ctn,
        .items-table td.col-pcs {
            text-align: center;
            width: 15%;
        }

        .items-table td {
            padding: 4px 2px;
            border-bottom: 1px solid #000;
            vertical-align: top;
        }

        .items-table tr.totals-row td {
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
            font-weight: bold;
            padding-top: 6px;
            padding-bottom: 6px;
        }

        .totals-row .total-label-cell {
            display: flex;
            justify-content: space-between;
        }

        .signature-block {
            margin-top: 40px;
            text-align: center;
        }

        .signature-line {
            border-top: 1px solid #000;
            margin: 0 10px 6px;
        }

        .print-btn, .note-btn {
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            display: block;
            margin: 0 auto 10px;
            color: #fff;
        }

        .print-btn { background: #007bff; }
        .print-btn:hover { background: #0056b3; }
        .note-btn { background: #28a745; }
        .note-btn:hover { background: #218838; }

        /* Brand note modal (same reusable feature as the original thermal print) */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0; top: 0; width: 100%; height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }
        .modal.show { display: flex; align-items: center; justify-content: center; }
        .modal-content {
            background-color: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            width: 90%;
            max-width: 500px;
            min-height: 300px;
            display: flex;
            flex-direction: column;
        }
        .modal-header { font-size: 20px; font-weight: bold; margin-bottom: 20px; color: #333; }
        .modal-body { flex: 1; margin-bottom: 20px; }
        .note-textarea {
            width: 100%; height: 150px; padding: 10px; font-size: 14px;
            font-family: 'Courier New', monospace; border: 1px solid #ddd; border-radius: 5px; resize: vertical;
        }
        .modal-footer { display: flex; gap: 10px; justify-content: flex-end; }
        .modal-footer button { padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; }
        .btn-save { background-color: #28a745; color: #fff; }
        .btn-save:hover { background-color: #218838; }
        .btn-cancel { background-color: #6c757d; color: #fff; }
        .btn-cancel:hover { background-color: #5a6268; }
        .btn-clear { background-color: #dc3545; color: #fff; }
        .btn-clear:hover { background-color: #c82333; }

        .notes-section {
            margin-top: 8px;
            font-size: 11px;
            white-space: pre-wrap;
            word-wrap: break-word;
            line-height: 1.3;
            display: none;
        }
        .notes-section.show { display: block; }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="note-btn" onclick="openBrandNoteModal()">Add Brand Note</button>
        <button class="print-btn" onclick="window.print()">Print Receipt</button>
    </div>

    <!-- Brand Note Modal -->
    <div id="brandNoteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">Add Brand Note</div>
            <div class="modal-body">
                <textarea id="brandNoteTextarea" class="note-textarea" placeholder="Enter your brand note here..."></textarea>
            </div>
            <div class="modal-footer">
                <button class="btn-clear" onclick="clearBrandNote()">Clear Brand Note</button>
                <button class="btn-cancel" onclick="closeBrandNoteModal()">Cancel</button>
                <button class="btn-save" onclick="saveBrandNote()">Save Brand Note</button>
            </div>
        </div>
    </div>

    <div class="thermal-receipt">
        <div class="text-center">
            <div class="company-name" id="companyName">Loading...</div>
            <div class="company-info" id="companyPhone"></div>
        </div>

        <div class="doc-title text-center">Delivery Challan</div>

        <div class="info-block">
            <div class="info-row split">
                <span><span class="label">Invoice #:</span> <span class="value" id="invoiceNo">Loading...</span></span>
                <span><span class="label">Date:</span> <span class="value" id="invoiceDate">Loading...</span></span>
            </div>
            <div class="info-row">
                <span class="label">Customer:</span><span class="value" id="customerName">Loading...</span>
            </div>
            <div class="info-row">
                <span class="label">Address:</span><span class="value" id="customerAddress"></span>
            </div>
            <div class="info-row">
                <span class="label">Sales Man:</span><span class="value" id="salesMan">-</span>
            </div>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th class="col-ctn">Ctn</th>
                    <th class="col-pcs">Pcs</th>
                    <th>Item</th>
                </tr>
            </thead>
            <tbody id="itemsTableBody">
                <tr>
                    <td colspan="3" class="text-center">Loading...</td>
                </tr>
            </tbody>
        </table>

        <div id="notesSection" class="notes-section">
            <div id="notesContent"></div>
        </div>

        <div class="signature-block">
            <div class="signature-line"></div>
            <div>Signature</div>
        </div>
    </div>

    <script>
        const urlParams = new URLSearchParams(window.location.search);
        const invoiceId = urlParams.get('id');
        let tenantId = null;

        if (!invoiceId) {
            alert('Invoice ID is required');
            window.close();
        }

        // Splits a line item's quantity into the Ctn or Pcs column based on its unit name
        // (e.g. "Carton (Ctn)" / "Piece (Pc)"). If a unit doesn't match either (shouldn't
        // normally happen - this business only orders in Carton or Piece), the quantity and
        // its real unit name are kept visible in the Item column instead of being silently
        // dropped from either numeric column.
        function classifyCtnPcs(item) {
            const uomName = (item.uom_name || '').toLowerCase();
            const qty = parseFloat(item.quantity) || 0;
            const isCarton = /carton|\bctn\b/.test(uomName);
            const isPiece = /piece|\bpcs?\b|\bpc\b/.test(uomName);

            if (isCarton) return { ctn: qty.toFixed(0), pcs: '', unitNote: '' };
            if (isPiece) return { ctn: '', pcs: qty.toFixed(0), unitNote: '' };
            return { ctn: '', pcs: '', unitNote: ` (${qty.toFixed(2)} ${item.uom_name || ''})` };
        }

        async function loadData() {
            try {
                const invoiceResponse = await fetch(`../../../../server/api/sale/delivery_chalan/pos-edit.php?id=${invoiceId}`);
                const invoiceData = await invoiceResponse.json();

                if (invoiceData.success) {
                    if (invoiceData.invoice.company_id) {
                        const companyResponse = await fetch(`../../../../server/api/sale/delivery_chalan/get-company-by-id.php?id=${invoiceData.invoice.company_id}`);
                        const companyData = await companyResponse.json();
                        if (companyData.success) populateCompanyData(companyData.company);
                    } else {
                        const companyResponse = await fetch('../../../../server/api/sale/delivery_chalan/get-company.php');
                        const companyData = await companyResponse.json();
                        if (companyData.success) populateCompanyData(companyData.company);
                    }
                    populateInvoiceData(invoiceData.invoice, invoiceData.items);
                    tenantId = invoiceData.invoice.tenant_id;
                } else {
                    alert('Error loading invoice: ' + invoiceData.message);
                }
            } catch (error) {
                alert('Error loading data: ' + error.message);
            }
        }

        function populateCompanyData(company) {
            if (company.logo_url) {
                const logoImg = document.createElement('img');
                logoImg.src = `../../../assets/uploads/company_logo/${company.logo_url}`;
                logoImg.alt = 'Logo';
                logoImg.className = 'company-logo';
                const textCenter = document.querySelector('.text-center');
                textCenter.insertBefore(logoImg, textCenter.firstChild);
            }
            document.getElementById('companyName').textContent = (company.company_name || company.legal_name || 'Company Name').toUpperCase();
            const phoneText = company.phone ? `Cell #. ${company.phone}` : '';
            document.getElementById('companyPhone').textContent = phoneText;
        }

        function populateInvoiceData(invoice, items) {
            document.getElementById('invoiceNo').textContent = invoice.bill_no;
            document.getElementById('invoiceDate').textContent = new Date(invoice.sale_date).toLocaleDateString('en-GB');
            document.getElementById('customerName').textContent = invoice.customer_name || '-';
            document.getElementById('customerAddress').textContent = invoice.customer_address || '';
            document.getElementById('salesMan').textContent = invoice.sales_officer_name || invoice.supplier_man_name || 'Cash Counter';

            const tbody = document.getElementById('itemsTableBody');
            tbody.innerHTML = '';

            let totalCtn = 0;
            let totalPcs = 0;
            let lineCount = 0;

            // Top-level items only - a child row (parent_row_id set) is a UOM variant of
            // its parent, shown as its own Ctn/Pcs line right after the parent, matching how
            // the rest of this app already treats parent/child invoice rows.
            items.filter(item => !item.parent_row_id).forEach((item) => {
                const split = classifyCtnPcs(item);
                lineCount++;
                if (split.ctn) totalCtn += parseFloat(split.ctn) || 0;
                if (split.pcs) totalPcs += parseFloat(split.pcs) || 0;

                const row = tbody.insertRow();
                row.innerHTML = `
                    <td class="col-ctn">${split.ctn}</td>
                    <td class="col-pcs">${split.pcs}</td>
                    <td>${item.product_name}${split.unitNote}</td>
                `;

                const children = items.filter(c => c.parent_row_id === item.id);
                children.forEach(child => {
                    const cSplit = classifyCtnPcs(child);
                    lineCount++;
                    if (cSplit.ctn) totalCtn += parseFloat(cSplit.ctn) || 0;
                    if (cSplit.pcs) totalPcs += parseFloat(cSplit.pcs) || 0;

                    const cRow = tbody.insertRow();
                    cRow.innerHTML = `
                        <td class="col-ctn">${cSplit.ctn}</td>
                        <td class="col-pcs">${cSplit.pcs}</td>
                        <td>${child.product_name}${cSplit.unitNote}</td>
                    `;
                });
            });

            // Totals row: Ctn total | Pcs total | "Total:" + item-line count - matches the
            // reference receipt exactly (Ctn and Pcs can't be meaningfully summed together,
            // so the grand total shown is how many item lines are on the chalan).
            const totalsRow = tbody.insertRow();
            totalsRow.className = 'totals-row';
            totalsRow.innerHTML = `
                <td class="col-ctn">${totalCtn || ''}</td>
                <td class="col-pcs">${totalPcs || ''}</td>
                <td><div class="total-label-cell"><span>Total:</span><span>${lineCount}</span></div></td>
            `;
        }

        loadData();

        // Brand Note Modal Functions (same reusable feature as the original thermal print)
        function openBrandNoteModal() {
            const modal = document.getElementById('brandNoteModal');
            const textarea = document.getElementById('brandNoteTextarea');
            const brandNoteKey = `brand_print_note_${tenantId}`;
            textarea.value = localStorage.getItem(brandNoteKey) || '';
            modal.classList.add('show');
        }

        function closeBrandNoteModal() {
            document.getElementById('brandNoteModal').classList.remove('show');
        }

        function saveBrandNote() {
            const textarea = document.getElementById('brandNoteTextarea');
            const noteText = textarea.value.trim();
            const brandNoteKey = `brand_print_note_${tenantId}`;

            if (noteText) {
                localStorage.setItem(brandNoteKey, noteText);
                displayBrandNote(noteText);
                closeBrandNoteModal();
                alert('Brand note saved successfully!');
            } else {
                alert('Please enter a note before saving.');
            }
        }

        function clearBrandNote() {
            if (confirm('Are you sure you want to clear the brand note? It will be removed from all invoices.')) {
                document.getElementById('brandNoteTextarea').value = '';
                localStorage.removeItem(`brand_print_note_${tenantId}`);
                document.getElementById('notesSection').classList.remove('show');
                closeBrandNoteModal();
            }
        }

        function displayBrandNote(noteText) {
            if (noteText) {
                document.getElementById('notesContent').textContent = noteText;
                document.getElementById('notesSection').classList.add('show');
            }
        }

        window.addEventListener('load', function() {
            setTimeout(() => {
                if (tenantId) {
                    const brandNote = localStorage.getItem(`brand_print_note_${tenantId}`);
                    if (brandNote) displayBrandNote(brandNote);
                }
            }, 1000);
        });

        document.getElementById('brandNoteModal').addEventListener('click', function(e) {
            if (e.target === this) closeBrandNoteModal();
        });
    </script>
</body>
</html>
