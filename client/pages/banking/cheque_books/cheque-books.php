<?php
require_once '../../../../includes/dashboard.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['user_id']) || empty($_SESSION['tenant_id'])) {
    header('Location: ../../auth/login.html');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cheque Book Management - LedgerOne ERP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --ink:#172b4d; --muted:#667085; --line:#e4e7ec; --surface:#fff; --canvas:#f5f7fb; --primary:#3157a4; --danger:#b42318; }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--canvas); color:var(--ink); font:14px/1.5 Inter,Arial,sans-serif; }
        .cb-wrap { max-width:1420px; margin:24px auto; padding:0 20px 36px; }
        .cb-header,.cb-card { background:var(--surface); border:1px solid var(--line); border-radius:12px; box-shadow:0 2px 8px #1018280a; }
        .cb-header { display:flex; align-items:center; justify-content:space-between; gap:16px; padding:20px 24px; margin-bottom:18px; }
        .cb-header h1 { margin:0; font-size:22px; }
        .cb-header p { color:var(--muted); margin:4px 0 0; }
        .cb-actions,.cb-row-actions { display:flex; flex-wrap:wrap; gap:8px; }
        .cb-card { padding:20px 24px; margin-bottom:18px; }
        .cb-card h2 { margin:0 0 16px; font-size:17px; }
        .cb-form { display:grid; grid-template-columns:repeat(4,minmax(150px,1fr)); gap:14px; align-items:end; }
        .cb-field label { display:block; font-weight:600; margin-bottom:5px; }
        .cb-field input,.cb-field select,.cb-field textarea { width:100%; min-height:40px; padding:8px 10px; border:1px solid #cbd2df; border-radius:7px; color:var(--ink); background:#fff; }
        .cb-field textarea { min-height:40px; resize:vertical; }
        .cb-field input[readonly] { background:#f2f4f7; }
        .cb-button { display:inline-flex; align-items:center; justify-content:center; gap:7px; border:1px solid transparent; border-radius:7px; padding:9px 13px; font-weight:600; cursor:pointer; color:#fff; background:var(--primary); text-decoration:none; }
        .cb-button.secondary { color:var(--ink); background:#fff; border-color:var(--line); }
        .cb-button.danger { background:var(--danger); }
        .cb-button.small { padding:6px 9px; font-size:12px; }
        .cb-table-wrap { overflow:auto; }
        .cb-table { width:100%; border-collapse:collapse; min-width:850px; }
        .cb-table th,.cb-table td { border-bottom:1px solid var(--line); padding:11px 9px; text-align:left; vertical-align:middle; }
        .cb-table th { color:#475467; background:#f9fafb; font-size:12px; text-transform:uppercase; letter-spacing:.03em; }
        .cb-empty { padding:28px; text-align:center; color:var(--muted); }
        .cb-message { display:none; margin-bottom:14px; padding:10px 12px; border-radius:7px; }
        .cb-message.error { display:block; background:#fef3f2; color:#b42318; }
        .cb-message.success { display:block; background:#ecfdf3; color:#027a48; }
        .cb-dialog { width:min(680px,calc(100vw - 24px)); border:0; border-radius:12px; padding:0; box-shadow:0 20px 60px #10182844; }
        .cb-dialog::backdrop { background:#10182880; }
        .cb-dialog-inner { padding:20px; }
        .cb-dialog-head { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:14px; }
        .cb-dialog-head h2 { margin:0; font-size:18px; }
        .cb-dialog-close { border:0; background:transparent; font-size:20px; cursor:pointer; }
        .cb-modal-form { display:grid; gap:12px; }
        @media (max-width:900px) { .cb-form { grid-template-columns:repeat(2,minmax(150px,1fr)); } }
        @media (max-width:560px) { .cb-wrap { margin:12px auto; padding:0 10px 24px; } .cb-header { align-items:flex-start; flex-direction:column; padding:16px; } .cb-card { padding:16px; } .cb-form { grid-template-columns:1fr; } }
    </style>
</head>
<body>
<main class="cb-wrap" style="margin-left: 69px; ">
    <header class="cb-header">
        <div>
            <h1>Cheque Book Management</h1>
            <p>Create cheque books and track available, used, and wasted cheque numbers.</p>
        </div>
        <div class="cb-actions">
            <a class="cb-button secondary" href="cheque-usage-history.php"><i class="fa-solid fa-clock-rotate-left"></i> Cheque Usage History</a>
        </div>
    </header>

    <div id="cb-message" class="cb-message" role="status"></div>
    <section class="cb-card">
        <h2 id="form-heading">Add Cheque Book</h2>
        <form id="cheque-book-form" class="cb-form">
            <input id="book-id" type="hidden">
            <div class="cb-field">
                <label for="bank-select">Bank</label>
                <select id="bank-select" required><option value="">Select bank</option></select>
            </div>
            <div class="cb-field">
                <label for="bank-account-select">Bank Account</label>
                <select id="bank-account-select" required disabled><option value="">Select bank account</option></select>
            </div>
            <div class="cb-field">
                <label for="account-holder">Account Holder Name</label>
                <input id="account-holder" type="text" readonly placeholder="Auto-filled from bank account">
            </div>
            <div class="cb-field">
                <label for="issue-date">Issue Date</label>
                <input id="issue-date" type="date" required>
            </div>
            <div class="cb-field">
                <label for="cheque-from">Cheque Number From</label>
                <input id="cheque-from" type="text" placeholder="e.g. D-92851806" autocomplete="off" required>
            </div>
            <div class="cb-field">
                <label for="cheque-to">Cheque Number To</label>
                <input id="cheque-to" type="text" placeholder="e.g. D-92851855" autocomplete="off" required>
            </div>
            <div class="cb-field">
                <label for="total-cheques">Total Cheques</label>
                <input id="total-cheques" type="number" readonly value="0">
            </div>
            <div class="cb-field">
                <label for="remarks">Remarks</label>
                <input id="remarks" type="text" maxlength="1000">
            </div>
            <div class="cb-actions">
                <button class="cb-button" id="save-book" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save</button>
                <button class="cb-button secondary" id="cancel-edit" type="button" hidden>Cancel Edit</button>
            </div>
        </form>
    </section>

    <section class="cb-card">
        <h2>Cheque Books</h2>
        <div class="cb-table-wrap">
            <table class="cb-table">
                <thead><tr>
                    <th>Bank / Account</th><th>Account Holder</th><th>Cheque Range</th><th>Total</th>
                    <th>Available</th><th>Used</th><th>Wasted</th><th>Issue Date</th><th>Actions</th>
                </tr></thead>
                <tbody id="cheque-books-body"><tr><td class="cb-empty" colspan="9">Loading cheque books...</td></tr></tbody>
            </table>
        </div>
    </section>
</main>

<dialog class="cb-dialog" id="waste-dialog">
    <form class="cb-dialog-inner cb-modal-form" id="waste-form">
        <div class="cb-dialog-head"><h2>Mark Cheque as Wasted</h2><button class="cb-dialog-close" type="button" data-close-dialog="waste-dialog" aria-label="Close">&times;</button></div>
        <input id="waste-book-id" type="hidden">
        <div class="cb-field"><label for="waste-cheque-number">Cheque Number</label><select id="waste-cheque-number" required></select></div>
        <div class="cb-field"><label for="waste-date">Waste Date</label><input id="waste-date" type="date" required></div>
        <div class="cb-field"><label for="waste-reason">Reason</label><textarea id="waste-reason" required maxlength="1000"></textarea></div>
        <div class="cb-actions"><button class="cb-button danger" type="submit">Save Wasted Cheque</button></div>
    </form>
</dialog>

<dialog class="cb-dialog" id="view-dialog">
    <div class="cb-dialog-inner">
        <div class="cb-dialog-head"><h2>Cheque Book Details</h2><button class="cb-dialog-close" type="button" data-close-dialog="view-dialog" aria-label="Close">&times;</button></div>
        <div id="view-book-content"></div>
    </div>
</dialog>
<script src="../../../assets/js/banking/cheque_books/cheque-books.js"></script>
</body>
</html>