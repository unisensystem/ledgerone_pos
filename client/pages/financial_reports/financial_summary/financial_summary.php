<?php
require_once '../../../../includes/dashboard.php';
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    header('Location: ../auth/login.html');
    exit();
}

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$base_url = $protocol . '://' . $host . '/ledgerone_erp';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Summary</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/line-awesome/1.3.0/line-awesome/css/line-awesome.min.css">
    <link rel="stylesheet" href="../../../assets/css/financial_reports/financial_summary/financial_summary.css">
</head>
<body>
    <div class="main-content">
        <div class="summary-container">
            <div class="page-header">
                <h1>Financial Summary</h1>
                <div class="filter-section">
                    <div class="date-filters">
                        <input type="date" class="form-control" id="dateFrom" placeholder="Date From">
                        <input type="date" class="form-control" id="dateTo" placeholder="Date To">
                    </div>
                    <div class="quick-filters">
                        <button class="btn-filter" data-filter="week">This Week</button>
                        <button class="btn-filter" data-filter="month">This Month</button>
                        <button class="btn-filter" data-filter="quarter">This Quarter</button>
                        <button class="btn-filter" data-filter="year">This Year</button>
                    </div>
                    <div class="currency-filter">
                        <select class="form-control" id="currencyFilter">
                            <option value="">Loading...</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="cards-grid">
                <div class="stat-card" data-type="daily-sales">
                    <div class="card-icon sales"><i class="las la-shopping-cart"></i></div>
                    <div class="card-content">
                        <h3>Daily Sales</h3>
                        <div class="amount">0.00</div>
                        <div class="change"><span class="percent">0%</span> from yesterday</div>
                    </div>
                </div>

                <div class="stat-card" data-type="total-sales">
                    <div class="card-icon sales"><i class="las la-chart-line"></i></div>
                    <div class="card-content">
                        <h3>Total Sales</h3>
                        <div class="amount">0.00</div>
                        <div class="change"><span class="percent">0%</span> from last month</div>
                    </div>
                </div>

                <div class="stat-card" data-type="daily-purchase">
                    <div class="card-icon purchase"><i class="las la-shopping-bag"></i></div>
                    <div class="card-content">
                        <h3>Daily Purchase</h3>
                        <div class="amount">0.00</div>
                        <div class="change"><span class="percent">0%</span> from yesterday</div>
                    </div>
                </div>

                <div class="stat-card" data-type="total-purchase">
                    <div class="card-icon purchase"><i class="las la-boxes"></i></div>
                    <div class="card-content">
                        <h3>Total Purchase</h3>
                        <div class="amount">0.00</div>
                        <div class="change"><span class="percent">0%</span> from last month</div>
                    </div>
                </div>

                <div class="stat-card" data-type="daily-sale-return">
                    <div class="card-icon return"><i class="las la-undo"></i></div>
                    <div class="card-content">
                        <h3>Daily Sale Return</h3>
                        <div class="amount">0.00</div>
                        <div class="change"><span class="percent">0%</span> from yesterday</div>
                    </div>
                </div>

                <div class="stat-card" data-type="total-sale-return">
                    <div class="card-icon return"><i class="las la-reply-all"></i></div>
                    <div class="card-content">
                        <h3>Total Sales Return</h3>
                        <div class="amount">0.00</div>
                        <div class="change"><span class="percent">0%</span> from last month</div>
                    </div>
                </div>

                <div class="stat-card" data-type="daily-purchase-return">
                    <div class="card-icon return"><i class="las la-redo"></i></div>
                    <div class="card-content">
                        <h3>Daily Purchase Return</h3>
                        <div class="amount">0.00</div>
                        <div class="change"><span class="percent">0%</span> from yesterday</div>
                    </div>
                </div>

                <div class="stat-card" data-type="total-purchase-return">
                    <div class="card-icon return"><i class="las la-exchange-alt"></i></div>
                    <div class="card-content">
                        <h3>Total Purchase Returns</h3>
                        <div class="amount">0.00</div>
                        <div class="change"><span class="percent">0%</span> from last month</div>
                    </div>
                </div>

                <div class="stat-card" data-type="daily-recovery">
                    <div class="card-icon recovery"><i class="las la-hand-holding-usd"></i></div>
                    <div class="card-content">
                        <h3>Daily Recovery</h3>
                        <div class="amount">0.00</div>
                        <div class="change"><span class="percent">0%</span> from yesterday</div>
                    </div>
                </div>

                <div class="stat-card" data-type="total-recovery">
                    <div class="card-icon recovery"><i class="las la-money-bill-wave"></i></div>
                    <div class="card-content">
                        <h3>Total Recovery</h3>
                        <div class="amount">0.00</div>
                        <div class="change"><span class="percent">0%</span> from last month</div>
                    </div>
                </div>

                <div class="stat-card" data-type="daily-payment">
                    <div class="card-icon payment"><i class="las la-credit-card"></i></div>
                    <div class="card-content">
                        <h3>Daily Payment</h3>
                        <div class="amount">0.00</div>
                        <div class="change"><span class="percent">0%</span> from yesterday</div>
                    </div>
                </div>

                <div class="stat-card" data-type="total-payment">
                    <div class="card-icon payment"><i class="las la-wallet"></i></div>
                    <div class="card-content">
                        <h3>Total Payment</h3>
                        <div class="amount">0.00</div>
                        <div class="change"><span class="percent">0%</span> from last month</div>
                    </div>
                </div>

                <div class="stat-card" data-type="daily-expenses">
                    <div class="card-icon expense"><i class="las la-file-invoice-dollar"></i></div>
                    <div class="card-content">
                        <h3>Daily Expenses</h3>
                        <div class="amount">0.00</div>
                        <div class="change"><span class="percent">0%</span> from yesterday</div>
                    </div>
                </div>

                <div class="stat-card" data-type="total-expenses">
                    <div class="card-icon expense"><i class="las la-receipt"></i></div>
                    <div class="card-content">
                        <h3>Total Expenses</h3>
                        <div class="amount">0.00</div>
                        <div class="change"><span class="percent">0%</span> from last month</div>
                    </div>
                </div>

                <div class="stat-card" data-type="daily-cash">
                    <div class="card-icon cash"><i class="las la-money-bill"></i></div>
                    <div class="card-content">
                        <h3>Daily Cash</h3>
                        <div class="amount">0.00</div>
                        <div class="change"><span class="percent">0%</span> from yesterday</div>
                    </div>
                </div>

                <div class="stat-card" data-type="total-cash">
                    <div class="card-icon cash"><i class="las la-coins"></i></div>
                    <div class="card-content">
                        <h3>Total Cash</h3>
                        <div class="amount">0.00</div>
                        <div class="change"><span class="percent">0%</span> from last month</div>
                    </div>
                </div>

                <div class="stat-card" data-type="daily-bank">
                    <div class="card-icon bank"><i class="las la-university"></i></div>
                    <div class="card-content">
                        <h3>Daily Bank</h3>
                        <div class="amount">0.00</div>
                        <div class="change"><span class="percent">0%</span> from yesterday</div>
                    </div>
                </div>

                <div class="stat-card" data-type="total-bank">
                    <div class="card-icon bank"><i class="las la-landmark"></i></div>
                    <div class="card-content">
                        <h3>Total Bank</h3>
                        <div class="amount">0.00</div>
                        <div class="change"><span class="percent">0%</span> from last month</div>
                    </div>
                </div>

                <div class="stat-card" data-type="daily-cheque">
                    <div class="card-icon cheque"><i class="las la-money-check"></i></div>
                    <div class="card-content">
                        <h3>Daily Cheque</h3>
                        <div class="amount">0.00</div>
                        <div class="change"><span class="percent">0%</span> from yesterday</div>
                    </div>
                </div>

                <div class="stat-card" data-type="total-cheque">
                    <div class="card-icon cheque"><i class="las la-money-check-alt"></i></div>
                    <div class="card-content">
                        <h3>Total Cheque</h3>
                        <div class="amount">0.00</div>
                        <div class="change"><span class="percent">0%</span> from last month</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../../../assets/js/financial_reports/financial_summary/financial_summary.js"></script>
</body>
</html>
