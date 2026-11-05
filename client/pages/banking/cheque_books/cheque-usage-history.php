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
    <title>Cheque Usage History - LedgerOne ERP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --ink:#172b4d; --muted:#667085; --line:#e4e7ec; --surface:#fff; --canvas:#f5f7fb; --primary:#3157a4; }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--canvas); color:var(--ink); font:14px/1.5 Inter,Arial,sans-serif; }
       .history-wrap {
    max-width: 1500px;
    margin: 24px auto;
    padding: 0 20px 36px;
    margin-left: 69px;
    margin-top: 69px;
}
        .history-card { background:var(--surface); border:1px solid var(--line); border-radius:12px; box-shadow:0 2px 8px #1018280a; padding:20px 24px; margin-bottom:18px; }
        .history-head { display:flex; justify-content:space-between; align-items:center; gap:14px; }
        h1 { font-size:22px; margin:0; } .sub { margin:4px 0 0; color:var(--muted); }
        .filters { display:grid; grid-template-columns:repeat(4,minmax(150px,1fr)); gap:12px; margin-top:18px; }
        label { display:block; font-weight:600; margin-bottom:5px; }
        input,select { width:100%; min-height:40px; padding:8px 10px; border:1px solid #cbd2df; border-radius:7px; background:#fff; color:var(--ink); }
        .filter-actions { display:flex; gap:8px; align-items:end; }
        .button { display:inline-flex; align-items:center; justify-content:center; gap:7px; padding:9px 13px; border:1px solid var(--line); border-radius:7px; background:#fff; color:var(--ink); text-decoration:none; font-weight:600; cursor:pointer; }
        .button.primary { background:var(--primary); border-color:var(--primary); color:#fff; }
        .table-wrap { overflow:auto; }
        table { width:100%; border-collapse:collapse; min-width:1120px; }
        th,td { border-bottom:1px solid var(--line); padding:11px 9px; text-align:left; }
        th { background:#f9fafb; color:#475467; font-size:12px; text-transform:uppercase; }
        .empty { color:var(--muted); padding:28px; text-align:center; }
        @media(max-width:850px) { .filters { grid-template-columns:repeat(2,minmax(140px,1fr)); } }
        @media(max-width:540px) { .history-wrap { margin:12px auto; padding:0 10px 24px; } .history-card { padding:16px; } .filters { grid-template-columns:1fr; } .history-head { align-items:flex-start; flex-direction:column; } }
    </style>
</head>
<body>
<main class="history-wrap">
    <section class="history-card history-head">
        <div><h1>Cheque Usage History</h1><p class="sub">Issued, received and wasted cheque transactions.</p></div>
        <a class="button" href="cheque-books.php"><i class="fa-solid fa-book"></i> Cheque Book Management</a>
    </section>
    <section class="history-card">
        <form id="history-filters" class="filters">
            <div><label for="filter-bank">Bank</label><select id="filter-bank"><option value="">All Banks</option></select></div>
            <div><label for="filter-account">Bank Account</label><select id="filter-account"><option value="">All Accounts</option></select></div>
            <div><label for="filter-account-number">Account Number</label><input id="filter-account-number" type="search"></div>
            <div><label for="filter-holder">Account Holder</label><input id="filter-holder" type="search"></div>
            <div><label for="filter-cheque-number">Cheque Number</label><input id="filter-cheque-number" type="search"></div>
            <div><label for="filter-type">Transaction Type</label>
                <select id="filter-type"><option value="">All Types</option><option>Payment</option><option>Receipt</option><option>Transfer</option><option>Expense</option><option>Payroll</option><option>Production Expense</option><option>Wasted</option></select>
            </div>
            <div><label for="filter-status">Status</label>
                <select id="filter-status"><option value="">All Status</option><option value="pending">Pending</option><option value="approved">Approved</option><option value="cancelled">Cancelled</option></select>
            </div>
            <div><label for="filter-date-from">Date From</label><input id="filter-date-from" type="date"></div>
            <div><label for="filter-date-to">Date To</label><input id="filter-date-to" type="date"></div>
            <div class="filter-actions"><button class="button primary" type="submit"><i class="fa-solid fa-filter"></i> Apply</button><button class="button" id="reset-filters" type="button">Reset</button></div>
        </form>
    </section>
    <section class="history-card">
        <div style="display:flex; justify-content:flex-end; margin-bottom:12px;">
            <button class="button" id="sort-toggle" type="button"><i class="fa-solid fa-arrow-down-wide-short"></i> Newest First</button>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Cheque Number</th><th>Bank Account</th><th>Account Holder</th><th>Transaction Type</th>
                    <th>Voucher Number</th><th>Party / Employee</th><th>Date</th><th>Amount</th><th>Transaction Status</th><th>View Transaction</th></tr></thead>
                <tbody id="history-body"><tr><td colspan="10" class="empty">Loading cheque history...</td></tr></tbody>
            </table>
        </div>
    </section>
</main>
<script src="../../../assets/js/banking/cheque_books/cheque-usage-history.js"></script>
</body>
</html>