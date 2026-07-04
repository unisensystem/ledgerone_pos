<?php
require_once '../../../../includes/dashboard.php';
if (session_status() == PHP_SESSION_NONE) session_start();
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) { header('Location: ../auth/login.html'); exit(); }

require_once '../../../../includes/connection.php';
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
<title>LedgerOne ERP | Container Tracking</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="../../../assets/css/container_tracking/container-tracking.css">
</head>
<body>
<div class="container">

  <!-- ── Header ── -->
  <div class="top-bar">
    <div>
      <h1><i class="fas fa-box"></i> Container Tracking</h1>
      <p class="subtitle">Track container movement — inbound, outbound, sales, purchases & expenses</p>
    </div>
    <button class="btn btn-outline" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
  </div>

  <!-- ── Filters ── -->
  <div class="filter-bar">
    <div class="fg">
      <label>Select Container</label>
      <select id="sel-container" class="fi"><option value="">— All Containers —</option></select>
    </div>
    <div class="fg">
      <label>Status</label>
      <select id="sel-status" class="fi">
        <option value="">— All Status —</option>
        <option value="active">Active</option>
        <option value="in_transit">In Transit</option>
        <option value="delivered">Delivered</option>
        <option value="returned">Returned</option>
      </select>
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

  <!-- ── Container Cards Grid ── -->
  <div id="view-cards">
    <div class="section-head"><i class="fas fa-boxes"></i> All Containers <span id="container-count" class="count-badge">0</span></div>
    <div id="cards-grid"></div>
  </div>

  <!-- ── Detail View ── -->
  <div id="view-detail" style="display:none">

    <!-- Back button + container title -->
    <div class="detail-topbar">
      <button class="btn btn-outline btn-sm" id="btn-back"><i class="fas fa-arrow-left"></i> All Containers</button>
      <div id="detail-title"></div>
    </div>

    <!-- KPI Row -->
    <div id="kpi-row"></div>

    <!-- Charts -->
    <div class="charts-grid">
      <div class="card">
        <div class="card-title">Container Movement Timeline</div>
        <div class="chart-box"><canvas id="ch-timeline"></canvas></div>
      </div>
      <div class="card">
        <div class="card-title">Financial Summary</div>
        <div class="chart-box"><canvas id="ch-summary"></canvas></div>
      </div>
    </div>

    <!-- Tabs -->
    <div class="tabs" id="tabs">
      <button class="tab active" data-tab="t-timeline">
        <i class="fas fa-history"></i> Timeline <span class="tab-cnt" id="cnt-timeline">0</span>
      </button>
      <button class="tab" data-tab="t-inbound">
        <i class="fas fa-arrow-right"></i> Inward <span class="tab-cnt" id="cnt-inbound">0</span>
      </button>
      <button class="tab" data-tab="t-outbound">
        <i class="fas fa-arrow-left"></i> Outward <span class="tab-cnt" id="cnt-outbound">0</span>
      </button>
      <button class="tab" data-tab="t-transfers">
        <i class="fas fa-exchange-alt"></i> Transfers <span class="tab-cnt" id="cnt-transfers">0</span>
      </button>
      <button class="tab" data-tab="t-sales">
        <i class="fas fa-receipt"></i> Sales <span class="tab-cnt" id="cnt-sales">0</span>
      </button>
      <button class="tab" data-tab="t-purchases">
        <i class="fas fa-shopping-cart"></i> Purchases <span class="tab-cnt" id="cnt-purchases">0</span>
      </button>
      <button class="tab" data-tab="t-received">
        <i class="fas fa-hand-holding-usd"></i> Received <span class="tab-cnt" id="cnt-received">0</span>
      </button>
      <button class="tab" data-tab="t-paid">
        <i class="fas fa-money-bill-wave"></i> Paid <span class="tab-cnt" id="cnt-paid">0</span>
      </button>
      <button class="tab" data-tab="t-expenses">
        <i class="fas fa-file-invoice-dollar"></i> Expenses <span class="tab-cnt" id="cnt-expenses">0</span>
      </button>
    </div>

    <!-- Tab Panels -->
    <div id="t-timeline" class="tab-panel active">
      <div class="card">
        <div class="panel-head">
          <span><i class="fas fa-history"></i> Container Movement Timeline</span>
          <input type="text" class="fi fi-sm" placeholder="Search..." oninput="filterTable('tbl-timeline',this.value)">
        </div>
        <div class="tbl-wrap">
          <table id="tbl-timeline">
            <thead><tr><th>Date</th><th>Event Type</th><th>From Location</th><th>To Location</th><th>Document #</th><th>Quantity</th><th>Amount</th><th>Status</th></tr></thead>
            <tbody></tbody>
          </table>
        </div>
        <div class="tbl-footer" id="foot-timeline"></div>
      </div>
    </div>

    <div id="t-inbound" class="tab-panel">
      <div class="card">
        <div class="panel-head">
          <span><i class="fas fa-arrow-right"></i> Inward Gatepasses</span>
          <input type="text" class="fi fi-sm" placeholder="Search..." oninput="filterTable('tbl-inbound',this.value)">
        </div>
        <div class="tbl-wrap">
          <table id="tbl-inbound">
<<<<<<< Updated upstream
            <thead><tr><th>Gatepass #</th><th>Date</th><th>Supplier</th><th>Branch</th><th>Items</th><th>Quantity</th><th>Status</th></tr></thead>
=======
            <thead><tr><th>Gatepass #</th><th>Date</th><th>Supplier</th><th>Branch</th><th>Items</th><th>Quantity</th><th>Engine No</th><th>Chassis No</th><th>Status</th></tr></thead>
>>>>>>> Stashed changes
            <tbody></tbody>
          </table>
        </div>
        <div class="tbl-footer" id="foot-inbound"></div>
      </div>
    </div>

    <div id="t-outbound" class="tab-panel">
      <div class="card">
        <div class="panel-head">
          <span><i class="fas fa-arrow-left"></i> Outward Gatepasses</span>
          <input type="text" class="fi fi-sm" placeholder="Search..." oninput="filterTable('tbl-outbound',this.value)">
        </div>
        <div class="tbl-wrap">
          <table id="tbl-outbound">
<<<<<<< Updated upstream
            <thead><tr><th>Gatepass #</th><th>Date</th><th>Supplier</th><th>Branch</th><th>Items</th><th>Quantity</th><th>Status</th></tr></thead>
=======
            <thead><tr><th>Gatepass #</th><th>Date</th><th>Supplier</th><th>Branch</th><th>Items</th><th>Quantity</th><th>Engine No</th><th>Chassis No</th><th>Status</th></tr></thead>
>>>>>>> Stashed changes
            <tbody></tbody>
          </table>
        </div>
        <div class="tbl-footer" id="foot-outbound"></div>
      </div>
    </div>

    <div id="t-transfers" class="tab-panel">
      <div class="card">
        <div class="panel-head">
          <span><i class="fas fa-exchange-alt"></i> Stock Transfers</span>
          <input type="text" class="fi fi-sm" placeholder="Search..." oninput="filterTable('tbl-transfers',this.value)">
        </div>
        <div class="tbl-wrap">
          <table id="tbl-transfers">
<<<<<<< Updated upstream
            <thead><tr><th>Transfer #</th><th>Date</th><th>From Location</th><th>To Location</th><th>Items</th><th>Total Items</th><th>Amount</th></tr></thead>
=======
            <thead><tr><th>Transfer #</th><th>Date</th><th>From Location</th><th>To Location</th><th>Items</th><th>Total Items</th><th>Engine No</th><th>Chassis No</th><th>Amount</th></tr></thead>
>>>>>>> Stashed changes
            <tbody></tbody>
          </table>
        </div>
        <div class="tbl-footer" id="foot-transfers"></div>
      </div>
    </div>

    <div id="t-sales" class="tab-panel">
      <div class="card">
        <div class="panel-head">
          <span><i class="fas fa-receipt"></i> Sale Invoices</span>
          <input type="text" class="fi fi-sm" placeholder="Search..." oninput="filterTable('tbl-sales',this.value)">
        </div>
        <div class="tbl-wrap">
          <table id="tbl-sales">
<<<<<<< Updated upstream
            <thead><tr><th>Bill #</th><th>Date</th><th>Customer</th><th>Items</th><th>Quantity</th><th>Net Amount</th><th>Status</th></tr></thead>
=======
            <thead><tr><th>Bill #</th><th>Date</th><th>Customer</th><th>Items</th><th>Quantity</th><th>Engine No</th><th>Chassis No</th><th>Net Amount</th><th>Status</th></tr></thead>
>>>>>>> Stashed changes
            <tbody></tbody>
          </table>
        </div>
        <div class="tbl-footer" id="foot-sales"></div>
      </div>
    </div>

    <div id="t-purchases" class="tab-panel">
      <div class="card">
        <div class="panel-head">
          <span><i class="fas fa-shopping-cart"></i> Purchase Invoices</span>
          <input type="text" class="fi fi-sm" placeholder="Search..." oninput="filterTable('tbl-purchases',this.value)">
        </div>
        <div class="tbl-wrap">
          <table id="tbl-purchases">
<<<<<<< Updated upstream
            <thead><tr><th>Bill #</th><th>Date</th><th>Supplier</th><th>Items</th><th>Quantity</th><th>Net Amount</th><th>Status</th></tr></thead>
=======
            <thead><tr><th>Bill #</th><th>Date</th><th>Supplier</th><th>Items</th><th>Quantity</th><th>Engine No</th><th>Chassis No</th><th>Net Amount</th><th>Status</th></tr></thead>
>>>>>>> Stashed changes
            <tbody></tbody>
          </table>
        </div>
        <div class="tbl-footer" id="foot-purchases"></div>
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

    <div id="t-expenses" class="tab-panel">
      <div class="card">
        <div class="panel-head">
          <span><i class="fas fa-file-invoice-dollar"></i> Expenses</span>
          <input type="text" class="fi fi-sm" placeholder="Search..." oninput="filterTable('tbl-expenses',this.value)">
        </div>
        <div class="tbl-wrap">
          <table id="tbl-expenses">
            <thead><tr><th>Voucher #</th><th>Date</th><th>Description</th><th>Total Amount</th></tr></thead>
            <tbody></tbody>
          </table>
        </div>
        <div class="tbl-footer" id="foot-expenses"></div>
      </div>
    </div>

  </div><!-- /view-detail -->

  <div class="pg-footer">LedgerOne ERP • Container Tracking • <span id="ftr-date"></span></div>
</div>

<script>const SYM = '<?php echo addslashes($sym); ?>';</script>
<script src="../../../assets/js/container_tracking/container-tracking.js"></script>
</body>
</html>
