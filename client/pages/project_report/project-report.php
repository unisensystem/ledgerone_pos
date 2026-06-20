<?php
require_once '../../../includes/dashboard.php';
if (session_status() == PHP_SESSION_NONE) session_start();
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) { header('Location: ../auth/login.html'); exit(); }

require_once '../../../includes/connection.php';
$stmt = $pdo->prepare("SELECT c.symbol FROM tenant_currencies tc JOIN ledgerone_public.currencies c ON tc.currency_id = c.id WHERE tc.tenant_id = ? AND tc.is_base_currency = 1");
$stmt->execute([$_SESSION['tenant_id']]);
$cur = $stmt->fetch();
$sym = $cur['symbol'] ?? 'Rs.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>LedgerOne ERP | Project Tracking</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="../../assets/css/project_report/project-report.css">
</head>
<body>
<div class="container">

  <!-- ── Header ── -->
  <div class="top-bar">
    <div>
      <h1><i class="fas fa-project-diagram"></i> Project Tracking</h1>
      <p class="subtitle">Project wise — sales, purchases, payments & expenses</p>
    </div>
    <button class="btn btn-outline" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
  </div>

  <!-- ── Filters ── -->
  <div class="filter-bar">
    <div class="fg">
      <label>Select Project</label>
      <select id="sel-project" class="fi"><option value="">— All Projects —</option></select>
    </div>
    <div class="fg">
      <label>Date From</label>
      <input type="date" id="date-from" class="fi">
    </div>
    <div class="fg">
      <label>Date To</label>
      <input type="date" id="date-to" class="fi">
    </div>
    <div class="fg fg-btn">
      <button class="btn btn-primary" id="btn-apply"><i class="fas fa-search"></i> Apply</button>
      <button class="btn btn-outline" id="btn-reset"><i class="fas fa-times"></i> Reset</button>
    </div>
  </div>

  <!-- ── Project Cards Grid (default) ── -->
  <div id="view-cards">
    <div class="section-head"><i class="fas fa-th-large"></i> All Projects <span id="proj-count" class="count-badge">0</span></div>
    <div id="cards-grid"></div>
  </div>

  <!-- ── Detail View ── -->
  <div id="view-detail" style="display:none">

    <!-- Back button + project title -->
    <div class="detail-topbar">
      <button class="btn btn-outline btn-sm" id="btn-back"><i class="fas fa-arrow-left"></i> All Projects</button>
      <div id="detail-title"></div>
    </div>

    <!-- KPI row -->
    <div id="kpi-row"></div>

    <!-- Charts -->
    <div class="charts-grid">
      <div class="card">
        <div class="card-title">Monthly Sales Trend</div>
        <div class="chart-box"><canvas id="ch-trend"></canvas></div>
      </div>
      <div class="card">
        <div class="card-title">Financial Summary</div>
        <div class="chart-box"><canvas id="ch-summary"></canvas></div>
      </div>
    </div>

    <!-- Tabs -->
    <div class="tabs" id="tabs">
      <button class="tab active" data-tab="t-sales">
        <i class="fas fa-receipt"></i> Sales <span class="tab-cnt" id="cnt-sales">0</span>
      </button>
      <button class="tab" data-tab="t-sale-ret">
        <i class="fas fa-undo"></i> Sale Returns <span class="tab-cnt" id="cnt-sale-ret">0</span>
      </button>
      <button class="tab" data-tab="t-purchase">
        <i class="fas fa-shopping-cart"></i> Purchases <span class="tab-cnt" id="cnt-purchase">0</span>
      </button>
      <button class="tab" data-tab="t-purch-ret">
        <i class="fas fa-undo-alt"></i> Purchase Returns <span class="tab-cnt" id="cnt-purch-ret">0</span>
      </button>
      <button class="tab" data-tab="t-received">
        <i class="fas fa-hand-holding-usd"></i> Received <span class="tab-cnt" id="cnt-received">0</span>
      </button>
      <button class="tab" data-tab="t-paid">
        <i class="fas fa-money-bill-wave"></i> Paid <span class="tab-cnt" id="cnt-paid">0</span>
      </button>
      <button class="tab" data-tab="t-expense">
        <i class="fas fa-file-invoice-dollar"></i> Expenses <span class="tab-cnt" id="cnt-expense">0</span>
      </button>
      <button class="tab" data-tab="t-quotations">
        <i class="fas fa-file-alt"></i> Quotations <span class="tab-cnt" id="cnt-quot">0</span>
      </button>
      <button class="tab" data-tab="t-customers">
        <i class="fas fa-user-tie"></i> Customers <span class="tab-cnt" id="cnt-cust">0</span>
      </button>
      <button class="tab" data-tab="t-suppliers">
        <i class="fas fa-truck"></i> Suppliers <span class="tab-cnt" id="cnt-supp">0</span>
      </button>
      <button class="tab" data-tab="t-team">
        <i class="fas fa-users"></i> Team <span class="tab-cnt" id="cnt-team">0</span>
      </button>
    </div>

    <!-- Tab panels -->
    <div id="t-sales" class="tab-panel active">
      <div class="card">
        <div class="panel-head">
          <span><i class="fas fa-receipt"></i> Sale Invoices</span>
          <input type="text" class="fi fi-sm" placeholder="Search..." oninput="filterTable('tbl-sales',this.value)">
        </div>
        <div class="tbl-wrap">
          <table id="tbl-sales">
            <thead><tr><th>Bill #</th><th>Date</th><th>Customer</th><th>Officer</th><th>Items</th><th>Total Bill</th><th>Discount</th><th>Net Amount</th><th>Status</th></tr></thead>
            <tbody></tbody>
          </table>
        </div>
        <div class="tbl-footer" id="foot-sales"></div>
      </div>
    </div>

    <div id="t-sale-ret" class="tab-panel">
      <div class="card">
        <div class="panel-head">
          <span><i class="fas fa-undo"></i> Sale Returns</span>
          <input type="text" class="fi fi-sm" placeholder="Search..." oninput="filterTable('tbl-sale-ret',this.value)">
        </div>
        <div class="tbl-wrap">
          <table id="tbl-sale-ret">
            <thead><tr><th>Bill #</th><th>Date</th><th>Customer</th><th>Total Bill</th><th>Net Amount</th></tr></thead>
            <tbody></tbody>
          </table>
        </div>
        <div class="tbl-footer" id="foot-sale-ret"></div>
      </div>
    </div>

    <div id="t-purchase" class="tab-panel">
      <div class="card">
        <div class="panel-head">
          <span><i class="fas fa-shopping-cart"></i> Purchase Invoices</span>
          <input type="text" class="fi fi-sm" placeholder="Search..." oninput="filterTable('tbl-purchase',this.value)">
        </div>
        <div class="tbl-wrap">
          <table id="tbl-purchase">
            <thead><tr><th>Bill #</th><th>Date</th><th>Supplier</th><th>Items</th><th>Total Bill</th><th>Net Amount</th><th>Status</th></tr></thead>
            <tbody></tbody>
          </table>
        </div>
        <div class="tbl-footer" id="foot-purchase"></div>
      </div>
    </div>

    <div id="t-purch-ret" class="tab-panel">
      <div class="card">
        <div class="panel-head">
          <span><i class="fas fa-undo-alt"></i> Purchase Returns</span>
          <input type="text" class="fi fi-sm" placeholder="Search..." oninput="filterTable('tbl-purch-ret',this.value)">
        </div>
        <div class="tbl-wrap">
          <table id="tbl-purch-ret">
            <thead><tr><th>Bill #</th><th>Date</th><th>Supplier</th><th>Total Bill</th><th>Net Amount</th></tr></thead>
            <tbody></tbody>
          </table>
        </div>
        <div class="tbl-footer" id="foot-purch-ret"></div>
      </div>
    </div>

    <div id="t-received" class="tab-panel">
      <div class="card">
        <div class="panel-head">
          <span><i class="fas fa-hand-holding-usd"></i> Received from Customers</span>
          <input type="text" class="fi fi-sm" placeholder="Search..." oninput="filterTable('tbl-received',this.value)">
        </div>
        <div class="tbl-wrap">
          <table id="tbl-received">
            <thead><tr><th>Voucher #</th><th>Date</th><th>Customer</th><th>Description</th><th>Amount</th></tr></thead>
            <tbody></tbody>
          </table>
        </div>
        <div class="tbl-footer" id="foot-received"></div>
      </div>
    </div>

    <div id="t-paid" class="tab-panel">
      <div class="card">
        <div class="panel-head">
          <span><i class="fas fa-money-bill-wave"></i> Paid to Suppliers</span>
          <input type="text" class="fi fi-sm" placeholder="Search..." oninput="filterTable('tbl-paid',this.value)">
        </div>
        <div class="tbl-wrap">
          <table id="tbl-paid">
            <thead><tr><th>Voucher #</th><th>Date</th><th>Supplier</th><th>Description</th><th>Amount</th></tr></thead>
            <tbody></tbody>
          </table>
        </div>
        <div class="tbl-footer" id="foot-paid"></div>
      </div>
    </div>

    <div id="t-expense" class="tab-panel">
      <div class="card">
        <div class="panel-head">
          <span><i class="fas fa-file-invoice-dollar"></i> Expense Vouchers</span>
          <input type="text" class="fi fi-sm" placeholder="Search..." oninput="filterTable('tbl-expense',this.value)">
        </div>
        <div class="tbl-wrap">
          <table id="tbl-expense">
            <thead><tr><th>Voucher #</th><th>Date</th><th>Description</th><th>Total Amount</th></tr></thead>
            <tbody></tbody>
          </table>
        </div>
        <div class="tbl-footer" id="foot-expense"></div>
      </div>
    </div>

    <div id="t-quotations" class="tab-panel">
      <div class="card">
        <div class="panel-head">
          <span><i class="fas fa-file-alt"></i> Quotations</span>
          <input type="text" class="fi fi-sm" placeholder="Search..." oninput="filterTable('tbl-quot',this.value)">
        </div>
        <div class="tbl-wrap">
          <table id="tbl-quot">
            <thead><tr><th>Quotation #</th><th>Date</th><th>Valid Till</th><th>Customer</th><th>Salesman</th><th>Items</th><th>Subtotal</th><th>Grand Total</th><th>Status</th></tr></thead>
            <tbody></tbody>
          </table>
        </div>
        <div class="tbl-footer" id="foot-quot"></div>
      </div>
    </div>

    <div id="t-customers" class="tab-panel">
      <div class="card">
        <div class="panel-head">
          <span><i class="fas fa-user-tie"></i> Linked Customers</span>
          <input type="text" class="fi fi-sm" placeholder="Search..." oninput="filterTable('tbl-cust',this.value)">
        </div>
        <div class="tbl-wrap">
          <table id="tbl-cust">
            <thead><tr><th>Code</th><th>Name</th><th>Phone</th><th>Current Balance</th><th>Status</th></tr></thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </div>

    <div id="t-suppliers" class="tab-panel">
      <div class="card">
        <div class="panel-head">
          <span><i class="fas fa-truck"></i> Linked Suppliers</span>
          <input type="text" class="fi fi-sm" placeholder="Search..." oninput="filterTable('tbl-supp',this.value)">
        </div>
        <div class="tbl-wrap">
          <table id="tbl-supp">
            <thead><tr><th>Code</th><th>Name</th><th>Phone</th><th>Current Balance</th><th>Status</th></tr></thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </div>

    <div id="t-team" class="tab-panel">
      <div class="card">
        <div class="panel-head"><span><i class="fas fa-users"></i> Team Members</span></div>
        <div class="tbl-wrap">
          <table id="tbl-team">
            <thead><tr><th>Code</th><th>Name</th><th>Designation</th><th>Phone</th><th>Added On</th></tr></thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </div>

  </div><!-- /view-detail -->

  <div class="pg-footer">LedgerOne ERP • Project Tracking • <span id="ftr-date"></span></div>
</div>

<script>const SYM = '<?php echo addslashes($sym); ?>';</script>
<script src="../../assets/js/project_report/project-report.js"></script>
</body>
</html>
