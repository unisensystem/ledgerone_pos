<?php
require_once '../../../../includes/dashboard.php';

require_once '../../../../includes/connection.php';
if (session_status() == PHP_SESSION_NONE) session_start();

$user_id = $_SESSION['user_id'] ?? null;
$tenant_id = $_SESSION['tenant_id'] ?? null;
if (!$user_id || !$tenant_id) { header('Location: ../../auth/login.html'); exit(); }

$modules = [
    'receive_voucher'  => ['label' => 'Receive Voucher',  'icon' => 'fa-money-check-alt'],
    'payment_voucher'  => ['label' => 'Payment Voucher',  'icon' => 'fa-file-invoice-dollar'],
    'journal_voucher'  => ['label' => 'Journal Voucher',  'icon' => 'fa-book'],
    'expense_voucher'  => ['label' => 'Expense Voucher',  'icon' => 'fa-receipt'],
    'sale_invoice'     => ['label' => 'Sale Invoice',     'icon' => 'fa-file-invoice'],
    'purchase_invoice' => ['label' => 'Purchase Invoice', 'icon' => 'fa-shopping-cart'],
    'sale_return' => ['label' => 'Sale Return', 'icon' => 'fa-undo'],
    'stock_adjustment' => ['label' => 'Stock Adjustment', 'icon' => 'fa-boxes'],
];

$stmt = $pdo->prepare("SELECT module_key, lock_date FROM posting_lock_dates WHERE tenant_id = ?");
$stmt->execute([$tenant_id]);
$existing = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $existing[$row['module_key']] = $row['lock_date'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Posting Lock Dates - LedgerOne ERP</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../../../assets/css/dashboard/dashboard.css">
<style>
    .lock-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        flex-wrap: wrap;
        gap: 1rem;
    }
    .lock-back-link {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        color: #64748b;
        text-decoration: none;
        font-size: 13px;
        font-weight: 600;
        margin-bottom: 0.75rem;
    }
    .lock-back-link:hover { color: var(--primary, #1f7bff); }

    .apply-all-card {
        background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
        border: 1.5px solid #bfdbfe;
        border-radius: 0.875rem;
        padding: 1.25rem 1.5rem;
        margin-bottom: 1.75rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        flex-wrap: wrap;
    }
    .apply-all-icon {
        width: 44px;
        height: 44px;
        border-radius: 0.625rem;
        background: #1f7bff;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.125rem;
        flex-shrink: 0;
    }
    .apply-all-text {
        flex: 1;
        min-width: 180px;
    }
    .apply-all-text strong {
        display: block;
        font-size: 14px;
        color: #0f172a;
        margin-bottom: 0.15rem;
    }
    .apply-all-text span {
        font-size: 12px;
        color: #64748b;
    }
    .apply-all-controls {
        display: flex;
        gap: 0.75rem;
        align-items: center;
    }
    .apply-all-controls input[type="date"] {
        height: 42px;
        padding: 0 0.875rem;
        border: 1.5px solid #bfdbfe;
        border-radius: 0.5rem;
        font-size: 14px;
        background: white;
    }
    .apply-all-btn {
        height: 42px;
        padding: 0 1.25rem;
        border: none;
        border-radius: 0.5rem;
        background: #1f7bff;
        color: white;
        font-weight: 600;
        font-size: 13px;
        cursor: pointer;
        white-space: nowrap;
        transition: opacity 0.2s;
    }
    .apply-all-btn:hover { opacity: 0.9; }
    .apply-all-btn:disabled { opacity: 0.6; cursor: not-allowed; }

    .lock-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 1.25rem;
    }

    .lock-card {
        background: white;
        border: 1.5px solid #e2e8f0;
        border-radius: 0.875rem;
        padding: 1.5rem;
        transition: all 0.2s;
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    .lock-card:hover {
        border-color: #cbd5e1;
        box-shadow: 0 4px 12px rgba(0,0,0,0.06);
    }

    .lock-card-top {
        display: flex;
        align-items: center;
        gap: 0.875rem;
    }
    .lock-icon {
        width: 44px;
        height: 44px;
        border-radius: 0.625rem;
        background: #eff6ff;
        color: #1f7bff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.125rem;
        flex-shrink: 0;
    }
    .lock-card-title {
        font-size: 1rem;
        font-weight: 700;
        color: #0f172a;
        margin: 0;
    }
    .lock-status {
        font-size: 12px;
        margin-top: 0.15rem;
    }
    .lock-status.set { color: #dc2626; font-weight: 600; }
    .lock-status.unset { color: #94a3b8; }

    .lock-card-body {
        display: flex;
        gap: 0.75rem;
        align-items: center;
    }
    .lock-card-body input[type="date"] {
        flex: 1;
        height: 42px;
        padding: 0 0.875rem;
        border: 1.5px solid #d6dbe4;
        border-radius: 0.5rem;
        font-size: 14px;
    }
    .lock-save-btn {
        height: 42px;
        padding: 0 1.25rem;
        border: none;
        border-radius: 0.5rem;
        background: linear-gradient(135deg, #1f7bff 0%, #1559b8 100%);
        color: white;
        font-weight: 600;
        font-size: 13px;
        cursor: pointer;
        white-space: nowrap;
        transition: opacity 0.2s;
    }
    .lock-save-btn:hover { opacity: 0.9; }
    .lock-save-btn:disabled { opacity: 0.6; cursor: not-allowed; }

    .lock-clear-btn {
        font-size: 12px;
        color: #94a3b8;
        background: none;
        border: none;
        cursor: pointer;
        text-align: left;
        padding: 0;
        width: fit-content;
    }
    .lock-clear-btn:hover { color: #dc2626; }

    .lock-toast {
        position: fixed;
        bottom: 24px;
        right: 24px;
        padding: 1rem 1.25rem;
        border-radius: 0.75rem;
        box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        z-index: 9999;
        font-size: 14px;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        animation: slideUp 0.3s ease;
    }
    .lock-toast.success { background: #16a34a; color: white; }
    .lock-toast.error { background: #dc2626; color: white; }
    @keyframes slideUp {
        from { transform: translateY(20px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }

    @media (max-width: 768px) {
        .lock-grid { grid-template-columns: 1fr; }
        .lock-card-body { flex-direction: column; align-items: stretch; }
        .apply-all-card { flex-direction: column; align-items: stretch; }
        .apply-all-controls { flex-direction: column; align-items: stretch; }
    }
</style>
</head>
<body>
<div class="container">

    <!-- <a href="../../dashboard/dashboard.php" class="lock-back-link">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a> -->

    <div class="lock-header">
        <div>
            <h1 style="margin:0;">Posting Lock Dates</h1>
            <p style="color:#64748b; margin-top:0.25rem;">
                Set a lock date per module. Entries dated after the lock date cannot be posted.
            </p>
        </div>
    </div>

    <div class="apply-all-card">
        <div class="apply-all-icon"><i class="fas fa-layer-group"></i></div>
        <div class="apply-all-text">
            <strong>Apply one date to all modules</strong>
            <span>Sets this lock date on every module below in one click. You can still edit any module individually after.</span>
        </div>
        <div class="apply-all-controls">
            <input type="date" id="applyAllDate">
            <button class="apply-all-btn" id="applyAllBtn">
                <i class="fas fa-check-double"></i> Apply to All
            </button>
        </div>
    </div>

    <div class="lock-grid">
        <?php foreach ($modules as $key => $mod): ?>
            <?php $lockDate = $existing[$key] ?? ''; ?>
            <div class="lock-card">
                <div class="lock-card-top">
                    <div class="lock-icon"><i class="fas <?= $mod['icon'] ?>"></i></div>
                    <div>
                        <h3 class="lock-card-title"><?= htmlspecialchars($mod['label']) ?></h3>
                        <div class="lock-status <?= $lockDate ? 'set' : 'unset' ?>" data-status-for="<?= $key ?>">
                            <?= $lockDate
                                ? '<i class="fas fa-lock"></i> Locked after ' . htmlspecialchars($lockDate)
                                : '<i class="fas fa-lock-open"></i> No lock set' ?>
                        </div>
                    </div>
                </div>
                <div class="lock-card-body">
                    <input type="date" class="lock-date-input" data-module="<?= $key ?>" value="<?= htmlspecialchars($lockDate) ?>">
                    <button class="lock-save-btn save-lock-btn" data-module="<?= $key ?>">
                        <i class="fas fa-check"></i> Save
                    </button>
                </div>
                <?php if ($lockDate): ?>
                    <button class="lock-clear-btn clear-lock-btn" data-module="<?= $key ?>">
                        <i class="fas fa-times"></i> Remove lock
                    </button>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

</div>

<script>
function showToast(message, isSuccess) {
    const toast = document.createElement('div');
    toast.className = 'lock-toast ' + (isSuccess ? 'success' : 'error');
    toast.innerHTML = `<i class="fas ${isSuccess ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i> ${message}`;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3500);
}

function updateStatus(module, lockDate) {
    const el = document.querySelector(`[data-status-for="${module}"]`);
    if (!el) return;
    if (lockDate) {
        el.className = 'lock-status set';
        el.innerHTML = `<i class="fas fa-lock"></i> Locked after ${lockDate}`;
    } else {
        el.className = 'lock-status unset';
        el.innerHTML = `<i class="fas fa-lock-open"></i> No lock set`;
    }
}

async function saveLockDate(module, lockDate) {
    const res = await fetch('../../../../server/api/settings/posting_lock/save-lock-date.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ module_key: module, lock_date: lockDate })
    });
    return res.json();
}

document.querySelectorAll('.save-lock-btn').forEach(btn => {
    btn.addEventListener('click', async function () {
        const module = this.dataset.module;
        const input = document.querySelector(`.lock-date-input[data-module="${module}"]`);
        const lockDate = input.value;
        if (!lockDate) { showToast('Please select a date first', false); return; }

        this.disabled = true;
        const originalHtml = this.innerHTML;
        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

        try {
            const data = await saveLockDate(module, lockDate);
            if (data.success) {
                showToast('Lock date saved successfully', true);
                updateStatus(module, lockDate);
            } else {
                showToast(data.message || data.error || 'Failed to save', false);
            }
        } catch (err) {
            console.error(err);
            showToast('Network error occurred', false);
        } finally {
            this.disabled = false;
            this.innerHTML = originalHtml;
        }
    });
});

document.querySelectorAll('.clear-lock-btn').forEach(btn => {
    btn.addEventListener('click', async function () {
        if (!confirm('Remove the lock date for this module?')) return;
        const module = this.dataset.module;

        try {
            const data = await saveLockDate(module, null);
            if (data.success) {
                document.querySelector(`.lock-date-input[data-module="${module}"]`).value = '';
                updateStatus(module, '');
                this.remove();
                showToast('Lock removed', true);
            } else {
                showToast(data.message || 'Failed to remove lock', false);
            }
        } catch (err) {
            console.error(err);
            showToast('Network error occurred', false);
        }
    });
});

// ===== Apply to All =====
document.getElementById('applyAllBtn').addEventListener('click', async function () {
    const dateInput = document.getElementById('applyAllDate');
    const lockDate = dateInput.value;
    if (!lockDate) { showToast('Please select a date first', false); return; }

    if (!confirm(`Apply ${lockDate} as the lock date for ALL modules?`)) return;

    this.disabled = true;
    const originalHtml = this.innerHTML;
    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Applying...';

    const moduleInputs = document.querySelectorAll('.lock-date-input');
    let successCount = 0;
    let failCount = 0;

    for (const input of moduleInputs) {
        const module = input.dataset.module;
        try {
            const data = await saveLockDate(module, lockDate);
            if (data.success) {
                input.value = lockDate;
                updateStatus(module, lockDate);
                successCount++;

                // Show the "Remove lock" button on this card if it wasn't there before
                const card = input.closest('.lock-card');
                if (card && !card.querySelector('.clear-lock-btn')) {
                    const clearBtn = document.createElement('button');
                    clearBtn.className = 'lock-clear-btn clear-lock-btn';
                    clearBtn.dataset.module = module;
                    clearBtn.innerHTML = '<i class="fas fa-times"></i> Remove lock';
                    clearBtn.addEventListener('click', async function () {
                        if (!confirm('Remove the lock date for this module?')) return;
                        const mod = this.dataset.module;
                        const res = await saveLockDate(mod, null);
                        if (res.success) {
                            document.querySelector(`.lock-date-input[data-module="${mod}"]`).value = '';
                            updateStatus(mod, '');
                            this.remove();
                            showToast('Lock removed', true);
                        } else {
                            showToast(res.message || 'Failed to remove lock', false);
                        }
                    });
                    card.appendChild(clearBtn);
                }
            } else {
                failCount++;
            }
        } catch (err) {
            console.error(err);
            failCount++;
        }
    }

    this.disabled = false;
    this.innerHTML = originalHtml;

    if (failCount === 0) {
        showToast(`Lock date applied to all ${successCount} modules`, true);
    } else {
        showToast(`Applied to ${successCount} modules, ${failCount} failed`, false);
    }
});
</script>
</body>
</html>