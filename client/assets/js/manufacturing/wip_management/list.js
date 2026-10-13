(function() {
    const API_URL = '../../../../server/api/manufacturing/wip_management/index.php';

    async function loadWIPList() {
        try {
            const res = await fetch(API_URL + '?action=wip_list');
            const data = await res.json();
            
            if (data.success) {
                renderWIPList(data.data);
            }
        } catch (err) {
            alert('Error loading WIP list');
        }
    }

    function renderWIPList(list) {
        const tbody = document.getElementById('wipListTable');
        
        if (!list || list.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:32px; color:#6B7280;">No records found</td></tr>';
            return;
        }
        
        tbody.innerHTML = '';
        list.forEach(wip => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td><strong>${wip.wip_number}</strong></td>
                <td>${wip.order_no}</td>
                <td>${wip.branch_name}</td>
                <td>${wip.issue_date}</td>
                <td>${wip.total_items}</td>
                <td>${parseFloat(wip.total_cost).toFixed(2)}</td>
                <td>
                    <a href="edit.php?id=${wip.id}&mode=view" class="btn btn-secondary" style="padding:6px 12px; font-size:12px;">
                        <i class="las la-eye"></i> View
                    </a>
                    <a href="edit.php?id=${wip.id}" class="btn btn-secondary" style="padding:6px 12px; font-size:12px;">
                        <i class="las la-edit"></i> Edit
                    </a>
                    <a href="print.php?id=${wip.id}" target="_blank" class="btn btn-secondary" style="padding:6px 12px; font-size:12px;">
                        <i class="las la-print"></i> Print
                    </a>
                    <button class="btn btn-danger" style="padding:6px 12px; font-size:12px;" onclick="deleteWIP(${wip.id})">
                        <i class="las la-trash"></i> Delete
                    </button>
                </td>
            `;
            tbody.appendChild(row);
        });
    }

    window.deleteWIP = async function(wipId) {
        if (confirm('Are you sure you want to delete this WIP? This will reverse all stock entries.')) {
            try {
                const res = await fetch(API_URL + '?action=delete_wip', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ wip_id: wipId })
                });
                const data = await res.json();
                
                if (data.success) {
                    alert('WIP deleted successfully!');
                    loadWIPList();
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (err) {
                alert('Error deleting WIP');
            }
        }
    };

    loadWIPList();
})();
