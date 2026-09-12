// ========== Activity & Audit Center ==========
// Unifies Login History / Failed Logins / Login Locations / Audit Trail / Reports
// (previously 4 scattered buttons+modals in panel.php) into one page with tabs.
// Audit Trail is the primary tab; Login History/Failed/Locations share one combined
// "Login Activity" tab instead of three separate ones.

const AC_API_BASE = '../../../../server/api/system_setup/admin_panel/';

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

// activity_logs.created_at / login_attempts.attempted_at come from MySQL already in
// Asia/Karachi wall-clock time (the DB server's system timezone), as "YYYY-MM-DD HH:MM:SS" -
// reformat that string directly to 12-hour AM/PM WITHOUT going through `new Date()`, since
// parsing a naive datetime string that way applies the browser's own local timezone and would
// shift the displayed time incorrectly.
function formatLogDateTime(dateTimeStr) {
    if (!dateTimeStr) return '-';
    const match = String(dateTimeStr).match(/^(\d{4}-\d{2}-\d{2})[ T](\d{2}):(\d{2}):(\d{2})/);
    if (!match) return dateTimeStr;
    const [, datePart, hStr, m, s] = match;
    let h = parseInt(hStr, 10);
    const ampm = h >= 12 ? 'PM' : 'AM';
    h = h % 12 || 12;
    return `${datePart} ${String(h).padStart(2, '0')}:${m}:${s} ${ampm}`;
}

// ---------- Searchable module dropdown ----------
// Same visual/interaction pattern as the Customer Code dropdown on
// client/pages/sale/pos_invoice/pos-add.php (.searchable-dropdown / .search-input /
// .dropdown-options / .dropdown-option), used here in place of a native <select> or
// <input list=datalist> since the module list runs to 60+ entries.
let acAuditModulesCache = [];
let acDeletedModulesCache = [];

function acInitModuleDropdown(inputId, optionsId, getModules, onSelect) {
    const input = document.getElementById(inputId);
    const optionsContainer = document.getElementById(optionsId);
    let selectedIndex = -1;
    // True right after opening (before the user types anything) - shows the full,
    // unfiltered list to browse, ignoring whatever module is already applied. This
    // avoids reopening a dropdown that already has a value pre-filtered down to just
    // that one match, and (unlike a clear-on-open + restore-on-blur approach) never
    // fights with the separate "Search" button over what the field's current text means.
    let browseAll = true;

    function render() {
        const term = browseAll ? '' : input.value.trim().toLowerCase();
        const modules = getModules();
        let html = '';
        if (''.includes(term)) {
            html += `<div class="dropdown-option ac-option-all" data-value="">All Modules</div>`;
        }
        html += modules
            .filter(m => m.toLowerCase().includes(term))
            .map(m => `<div class="dropdown-option" data-value="${escapeHtml(m)}">${escapeHtml(m)}</div>`)
            .join('');
        optionsContainer.innerHTML = html;
        selectedIndex = -1;
    }

    function openList() {
        browseAll = true;
        optionsContainer.style.display = 'block';
        render();
    }

    function closeList() {
        optionsContainer.style.display = 'none';
    }

    function highlight(opts) {
        opts.forEach((opt, i) => opt.classList.toggle('selected', i === selectedIndex));
        if (selectedIndex >= 0 && opts[selectedIndex]) opts[selectedIndex].scrollIntoView({ block: 'nearest' });
    }

    input.addEventListener('click', (e) => { e.stopPropagation(); openList(); });
    input.addEventListener('focus', openList);
    // Typing only re-filters the visible option list (a client-side render, no network
    // call) - the module filter is an exact match server-side, so firing a fetch on every
    // keystroke would just flicker "No results" until the full name was typed. The actual
    // filter is applied on a real selection (click/Enter) or the row's own Search button.
    input.addEventListener('input', () => { browseAll = false; render(); });
    // Closes on Tab-away (a plain blur has no accompanying click event, so the
    // document-level click-to-close handler below would otherwise never fire). Delayed so
    // a genuine option click - which blurs the input first, then fires its own click - has
    // time to run and read the option before this closes the list out from under it.
    input.addEventListener('blur', () => { setTimeout(closeList, 150); });

    input.addEventListener('keydown', (e) => {
        const opts = Array.from(optionsContainer.getElementsByClassName('dropdown-option'));
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            selectedIndex = Math.min(selectedIndex + 1, opts.length - 1);
            highlight(opts);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            selectedIndex = Math.max(selectedIndex - 1, -1);
            highlight(opts);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            const pick = selectedIndex >= 0 ? opts[selectedIndex] : opts[0];
            if (pick) pick.click();
        } else if (e.key === 'Escape') {
            closeList();
        }
    });

    optionsContainer.addEventListener('click', (e) => {
        const opt = e.target.closest('.dropdown-option');
        if (!opt) return;
        input.value = opt.getAttribute('data-value');
        closeList();
        onSelect(input.value);
    });

    document.addEventListener('click', closeList);
}

const AC_TABS = ['audit-trail', 'login-activity', 'report-overview', 'deleted-records', 'user-activity', 'manage-logs'];
const acLoadedTabs = new Set();

function acSwitchTab(tab) {
    if (!AC_TABS.includes(tab)) return;
    // Manage Logs stays inert until the Super Admin check confirms access - even via a
    // deep link (?view=manage-logs) - the server rejects every action there regardless,
    // but there is no reason to render the delete UI for a user who can't use it.
    if (tab === 'manage-logs' && document.getElementById('manageLogsTabBtn').hidden) tab = 'audit-trail';

    document.querySelectorAll('.ac-tab').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.tab === tab);
    });
    document.querySelectorAll('.ac-panel').forEach(panel => {
        panel.classList.toggle('active', panel.id === `panel-${tab}`);
    });

    // Fetch each tab's data lazily, once, the first time it's opened.
    if (!acLoadedTabs.has(tab)) {
        acLoadedTabs.add(tab);
        if (tab === 'login-activity') { fetchLoginHistory(); fetchFailedLogins(); fetchLoginLocations(); }
        if (tab === 'audit-trail') fetchActivityLog(1);
        if (tab === 'report-overview') loadReportOverview();
        if (tab === 'deleted-records') fetchDeletedRecords(1);
        if (tab === 'user-activity') fetchUserActivityReport();
    }
    if (tab === 'manage-logs') acEnsureModuleListLoaded();

    const url = new URL(window.location);
    url.searchParams.set('view', tab);
    window.history.replaceState({}, '', url);
}

// ---------- Login Activity (History / Failed / Locations) - all three share one "Show N" size ----------
let loginHistoryPage = 1, loginHistoryTotalPages = 1;
let failedLoginsPage = 1, failedLoginsTotalPages = 1;
let loginLocationsPage = 1, loginLocationsTotalPages = 1;

function acLoginActivityLimit() {
    return document.getElementById('loginActivityPageSize').value;
}

function acChangeLoginActivityPageSize() {
    fetchLoginHistory(1);
    fetchFailedLogins(1);
    fetchLoginLocations(1);
}

function loginHistoryGoToPage(delta) {
    const next = loginHistoryPage + delta;
    if (next < 1 || next > loginHistoryTotalPages) return;
    fetchLoginHistory(next);
}
function failedLoginsGoToPage(delta) {
    const next = failedLoginsPage + delta;
    if (next < 1 || next > failedLoginsTotalPages) return;
    fetchFailedLogins(next);
}
function loginLocationsGoToPage(delta) {
    const next = loginLocationsPage + delta;
    if (next < 1 || next > loginLocationsTotalPages) return;
    fetchLoginLocations(next);
}

function acRenderPageInfo(elId, page, limit, pagination) {
    const info = document.getElementById(elId);
    const start = pagination.total_records === 0 ? 0 : ((page - 1) * pagination.limit) + 1;
    const end = Math.min(start + limit - 1, pagination.total_records);
    info.textContent = `Showing ${pagination.total_records === 0 ? 0 : start}-${Math.min(end, pagination.total_records)} of ${pagination.total_records}`;
}

async function fetchLoginHistory(page) {
    page = page || loginHistoryPage || 1;
    loginHistoryPage = page;
    const tbody = document.getElementById('loginHistoryBody');
    tbody.innerHTML = '<tr><td colspan="5" class="ac-loading">Loading...</td></tr>';
    try {
        const params = new URLSearchParams({ page, limit: acLoginActivityLimit() });
        const response = await fetch(`${AC_API_BASE}login-history.php?${params}`);
        const data = await response.json();
        if (data.success) {
            if (data.data.length > 0) {
                tbody.innerHTML = data.data.map(row => {
                    const statusClass = row.status === 'success' ? 'status-active' : 'status-inactive';
                    const statusLabel = row.status ? row.status.charAt(0).toUpperCase() + row.status.slice(1) : '-';
                    return `<tr>
                        <td>${escapeHtml(row.user)}</td>
                        <td>${escapeHtml(formatLogDateTime(row.login_time))}</td>
                        <td>${escapeHtml(row.ip_address)}</td>
                        <td>${escapeHtml(row.device)}</td>
                        <td><span class="status-badge ${statusClass}">${escapeHtml(statusLabel)}</span></td>
                    </tr>`;
                }).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="5"><div class="ac-empty"><i class="fas fa-history"></i>No login history found</div></td></tr>';
            }
            loginHistoryTotalPages = data.pagination.total_pages;
            acRenderPageInfo('loginHistoryInfo', page, data.data.length, data.pagination);
            document.getElementById('loginHistoryPrevBtn').disabled = page <= 1;
            document.getElementById('loginHistoryNextBtn').disabled = page >= loginHistoryTotalPages;
        } else {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:40px;color:var(--error);">Failed to load login history</td></tr>';
        }
    } catch (error) {
        console.error('Error fetching login history:', error);
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:40px;color:var(--error);">Failed to load login history</td></tr>';
    }
}

async function fetchFailedLogins(page) {
    page = page || failedLoginsPage || 1;
    failedLoginsPage = page;
    const tbody = document.getElementById('failedLoginsBody');
    tbody.innerHTML = '<tr><td colspan="4" class="ac-loading">Loading...</td></tr>';
    try {
        const params = new URLSearchParams({ page, limit: acLoginActivityLimit() });
        const response = await fetch(`${AC_API_BASE}failed-logins.php?${params}`);
        const data = await response.json();
        if (data.success) {
            if (data.data.length > 0) {
                tbody.innerHTML = data.data.map(row => `<tr>
                        <td>${escapeHtml(row.email)}</td>
                        <td>${escapeHtml(formatLogDateTime(row.attempt_time))}</td>
                        <td>${escapeHtml(row.ip_address)}</td>
                        <td><span class="status-badge status-inactive">${escapeHtml(row.reason)}</span></td>
                    </tr>`).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="4"><div class="ac-empty"><i class="fas fa-shield-alt"></i>No failed login attempts found</div></td></tr>';
            }
            failedLoginsTotalPages = data.pagination.total_pages;
            acRenderPageInfo('failedLoginsInfo', page, data.data.length, data.pagination);
            document.getElementById('failedLoginsPrevBtn').disabled = page <= 1;
            document.getElementById('failedLoginsNextBtn').disabled = page >= failedLoginsTotalPages;
        } else {
            tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:40px;color:var(--error);">Failed to load failed logins</td></tr>';
        }
    } catch (error) {
        console.error('Error fetching failed logins:', error);
        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:40px;color:var(--error);">Failed to load failed logins</td></tr>';
    }
}

async function fetchLoginLocations(page) {
    page = page || loginLocationsPage || 1;
    loginLocationsPage = page;
    const tbody = document.getElementById('loginLocationsBody');
    tbody.innerHTML = '<tr><td colspan="5" class="ac-loading">Loading...</td></tr>';
    try {
        const params = new URLSearchParams({ page, limit: acLoginActivityLimit() });
        const response = await fetch(`${AC_API_BASE}login-locations.php?${params}`);
        const data = await response.json();
        if (data.success) {
            if (data.data.length > 0) {
                tbody.innerHTML = data.data.map(row => `<tr>
                        <td>${escapeHtml(row.user)}</td>
                        <td>${escapeHtml(row.location)}</td>
                        <td>${escapeHtml(row.ip_address)}</td>
                        <td>${escapeHtml(formatLogDateTime(row.last_login))}</td>
                        <td>${escapeHtml(row.login_count)}</td>
                    </tr>`).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="5"><div class="ac-empty"><i class="fas fa-map-marker-alt"></i>No login locations found</div></td></tr>';
            }
            loginLocationsTotalPages = data.pagination.total_pages;
            acRenderPageInfo('loginLocationsInfo', page, data.data.length, data.pagination);
            document.getElementById('loginLocationsPrevBtn').disabled = page <= 1;
            document.getElementById('loginLocationsNextBtn').disabled = page >= loginLocationsTotalPages;
        } else {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:40px;color:var(--error);">Failed to load login locations</td></tr>';
        }
    } catch (error) {
        console.error('Error fetching login locations:', error);
        tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:40px;color:var(--error);">Failed to load login locations</td></tr>';
    }
}

// ---------- Audit Trail (Activity Log) ----------
let activityLogPage = 1;
let activityLogTotalPages = 1;
let activityLogModulesLoaded = false;

const ACTIVITY_ACTION_LABELS = { create: 'Created', update: 'Updated', delete: 'Deleted' };
const ACTIVITY_ACTION_CLASSES = { create: 'status-active', update: 'status-pending', delete: 'status-inactive' };

function activityLogGoToPage(delta) {
    const nextPage = activityLogPage + delta;
    if (nextPage < 1 || nextPage > activityLogTotalPages) return;
    fetchActivityLog(nextPage);
}

function acClearFilters() {
    document.getElementById('activityLogModuleFilter').value = '';
    document.getElementById('activityLogActionFilter').value = '';
    document.getElementById('activityLogFromDate').value = '';
    document.getElementById('activityLogToDate').value = '';
    document.getElementById('activityLogSearch').value = '';
    fetchActivityLog(1);
}

async function fetchActivityLog(page) {
    activityLogPage = page;
    const tbody = document.getElementById('activityLogBody');
    tbody.innerHTML = '<tr><td colspan="6" class="ac-loading">Loading...</td></tr>';

    try {
        const params = new URLSearchParams({
            page: page,
            limit: document.getElementById('activityLogPageSize').value,
            module: document.getElementById('activityLogModuleFilter').value,
            action: document.getElementById('activityLogActionFilter').value,
            search: document.getElementById('activityLogSearch').value,
            from_date: document.getElementById('activityLogFromDate').value,
            to_date: document.getElementById('activityLogToDate').value
        });

        const response = await fetch(`${AC_API_BASE}activity-log.php?${params}`);
        const data = await response.json();

        if (data.success) {
            if (!activityLogModulesLoaded) {
                acAuditModulesCache = data.modules;
                activityLogModulesLoaded = true;
            }

            if (data.data.length > 0) {
                tbody.innerHTML = data.data.map(row => {
                    const actionLabel = ACTIVITY_ACTION_LABELS[row.action] || row.action;
                    const actionClass = ACTIVITY_ACTION_CLASSES[row.action] || '';
                    return `<tr>
                        <td>${escapeHtml(formatLogDateTime(row.created_at))}</td>
                        <td>${escapeHtml(row.user_name)}</td>
                        <td><span class="status-badge ${actionClass}">${escapeHtml(actionLabel)}</span></td>
                        <td>${escapeHtml(row.module)}</td>
                        <td>${escapeHtml(row.record_reference || row.record_id || '-')}</td>
                        <td>${escapeHtml(row.description)}</td>
                    </tr>`;
                }).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="6"><div class="ac-empty"><i class="fas fa-clipboard-list"></i>No activity found</div></td></tr>';
            }

            activityLogTotalPages = data.pagination.total_pages;
            const info = document.getElementById('activityLogInfo');
            const start = data.pagination.total_records === 0 ? 0 : ((page - 1) * data.pagination.limit) + 1;
            const end = Math.min(start + data.data.length - 1, data.pagination.total_records);
            info.textContent = `Showing ${start}-${end} of ${data.pagination.total_records}`;

            document.getElementById('activityLogPrevBtn').disabled = page <= 1;
            document.getElementById('activityLogNextBtn').disabled = page >= activityLogTotalPages;
        } else {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:40px;color:var(--error);">Failed to load activity log</td></tr>';
        }
    } catch (error) {
        console.error('Error fetching activity log:', error);
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:40px;color:var(--error);">Failed to load activity log</td></tr>';
    }
}

// Exports every record matching the current Audit Trail filters (not just the visible page),
// fetched 100 at a time up to a 2,000-row safety cap so a broad/unfiltered export can't hang the tab.
async function exportActivityLogCsv(event) {
    const btn = event?.target?.closest('button');
    const originalHtml = btn ? btn.innerHTML : null;
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Exporting...'; }

    try {
        const baseParams = {
            module: document.getElementById('activityLogModuleFilter').value,
            action: document.getElementById('activityLogActionFilter').value,
            search: document.getElementById('activityLogSearch').value,
            from_date: document.getElementById('activityLogFromDate').value,
            to_date: document.getElementById('activityLogToDate').value
        };

        let allRows = [];
        let page = 1;
        const maxRows = 2000;
        while (true) {
            const params = new URLSearchParams({ ...baseParams, page, limit: 100 });
            const response = await fetch(`${AC_API_BASE}activity-log.php?${params}`);
            const data = await response.json();
            if (!data.success) break;
            allRows = allRows.concat(data.data);
            if (page >= data.pagination.total_pages || allRows.length >= maxRows) break;
            page++;
        }

        if (allRows.length === 0) {
            alert('No activity matches the current filters.');
            return;
        }

        acDownloadCsv(
            `activity-log-${new Date().toISOString().slice(0, 10)}.csv`,
            ['Date/Time', 'User', 'Action', 'Module', 'Record', 'Description'],
            allRows.map(row => [
                formatLogDateTime(row.created_at), row.user_name, ACTIVITY_ACTION_LABELS[row.action] || row.action,
                row.module, row.record_reference || row.record_id, row.description
            ])
        );
    } catch (error) {
        console.error('Error exporting activity log:', error);
        alert('Failed to export activity log.');
    } finally {
        if (btn) { btn.disabled = false; btn.innerHTML = originalHtml; }
    }
}

// ---------- Overview stats ----------
async function acLoadStats() {
    try {
        const response = await fetch(AC_API_BASE + 'activity-stats.php');
        const data = await response.json();
        if (data.success) {
            document.getElementById('statTodayActivities').textContent = data.data.today_activities;
            document.getElementById('statTodayLogins').textContent = data.data.today_logins;
            document.getElementById('statTodayFailedLogins').textContent = data.data.today_failed_logins;
            document.getElementById('statDeletions30d').textContent = data.data.deletions_30d;
        }
    } catch (error) {
        console.error('Error fetching activity stats:', error);
    }
}

// ---------- Reports: Summary (daily chart + module breakdown) ----------
let acReportOverviewLoaded = false;

function acDrawDailyChart(daily) {
    const canvas = document.getElementById('dailyActivityChart');
    const ctx = canvas.getContext('2d');
    const dpr = window.devicePixelRatio || 1;
    const cssWidth = canvas.clientWidth || 600;
    const cssHeight = canvas.clientHeight || 220;
    canvas.width = cssWidth * dpr;
    canvas.height = cssHeight * dpr;
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.clearRect(0, 0, cssWidth, cssHeight);

    const padding = { top: 16, right: 12, bottom: 30, left: 12 };
    const chartWidth = cssWidth - padding.left - padding.right;
    const chartHeight = cssHeight - padding.top - padding.bottom;
    const maxVal = Math.max(1, ...daily.map(d => d.total));
    const barCount = daily.length;
    const gap = 14;
    const barWidth = (chartWidth - gap * (barCount - 1)) / barCount;

    ctx.font = '11px sans-serif';
    ctx.textAlign = 'center';

    daily.forEach((d, i) => {
        const barHeight = (d.total / maxVal) * (chartHeight - 20);
        const x = padding.left + i * (barWidth + gap);
        const y = padding.top + (chartHeight - barHeight);

        ctx.fillStyle = '#1f7bff';
        ctx.beginPath();
        if (ctx.roundRect) {
            ctx.roundRect(x, y, barWidth, barHeight, 3);
            ctx.fill();
        } else {
            ctx.fillRect(x, y, barWidth, barHeight);
        }

        ctx.fillStyle = '#0e1a2b';
        ctx.fillText(String(d.total), x + barWidth / 2, y - 6);

        const label = new Date(d.day + 'T00:00:00').toLocaleDateString(undefined, { weekday: 'short' });
        ctx.fillStyle = '#6b7280';
        ctx.fillText(label, x + barWidth / 2, padding.top + chartHeight + 18);
    });
}

function acRenderModuleBreakdown(byModule) {
    const container = document.getElementById('moduleBreakdownList');
    if (!byModule || byModule.length === 0) {
        container.innerHTML = '<div class="ac-empty"><i class="fas fa-chart-bar"></i>No activity in the last 30 days</div>';
        return;
    }
    const maxTotal = Math.max(...byModule.map(m => parseInt(m.total, 10)));
    container.innerHTML = byModule.map(m => {
        const pct = Math.max(4, Math.round((parseInt(m.total, 10) / maxTotal) * 100));
        return `<div class="ac-module-row">
            <div class="ac-module-name" title="${escapeHtml(m.module)}">${escapeHtml(m.module)}</div>
            <div class="ac-module-bar-track"><div class="ac-module-bar-fill" style="width:${pct}%"></div></div>
            <div class="ac-module-count">${escapeHtml(m.total)}</div>
        </div>`;
    }).join('');
}

async function loadReportOverview() {
    try {
        const response = await fetch(AC_API_BASE + 'activity-stats.php');
        const data = await response.json();
        if (data.success) {
            acDrawDailyChart(data.data.daily);
            acRenderModuleBreakdown(data.data.by_module);
            acReportOverviewLoaded = true;
        }
    } catch (error) {
        console.error('Error loading report overview:', error);
    }
}

// Redraw the chart if the window is resized while this tab is visible (canvas needs
// explicit re-render on resize since it doesn't scale like normal DOM content).
window.addEventListener('resize', () => {
    if (acReportOverviewLoaded && document.getElementById('panel-report-overview').classList.contains('active')) {
        loadReportOverview();
    }
});

// ---------- Reports: Deleted Records ----------
let deletedRecordsPage = 1;
let deletedRecordsTotalPages = 1;
let deletedModulesLoaded = false;
let deletedRecordsCache = [];

function deletedRecordsGoToPage(delta) {
    const nextPage = deletedRecordsPage + delta;
    if (nextPage < 1 || nextPage > deletedRecordsTotalPages) return;
    fetchDeletedRecords(nextPage);
}

function acClearDeletedFilters() {
    document.getElementById('deletedModuleFilter').value = '';
    document.getElementById('deletedFromDate').value = '';
    document.getElementById('deletedToDate').value = '';
    document.getElementById('deletedSearch').value = '';
    fetchDeletedRecords(1);
}

async function fetchDeletedRecords(page) {
    deletedRecordsPage = page;
    const tbody = document.getElementById('deletedRecordsBody');
    tbody.innerHTML = '<tr><td colspan="6" class="ac-loading">Loading...</td></tr>';

    try {
        const params = new URLSearchParams({
            page: page,
            limit: document.getElementById('deletedPageSize').value,
            module: document.getElementById('deletedModuleFilter').value,
            search: document.getElementById('deletedSearch').value,
            from_date: document.getElementById('deletedFromDate').value,
            to_date: document.getElementById('deletedToDate').value
        });

        const response = await fetch(`${AC_API_BASE}deleted-records.php?${params}`);
        const data = await response.json();

        if (data.success) {
            if (!deletedModulesLoaded) {
                acDeletedModulesCache = data.modules;
                deletedModulesLoaded = true;
            }

            deletedRecordsCache = data.data;

            if (data.data.length > 0) {
                tbody.innerHTML = data.data.map((row, idx) => `<tr>
                        <td>${escapeHtml(formatLogDateTime(row.created_at))}</td>
                        <td>${escapeHtml(row.user_name)}</td>
                        <td>${escapeHtml(row.module)}</td>
                        <td>${escapeHtml(row.record_reference || row.record_id || '-')}</td>
                        <td>${escapeHtml(row.description)}</td>
                        <td><button class="btn btn-secondary btn-sm" onclick="openSnapshot(${idx})"><i class="fas fa-camera"></i> View</button></td>
                    </tr>`).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="6"><div class="ac-empty"><i class="fas fa-trash-restore"></i>No deleted records found</div></td></tr>';
            }

            deletedRecordsTotalPages = data.pagination.total_pages;
            const info = document.getElementById('deletedRecordsInfo');
            const start = data.pagination.total_records === 0 ? 0 : ((page - 1) * data.pagination.limit) + 1;
            const end = Math.min(start + data.data.length - 1, data.pagination.total_records);
            info.textContent = `Showing ${start}-${end} of ${data.pagination.total_records}`;

            document.getElementById('deletedRecordsPrevBtn').disabled = page <= 1;
            document.getElementById('deletedRecordsNextBtn').disabled = page >= deletedRecordsTotalPages;
        } else {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:40px;color:var(--error);">Failed to load deleted records</td></tr>';
        }
    } catch (error) {
        console.error('Error fetching deleted records:', error);
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;padding:40px;color:var(--error);">Failed to load deleted records</td></tr>';
    }
}

function openSnapshot(idx) {
    const row = deletedRecordsCache[idx];
    if (!row) return;
    const body = document.getElementById('snapshotBody');

    let fields = null;
    try {
        fields = row.old_values ? JSON.parse(row.old_values) : null;
    } catch (e) {
        fields = null;
    }

    let html = `<p style="margin:0 0 12px 0; font-size:13px; color:var(--subtext);">
        ${escapeHtml(row.module)} <b>${escapeHtml(row.record_reference || row.record_id)}</b> &mdash;
        deleted by <b>${escapeHtml(row.user_name)}</b> on ${escapeHtml(formatLogDateTime(row.created_at))}
    </p>`;

    if (fields && typeof fields === 'object' && Object.keys(fields).length > 0) {
        html += `<table class="ac-snapshot-table">${Object.entries(fields).map(([key, value]) => `
            <tr><td>${escapeHtml(key)}</td><td>${escapeHtml(typeof value === 'object' && value !== null ? JSON.stringify(value) : value)}</td></tr>
        `).join('')}</table>`;
    } else {
        html += '<div class="ac-empty"><i class="fas fa-camera"></i>No snapshot data was captured for this deletion</div>';
    }

    body.innerHTML = html;
    document.getElementById('snapshotModal').style.display = 'flex';
}

function closeSnapshotModal() {
    document.getElementById('snapshotModal').style.display = 'none';
}

function acCsvEscape(value) {
    const str = String(value ?? '');
    if (/[",\n]/.test(str)) return '"' + str.replace(/"/g, '""') + '"';
    return str;
}

function acDownloadCsv(filename, headers, rows) {
    const lines = [headers.map(acCsvEscape).join(',')].concat(
        rows.map(r => r.map(acCsvEscape).join(','))
    );
    const blob = new Blob(['﻿' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}

// Exports every record matching the current filters (not just the visible page), fetched
// 100 at a time up to a 2,000-row safety cap so a broad/unfiltered export can't hang the tab.
async function exportDeletedRecordsCsv(event) {
    const btn = event?.target?.closest('button');
    const originalHtml = btn ? btn.innerHTML : null;
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Exporting...'; }

    try {
        const baseParams = {
            module: document.getElementById('deletedModuleFilter').value,
            search: document.getElementById('deletedSearch').value,
            from_date: document.getElementById('deletedFromDate').value,
            to_date: document.getElementById('deletedToDate').value
        };

        let allRows = [];
        let page = 1;
        const maxRows = 2000;
        while (true) {
            const params = new URLSearchParams({ ...baseParams, page, limit: 100 });
            const response = await fetch(`${AC_API_BASE}deleted-records.php?${params}`);
            const data = await response.json();
            if (!data.success) break;
            allRows = allRows.concat(data.data);
            if (page >= data.pagination.total_pages || allRows.length >= maxRows) break;
            page++;
        }

        if (allRows.length === 0) {
            alert('No deleted records match the current filters.');
            return;
        }

        acDownloadCsv(
            `deleted-records-${new Date().toISOString().slice(0, 10)}.csv`,
            ['Date/Time', 'Deleted By', 'Module', 'Record', 'Description'],
            allRows.map(row => [formatLogDateTime(row.created_at), row.user_name, row.module, row.record_reference || row.record_id, row.description])
        );
    } catch (error) {
        console.error('Error exporting deleted records:', error);
        alert('Failed to export deleted records.');
    } finally {
        if (btn) { btn.disabled = false; btn.innerHTML = originalHtml; }
    }
}

// ---------- Reports: User Activity ----------
async function fetchUserActivityReport() {
    const tbody = document.getElementById('userActivityBody');
    tbody.innerHTML = '<tr><td colspan="7" class="ac-loading">Loading...</td></tr>';

    try {
        const params = new URLSearchParams({
            from_date: document.getElementById('userActivityFromDate').value,
            to_date: document.getElementById('userActivityToDate').value
        });
        const response = await fetch(`${AC_API_BASE}user-activity-report.php?${params}`);
        const data = await response.json();

        if (data.success) {
            document.getElementById('userActivityFromDate').value = data.from_date;
            document.getElementById('userActivityToDate').value = data.to_date;

            if (data.data.length > 0) {
                tbody.innerHTML = data.data.map((row, idx) => `<tr>
                        <td>${escapeHtml(row.user_name)}</td>
                        <td>${escapeHtml(row.logins)}</td>
                        <td>${escapeHtml(row.created)}</td>
                        <td>${escapeHtml(row.updated)}</td>
                        <td>${escapeHtml(row.deleted)}</td>
                        <td><b>${escapeHtml(row.total)}</b></td>
                        <td><button class="btn btn-secondary btn-sm" data-user-idx="${idx}"><i class="fas fa-search"></i> View Details</button></td>
                    </tr>`).join('');
                // Attach listeners after insertion (rather than an inline onclick built from
                // row.user_name) so a user_name containing a quote can't break out of an
                // HTML-attribute/JS-string context - the raw value stays a JS reference, never
                // round-trips through HTML-entity encoding inside an executable attribute.
                tbody.querySelectorAll('button[data-user-idx]').forEach(btn => {
                    btn.addEventListener('click', () => acDrillDownUser(data.data[parseInt(btn.dataset.userIdx, 10)].user_name));
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="7"><div class="ac-empty"><i class="fas fa-user-clock"></i>No activity in this date range</div></td></tr>';
            }
        } else {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:40px;color:var(--error);">Failed to load user activity report</td></tr>';
        }
    } catch (error) {
        console.error('Error fetching user activity report:', error);
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:40px;color:var(--error);">Failed to load user activity report</td></tr>';
    }
}

// Jumps to the Audit Trail tab pre-filtered to this user, per the report's "View Details" drill-down.
function acDrillDownUser(userName) {
    acSwitchTab('audit-trail');
    document.getElementById('activityLogSearch').value = userName;
    fetchActivityLog(1);
}

// Populates acAuditModulesCache in the background (without touching the Audit Trail
// table/pagination state) so the Manage Logs module dropdown has options even if the
// user opens Manage Logs before ever visiting the Audit Trail tab.
let acModuleListLoaded = false;
async function acEnsureModuleListLoaded() {
    if (acModuleListLoaded || activityLogModulesLoaded) return;
    try {
        const response = await fetch(`${AC_API_BASE}activity-log.php?page=1&limit=1`);
        const data = await response.json();
        if (data.success) {
            acAuditModulesCache = data.modules;
            acModuleListLoaded = true;
            activityLogModulesLoaded = true;
        }
    } catch (error) {
        console.error('Error loading module list:', error);
    }
}

// ---------- Manage Logs (Super Admin only - the server re-checks the role on every
// count/export/delete request below; hiding the tab here is a UX convenience, not the
// security boundary) ----------
let mlCurrentType = 'audit'; // 'audit' | 'login'
let mlLastPreview = null;

function acSetManageLogsType(type) {
    mlCurrentType = type;
    document.getElementById('mlTypeAudit').classList.toggle('active', type === 'audit');
    document.getElementById('mlTypeLogin').classList.toggle('active', type === 'login');
    document.getElementById('mlModule').style.display = type === 'audit' ? '' : 'none';
    document.getElementById('mlAction').style.display = type === 'audit' ? '' : 'none';
    document.getElementById('mlStatus').style.display = type === 'login' ? '' : 'none';
    document.getElementById('mlPreviewSection').hidden = true;
    mlLastPreview = null;
}

async function acPreviewLogsToDelete() {
    const module = mlCurrentType === 'audit' ? document.getElementById('mlModule').value.trim() : '';
    const action = mlCurrentType === 'audit' ? document.getElementById('mlAction').value : document.getElementById('mlStatus').value;
    const toDate = document.getElementById('mlToDate').value;

    try {
        const params = new URLSearchParams({ log_type: mlCurrentType, module, action, to_date: toDate });
        const response = await fetch(`${AC_API_BASE}logs-count.php?${params}`);
        const data = await response.json();
        if (!data.success) {
            alert(data.message || 'Failed to count matching records');
            return;
        }

        const isAllScope = mlCurrentType === 'audit' ? module === '' : action === '';
        mlLastPreview = { module, action, toDate, count: data.count, isAllScope };

        const scopeLabel = mlCurrentType === 'audit'
            ? `Module: ${module || 'All'}, Action: ${action || 'All'}`
            : `Status: ${action || 'All'}`;
        document.getElementById('mlCountSummary').innerHTML =
            `<i class="fas fa-info-circle"></i> <b>${data.count}</b> ${mlCurrentType === 'audit' ? 'Audit Trail' : 'Login Activity'} record(s) match &mdash; ${escapeHtml(scopeLabel)}${toDate ? ', up to ' + escapeHtml(toDate) : ''}.`;

        document.getElementById('mlAllScopeWarning').hidden = !isAllScope;
        document.getElementById('mlConfirmAllInput').value = '';
        document.getElementById('mlBackedUpCheck').checked = false;
        document.getElementById('mlPreviewSection').hidden = data.count === 0;
        if (data.count === 0) {
            alert('No records match these filters - nothing to delete.');
        }
        acUpdateDeleteButtonState();
    } catch (error) {
        console.error('Error previewing logs to delete:', error);
        alert('Failed to preview matching records.');
    }
}

function acDownloadLogsBackup(format) {
    if (!mlLastPreview) return;
    const params = new URLSearchParams({
        log_type: mlCurrentType, module: mlLastPreview.module, action: mlLastPreview.action,
        to_date: mlLastPreview.toDate, format
    });
    window.open(`${AC_API_BASE}logs-export.php?${params}`, '_blank');
}

function acUpdateDeleteButtonState() {
    const btn = document.getElementById('mlDeleteBtn');
    if (!mlLastPreview) { btn.disabled = true; return; }
    const backedUp = document.getElementById('mlBackedUpCheck').checked;
    const allConfirmed = !mlLastPreview.isAllScope || document.getElementById('mlConfirmAllInput').value.trim() === 'DELETE ALL';
    btn.disabled = !(backedUp && allConfirmed);
}

async function acDeleteLogs() {
    if (!mlLastPreview) return;
    const { module, action, toDate, count, isAllScope } = mlLastPreview;

    if (!confirm(`Permanently delete ${count} ${mlCurrentType === 'audit' ? 'Audit Trail' : 'Login Activity'} record(s)? This cannot be undone.`)) return;

    const btn = document.getElementById('mlDeleteBtn');
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';

    try {
        const response = await fetch(`${AC_API_BASE}logs-delete.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                log_type: mlCurrentType, module, action, to_date: toDate,
                confirm_all: isAllScope, backed_up: document.getElementById('mlBackedUpCheck').checked
            })
        });
        const data = await response.json();
        if (!data.success) {
            alert(data.message || 'Failed to delete records');
            btn.disabled = false;
            btn.innerHTML = originalHtml;
            return;
        }

        alert(`${data.deleted_count} record(s) deleted.`);
        document.getElementById('mlPreviewSection').hidden = true;
        mlLastPreview = null;
        btn.innerHTML = originalHtml;

        // Invalidate caches so every affected tab refetches fresh data next time it's opened.
        ['audit-trail', 'login-activity', 'report-overview', 'deleted-records', 'user-activity'].forEach(t => acLoadedTabs.delete(t));
        acLoadStats();
    } catch (error) {
        console.error('Error deleting logs:', error);
        alert('Failed to delete records.');
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    }
}

async function acCheckSuperAdmin() {
    try {
        const response = await fetch(AC_API_BASE + 'is-super-admin.php');
        const data = await response.json();
        if (data.success && data.is_super_admin) {
            document.getElementById('manageLogsTabBtn').hidden = false;
        }
    } catch (error) {
        console.error('Error checking Super Admin status:', error);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    acLoadStats();
    acCheckSuperAdmin();

    acInitModuleDropdown('activityLogModuleFilter', 'activityLogModuleOptions', () => acAuditModulesCache, () => fetchActivityLog(1));
    acInitModuleDropdown('deletedModuleFilter', 'deletedModuleOptions', () => acDeletedModulesCache, () => fetchDeletedRecords(1));
    acInitModuleDropdown('mlModule', 'mlModuleOptions', () => acAuditModulesCache, () => {});

    const today = new Date().toISOString().slice(0, 10);
    const monthAgo = new Date(Date.now() - 30 * 86400000).toISOString().slice(0, 10);
    document.getElementById('userActivityFromDate').value = monthAgo;
    document.getElementById('userActivityToDate').value = today;

    const requestedView = new URLSearchParams(window.location.search).get('view');
    acSwitchTab(AC_TABS.includes(requestedView) ? requestedView : 'audit-trail');
});
