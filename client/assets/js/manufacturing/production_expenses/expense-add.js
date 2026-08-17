  // ----- DYNAMIC DATA ------
  let PO_DATA = {};
  let ALL_PO_OPTIONS = [];
  const CASH_ACCOUNTS = [{ value: 'cash-main', label: 'Cash in Hand — Main' },{ value: 'cash-factory', label: 'Cash in Hand — Factory' },{ value: 'petty', label: 'Petty Cash' }];
  let BANK_ACCOUNTS = [];
  const ACCRUED_ACCOUNT = { value: 'accrued-exp', label: '2300 — Accrued Expenses (Liability)' };
  const CASH_ACCOUNT = { value: 'cash-main', label: 'Cash in Hand — Main' };
  let GL_DEBIT_ACCOUNTS = [];
  let VENDORS = [];

  let rowCounter = 0;
  let batchCounter = 0;
  let periodBatches = [];
  
  window.onload = async () => {
    document.getElementById('entryDate').value = new Date().toISOString().split('T')[0];
    await loadProductionOrders();
    await loadBankAccounts();
    await loadSuppliers();
    await loadExpenseAccounts();
    addRow();
    setupSearchableDropdown();
  };

  function setupSearchableDropdown() {
    const searchInput = document.getElementById('prodOrderSearch');
    const dropdown = document.getElementById('prodOrderDropdown');
    
    searchInput.addEventListener('focus', () => {
      renderDropdownOptions('');
      dropdown.style.display = 'block';
    });
    
    searchInput.addEventListener('input', (e) => {
      renderDropdownOptions(e.target.value);
      dropdown.style.display = 'block';
    });
    
    document.addEventListener('click', (e) => {
      if (!e.target.closest('.field')) {
        dropdown.style.display = 'none';
      }
    });
  }

  function toggleAllocationMode() {
    const method = document.getElementById('allocMethod').value;
    const singlePOField = document.getElementById('singlePOField');
    const singlePOInfo = document.getElementById('singlePOInfo');
    const multiBatchSection = document.getElementById('multiBatchSection');
    const periodSection = document.getElementById('periodSection');
    const infoRow = document.getElementById('infoRow');
    
    singlePOField.style.display = 'none';
    singlePOInfo.style.display = 'none';
    multiBatchSection.style.display = 'none';
    periodSection.style.display = 'none';
    infoRow.style.display = 'none';
    
    if (method === 'direct') {
      singlePOField.style.display = 'block';
      singlePOInfo.style.display = 'grid';
    } else if (method === 'multi') {
      multiBatchSection.style.display = 'block';
      if (document.querySelectorAll('.batch-row').length === 0) {
        addBatchRow();
      }
    } else if (method === 'period') {
      periodSection.style.display = 'block';
      const today = new Date();
      const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
      const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
      document.getElementById('periodFrom').value = firstDay.toISOString().split('T')[0];
      document.getElementById('periodTo').value = lastDay.toISOString().split('T')[0];
    }
  }

  function addBatchRow() {
    batchCounter++;
    const container = document.getElementById('multiBatchList');
    const div = document.createElement('div');
    div.className = 'batch-row';
    div.id = `batch-${batchCounter}`;
    
    let poOptions = '<option value="">— Select PO —</option>';
    ALL_PO_OPTIONS.forEach(po => {
      poOptions += `<option value="${po.value}">${po.text}</option>`;
    });
    
    div.innerHTML = `
      <select class="batch-po" onchange="updateBatchQty(${batchCounter})">${poOptions}</select>
      <input type="number" class="batch-qty" placeholder="Qty Produced" readonly>
      <input type="number" class="batch-alloc" placeholder="% or Amount" oninput="recalcAllocations()">
      <button class="btn-del" onclick="removeBatchRow(${batchCounter})"><i class="fas fa-times"></i></button>
    `;
    container.appendChild(div);
  }

  function removeBatchRow(id) {
    document.getElementById(`batch-${id}`).remove();
    recalcAllocations();
  }

  function updateBatchQty(batchId) {
    const row = document.getElementById(`batch-${batchId}`);
    const poId = row.querySelector('.batch-po').value;
    const qtyInput = row.querySelector('.batch-qty');
    
    if (poId && PO_DATA[poId]) {
      qtyInput.value = parseFloat(PO_DATA[poId].qty.split(' ')[0].replace(/,/g, ''));
    } else {
      qtyInput.value = '';
    }
    recalcAllocations();
  }

  function recalcAllocations() {
    const totalExpense = parseFloat(document.getElementById('totalAmount').innerText.replace(/[^0-9.]/g, '')) || 0;
    if (totalExpense === 0) return;
    
    const batches = document.querySelectorAll('.batch-row');
    let totalQty = 0;
    batches.forEach(row => {
      const qty = parseFloat(row.querySelector('.batch-qty').value) || 0;
      totalQty += qty;
    });
    
    if (totalQty > 0) {
      batches.forEach(row => {
        const qty = parseFloat(row.querySelector('.batch-qty').value) || 0;
        const allocInput = row.querySelector('.batch-alloc');
        if (!allocInput.value) {
          const share = (qty / totalQty) * totalExpense;
          allocInput.placeholder = `Auto: ${fmt(share)}`;
        }
      });
    }
  }

  async function loadPeriodBatches() {
    const periodFrom = document.getElementById('periodFrom').value;
    const periodTo = document.getElementById('periodTo').value;
    const basis = document.getElementById('allocationBasis').value;
    
    if (!periodFrom || !periodTo) {
      showToast('error', 'Validation', 'Select period dates');
      return;
    }
    
    try {
      const response = await fetch(`../../../../server/api/manufacturing/production_expenses/get-period-batches.php?from=${periodFrom}&to=${periodTo}&basis=${basis}`);
      const result = await response.json();
      
      if (result.success && result.data) {
        periodBatches = result.data;
        const preview = document.getElementById('periodBatchesPreview');
        const list = document.getElementById('periodBatchesList');
        
        if (periodBatches.length === 0) {
          list.innerHTML = '<div style="color:var(--subtext);">No batches found in this period</div>';
        } else {
          list.innerHTML = periodBatches.map(b => 
            `<div style="padding:4px 0; border-bottom:1px solid var(--border-default);">${b.order_no} — ${b.product_name} (${b.qty} ${b.uom}) — ${basis === 'qty' ? 'Qty: ' + b.allocation_base : basis === 'hours' ? 'Hrs: ' + b.allocation_base : 'Equal'}</div>`
          ).join('');
          list.innerHTML += `<div style="margin-top:8px; font-weight:600; color:var(--primary);">Total: ${periodBatches.length} batches</div>`;
        }
        preview.style.display = 'block';
        showToast('success', 'Loaded', `${periodBatches.length} batches found`);
      }
    } catch (error) {
      console.error('Error loading period batches:', error);
      showToast('error', 'Error', 'Failed to load batches');
    }
  }

  function renderDropdownOptions(searchTerm) {
    const dropdown = document.getElementById('prodOrderDropdown');
    const filtered = ALL_PO_OPTIONS.filter(po => 
      po.text.toLowerCase().includes(searchTerm.toLowerCase())
    );
    
    if (filtered.length === 0) {
      dropdown.innerHTML = '<div class="dropdown-item empty">No production orders found</div>';
      return;
    }
    
    dropdown.innerHTML = filtered.map(po => 
      `<div class="dropdown-item" data-value="${po.value}">${po.text}</div>`
    ).join('');
    
    dropdown.querySelectorAll('.dropdown-item').forEach(item => {
      item.addEventListener('click', () => {
        const value = item.dataset.value;
        document.getElementById('prodOrderSearch').value = item.textContent;
        document.getElementById('prodOrder').value = value;
        dropdown.style.display = 'none';
        loadPODetails();
      });
    });
  }

  async function loadProductionOrders() {
    try {
      const response = await fetch('../../../../server/api/manufacturing/production_expenses/get-production-orders.php');
      const result = await response.json();
      
      if (result.success && result.data) {
        const select = document.getElementById('prodOrder');
        select.innerHTML = '<option value="">— Select Production Order —</option>';
        ALL_PO_OPTIONS = [];
        
        result.data.forEach(po => {
          const option = document.createElement('option');
          option.value = po.id;
          const optionText = `${po.order_no} — ${po.product_name} (${po.status})`;
          option.textContent = optionText;
          select.appendChild(option);
          
          ALL_PO_OPTIONS.push({ value: po.id, text: optionText });
          
          PO_DATA[po.id] = {
            fg: po.product_name,
            qty: `${parseFloat(po.order_qty).toLocaleString('en-PK')} ${po.uom_name}`,
            date: formatDate(po.start_date),
            bom: po.bom_name,
            status: po.status,
            prevExp: 'Rs. 0'
          };
        });
      }
    } catch (error) {
      console.error('Error loading production orders:', error);
      showToast('error', 'Error', 'Failed to load production orders');
    }
  }

  function formatDate(dateStr) {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-PK', { day: '2-digit', month: 'short', year: 'numeric' });
  }

  async function loadBankAccounts() {
    try {
      const response = await fetch('../../../../server/api/manufacturing/production_expenses/get-bank-accounts.php');
      const result = await response.json();
      
      if (result.success && result.data) {
        BANK_ACCOUNTS = result.data.map(acc => ({
          value: acc.id,
          label: acc.label
        }));
      }
    } catch (error) {
      console.error('Error loading bank accounts:', error);
      showToast('error', 'Error', 'Failed to load bank accounts');
    }
  }

  async function loadSuppliers() {
    try {
      const response = await fetch('../../../../server/api/manufacturing/production_expenses/get-suppliers.php');
      const result = await response.json();
      
      if (result.success && result.data) {
        VENDORS = result.data.map(sup => ({
          value: sup.id,
          label: sup.label
        }));
      }
    } catch (error) {
      console.error('Error loading suppliers:', error);
      showToast('error', 'Error', 'Failed to load suppliers');
    }
  }

  async function loadExpenseAccounts() {
    try {
      const response = await fetch('../../../../server/api/manufacturing/production_expenses/get-expense-accounts.php');
      const result = await response.json();
      
      console.log('Expense accounts loaded:', result);
      
      if (result.success && result.data) {
        GL_DEBIT_ACCOUNTS = result.data.map(acc => ({
          value: acc.id,
          label: acc.label,
          category: acc.expense_category
        }));
        console.log('GL_DEBIT_ACCOUNTS:', GL_DEBIT_ACCOUNTS);
      }
    } catch (error) {
      console.error('Error loading expense accounts:', error);
      showToast('error', 'Error', 'Failed to load expense accounts');
    }
  }

  function onPayStatusChange(rowId) {
    const row = document.getElementById(`row-${rowId}`);
    const payStatus = row.querySelector('.pay-status').value;
    const payMode = row.querySelector('.pay-mode');
    const bankAccount = row.querySelector('.bank-account');
    const chequeNo = row.querySelector('.cheque-no');
    const chequeDate = row.querySelector('.cheque-date');
    
    row.dataset.payStatus = payStatus;
    
    if (payStatus === 'accrued') {
      payMode.disabled = true;
      payMode.value = '';
      bankAccount.style.display = 'none';
      bankAccount.value = '';
      chequeNo.style.display = 'none';
      chequeNo.value = '';
      chequeDate.style.display = 'none';
      chequeDate.value = '';
    } else {
      payMode.disabled = false;
      payMode.value = '';
      bankAccount.style.display = 'none';
      bankAccount.value = '';
      chequeNo.style.display = 'none';
      chequeNo.value = '';
      chequeDate.style.display = 'none';
      chequeDate.value = '';
    }
    updateGL();
  }

  function onPayModeChange(rowId) {
    const row = document.getElementById(`row-${rowId}`);
    const payStatus = row.querySelector('.pay-status').value;
    if (payStatus === 'accrued') return;
    
    const payMode = row.querySelector('.pay-mode').value;
    const bankAccount = row.querySelector('.bank-account');
    const chequeNo = row.querySelector('.cheque-no');
    const chequeDate = row.querySelector('.cheque-date');
    
    if (payMode === 'cash') {
      bankAccount.style.display = 'none';
      bankAccount.value = '';
      chequeNo.style.display = 'none';
      chequeNo.value = '';
      chequeDate.style.display = 'none';
      chequeDate.value = '';
    } else if (payMode === 'bank') {
      bankAccount.style.display = 'table-cell';
      bankAccount.innerHTML = '<option value="">— Select —</option>';
      BANK_ACCOUNTS.forEach(a => {
        bankAccount.innerHTML += `<option value="${a.value}">${a.label}</option>`;
      });
      chequeNo.style.display = 'none';
      chequeNo.value = '';
      chequeDate.style.display = 'none';
      chequeDate.value = '';
    } else if (payMode === 'cheque') {
      bankAccount.style.display = 'table-cell';
      bankAccount.innerHTML = '<option value="">— Select —</option>';
      BANK_ACCOUNTS.forEach(a => {
        bankAccount.innerHTML += `<option value="${a.value}">${a.label}</option>`;
      });
      chequeNo.style.display = 'table-cell';
      chequeDate.style.display = 'table-cell';
    } else {
      bankAccount.style.display = 'none';
      bankAccount.value = '';
      chequeNo.style.display = 'none';
      chequeNo.value = '';
      chequeDate.style.display = 'none';
      chequeDate.value = '';
    }
    updateGL();
  }

  function loadPODetails() {
    const val = document.getElementById('prodOrder').value;
    const infoRow = document.getElementById('infoRow');
    if (!val) {
      document.getElementById('fgName').value = ''; document.getElementById('plannedQty').value = '';
      infoRow.style.display = 'none'; document.getElementById('poHint').textContent = 'Select a production order to auto-fill';
      return;
    }
    const d = PO_DATA[val];
    document.getElementById('fgName').value = d.fg; document.getElementById('plannedQty').value = d.qty;
    document.getElementById('infoPoDate').innerText = d.date; document.getElementById('infoBom').innerText = d.bom;
    document.getElementById('infoStatus').innerText = d.status; document.getElementById('infoPrevExp').innerText = d.prevExp;
    infoRow.style.display = 'flex';
    document.getElementById('poHint').textContent = '';
  }

  function setupGLAccountSearch(rowId) {
    const searchInput = document.querySelector(`#row-${rowId} .gl-search-input`);
    const dropdown = document.querySelector(`#row-${rowId} .gl-search-dropdown`);
    const hiddenSelect = document.querySelector(`#row-${rowId} .gl-debit`);
    
    if (!searchInput || !dropdown) return;
    
    searchInput.addEventListener('focus', () => {
      renderGLDropdownOptions(rowId, '');
      dropdown.style.display = 'block';
    });
    
    searchInput.addEventListener('input', (e) => {
      renderGLDropdownOptions(rowId, e.target.value);
      dropdown.style.display = 'block';
    });
    
    document.addEventListener('click', (e) => {
      if (!e.target.closest(`#row-${rowId} .gl-search-wrapper`)) {
        dropdown.style.display = 'none';
      }
    });
  }

  function renderGLDropdownOptions(rowId, searchTerm) {
    const dropdown = document.querySelector(`#row-${rowId} .gl-search-dropdown`);
    if (!dropdown) return;
    
    const filtered = GL_DEBIT_ACCOUNTS.filter(acc => 
      acc.label.toLowerCase().includes(searchTerm.toLowerCase())
    );
    
    if (filtered.length === 0) {
      dropdown.innerHTML = '<div class="dropdown-item empty">No accounts found</div>';
      return;
    }
    
    dropdown.innerHTML = filtered.map(acc => 
      `<div class="dropdown-item" data-value="${acc.value}">${acc.label}</div>`
    ).join('');
    
    dropdown.querySelectorAll('.dropdown-item').forEach(item => {
      item.addEventListener('click', () => {
        const value = item.dataset.value;
        const searchInput = document.querySelector(`#row-${rowId} .gl-search-input`);
        const hiddenSelect = document.querySelector(`#row-${rowId} .gl-debit`);
        
        searchInput.value = item.textContent;
        
        // Add option to hidden select if it doesn't exist
        if (!hiddenSelect.querySelector(`option[value="${value}"]`)) {
          const option = document.createElement('option');
          option.value = value;
          option.textContent = item.textContent;
          hiddenSelect.appendChild(option);
        }
        hiddenSelect.value = value;
        
        dropdown.style.display = 'none';
        updateGL();
      });
    });
  }



  function addRow() {
    rowCounter++;
    const tbody = document.getElementById('lineBody');
    const tr = document.createElement('tr'); tr.id = `row-${rowCounter}`; tr.dataset.mode = 'flat'; tr.dataset.payStatus = 'paid';
    let vendorOpts = '<option value="">— None —</option>';
    VENDORS.forEach(v => vendorOpts += `<option value="${v.value}">${v.label}</option>`);
    let payStatusOpts = `<option value="paid">Paid</option><option value="accrued">Accrued</option>`;
    let payModeOpts = `<option value="">— Select —</option><option value="cash">Cash</option><option value="bank">Bank</option><option value="cheque">Cheque</option>`;
    tr.innerHTML = `
      <td>${rowCounter}</td>
      <td>
        <div class="gl-search-wrapper" style="position:relative;">
          <input type="text" class="td-input gl-search-input" placeholder="Search GL account..." autocomplete="off">
          <select class="gl-debit" style="display:none;"></select>
          <div class="gl-search-dropdown search-dropdown"></div>
        </div>
      </td>
      <td><input type="text" class="td-input" placeholder="Optional note"></td>
      <td><div class="mode-toggle"><button class="active" onclick="setMode(${rowCounter},'flat',this)">Flat</button><button onclick="setMode(${rowCounter},'hrs',this)">Hrs×Rate</button></div></td>
      <td><input type="number" class="td-input hrs-field" placeholder="0" style="display:none" oninput="calcAmount(${rowCounter})"></td>
      <td><input type="number" class="td-input rate-field" placeholder="0.00" style="display:none" oninput="calcAmount(${rowCounter})"></td>
      <td><input type="number" class="td-input amount-field" placeholder="0.00" oninput="updateTotals()"></td>
      <td><select class="td-input vendor">${vendorOpts}</select></td>
      <td><select class="td-input pay-status" onchange="onPayStatusChange(${rowCounter})">${payStatusOpts}</select></td>
      <td><select class="td-input pay-mode" onchange="onPayModeChange(${rowCounter})">${payModeOpts}</select></td>
      <td><select class="td-input bank-account" onchange="updateGL()" style="display:none;">—</select></td>
      <td><input type="text" class="td-input cheque-no" placeholder="Cheque #" style="display:none;"></td>
      <td><input type="date" class="td-input cheque-date" style="display:none;"></td>
      <td><button class="btn-del" onclick="deleteRow(${rowCounter})"><i class="fas fa-trash-alt"></i></button></td>`;
    tbody.appendChild(tr);
    setupGLAccountSearch(rowCounter);
    onPayStatusChange(rowCounter);
    updateTotals();
  }



  function setMode(rowId, mode, btn) {
    const row = document.getElementById(`row-${rowId}`);
    row.dataset.mode = mode;
    const btns = row.querySelectorAll('.mode-toggle button');
    btns.forEach(b => b.classList.remove('active')); btn.classList.add('active');
    const hrs = row.querySelector('.hrs-field'), rate = row.querySelector('.rate-field'), amt = row.querySelector('.amount-field');
    if (mode === 'hrs') { hrs.style.display = 'block'; rate.style.display = 'block'; amt.setAttribute('readonly', true); amt.placeholder = 'Auto'; amt.value = ''; }
    else { hrs.style.display = 'none'; rate.style.display = 'none'; amt.removeAttribute('readonly'); amt.placeholder = '0.00'; updateTotals(); }
  }

  function calcAmount(rowId) {
    const row = document.getElementById(`row-${rowId}`);
    const hrs = parseFloat(row.querySelector('.hrs-field').value) || 0;
    const rate = parseFloat(row.querySelector('.rate-field').value) || 0;
    const amtField = row.querySelector('.amount-field');
    amtField.value = (hrs * rate).toFixed(2);
    updateTotals();
  }

  function deleteRow(rowId) {
    if (document.querySelectorAll('#lineBody tr').length <= 1) { showToast('error', 'Cannot remove', 'At least one expense line required.'); return; }
    document.getElementById(`row-${rowId}`).remove();
    updateTotals();
  }

  function updateTotals() {
    let grand = 0;
    document.querySelectorAll('#lineBody tr').forEach(row => {
      let amt = parseFloat(row.querySelector('.amount-field')?.value) || 0;
      grand += amt;
    });
    document.getElementById('totalAmount').innerText = 'Rs. ' + fmt(grand);
    recalcAllocations();
    updateGL();
  }

  function updateGL() {
    const glBody = document.getElementById('glBody');
    let debitEntries = [];
    let creditEntries = {};
    
    document.querySelectorAll('#lineBody tr').forEach(row => {
      let amt = parseFloat(row.querySelector('.amount-field')?.value) || 0;
      if (amt <= 0) return;
      
      let glSelect = row.querySelector('.gl-debit');
      let glValue = glSelect?.value;
      let glLabel = glSelect?.options[glSelect.selectedIndex]?.text || '';
      
      if (glValue && glValue !== '') {
        debitEntries.push({ account: glLabel, amount: amt });
      }
      
      const payStatus = row.querySelector('.pay-status')?.value;
      const payMode = row.querySelector('.pay-mode')?.value;
      const bankAccount = row.querySelector('.bank-account');
      
      let creditAccount = '';
      let creditLabel = '';
      
      if (payStatus === 'accrued') {
        creditAccount = ACCRUED_ACCOUNT.value;
        creditLabel = ACCRUED_ACCOUNT.label;
      } else if (payMode === 'cash') {
        creditAccount = CASH_ACCOUNT.value;
        creditLabel = CASH_ACCOUNT.label;
      } else if ((payMode === 'bank' || payMode === 'cheque') && bankAccount.value) {
        creditAccount = bankAccount.value;
        creditLabel = bankAccount.options[bankAccount.selectedIndex]?.text || 'Bank Account';
      }
      
      if (creditAccount) {
        if (!creditEntries[creditAccount]) {
          creditEntries[creditAccount] = { label: creditLabel, amount: 0 };
        }
        creditEntries[creditAccount].amount += amt;
      }
    });
    
    if (debitEntries.length === 0) {
      glBody.innerHTML = '<div class="gl-empty">Add expense lines to preview journal entries.</div>';
      document.getElementById('glStatus').innerText = 'No entries';
      return;
    }
    
    let html = '';
    let grandTotal = 0;
    debitEntries.forEach(e => {
      html += `<div class="gl-entry"><span class="gl-type dr">Dr.</span><span class="gl-account">${e.account}</span><span class="gl-amount dr">Rs. ${fmt(e.amount)}</span></div>`;
      grandTotal += e.amount;
    });
    
    Object.keys(creditEntries).forEach(key => {
      const cr = creditEntries[key];
      html += `<div class="gl-entry" style="background:#F9FAFE"><span class="gl-type cr">Cr.</span><span class="gl-account">${cr.label}</span><span class="gl-amount cr">Rs. ${fmt(cr.amount)}</span></div>`;
    });
    
    glBody.innerHTML = html;
    const totalEntries = debitEntries.length + Object.keys(creditEntries).length;
    document.getElementById('glStatus').innerHTML = `${totalEntries} entries · Rs. ${fmt(grandTotal)}`;
  }

  function saveAndPost() {
    const allocMethod = document.getElementById('allocMethod').value;
    
    if (allocMethod === 'direct') {
      if (!document.getElementById('prodOrder').value) { showToast('error','Validation','Select Production Order'); return; }
    } else if (allocMethod === 'multi') {
      const batches = document.querySelectorAll('.batch-row');
      if (batches.length === 0) { showToast('error','Validation','Add at least one batch'); return; }
      let hasValidBatch = false;
      batches.forEach(row => {
        if (row.querySelector('.batch-po').value) hasValidBatch = true;
      });
      if (!hasValidBatch) { showToast('error','Validation','Select at least one production order'); return; }
    } else if (allocMethod === 'period') {
      if (periodBatches.length === 0) { showToast('error','Validation','Load period batches first'); return; }
      if (!document.getElementById('periodFrom').value || !document.getElementById('periodTo').value) {
        showToast('error','Validation','Period dates required'); return;
      }
    }
    
    if (!document.getElementById('entryDate').value) { showToast('error','Validation','Entry date required'); return; }
    
    let hasAmount = false, valid = true;
    const lines = [];
    
    document.querySelectorAll('#lineBody tr').forEach((row,i) => {
      let amt = parseFloat(row.querySelector('.amount-field')?.value) || 0;
      let glSelect = row.querySelector('.gl-debit');
      let glValue = glSelect?.value;
      let payStatus = row.querySelector('.pay-status')?.value;
      let payMode = row.querySelector('.pay-mode')?.value;
      let bankAccount = row.querySelector('.bank-account')?.value;
      let chequeNo = row.querySelector('.cheque-no')?.value;
      let chequeDate = row.querySelector('.cheque-date')?.value;
      let description = row.querySelectorAll('.td-input')[0]?.value;
      let vendor = row.querySelector('.vendor')?.value;
      let hours = parseFloat(row.querySelector('.hrs-field')?.value) || null;
      let rate = parseFloat(row.querySelector('.rate-field')?.value) || null;
      
      if (amt > 0) {
        hasAmount = true;
        if (!glValue) { showToast('error',`Line ${i+1}`,'GL Debit account required'); valid = false; }
        if (payStatus === 'paid') {
          if (!payMode) { showToast('error',`Line ${i+1}`,'Payment Mode required'); valid = false; }
          if (payMode === 'bank' && !bankAccount) { showToast('error',`Line ${i+1}`,'Bank Account required'); valid = false; }
          if (payMode === 'cheque') {
            if (!bankAccount) { showToast('error',`Line ${i+1}`,'Bank Account required'); valid = false; }
            if (!chequeNo) { showToast('error',`Line ${i+1}`,'Cheque Number required'); valid = false; }
            if (!chequeDate) { showToast('error',`Line ${i+1}`,'Cheque Date required'); valid = false; }
          }
        }
        
        lines.push({
          gl_account_id: glValue,
          description: description,
          hours: hours,
          rate: rate,
          amount: amt,
          vendor_id: vendor || null,
          payment_status: payStatus,
          payment_mode: payMode || null,
          bank_account_id: bankAccount || null,
          cheque_no: chequeNo || null,
          cheque_date: chequeDate || null
        });
      }
    });
    
    if (!hasAmount) { showToast('error','Validation','Add at least one expense line with amount'); return; }
    if (!valid) return;
    
    let data;
    if (allocMethod === 'direct') {
      data = {
        allocation_method: 'direct',
        production_order_id: document.getElementById('prodOrder').value,
        entry_date: document.getElementById('entryDate').value,
        narration: document.getElementById('narration').value,
        lines: lines
      };
    } else if (allocMethod === 'multi') {
      const batches = [];
      const totalExpense = lines.reduce((sum, line) => sum + line.amount, 0);
      let totalQty = 0;
      
      document.querySelectorAll('.batch-row').forEach(row => {
        const poId = row.querySelector('.batch-po').value;
        const qty = parseFloat(row.querySelector('.batch-qty').value) || 0;
        if (poId && qty > 0) {
          batches.push({ po_id: poId, qty: qty });
          totalQty += qty;
        }
      });
      
      batches.forEach(batch => {
        batch.allocation_amount = (batch.qty / totalQty) * totalExpense;
        batch.per_unit_cost = batch.allocation_amount / batch.qty;
      });
      
      data = {
        allocation_method: 'multi',
        entry_date: document.getElementById('entryDate').value,
        narration: document.getElementById('narration').value,
        batches: batches,
        lines: lines
      };
    } else if (allocMethod === 'period') {
      const totalExpense = lines.reduce((sum, line) => sum + line.amount, 0);
      const basis = document.getElementById('allocationBasis').value;
      
      let totalBase = 0;
      if (basis === 'equal') {
        totalBase = periodBatches.length;
      } else {
        totalBase = periodBatches.reduce((sum, b) => sum + parseFloat(b.allocation_base), 0);
      }
      
      const batches = periodBatches.map(b => {
        const base = basis === 'equal' ? 1 : parseFloat(b.allocation_base);
        const allocated = (base / totalBase) * totalExpense;
        return {
          po_id: b.id,
          allocation_amount: allocated,
          per_unit_cost: b.qty > 0 ? allocated / b.qty : 0
        };
      });
      
      data = {
        allocation_method: 'period',
        entry_date: document.getElementById('entryDate').value,
        narration: document.getElementById('narration').value,
        period_from: document.getElementById('periodFrom').value,
        period_to: document.getElementById('periodTo').value,
        allocation_basis: basis,
        batches: batches,
        lines: lines
      };
    }
    
    fetch('../../../../server/api/manufacturing/production_expenses/expense-add.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
      if (result.success) {
        showToast('success','Entry Posted','Production Expense saved & GL posted.');
        setTimeout(() => {
          window.location.href = 'expense-list.php';
        }, 2000);
      } else {
        showToast('error','Error', result.message);
      }
    })
    .catch(error => {
      console.error('Error:', error);
      showToast('error','Error','Failed to save expense');
    });
  }

  function resetForm() {
    if (!confirm('Discard all changes?')) return;
    document.getElementById('prodOrder').value = ''; document.getElementById('prodOrderSearch').value = ''; document.getElementById('fgName').value = ''; document.getElementById('plannedQty').value = '';
    document.getElementById('narration').value = ''; document.getElementById('infoRow').style.display = 'none';
    document.getElementById('lineBody').innerHTML = ''; rowCounter = 0; addRow(); updateTotals();
  }

  function fmt(n) { return Number(n).toLocaleString('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
  function showToast(type, title, msg) {
    const t = document.getElementById('toast');
    const icon = t.querySelector('#toastIcon');
    icon.className = type === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-triangle';
    document.getElementById('toastTitle').innerText = title;
    document.getElementById('toastMsg').innerText = msg;
    t.classList.remove('success','error'); t.classList.add(type);
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3800);
  }

  window.loadPeriodBatches = loadPeriodBatches;