// Live Tracking — shows every mobile-app user's most recent location (today)
// as a color-coded dot on the map (moving/stopped/offline/alert/paused),
// and lets the admin click one to draw their full route for the day. Also
// has a History Report tab (date-range distance/stops summary) and a
// "Manage Access" modal where the admin can turn tracking on/off per user.
//
// Polls get-live-locations.php every 30s for the live dots; the route for
// a selected officer and the report table are fetched on demand, not
// polled (they're historical, not live).
const OT_API_BASE = '../../../../server/api/system_setup/admin_panel/';
const OT_POLL_MS = 30000;

const OT_STATUS_LABELS = {
    moving: 'Moving',
    stopped: 'Stopped',
    offline: 'Offline',
    gps_off: 'GPS is off',
    permission_denied: 'Location permission denied',
    paused_by_officer: 'Paused by officer',
    disabled_by_admin: 'Tracking disabled',
};
const OT_ALERT_STATUSES = ['gps_off', 'permission_denied'];

let otMap = null;
let otMarkers = {}; // user_id -> L.circleMarker
let otRouteLine = null;
let otRouteMarkers = [];
let otSelectedUserId = null;
let otOfficers = [];

document.addEventListener('DOMContentLoaded', () => {
    initMap();
    loadLiveLocations();
    setInterval(loadLiveLocations, OT_POLL_MS);

    document.getElementById('otRefreshBtn').addEventListener('click', loadLiveLocations);

    initTabs();
    initAccessModal();
    initReportTab();
});

function initTabs() {
    document.querySelectorAll('.ot-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.ot-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.ot-panel').forEach(p => p.classList.remove('active'));
            tab.classList.add('active');
            document.getElementById(tab.dataset.tab === 'map' ? 'otMapPanel' : 'otReportPanel').classList.add('active');
            if (tab.dataset.tab === 'map' && otMap) {
                setTimeout(() => otMap.invalidateSize(), 50);
            }
        });
    });
}

function initMap() {
    otMap = L.map('otMap').setView([30.3753, 69.3451], 6);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(otMap);
}

function statusDotClass(status) {
    if (status === 'disabled_by_admin') return 'dot-offline';
    if (status === 'paused_by_officer') return 'dot-paused';
    if (OT_ALERT_STATUSES.includes(status)) return 'dot-alert';
    if (status === 'offline') return 'dot-offline';
    if (status === 'stopped') return 'dot-stopped';
    return 'dot-moving';
}

function statusColor(status) {
    if (status === 'disabled_by_admin' || status === 'offline') return '#9ca3af';
    if (status === 'paused_by_officer') return '#7c3aed';
    if (OT_ALERT_STATUSES.includes(status)) return '#e34f4f';
    if (status === 'stopped') return '#d97706';
    return '#2fbf71';
}

function statusBadgeClass(status) {
    if (status === 'disabled_by_admin') return 'ot-status-offline';
    if (status === 'paused_by_officer') return 'ot-status-paused';
    if (OT_ALERT_STATUSES.includes(status)) return 'ot-status-alert';
    if (status === 'offline') return 'ot-status-offline';
    if (status === 'stopped') return 'ot-status-stopped';
    return 'ot-status-moving';
}

async function loadLiveLocations() {
    try {
        const res = await fetch(`${OT_API_BASE}get-live-locations.php`);
        const data = await res.json();
        if (!data.success) return;

        otOfficers = data.data || [];
        renderOfficerList();
        renderLiveMarkers();

        document.getElementById('otOnlineCount').textContent = otOfficers.length;
        document.getElementById('otLastUpdated').textContent =
            'Updated ' + new Date().toLocaleTimeString();
    } catch (e) {
        console.error('Failed to load live locations', e);
    }
}

function renderOfficerList() {
    const list = document.getElementById('otOfficerList');

    if (otOfficers.length === 0) {
        list.innerHTML = '<div class="ot-empty"><i class="fas fa-location-dot"></i> No officers online right now</div>';
        return;
    }

    list.innerHTML = otOfficers.map(o => `
        <div class="ot-officer-item ${o.user_id === otSelectedUserId ? 'active' : ''}" data-user-id="${o.user_id}">
            <span class="ot-officer-dot ${statusDotClass(o.status)}"></span>
            <div class="ot-officer-info">
                <div class="ot-officer-name">${escapeHtml(o.name)}</div>
                <div class="ot-officer-time">
                    <span class="ot-status-badge ${statusBadgeClass(o.status)}">${OT_STATUS_LABELS[o.status] || o.status}</span>
                    &middot; ${formatTime(o.recorded_at)}
                </div>
                <div class="ot-officer-distance">${o.distance_km_today} km today</div>
            </div>
        </div>
    `).join('');

    list.querySelectorAll('.ot-officer-item').forEach(item => {
        item.addEventListener('click', () => {
            const userId = parseInt(item.dataset.userId, 10);
            selectOfficer(userId);
        });
    });
}

function renderLiveMarkers() {
    const seenIds = new Set();

    otOfficers.forEach(o => {
        seenIds.add(o.user_id);
        const latLng = [o.latitude, o.longitude];
        const color = statusColor(o.status);

        if (otMarkers[o.user_id]) {
            otMarkers[o.user_id].setLatLng(latLng);
            otMarkers[o.user_id].setStyle({ color, fillColor: color });
        } else {
            const marker = L.circleMarker(latLng, {
                radius: 8,
                color,
                fillColor: color,
                fillOpacity: 0.9,
                weight: 2
            }).addTo(otMap);
            marker.on('click', () => selectOfficer(o.user_id));
            otMarkers[o.user_id] = marker;
        }

        otMarkers[o.user_id].bindPopup(
            `<div class="ot-popup-name">${escapeHtml(o.name)}</div>` +
            `<div class="ot-popup-time">${OT_STATUS_LABELS[o.status] || o.status} &middot; ${formatTime(o.recorded_at)}</div>` +
            `<div class="ot-popup-time">${o.distance_km_today} km today</div>`
        );
    });

    Object.keys(otMarkers).forEach(id => {
        if (!seenIds.has(parseInt(id, 10))) {
            otMap.removeLayer(otMarkers[id]);
            delete otMarkers[id];
        }
    });

    if (!otMap._otHasFitOnce && otOfficers.length > 0) {
        const bounds = L.latLngBounds(otOfficers.map(o => [o.latitude, o.longitude]));
        otMap.fitBounds(bounds, { padding: [40, 40], maxZoom: 14 });
        otMap._otHasFitOnce = true;
    }
}

async function selectOfficer(userId) {
    otSelectedUserId = userId;
    renderOfficerList();

    const today = new Date().toISOString().slice(0, 10);
    try {
        const res = await fetch(`${OT_API_BASE}get-officer-route.php?user_id=${userId}&date=${today}`);
        const data = await res.json();
        if (!data.success) return;

        drawRoute(data.data || []);
    } catch (e) {
        console.error('Failed to load officer route', e);
    }
}

function drawRoute(points) {
    if (otRouteLine) {
        otMap.removeLayer(otRouteLine);
        otRouteLine = null;
    }
    otRouteMarkers.forEach(m => otMap.removeLayer(m));
    otRouteMarkers = [];

    if (points.length === 0) return;

    const latLngs = points.map(p => [p.latitude, p.longitude]);
    otRouteLine = L.polyline(latLngs, { color: '#1f7bff', weight: 3, opacity: 0.8 }).addTo(otMap);

    points.forEach(p => {
        const m = L.circleMarker([p.latitude, p.longitude], {
            radius: 3,
            color: '#1f7bff',
            fillColor: '#1f7bff',
            fillOpacity: 0.6,
            weight: 1
        }).bindTooltip(formatTime(p.recorded_at), { direction: 'top' });
        m.addTo(otMap);
        otRouteMarkers.push(m);
    });

    otMap.fitBounds(latLngs, { padding: [40, 40], maxZoom: 15 });
}

// ===== Manage Access modal =====

function initAccessModal() {
    document.getElementById('otManageAccessBtn').addEventListener('click', openAccessModal);
    document.getElementById('otAccessModalClose').addEventListener('click', closeAccessModal);
    document.getElementById('otAccessModalClose2').addEventListener('click', closeAccessModal);
}

function openAccessModal() {
    document.getElementById('otAccessModal').style.display = 'flex';
    loadAccessList();
}
function closeAccessModal() {
    document.getElementById('otAccessModal').style.display = 'none';
}

async function loadAccessList() {
    const list = document.getElementById('otAccessList');
    list.innerHTML = '<div class="ot-empty">Loading...</div>';
    try {
        const res = await fetch(`${OT_API_BASE}get-tracking-settings.php`);
        const data = await res.json();
        if (!data.success) {
            list.innerHTML = '<div class="ot-empty">Could not load users</div>';
            return;
        }

        const users = data.data || [];
        if (users.length === 0) {
            list.innerHTML = '<div class="ot-empty">No users found</div>';
            return;
        }

        list.innerHTML = users.map(u => `
            <div class="ot-access-row">
                <div>
                    <div class="ot-access-name">${escapeHtml(u.name)}</div>
                    <div class="ot-access-sub">${u.admin_enabled ? (u.officer_enabled ? 'Tracking active' : 'Paused by officer') : 'Tracking off (by admin)'}</div>
                </div>
                <label class="ot-switch">
                    <input type="checkbox" data-user-id="${u.user_id}" ${u.admin_enabled ? 'checked' : ''}>
                    <span class="ot-switch-slider"></span>
                </label>
            </div>
        `).join('');

        list.querySelectorAll('input[type="checkbox"]').forEach(cb => {
            cb.addEventListener('change', async () => {
                const userId = parseInt(cb.dataset.userId, 10);
                const enabled = cb.checked;
                cb.disabled = true;
                try {
                    await fetch(`${OT_API_BASE}set-tracking-enabled.php`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ user_id: userId, enabled })
                    });
                } catch (e) {
                    console.error('Failed to update tracking access', e);
                    cb.checked = !enabled;
                } finally {
                    cb.disabled = false;
                    loadLiveLocations();
                }
            });
        });
    } catch (e) {
        console.error('Failed to load access list', e);
        list.innerHTML = '<div class="ot-empty">Could not load users</div>';
    }
}

// ===== History Report tab =====

function initReportTab() {
    const today = new Date().toISOString().slice(0, 10);
    const weekAgo = new Date(Date.now() - 6 * 86400000).toISOString().slice(0, 10);
    document.getElementById('otReportFrom').value = weekAgo;
    document.getElementById('otReportTo').value = today;

    document.getElementById('otReportLoadBtn').addEventListener('click', loadReport);

    populateReportUserFilter();
}

function populateReportUserFilter() {
    const select = document.getElementById('otReportUserFilter');
    fetch(`${OT_API_BASE}get-tracking-settings.php`)
        .then(res => res.json())
        .then(data => {
            if (!data.success) return;
            (data.data || []).forEach(u => {
                const opt = document.createElement('option');
                opt.value = u.user_id;
                opt.textContent = u.name;
                select.appendChild(opt);
            });
        })
        .catch(e => console.error('Failed to load officers for filter', e));
}

async function loadReport() {
    const tbody = document.getElementById('otReportTableBody');
    tbody.innerHTML = '<tr><td colspan="6" class="ot-empty">Loading...</td></tr>';

    const from = document.getElementById('otReportFrom').value;
    const to = document.getElementById('otReportTo').value;
    const userId = document.getElementById('otReportUserFilter').value;

    const params = new URLSearchParams({ from_date: from, to_date: to });
    if (userId) params.set('user_id', userId);

    try {
        const res = await fetch(`${OT_API_BASE}get-tracking-report.php?${params}`);
        const data = await res.json();
        if (!data.success) {
            tbody.innerHTML = '<tr><td colspan="6" class="ot-empty">Could not load report</td></tr>';
            return;
        }

        const rows = data.data || [];
        if (rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="ot-empty">No activity in this date range</td></tr>';
            return;
        }

        tbody.innerHTML = rows.map(r => `
            <tr>
                <td>${escapeHtml(r.name)}</td>
                <td>${r.date}</td>
                <td>${formatTime(r.first_seen)}</td>
                <td>${formatTime(r.last_seen)}</td>
                <td>${r.distance_km}</td>
                <td>${r.stops}</td>
            </tr>
        `).join('');
    } catch (e) {
        console.error('Failed to load report', e);
        tbody.innerHTML = '<tr><td colspan="6" class="ot-empty">Could not load report</td></tr>';
    }
}

// ===== Helpers =====

function formatTime(isoLike) {
    if (!isoLike) return '—';
    const d = new Date(isoLike.replace(' ', 'T'));
    if (isNaN(d.getTime())) return isoLike;
    return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str ?? '';
    return div.innerHTML;
}
