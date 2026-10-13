let refreshInterval;
let currentCurrencySymbol = 'Rs';

const cardRoutes = {
    'daily-sales':            '../../../pages/sale/pos_invoice/pos-list.php',
    'total-sales':            '../../../pages/sale/pos_invoice/pos-list.php',
    'daily-purchase':         '../../../pages/purchase/purchase_invoice/purchase-list.php',
    'total-purchase':         '../../../pages/purchase/purchase_invoice/purchase-list.php',
    'daily-sale-return':      '../../../pages/sale/sale_return/return-list.php',
    'total-sale-return':      '../../../pages/sale/sale_return/return-list.php',
    'daily-purchase-return':  '../../../pages/purchase/purchase_return/return-list.php',
    'total-purchase-return':  '../../../pages/purchase/purchase_return/return-list.php',
    'daily-recovery':         '../../../pages/vouchers/receive_voucher/receive-list.php',
    'total-recovery':         '../../../pages/vouchers/receive_voucher/receive-list.php',
    'daily-payment':          '../../../pages/vouchers/payment_voucher/payment-list.php',
    'total-payment':          '../../../pages/vouchers/payment_voucher/payment-list.php',
    'daily-expenses':         '../../../pages/vouchers/expense_voucher/expense-list.php',
    'total-expenses':         '../../../pages/vouchers/expense_voucher/expense-list.php',
    'daily-cash':             '../../../pages/financial_reports/cash_flow/cash-flow.php',
    'total-cash':             '../../../pages/financial_reports/cash_flow/cash-flow.php',
    'daily-bank':             '../../../pages/financial_reports/cash_flow/cash-flow.php',
    'total-bank':             '../../../pages/financial_reports/cash_flow/cash-flow.php',
    'daily-cheque':           '../../../pages/financial_reports/cash_flow/cash-flow.php',
    'total-cheque':           '../../../pages/financial_reports/cash_flow/cash-flow.php',
};

$(document).ready(function() {
    setDefaultDates();
    loadCurrencies();
    loadData();
    
    refreshInterval = setInterval(loadData, 5000);
    
    $('#dateFrom, #dateTo').on('change', loadData);
    
    $('#currencyFilter').on('change', function() {
        const selectedOption = $(this).find('option:selected');
        currentCurrencySymbol = selectedOption.data('symbol') || 'Rs';
        loadData();
    });
    
    $('.btn-filter').on('click', function() {
        $('.btn-filter').removeClass('active');
        $(this).addClass('active');
        
        const filter = $(this).data('filter');
        applyQuickFilter(filter);
    });

    // Card click navigation
    $(document).on('click', '.stat-card', function() {
        const type = $(this).data('type');
        const url = cardRoutes[type];
        if (url) {
            window.location.href = url;
        }
    });
});

function setDefaultDates() {
    const today = new Date();
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    
    $('#dateFrom').val(formatDate(firstDay));
    $('#dateTo').val(formatDate(today));
}

function formatDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function applyQuickFilter(filter) {
    const today = new Date();
    let fromDate, toDate = today;
    
    switch(filter) {
        case 'week':
            fromDate = new Date(today);
            fromDate.setDate(today.getDate() - today.getDay());
            break;
        case 'month':
            fromDate = new Date(today.getFullYear(), today.getMonth(), 1);
            break;
        case 'quarter':
            const quarter = Math.floor(today.getMonth() / 3);
            fromDate = new Date(today.getFullYear(), quarter * 3, 1);
            break;
        case 'year':
            fromDate = new Date(today.getFullYear(), 0, 1);
            break;
    }
    
    $('#dateFrom').val(formatDate(fromDate));
    $('#dateTo').val(formatDate(toDate));
    loadData();
}

async function loadCurrencies() {
    try {
        const response = await fetch('../../../../server/api/financial_reports/financial_summary/get-currencies.php');
        const result = await response.json();
        
        if (result.success) {
            const currencyFilter = $('#currencyFilter');
            currencyFilter.empty();
            result.data.forEach(currency => {
                const option = $('<option></option>')
                    .val(currency.id)
                    .text(`${currency.name} (${currency.symbol})`)
                    .data('symbol', currency.symbol);
                if (currency.is_base) {
                    option.prop('selected', true);
                    currentCurrencySymbol = currency.symbol;
                }
                currencyFilter.append(option);
            });
        }
    } catch (error) {
        console.error('Error loading currencies:', error);
    }
}

function loadData() {
    const dateFrom = $('#dateFrom').val();
    const dateTo = $('#dateTo').val();
    const currencyId = $('#currencyFilter').val();
    
    $('.stat-card').addClass('loading');
    
    const params = { date_from: dateFrom, date_to: dateTo };
    if (currencyId) params.currency_id = currencyId;
    
    $.ajax({
        url: '../../../../server/api/financial_reports/financial_summary/financial_summary.php',
        method: 'GET',
        data: params,
        success: function(response) {
            if (response.success) {
                updateCards(response.data);
                if (response.currency_symbol) {
                    currentCurrencySymbol = response.currency_symbol;
                }
            } else {
                console.error('API Error:', response.message);
            }
            $('.stat-card').removeClass('loading');
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', error);
            console.error('Response:', xhr.responseText);
            $('.stat-card').removeClass('loading');
        }
    });
}

function updateCards(data) {
    updateCard('daily-sales', data.daily_sales, data.daily_sales_change);
    updateCard('total-sales', data.total_sales, data.total_sales_change);
    updateCard('daily-purchase', data.daily_purchase, data.daily_purchase_change);
    updateCard('total-purchase', data.total_purchase, data.total_purchase_change);
    updateCard('daily-sale-return', data.daily_sale_return, data.daily_sale_return_change);
    updateCard('total-sale-return', data.total_sale_return, data.total_sale_return_change);
    updateCard('daily-purchase-return', data.daily_purchase_return, data.daily_purchase_return_change);
    updateCard('total-purchase-return', data.total_purchase_return, data.total_purchase_return_change);
    updateCard('daily-recovery', data.daily_recovery, data.daily_recovery_change);
    updateCard('total-recovery', data.total_recovery, data.total_recovery_change);
    updateCard('daily-payment', data.daily_payment, data.daily_payment_change);
    updateCard('total-payment', data.total_payment, data.total_payment_change);
    updateCard('daily-expenses', data.daily_expenses, data.daily_expenses_change);
    updateCard('total-expenses', data.total_expenses, data.total_expenses_change);
    updateCard('daily-cash', data.daily_cash, data.daily_cash_change);
    updateCard('total-cash', data.total_cash, data.total_cash_change);
    updateCard('daily-bank', data.daily_bank, data.daily_bank_change);
    updateCard('total-bank', data.total_bank, data.total_bank_change);
    updateCard('daily-cheque', data.daily_cheque, data.daily_cheque_change);
    updateCard('total-cheque', data.total_cheque, data.total_cheque_change);
}

function updateCard(type, amount, change) {
    const card = $(`.stat-card[data-type="${type}"]`);
    const isDaily = type.includes('daily');
    const compareText = isDaily ? 'from yesterday' : 'from last month';
    
    card.find('.amount').text(currentCurrencySymbol + parseFloat(amount || 0).toFixed(2));
    
    const changePercent = parseFloat(change || 0);
    const changeClass = changePercent >= 0 ? 'positive' : 'negative';
    
    card.find('.change')
        .removeClass('positive negative')
        .addClass(changeClass)
        .html(`<span class="percent">${Math.abs(changePercent).toFixed(1)}%</span> ${compareText}`);
}
