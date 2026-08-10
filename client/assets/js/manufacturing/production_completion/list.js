(function() {
    const API_URL = BASE_URL + '/server/api/manufacturing/production_completion/list.php';

    async function loadCompletions() {
        const search = document.getElementById('searchInput').value;
        const dateFrom = document.getElementById('dateFrom').value;
        const dateTo = document.getElementById('dateTo').value;

        let url = API_URL + '?action=list';
        if (search) url += `&search=${search}`;
        if (dateFrom) url += `&date_from=${dateFrom}`;
        if (dateTo) url += `&date_to=${dateTo}`;

        try {
            const res = await fetch(url);
            const data = await res.json();

            if (data.success) {
                renderTable(data.data);
            }
        } catch (err) {
            alert('Error loading completions');
        }
    }

    function renderTable(completions) {
        const tbody = document.getElementById('completionsTable');
        
        if (!completions || completions.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center; padding:32px; color:#6B7280;">No completions found</td></tr>';
            return;
        }

        tbody.innerHTML = '';
        completions.forEach(c => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td><strong>${c.completion_no}</strong></td>
                <td>${c.order_no}</td>
                <td>${c.complete_date}</td>
                <td>${c.total_products}</td>
                <td>${c.total_quantity}</td>
                <td><strong>${parseFloat(c.total_cost).toFixed(2)}</strong></td>
                <td>
                    <button class="btn btn-info btn-sm" onclick="viewCompletion(${c.id})">
                        <i class="las la-eye"></i>
                    </button>
                    <button class="btn btn-secondary btn-sm" onclick="editCompletion(${c.id})">
                        <i class="las la-edit"></i>
                    </button>
                    <button class="btn btn-danger btn-sm" onclick="deleteCompletion(${c.id})">
                        <i class="las la-trash"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(row);
        });
    }

    window.viewCompletion = function(id) {
        window.location.href = `view.php?id=${id}`;
    };

    window.editCompletion = function(id) {
        window.location.href = `index.php?id=${id}`;
    };

    window.deleteCompletion = async function(id) {
        if (!confirm('Are you sure? This will reverse all stock entries.')) {
            return;
        }

        try {
            const res = await fetch(API_URL + `?action=delete&id=${id}`, {
                method: 'DELETE'
            });
            const data = await res.json();

            if (data.success) {
                alert('Completion deleted successfully');
                loadCompletions();
            } else {
                alert('Error: ' + data.message);
            }
        } catch (err) {
            alert('Error deleting completion');
        }
    };

    document.getElementById('searchInput').addEventListener('input', loadCompletions);
    document.getElementById('dateFrom').addEventListener('change', loadCompletions);
    document.getElementById('dateTo').addEventListener('change', loadCompletions);
    
    document.getElementById('clearBtn').addEventListener('click', () => {
        document.getElementById('searchInput').value = '';
        document.getElementById('dateFrom').value = '';
        document.getElementById('dateTo').value = '';
        loadCompletions();
    });

    loadCompletions();
})();
