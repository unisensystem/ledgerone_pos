/**
 * Activity History widget — the "Who changed this?" button.
 * Drop into any page with:
 *   <script src="/ledgerone_erp/client/assets/js/shared/activity-history-widget.js"></script>
 *   <button onclick="openActivityHistory('Sale Invoice', 123, 'INV-1052')"><i class="fas fa-history"></i> Activity History</button>
 *
 * Self-contained: injects its own modal markup and scoped styles on first use, so it
 * works regardless of which page/module CSS is already loaded.
 */
(function () {
    const API_URL = '/ledgerone_erp/server/api/system_setup/admin_panel/record-history.php';

    const ACTION_META = {
        create: { label: 'Created', icon: 'fa-plus-circle', color: '#2fbf71' },
        update: { label: 'Updated', icon: 'fa-pen', color: '#e8b23f' },
        delete: { label: 'Deleted', icon: 'fa-trash', color: '#e34f4f' },
    };

    function ahEscapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    // Mirrors the same non-Date-object reformat used by the Activity & Audit Center, so
    // times display in the DB server's own wall-clock time rather than the browser's.
    function ahFormatDateTime(dateTimeStr) {
        if (!dateTimeStr) return '-';
        const match = String(dateTimeStr).match(/^(\d{4}-\d{2}-\d{2})[ T](\d{2}):(\d{2}):(\d{2})/);
        if (!match) return dateTimeStr;
        const [, datePart, hStr, m] = match;
        let h = parseInt(hStr, 10);
        const ampm = h >= 12 ? 'PM' : 'AM';
        h = h % 12 || 12;
        return `${datePart} ${String(h).padStart(2, '0')}:${m} ${ampm}`;
    }

    function ahInjectOnce() {
        if (document.getElementById('ahModalOverlay')) return;

        const style = document.createElement('style');
        style.textContent = `
            #ahModalOverlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 10050; justify-content: center; align-items: center; }
            #ahModalOverlay.ah-open { display: flex; }
            .ah-modal { background: #fff; border-radius: 8px; width: 92%; max-width: 560px; max-height: 82vh; display: flex; flex-direction: column; box-shadow: 0 20px 60px rgba(0,0,0,0.3); font-family: inherit; }
            .ah-header { display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; border-bottom: 1px solid #e1e6ee; }
            .ah-title { font-size: 16px; font-weight: 700; color: #0e1a2b; display: flex; align-items: center; gap: 8px; }
            .ah-subtitle { font-size: 12px; color: #6b7280; margin-top: 2px; }
            .ah-close { background: none; border: none; font-size: 22px; line-height: 1; color: #6b7280; cursor: pointer; }
            .ah-close:hover { color: #0e1a2b; }
            .ah-body { padding: 16px 20px; overflow-y: auto; }
            .ah-timeline { list-style: none; margin: 0; padding: 0; }
            .ah-item { display: flex; gap: 12px; padding-bottom: 18px; position: relative; }
            .ah-item:not(:last-child)::before { content: ''; position: absolute; left: 13px; top: 28px; bottom: 0; width: 2px; background: #e1e6ee; }
            .ah-dot { flex-shrink: 0; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 12px; z-index: 1; }
            .ah-content { flex: 1; padding-top: 3px; }
            .ah-meta { font-size: 12px; color: #6b7280; margin-bottom: 2px; }
            .ah-meta b { color: #0e1a2b; }
            .ah-desc { font-size: 13px; color: #0e1a2b; line-height: 1.5; }
            .ah-empty, .ah-loading, .ah-error { text-align: center; padding: 36px 16px; color: #6b7280; font-size: 13px; }
            .ah-error { color: #e34f4f; }
        `;
        document.head.appendChild(style);

        const overlay = document.createElement('div');
        overlay.id = 'ahModalOverlay';
        overlay.innerHTML = `
            <div class="ah-modal">
                <div class="ah-header">
                    <div>
                        <div class="ah-title"><i class="fas fa-history"></i> Activity History</div>
                        <div class="ah-subtitle" id="ahSubtitle"></div>
                    </div>
                    <button class="ah-close" onclick="closeActivityHistory()">&times;</button>
                </div>
                <div class="ah-body" id="ahBody"></div>
            </div>
        `;
        overlay.addEventListener('click', (e) => { if (e.target === overlay) closeActivityHistory(); });
        document.body.appendChild(overlay);
    }

    window.openActivityHistory = async function (module, recordId, recordLabel) {
        ahInjectOnce();
        const overlay = document.getElementById('ahModalOverlay');
        const body = document.getElementById('ahBody');
        document.getElementById('ahSubtitle').textContent = recordLabel ? `${module} · ${recordLabel}` : module;
        overlay.classList.add('ah-open');
        body.innerHTML = '<div class="ah-loading"><i class="fas fa-spinner fa-spin"></i> Loading history...</div>';

        try {
            const params = new URLSearchParams({ module, record_id: recordId });
            const response = await fetch(`${API_URL}?${params}`);
            const data = await response.json();

            if (!data.success) {
                body.innerHTML = '<div class="ah-error">Failed to load activity history</div>';
                return;
            }

            if (data.data.length === 0) {
                body.innerHTML = '<div class="ah-empty"><i class="fas fa-inbox" style="font-size:24px;display:block;margin-bottom:8px;opacity:0.5;"></i>No recorded activity for this record yet</div>';
                return;
            }

            body.innerHTML = `<ul class="ah-timeline">${data.data.map(entry => {
                const meta = ACTION_META[entry.action] || { label: entry.action, icon: 'fa-circle', color: '#6b7280' };
                return `<li class="ah-item">
                    <div class="ah-dot" style="background:${meta.color};"><i class="fas ${meta.icon}"></i></div>
                    <div class="ah-content">
                        <div class="ah-meta">${ahEscapeHtml(ahFormatDateTime(entry.created_at))} &mdash; <b>${ahEscapeHtml(entry.user_name)}</b> ${ahEscapeHtml(meta.label.toLowerCase())} this record</div>
                        <div class="ah-desc">${ahEscapeHtml(entry.description)}</div>
                    </div>
                </li>`;
            }).join('')}</ul>`;
        } catch (error) {
            console.error('Error fetching activity history:', error);
            body.innerHTML = '<div class="ah-error">Failed to load activity history</div>';
        }
    };

    window.closeActivityHistory = function () {
        const overlay = document.getElementById('ahModalOverlay');
        if (overlay) overlay.classList.remove('ah-open');
    };
})();
