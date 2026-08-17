(function () {
    const LIST_API = '../../../../server/api/manufacturing/wastage_entry/list.php';

    let allEntries = [];

    async function loadEntries() {
        const res = await fetch(`${LIST_API}?action=list`);
        const data = await res.json();
        if (data.success) {
            allEntries = data.data;
            filterData();
        }
    }

    function filterData() {
        const search = document.getElementById('searchInput').value.toLowerCase();
        const type = document.getElementById('typeFilter').value;

        const filtered = allEntries.filter(e => {
            const matchSearch = !search
                || e.wastage_no.toLowerCase().includes(search)
                || e.order_no.toLowerCase().includes(search)
                || e.product_name.toLowerCase().includes(search);
            const matchType = type === 'all' || e.wastage_type === type;
            return matchSearch && matchType;
        });

        renderTable(filtered);
    }

    function renderTable(entries) {
        const tbody = document.getElementById('tableBody');

        if (entries.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:32px; color:#6B7280;">No wastage entries found</td></tr>';
            return;
        }

        tbody.innerHTML = entries.map(e => `
            <tr>
                <td><strong>${e.wastage_no}</strong></td>
                <td>${e.order_no}</td>
                <td>${e.product_name}</td>
                <td>${formatDate(e.wastage_date)}</td>
                <td>
                    <span class="badge ${e.wastage_type === 'percentage' ? 'type-pct' : 'type-qty'}">
                        ${e.wastage_type === 'percentage' ? 'Percentage' : 'Quantity'}
                    </span>
                </td>
                <td style="color:#6B7280; font-size:13px;">${e.remarks || '—'}</td>
                <td>
                    <div class="action-btns">
                        <button class="btn-icon btn-view" onclick="viewEntry(${e.id})" title="View">
                            <i class="las la-eye"></i>
                        </button>
                        <button class="btn-icon btn-delete" onclick="openDeleteModal(${e.id}, '${e.wastage_no}')" title="Delete">
                            <i class="las la-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    function formatDate(dateStr) {
        if (!dateStr) return '—';
        const d = new Date(dateStr);
        return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    }

    window.viewEntry = function (id) {
        window.location.href = `view.php?id=${id}`;
    };

    window.openDeleteModal = function (id, wastageNo) {
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteWastageNo').textContent = wastageNo;
        document.getElementById('deleteModal').style.display = 'flex';
    };

    window.closeDeleteModal = function () {
        document.getElementById('deleteModal').style.display = 'none';
    };

    window.confirmDelete = async function () {
        const id = document.getElementById('deleteId').value;
        try {
            const res = await fetch(`${LIST_API}?id=${id}`, { method: 'DELETE' });
            const data = await res.json();
            if (data.success) {
                closeDeleteModal();
                showSuccess('Wastage entry deleted successfully');
                allEntries = allEntries.filter(e => e.id != id);
                filterData();
            } else {
                alert('Error: ' + data.message);
            }
        } catch (err) {
            alert('Error deleting entry');
        }
    };

    function showSuccess(msg) {
        const el = document.getElementById('successMessage');
        document.getElementById('successText').textContent = msg;
        el.style.display = 'flex';
        setTimeout(() => { el.style.display = 'none'; }, 3000);
    }

    function checkSuccessParam() {
        if (new URLSearchParams(window.location.search).get('success') === '1') {
            showSuccess('Wastage entry saved successfully');
            history.replaceState(null, '', 'list.php');
        }
    }

    document.getElementById('searchInput').addEventListener('input', filterData);
    document.getElementById('typeFilter').addEventListener('change', filterData);
    document.getElementById('resetFiltersBtn').addEventListener('click', () => {
        document.getElementById('searchInput').value = '';
        document.getElementById('typeFilter').value = 'all';
        filterData();
    });

    document.getElementById('deleteModal').addEventListener('click', function (e) {
        if (e.target === this) closeDeleteModal();
    });

    checkSuccessParam();
    loadEntries();
})();
