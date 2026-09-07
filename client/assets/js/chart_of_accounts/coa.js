// CoA data structure
let chartOfAccounts = {
    id: "root",
    name: "Chart of Accounts",
    children: []
};

const iconClassMap = {
    '1': 'assets',
    '2': 'liabilities',
    '3': 'equity',
    '4': 'income',
    '5': 'expenses'
};

// DOM Elements
const coaTree = document.getElementById('coaTree');
const expandAllBtn = document.getElementById('expandAllBtn');
const collapseAllBtn = document.getElementById('collapseAllBtn');
const addSubAccountBtn = document.getElementById('addSubAccountBtn');
const addPostingAccountBtn = document.getElementById('addPostingAccountBtn');
const addPartnerAccountBtn = document.getElementById('addPartnerAccountBtn');
const showManageableBtn = document.getElementById('showManageableBtn');
const showAllBtn = document.getElementById('showAllBtn');
const totalAccountsSpan = document.getElementById('totalAccounts');

// Modals
const subAccountModal = document.getElementById('subAccountModal');
const postingAccountModal = document.getElementById('postingAccountModal');
const partnerAccountModal = document.getElementById('partnerAccountModal');
const editSubAccountModal = document.getElementById('editSubAccountModal');
const editPostingAccountModal = document.getElementById('editPostingAccountModal');
const editPartnerAccountModal = document.getElementById('editPartnerAccountModal');
const convertToPartnerModal = document.getElementById('convertToPartnerModal');

// Sub Account Modal Elements
const closeSubAccountModalBtn = document.getElementById('closeSubAccountModalBtn');
const cancelSubAccountBtn = document.getElementById('cancelSubAccountBtn');
const saveSubAccountBtn = document.getElementById('saveSubAccountBtn');
const subAccountForm = document.getElementById('subAccountForm');
const subAccountParentSelect = document.getElementById('subAccountParent');

// Posting Account Modal Elements
const closePostingAccountModalBtn = document.getElementById('closePostingAccountModalBtn');
const cancelPostingAccountBtn = document.getElementById('cancelPostingAccountBtn');
const savePostingAccountBtn = document.getElementById('savePostingAccountBtn');
const postingAccountForm = document.getElementById('postingAccountForm');
const postingAccountParentSelect = document.getElementById('postingAccountParent');

// Partner Account Modal Elements
const closePartnerAccountModalBtn = document.getElementById('closePartnerAccountModalBtn');
const cancelPartnerAccountBtn = document.getElementById('cancelPartnerAccountBtn');
const savePartnerAccountBtn = document.getElementById('savePartnerAccountBtn');
const partnerAccountForm = document.getElementById('partnerAccountForm');
const partnerAccountParentSelect = document.getElementById('partnerAccountParent');
const partnerRowsContainer = document.getElementById('partnerRowsContainer');
const addPartnerRowBtn = document.getElementById('addPartnerRowBtn');
const remainingPercentageDisplay = document.getElementById('remainingPercentageDisplay');

// Edit Sub Account Modal Elements
const closeEditSubAccountModalBtn = document.getElementById('closeEditSubAccountModalBtn');
const cancelEditSubAccountBtn = document.getElementById('cancelEditSubAccountBtn');
const updateSubAccountBtn = document.getElementById('updateSubAccountBtn');
const editSubAccountForm = document.getElementById('editSubAccountForm');
const editSubAccountParentSelect = document.getElementById('editSubAccountParent');

// Edit Posting Account Modal Elements
const closeEditPostingAccountModalBtn = document.getElementById('closeEditPostingAccountModalBtn');
const cancelEditPostingAccountBtn = document.getElementById('cancelEditPostingAccountBtn');
const updatePostingAccountBtn = document.getElementById('updatePostingAccountBtn');
const editPostingAccountForm = document.getElementById('editPostingAccountForm');
const editPostingAccountParentSelect = document.getElementById('editPostingAccountParent');

// Edit Partner Account Modal Elements
const closeEditPartnerAccountModalBtn = document.getElementById('closeEditPartnerAccountModalBtn');
const cancelEditPartnerAccountBtn = document.getElementById('cancelEditPartnerAccountBtn');
const updatePartnerAccountBtn = document.getElementById('updatePartnerAccountBtn');
const editPartnerAccountForm = document.getElementById('editPartnerAccountForm');
const editPartnerAccountParentSelect = document.getElementById('editPartnerAccountParent');
const editPartnerRowsContainer = document.getElementById('editPartnerRowsContainer');
const editAddPartnerRowBtn = document.getElementById('editAddPartnerRowBtn');
const editRemainingPercentageDisplay = document.getElementById('editRemainingPercentageDisplay');

// Convert to Partner Modal Elements
const closeConvertToPartnerModalBtn = document.getElementById('closeConvertToPartnerModalBtn');
const cancelConvertToPartnerBtn = document.getElementById('cancelConvertToPartnerBtn');
const saveConvertToPartnerBtn = document.getElementById('saveConvertToPartnerBtn');
const convertToPartnerAccountName = document.getElementById('convertToPartnerAccountName');
const convertPartnerRowsContainer = document.getElementById('convertPartnerRowsContainer');
const convertAddPartnerRowBtn = document.getElementById('convertAddPartnerRowBtn');
const convertRemainingPercentageDisplay = document.getElementById('convertRemainingPercentageDisplay');
let convertToPartnerAccountId = null;

let expandedNodes = new Set();
let showOnlyManageable = false;
let totalAccountsCount = 0;

// Fetch CoA data from API
async function fetchCoAData() {
    try {
        const response = await fetch('../../../server/api/chart_of_accounts/coa-add.php');
        const result = await response.json();
        
        if (result.success) {
            buildCoATree(result.data);
            renderCoATree();
        } else {
            alert('Failed to load Chart of Accounts: ' + result.message);
        }
    } catch (error) {
        console.error('Error fetching CoA data:', error);
        alert('Error loading Chart of Accounts');
    }
}

// Build CoA tree from API data
function buildCoATree(data) {
    const { accountHeads, subAccounts, accounts, accountPartners } = data;
    
    // Build account heads
    chartOfAccounts.children = accountHeads.map(head => ({
        id: head.id.toString(),
        name: head.name,
        type: 'head',
        status: head.tenant_id === 0 ? 'system-locked' : 'manageable',
        iconClass: iconClassMap[head.id] || 'assets',
        tenant_id: head.tenant_id,
        children: []
    }));
    
    // Build sub accounts hierarchy
    const subAccountMap = {};
    subAccounts.forEach(sub => {
        subAccountMap[sub.id] = {
            id: sub.id.toString(),
            name: sub.name,
            type: 'sub',
            status: sub.tenant_id === 0 ? 'system-locked' : 'manageable',
            iconClass: 'sub-account',
            account_head_id: sub.account_head_id,
            parent_id: sub.parent_id,
            tenant_id: sub.tenant_id,
            children: []
        };
    });
    
    // Attach sub accounts to heads or parent subs
    Object.values(subAccountMap).forEach(sub => {
        if (sub.parent_id) {
            if (subAccountMap[sub.parent_id]) {
                subAccountMap[sub.parent_id].children.push(sub);
            }
        } else {
            const head = chartOfAccounts.children.find(h => h.id === sub.account_head_id.toString());
            if (head) head.children.push(sub);
        }
    });

    // Group partner splits by account_id
    const partnersByAccount = {};
    (accountPartners || []).forEach(p => {
        if (!partnersByAccount[p.account_id]) partnersByAccount[p.account_id] = [];
        partnersByAccount[p.account_id].push({
            partner_name: p.partner_name,
            percentage: parseFloat(p.percentage)
        });
    });
    
    // Attach posting accounts and partner accounts
    accounts.forEach(acc => {
        const parentSub = subAccountMap[acc.sub_account_id];
        if (parentSub) {
            const isPartnerAccount = acc.is_partner_account == 1;

            const node = {
                id: acc.id.toString(),
                name: acc.name,
                type: isPartnerAccount ? 'partner' : 'posting',
                status: acc.tenant_id === 0 ? 'system-locked' : 'manageable',
                balanceType: acc.debit > 0 ? 'debit' : 'credit',
                balanceAmount: acc.debit > 0 ? parseFloat(acc.debit) : parseFloat(acc.credit),
                tenant_id: acc.tenant_id
            };

            if (isPartnerAccount) {
                node.partners = partnersByAccount[acc.id] || [];
            }

            parentSub.children.push(node);
        }
    });
    
    // Propagate system-locked status upwards
    function propagateSystemLocked(node) {
        if (!node.children || node.children.length === 0) {
            return node.tenant_id === 0;
        }
        
        let hasSystemLocked = node.tenant_id === 0;
        for (let child of node.children) {
            if (propagateSystemLocked(child)) {
                hasSystemLocked = true;
            }
        }
        
        if (hasSystemLocked) {
            node.status = 'system-locked';
        }
        
        return hasSystemLocked;
    }
    
    chartOfAccounts.children.forEach(head => propagateSystemLocked(head));
}

// Initialize the CoA tree
function renderCoATree() {
    coaTree.innerHTML = '';
    totalAccountsCount = 0;

    chartOfAccounts.children.forEach(account => {
        const node = createAccountNode(account, 0);
        coaTree.appendChild(node);
    });

    // Update total accounts count
    totalAccountsSpan.textContent = totalAccountsCount;
}

// Create an account node element
function createAccountNode(account, depth) {
    totalAccountsCount++;

    const node = document.createElement('div');
    node.className = 'account-node';
    node.dataset.id = account.id;
    node.dataset.type = account.type;

    const hasChildren = account.children && account.children.length > 0;
    const isExpanded = expandedNodes.has(account.id + '-' + depth);

    // Create header
    const header = document.createElement('div');
    header.className = `account-header ${account.status === 'system-locked' ? 'system-locked' : ''} ${isExpanded ? 'active' : ''}`;

    // Toggle icon
    if (hasChildren) {
        const toggleIcon = document.createElement('span');
        toggleIcon.className = `account-toggle ${isExpanded ? '' : 'collapsed'}`;
        toggleIcon.innerHTML = '<i class="fas fa-chevron-down"></i>';
        toggleIcon.addEventListener('click', (e) => {
            e.stopPropagation();
            toggleNode(account.id + '-' + depth, account, node);
        });
        header.appendChild(toggleIcon);
    } else {
        const spacer = document.createElement('span');
        spacer.style.width = '24px';
        header.appendChild(spacer);
    }

    // Account icon
    const icon = document.createElement('div');
    icon.className = `account-icon ${account.iconClass || 'assets'} ${account.type === 'partner' ? 'partner' : ''}`;

    // Set icon text based on account type
    if (account.type === 'head') {
        icon.textContent = account.name.charAt(0);
    } else if (account.type === 'sub') {
        icon.innerHTML = '<i class="fas fa-folder"></i>';
    } else if (account.type === 'posting') {
        icon.innerHTML = '<i class="fas fa-file-invoice"></i>';
    } else if (account.type === 'partner') {
        icon.innerHTML = '<i class="fas fa-handshake"></i>';
    }

    header.appendChild(icon);

    // Account info
    const info = document.createElement('div');
    info.className = 'account-info';

    const nameRow = document.createElement('div');
    nameRow.style.display = 'flex';
    nameRow.style.alignItems = 'center';

    const name = document.createElement('div');
    name.className = 'account-name';
    name.textContent = account.name;

    const typeBadge = document.createElement('span');
    typeBadge.className = 'account-type';
    typeBadge.textContent = account.type === 'head' ? 'Head' :
        account.type === 'sub' ? 'Sub Account' :
        account.type === 'partner' ? 'Partner Account' : 'Posting';

    nameRow.appendChild(name);
    nameRow.appendChild(typeBadge);

    const id = document.createElement('div');
    id.className = 'account-id';
    id.textContent = `ID: ${account.id}`;

    // Add balance for posting accounts
    if (account.type === 'posting') {
        id.innerHTML += ` | Balance: ${account.balanceAmount.toFixed(2)} (${account.balanceType})`;
    }

    // Add balance + partner split for partner accounts
    if (account.type === 'partner') {
        id.innerHTML += ` | Balance: ${account.balanceAmount.toFixed(2)} (${account.balanceType})`;

        const partners = account.partners || [];
        const partnerTotal = partners.reduce((sum, p) => sum + p.percentage, 0);
        const selfPercentage = 100 - partnerTotal;
        const partnerText = partners.map(p => `${p.partner_name}: ${p.percentage}%`).join(', ');

        id.innerHTML += `<br>Partners: ${partnerText}${partnerText ? ', ' : ''}Self/Owner: ${selfPercentage.toFixed(2)}%`;
    }

    info.appendChild(nameRow);
    info.appendChild(id);
    header.appendChild(info);

    // Account status
    const status = document.createElement('div');
    status.className = `account-status status-${account.status}`;
    status.textContent = account.status === 'system-locked' ? 'System Locked' : 'Manageable';
    header.appendChild(status);

    // Account actions (only for manageable accounts)
    if (account.status === 'manageable') {
        const actions = document.createElement('div');
        actions.className = 'account-actions';

        // Edit button (only for sub, posting, and partner accounts)
        if (account.type === 'sub' || account.type === 'posting' || account.type === 'partner') {
            const editBtn = document.createElement('button');
            editBtn.className = 'btn btn-secondary btn-sm';
            editBtn.innerHTML = '<i class="fas fa-edit"></i>';
            editBtn.title = 'Edit Account';
            editBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                editAccount(account);
            });
            actions.appendChild(editBtn);
        }

        // Delete button (only for sub, posting, and partner accounts)
        if (account.type === 'sub' || account.type === 'posting' || account.type === 'partner') {
            const deleteBtn = document.createElement('button');
            deleteBtn.className = 'btn btn-secondary btn-sm';
            deleteBtn.innerHTML = '<i class="fas fa-trash"></i>';
            deleteBtn.title = 'Delete Account';
            deleteBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                deleteAccount(account);
            });
            actions.appendChild(deleteBtn);
        }

        // Convert to Partner button (only for an EXISTING Posting Account) -
        // lets the user add partners later without deleting/recreating the account,
        // so its account_id and existing ledger history stay intact.
        if (account.type === 'posting') {
            const convertBtn = document.createElement('button');
            convertBtn.className = 'btn btn-secondary btn-sm';
            convertBtn.innerHTML = '<i class="fas fa-handshake"></i>';
            convertBtn.title = 'Add Partners to this Account';
            convertBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                openConvertToPartnerModal(account);
            });
            actions.appendChild(convertBtn);
        }

        header.appendChild(actions);
    }

    // Toggle children on header click
    if (hasChildren) {
        header.addEventListener('click', (e) => {
            if (!e.target.closest('.account-toggle') && !e.target.closest('.account-actions')) {
                toggleNode(account.id + '-' + depth, account, node);
            }
        });
    }

    node.appendChild(header);

    // Create children container
    if (hasChildren) {
        const childrenContainer = document.createElement('div');
        childrenContainer.className = `account-children ${isExpanded ? 'expanded' : ''}`;

        account.children.forEach(child => {
            // Filter out non-manageable accounts if filter is active
            if (showOnlyManageable && child.status !== 'manageable' &&
                (!child.children || child.children.length === 0)) {
                return;
            }

            const childNode = createAccountNode(child, depth + 1);
            childNode.classList.add('sub-account');
            childrenContainer.appendChild(childNode);
        });

        node.appendChild(childrenContainer);
    }

    // Hide non-manageable accounts if filter is active
    if (showOnlyManageable && account.status !== 'manageable' &&
        (!account.children || account.children.length === 0)) {
        node.style.display = 'none';
    } else {
        node.style.display = 'block';
    }

    return node;
}

// Toggle node expansion
function toggleNode(nodeId, account, nodeElement) {
    if (expandedNodes.has(nodeId)) {
        expandedNodes.delete(nodeId);
    } else {
        expandedNodes.add(nodeId);
    }

    renderCoATree();
}

// Expand all nodes
function expandAllNodes() {
    expandedNodes.clear();

    function collectIds(account, depth) {
        expandedNodes.add(account.id + '-' + depth);
        if (account.children) {
            account.children.forEach(child => {
                collectIds(child, depth + 1);
            });
        }
    }

    chartOfAccounts.children.forEach(account => {
        collectIds(account, 0);
    });

    renderCoATree();
}

// Collapse all nodes
function collapseAllNodes() {
    expandedNodes.clear();
    renderCoATree();
}

// Populate parent account select for Sub Accounts
function populateSubAccountParents() {
    subAccountParentSelect.innerHTML = '<option value="">Select parent account</option>';

    function addOptions(account, prefix = '') {
        // Only allow Account Heads and Sub Accounts as parents for Sub Accounts
        if (account.type === 'head' || account.type === 'sub') {
            const option = document.createElement('option');
            option.value = account.id;

            let typeText = '';
            if (account.type === 'head') typeText = ' [Head]';
            else if (account.type === 'sub') typeText = ' [Sub]';

            option.textContent = `${prefix}${account.name}${typeText} (ID: ${account.id})`;
            subAccountParentSelect.appendChild(option);
        }

        if (account.children) {
            account.children.forEach(child => {
                addOptions(child, prefix + '-- ');
            });
        }
    }

    chartOfAccounts.children.forEach(account => {
        addOptions(account);
    });
}

// Populate parent account select for Edit Posting Accounts
function populateEditPostingAccountParents() {
    editPostingAccountParentSelect.innerHTML = '<option value="">Select parent sub account</option>';

    function addOptions(account, prefix = '') {
        if (account.type === 'sub') {
            const option = document.createElement('option');
            option.value = account.id;
            option.textContent = `${prefix}${account.name} [Sub] (ID: ${account.id})`;
            editPostingAccountParentSelect.appendChild(option);
        }

        if (account.children) {
            account.children.forEach(child => {
                addOptions(child, prefix + '-- ');
            });
        }
    }

    chartOfAccounts.children.forEach(account => {
        addOptions(account);
    });
}

// Populate parent account select for Posting Accounts
function populatePostingAccountParents() {
    postingAccountParentSelect.innerHTML = '<option value="">Select parent sub account</option>';

    function addOptions(account, prefix = '') {
        // Only allow Sub Accounts as parents for Posting Accounts (NOT Account Heads)
        if (account.type === 'sub') {
            const option = document.createElement('option');
            option.value = account.id;
            option.textContent = `${prefix}${account.name} [Sub] (ID: ${account.id})`;
            postingAccountParentSelect.appendChild(option);
        }

        if (account.children) {
            account.children.forEach(child => {
                addOptions(child, prefix + '-- ');
            });
        }
    }

    chartOfAccounts.children.forEach(account => {
        addOptions(account);
    });

    // If no sub accounts exist, disable the select
    if (postingAccountParentSelect.options.length <= 1) {
        postingAccountParentSelect.disabled = true;
        postingAccountParentSelect.innerHTML = '<option value="">No Sub Accounts available. Create a Sub Account first.</option>';
    } else {
        postingAccountParentSelect.disabled = false;
    }
}

// Populate parent account select for Partner Accounts
function populatePartnerAccountParents() {
    partnerAccountParentSelect.innerHTML = '<option value="">Select parent sub account</option>';

    function addOptions(account, prefix = '') {
        // Only allow Sub Accounts as parents for Partner Accounts (NOT Account Heads)
        if (account.type === 'sub') {
            const option = document.createElement('option');
            option.value = account.id;
            option.textContent = `${prefix}${account.name} [Sub] (ID: ${account.id})`;
            partnerAccountParentSelect.appendChild(option);
        }

        if (account.children) {
            account.children.forEach(child => {
                addOptions(child, prefix + '-- ');
            });
        }
    }

    chartOfAccounts.children.forEach(account => {
        addOptions(account);
    });

    // If no sub accounts exist, disable the select
    if (partnerAccountParentSelect.options.length <= 1) {
        partnerAccountParentSelect.disabled = true;
        partnerAccountParentSelect.innerHTML = '<option value="">No Sub Accounts available. Create a Sub Account first.</option>';
    } else {
        partnerAccountParentSelect.disabled = false;
    }
}

// Populate parent account select for Edit Partner Accounts
function populateEditPartnerAccountParents() {
    editPartnerAccountParentSelect.innerHTML = '<option value="">Select parent sub account</option>';

    function addOptions(account, prefix = '') {
        if (account.type === 'sub') {
            const option = document.createElement('option');
            option.value = account.id;
            option.textContent = `${prefix}${account.name} [Sub] (ID: ${account.id})`;
            editPartnerAccountParentSelect.appendChild(option);
        }

        if (account.children) {
            account.children.forEach(child => {
                addOptions(child, prefix + '-- ');
            });
        }
    }

    chartOfAccounts.children.forEach(account => {
        addOptions(account);
    });
}

// ---- Partner rows (dynamic Add Partner UI) helpers ----

function getPartnerRowsData(containerEl) {
    const rows = containerEl.querySelectorAll('.partner-row');
    const partners = [];

    rows.forEach(row => {
        const name = row.querySelector('.partner-name-input').value.trim();
        const percentage = parseFloat(row.querySelector('.partner-percentage-input').value);
        if (name && !isNaN(percentage) && percentage > 0) {
            partners.push({ name, percentage });
        }
    });

    return partners;
}

function calculateRemainingPercentage(containerEl) {
    const partners = getPartnerRowsData(containerEl);
    const total = partners.reduce((sum, p) => sum + p.percentage, 0);
    return { total, remaining: 100 - total };
}

function updateRemainingDisplay(containerEl, displayEl) {
    const { total, remaining } = calculateRemainingPercentage(containerEl);

    if (remaining < 0) {
        displayEl.textContent = `Total exceeds 100% by ${Math.abs(remaining).toFixed(2)}%`;
        displayEl.style.color = '#c0392b';
    } else {
        displayEl.textContent = `Remaining (Self / Owner): ${remaining.toFixed(2)}%`;
        displayEl.style.color = '';
    }
}

function createPartnerRow(name = '', percentage = '') {
    const row = document.createElement('div');
    row.className = 'partner-row';
    row.style.display = 'flex';
    row.style.gap = '10px';
    row.style.marginBottom = '8px';
    row.style.alignItems = 'center';

    row.innerHTML = `
        <input type="text" class="form-input partner-name-input" placeholder="Partner name" value="${name}" style="flex: 2;">
        <input type="number" class="form-input partner-percentage-input" placeholder="%" min="0" max="100" step="0.01" value="${percentage}" style="flex: 1;">
        <button type="button" class="btn btn-secondary btn-sm remove-partner-row-btn"><i class="fas fa-times"></i></button>
    `;

    return row;
}

function addPartnerRow(containerEl, displayEl, name = '', percentage = '') {
    const row = createPartnerRow(name, percentage);

    row.querySelector('.remove-partner-row-btn').addEventListener('click', () => {
        row.remove();
        updateRemainingDisplay(containerEl, displayEl);
    });

    row.querySelector('.partner-name-input').addEventListener('input', () => updateRemainingDisplay(containerEl, displayEl));
    row.querySelector('.partner-percentage-input').addEventListener('input', () => updateRemainingDisplay(containerEl, displayEl));

    containerEl.appendChild(row);
    updateRemainingDisplay(containerEl, displayEl);
}

// Add new Sub Account
async function addNewSubAccount() {
    const parentId = subAccountParentSelect.value;
    const accountName = document.getElementById('subAccountName').value;

    if (!parentId || !accountName) {
        alert('Please fill all fields correctly.');
        return;
    }

    // Find parent to determine account_head_id
    let account_head_id = null;
    let parent_id = null;
    
    function findParent(account) {
        if (account.id === parentId) {
            if (account.type === 'head') {
                account_head_id = account.id;
                parent_id = null;
            } else if (account.type === 'sub') {
                account_head_id = account.account_head_id;
                parent_id = account.id;
            }
            return true;
        }
        if (account.children) {
            for (let child of account.children) {
                if (findParent(child)) return true;
            }
        }
        return false;
    }
    
    chartOfAccounts.children.forEach(acc => findParent(acc));

    try {
        const response = await fetch('../../../server/api/chart_of_accounts/coa-add.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                type: 'sub',
                account_head_id: account_head_id,
                name: accountName,
                parent_id: parent_id
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            subAccountModal.classList.remove('active');
            subAccountForm.reset();
            expandedNodes.add(parentId + '-' + getDepth(parentId));
            await fetchCoAData();
            populatePostingAccountParents();
            populatePartnerAccountParents();
            alert('Sub Account added successfully!');
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        console.error('Error adding sub account:', error);
        alert('Error adding sub account');
    }
}

// Add new Posting Account
async function addNewPostingAccount() {
    const parentId = postingAccountParentSelect.value;
    const accountName = document.getElementById('postingAccountName').value;
    const balanceType = document.querySelector('input[name="balanceType"]:checked').value;
    const balanceAmount = parseFloat(document.getElementById('balanceAmount').value);

    if (!parentId || !accountName || isNaN(balanceAmount)) {
        alert('Please fill all fields correctly.');
        return;
    }

    try {
        const response = await fetch('../../../server/api/chart_of_accounts/coa-add.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                type: 'account',
                sub_account_id: parentId,
                name: accountName,
                debit: balanceType === 'debit' ? balanceAmount : 0,
                credit: balanceType === 'credit' ? balanceAmount : 0
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            postingAccountModal.classList.remove('active');
            postingAccountForm.reset();
            expandedNodes.add(parentId + '-' + getDepth(parentId));
            await fetchCoAData();
            alert('Posting Account added successfully!');
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        console.error('Error adding posting account:', error);
        alert('Error adding posting account');
    }
}

// Add new Partner Account
async function addNewPartnerAccount() {
    const parentId = partnerAccountParentSelect.value;
    const accountName = document.getElementById('partnerAccountName').value;
    const balanceType = document.querySelector('input[name="partnerBalanceType"]:checked').value;
    const balanceAmount = parseFloat(document.getElementById('partnerBalanceAmount').value);
    const partners = getPartnerRowsData(partnerRowsContainer);

    if (!parentId || !accountName || isNaN(balanceAmount)) {
        alert('Please fill all fields correctly.');
        return;
    }

    const { total } = calculateRemainingPercentage(partnerRowsContainer);
    if (total > 100) {
        alert('Total partner percentage cannot exceed 100%.');
        return;
    }

    try {
        const response = await fetch('../../../server/api/chart_of_accounts/coa-add.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                type: 'partner',
                sub_account_id: parentId,
                name: accountName,
                debit: balanceType === 'debit' ? balanceAmount : 0,
                credit: balanceType === 'credit' ? balanceAmount : 0,
                partners: partners
            })
        });

        const result = await response.json();

        if (result.success) {
            partnerAccountModal.classList.remove('active');
            partnerAccountForm.reset();
            partnerRowsContainer.innerHTML = '';
            expandedNodes.add(parentId + '-' + getDepth(parentId));
            await fetchCoAData();
            alert('Partner Account added successfully!');
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        console.error('Error adding partner account:', error);
        alert('Error adding partner account');
    }
}

// Edit account
function editAccount(account) {
    if (account.type === 'sub') {
        populateSubAccountParents();
        document.getElementById('editSubAccountId').value = account.id;
        document.getElementById('editSubAccountName').value = account.name;
        
        // Set parent
        if (account.parent_id) {
            editSubAccountParentSelect.value = account.parent_id;
        } else {
            editSubAccountParentSelect.value = account.account_head_id;
        }
        
        editSubAccountModal.classList.add('active');
    } else if (account.type === 'posting') {
        populateEditPostingAccountParents();
        document.getElementById('editPostingAccountId').value = account.id;
        document.getElementById('editPostingAccountName').value = account.name;
        
        // Find parent sub account
        let parentSubId = null;
        function findParentSub(acc) {
            if (acc.children) {
                for (let child of acc.children) {
                    if (child.id === account.id) {
                        parentSubId = acc.id;
                        return true;
                    }
                    if (findParentSub(child)) return true;
                }
            }
            return false;
        }
        chartOfAccounts.children.forEach(acc => findParentSub(acc));
        editPostingAccountParentSelect.value = parentSubId;
        
        editPostingAccountModal.classList.add('active');
    } else if (account.type === 'partner') {
        populateEditPartnerAccountParents();
        document.getElementById('editPartnerAccountId').value = account.id;
        document.getElementById('editPartnerAccountName').value = account.name;

        // Find parent sub account
        let parentSubId = null;
        function findParentSub(acc) {
            if (acc.children) {
                for (let child of acc.children) {
                    if (child.id === account.id) {
                        parentSubId = acc.id;
                        return true;
                    }
                    if (findParentSub(child)) return true;
                }
            }
            return false;
        }
        chartOfAccounts.children.forEach(acc => findParentSub(acc));
        editPartnerAccountParentSelect.value = parentSubId;

        // Pre-fill existing partner rows
        editPartnerRowsContainer.innerHTML = '';
        (account.partners || []).forEach(p => {
            addPartnerRow(editPartnerRowsContainer, editRemainingPercentageDisplay, p.partner_name, p.percentage);
        });
        updateRemainingDisplay(editPartnerRowsContainer, editRemainingPercentageDisplay);

        editPartnerAccountModal.classList.add('active');
    }
}

// Update Sub Account
async function updateSubAccount() {
    const id = document.getElementById('editSubAccountId').value;
    const parentId = editSubAccountParentSelect.value;
    const accountName = document.getElementById('editSubAccountName').value;

    if (!parentId || !accountName) {
        alert('Please fill all fields correctly.');
        return;
    }

    let account_head_id = null;
    let parent_id = null;
    
    function findParent(account) {
        if (account.id === parentId) {
            if (account.type === 'head') {
                account_head_id = account.id;
                parent_id = null;
            } else if (account.type === 'sub') {
                account_head_id = account.account_head_id;
                parent_id = account.id;
            }
            return true;
        }
        if (account.children) {
            for (let child of account.children) {
                if (findParent(child)) return true;
            }
        }
        return false;
    }
    
    chartOfAccounts.children.forEach(acc => findParent(acc));

    try {
        const response = await fetch('../../../server/api/chart_of_accounts/coa-add.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                type: 'sub',
                id: id,
                account_head_id: account_head_id,
                name: accountName,
                parent_id: parent_id
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            editSubAccountModal.classList.remove('active');
            editSubAccountForm.reset();
            await fetchCoAData();
            alert('Sub Account updated successfully!');
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        console.error('Error updating sub account:', error);
        alert('Error updating sub account');
    }
}

// Update Posting Account
async function updatePostingAccount() {
    const id = document.getElementById('editPostingAccountId').value;
    const parentId = editPostingAccountParentSelect.value;
    const accountName = document.getElementById('editPostingAccountName').value;

    if (!parentId || !accountName) {
        alert('Please fill all fields correctly.');
        return;
    }

    try {
        const response = await fetch('../../../server/api/chart_of_accounts/coa-add.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                type: 'account',
                id: id,
                sub_account_id: parentId,
                name: accountName,
                debit: 0,
                credit: 0
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            editPostingAccountModal.classList.remove('active');
            editPostingAccountForm.reset();
            await fetchCoAData();
            alert('Posting Account updated successfully!');
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        console.error('Error updating posting account:', error);
        alert('Error updating posting account');
    }
}

// Update Partner Account
async function updatePartnerAccount() {
    const id = document.getElementById('editPartnerAccountId').value;
    const parentId = editPartnerAccountParentSelect.value;
    const accountName = document.getElementById('editPartnerAccountName').value;
    const partners = getPartnerRowsData(editPartnerRowsContainer);

    if (!parentId || !accountName) {
        alert('Please fill all fields correctly.');
        return;
    }

    const { total } = calculateRemainingPercentage(editPartnerRowsContainer);
    if (total > 100) {
        alert('Total partner percentage cannot exceed 100%.');
        return;
    }

    try {
        const response = await fetch('../../../server/api/chart_of_accounts/coa-add.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                type: 'partner',
                id: id,
                sub_account_id: parentId,
                name: accountName,
                debit: 0,
                credit: 0,
                partners: partners
            })
        });

        const result = await response.json();

        if (result.success) {
            editPartnerAccountModal.classList.remove('active');
            editPartnerAccountForm.reset();
            editPartnerRowsContainer.innerHTML = '';
            await fetchCoAData();
            alert('Partner Account updated successfully!');
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        console.error('Error updating partner account:', error);
        alert('Error updating partner account');
    }
}

// Open the Convert to Partner modal for an existing Posting Account
function openConvertToPartnerModal(account) {
    convertToPartnerAccountId = account.id;
    convertToPartnerAccountName.textContent = `${account.name} (ID: ${account.id})`;

    convertPartnerRowsContainer.innerHTML = '';
    addPartnerRow(convertPartnerRowsContainer, convertRemainingPercentageDisplay);

    convertToPartnerModal.classList.add('active');
}

// Convert an existing Posting Account to a Partner Account (same account_id, name, balance)
async function saveConvertToPartner() {
    const partners = getPartnerRowsData(convertPartnerRowsContainer);

    if (partners.length === 0) {
        alert('Please add at least one partner.');
        return;
    }

    const { total } = calculateRemainingPercentage(convertPartnerRowsContainer);
    if (total > 100) {
        alert('Total partner percentage cannot exceed 100%.');
        return;
    }

    try {
        const response = await fetch('../../../server/api/chart_of_accounts/coa-add.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                type: 'convert_to_partner',
                id: convertToPartnerAccountId,
                partners: partners
            })
        });

        const result = await response.json();

        if (result.success) {
            convertToPartnerModal.classList.remove('active');
            convertPartnerRowsContainer.innerHTML = '';
            await fetchCoAData();
            alert('Account converted to Partner Account successfully! Its ID and history stayed the same.');
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        console.error('Error converting account to partner:', error);
        alert('Error converting account to partner');
    }
}

// Delete account
async function deleteAccount(account) {
    if (!confirm(`Are you sure you want to delete "${account.name}"? This action cannot be undone.`)) {
        return;
    }

    try {
        const response = await fetch('../../../server/api/chart_of_accounts/coa-add.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                type: account.type === 'sub' ? 'sub' : 'account',
                id: account.id
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            await fetchCoAData();
            alert('Account deleted successfully!');
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        console.error('Error deleting account:', error);
        alert('Error deleting account');
    }
}

// Get depth of an account by ID
function getDepth(accountId, account = null, currentDepth = 0) {
    if (!account) {
        for (let acc of chartOfAccounts.children) {
            const depth = getDepth(accountId, acc, 0);
            if (depth !== -1) return depth;
        }
        return -1;
    }

    if (account.id === accountId) {
        return currentDepth;
    }

    if (account.children) {
        for (let child of account.children) {
            const depth = getDepth(accountId, child, currentDepth + 1);
            if (depth !== -1) return depth;
        }
    }

    return -1;
}

// Event Listeners
expandAllBtn.addEventListener('click', expandAllNodes);
collapseAllBtn.addEventListener('click', collapseAllNodes);

addSubAccountBtn.addEventListener('click', () => {
    populateSubAccountParents();
    subAccountModal.classList.add('active');
});

addPostingAccountBtn.addEventListener('click', () => {
    populatePostingAccountParents();
    postingAccountModal.classList.add('active');
});

addPartnerAccountBtn.addEventListener('click', () => {
    populatePartnerAccountParents();
    partnerRowsContainer.innerHTML = '';
    addPartnerRow(partnerRowsContainer, remainingPercentageDisplay);
    partnerAccountModal.classList.add('active');
});

showManageableBtn.addEventListener('click', () => {
    showOnlyManageable = true;
    showManageableBtn.classList.add('btn-primary');
    showManageableBtn.classList.remove('btn-secondary');
    showAllBtn.classList.remove('btn-primary');
    showAllBtn.classList.add('btn-secondary');
    renderCoATree();
});

showAllBtn.addEventListener('click', () => {
    showOnlyManageable = false;
    showAllBtn.classList.add('btn-primary');
    showAllBtn.classList.remove('btn-secondary');
    showManageableBtn.classList.remove('btn-primary');
    showManageableBtn.classList.add('btn-secondary');
    renderCoATree();
});

// Sub Account Modal Event Listeners
closeSubAccountModalBtn.addEventListener('click', () => {
    subAccountModal.classList.remove('active');
});

cancelSubAccountBtn.addEventListener('click', () => {
    subAccountModal.classList.remove('active');
});

saveSubAccountBtn.addEventListener('click', addNewSubAccount);

// Posting Account Modal Event Listeners
closePostingAccountModalBtn.addEventListener('click', () => {
    postingAccountModal.classList.remove('active');
});

cancelPostingAccountBtn.addEventListener('click', () => {
    postingAccountModal.classList.remove('active');
});

savePostingAccountBtn.addEventListener('click', addNewPostingAccount);

// Partner Account Modal Event Listeners
closePartnerAccountModalBtn.addEventListener('click', () => {
    partnerAccountModal.classList.remove('active');
});

cancelPartnerAccountBtn.addEventListener('click', () => {
    partnerAccountModal.classList.remove('active');
});

savePartnerAccountBtn.addEventListener('click', addNewPartnerAccount);

addPartnerRowBtn.addEventListener('click', () => {
    addPartnerRow(partnerRowsContainer, remainingPercentageDisplay);
});

editAddPartnerRowBtn.addEventListener('click', () => {
    addPartnerRow(editPartnerRowsContainer, editRemainingPercentageDisplay);
});

// Edit Sub Account Modal Event Listeners
closeEditSubAccountModalBtn.addEventListener('click', () => {
    editSubAccountModal.classList.remove('active');
});

cancelEditSubAccountBtn.addEventListener('click', () => {
    editSubAccountModal.classList.remove('active');
});

updateSubAccountBtn.addEventListener('click', updateSubAccount);

// Edit Posting Account Modal Event Listeners
closeEditPostingAccountModalBtn.addEventListener('click', () => {
    editPostingAccountModal.classList.remove('active');
});

cancelEditPostingAccountBtn.addEventListener('click', () => {
    editPostingAccountModal.classList.remove('active');
});

updatePostingAccountBtn.addEventListener('click', updatePostingAccount);

// Edit Partner Account Modal Event Listeners
closeEditPartnerAccountModalBtn.addEventListener('click', () => {
    editPartnerAccountModal.classList.remove('active');
});

cancelEditPartnerAccountBtn.addEventListener('click', () => {
    editPartnerAccountModal.classList.remove('active');
});

updatePartnerAccountBtn.addEventListener('click', updatePartnerAccount);

// Close modals when clicking outside
subAccountModal.addEventListener('click', (e) => {
    if (e.target === subAccountModal) {
        subAccountModal.classList.remove('active');
    }
});

postingAccountModal.addEventListener('click', (e) => {
    if (e.target === postingAccountModal) {
        postingAccountModal.classList.remove('active');
    }
});

partnerAccountModal.addEventListener('click', (e) => {
    if (e.target === partnerAccountModal) {
        partnerAccountModal.classList.remove('active');
    }
});

editSubAccountModal.addEventListener('click', (e) => {
    if (e.target === editSubAccountModal) {
        editSubAccountModal.classList.remove('active');
    }
});

editPostingAccountModal.addEventListener('click', (e) => {
    if (e.target === editPostingAccountModal) {
        editPostingAccountModal.classList.remove('active');
    }
});

editPartnerAccountModal.addEventListener('click', (e) => {
    if (e.target === editPartnerAccountModal) {
        editPartnerAccountModal.classList.remove('active');
    }
});

// Convert to Partner Modal Event Listeners
closeConvertToPartnerModalBtn.addEventListener('click', () => {
    convertToPartnerModal.classList.remove('active');
});

cancelConvertToPartnerBtn.addEventListener('click', () => {
    convertToPartnerModal.classList.remove('active');
});

saveConvertToPartnerBtn.addEventListener('click', saveConvertToPartner);

convertAddPartnerRowBtn.addEventListener('click', () => {
    addPartnerRow(convertPartnerRowsContainer, convertRemainingPercentageDisplay);
});

convertToPartnerModal.addEventListener('click', (e) => {
    if (e.target === convertToPartnerModal) {
        convertToPartnerModal.classList.remove('active');
    }
});

// Initialize
fetchCoAData().then(() => {
    populateSubAccountParents();
    populatePostingAccountParents();
    populatePartnerAccountParents();
    // Expand first level by default
    chartOfAccounts.children.forEach(account => {
        expandedNodes.add(account.id + '-0');
    });
    renderCoATree();
});