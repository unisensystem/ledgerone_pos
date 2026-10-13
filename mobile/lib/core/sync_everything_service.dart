import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

import '../features/customer_ledger/customer_ledger_api.dart';
import '../features/receive_voucher/receive_voucher_api.dart';
import '../features/sale_order/sale_order_api.dart';
import '../features/sale_return/sale_return_api.dart';
import 'reference_cache.dart';

class _RefTask {
  final String cacheKey;
  final String label;
  final Future<dynamic> Function() fetch;
  const _RefTask(this.cacheKey, this.label, this.fetch);
}

/// One manual "refresh everything while I'm on office WiFi" action — for a
/// booker about to head into the field who wants every screen's cached data
/// (Sale Order/Receive Voucher/Sale Return/Add Customer reference lists,
/// plus every customer's Customer Ledger summary balance) as current as
/// possible before losing connectivity, instead of relying on each screen's
/// own cache-on-open behaviour, which only refreshes whatever that booker
/// happens to actually open that day.
///
/// Deliberately sequential, not concurrent: a field/office connection can
/// still be weak, and firing dozens of requests at once risks timing out
/// the whole batch rather than finishing meaningfully faster — confirmed
/// acceptable with the user (time is not a constraint here, correctness
/// is). Each item is wrapped individually, so one flaky request only costs
/// that one item instead of aborting the rest of the sync.
class SyncEverythingService {
  static const _lastSyncKey = 'last_full_sync_at';

  static Future<DateTime?> getLastSyncTime() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_lastSyncKey);
    return raw != null ? DateTime.tryParse(raw) : null;
  }

  static Future<void> _markSynced() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_lastSyncKey, DateTime.now().toIso8601String());
  }

  /// Same cache shape Customer Ledger's own screen writes (see
  /// customer_ledger_screen.dart's _cacheSummary) — must match exactly so
  /// that screen's offline fallback can read what this writes.
  static Future<void> _cacheCustomerSummary(
    int customerId,
    Map<String, dynamic>? summary,
  ) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(
      'customer_ledger_summary_$customerId',
      jsonEncode({
        'summary': summary,
        'cachedAt': DateTime.now().toIso8601String(),
      }),
    );
  }

  /// Runs the full refresh. [onProgress] fires after every single step with
  /// a human label and (current, total) so the caller can render a
  /// progress bar. Returns the count of Customer Ledger summaries that
  /// failed to refresh (reference-data failures aren't counted — those
  /// screens already fall back to whatever was last cached regardless).
  static Future<int> run({
    required void Function(String label, int current, int total) onProgress,
  }) async {
    // Fetched first and separately (rather than as just another ref task)
    // because the Customer Ledger loop below needs the id list — this also
    // doubles as the 'so_customers' cache refresh.
    List<dynamic> customers = [];
    try {
      customers = await ReferenceCache.cached(
        'so_customers',
        SaleOrderApi.getCustomers,
      );
    } catch (_) {
      // Fall through with an empty list — reference tasks and whatever
      // customers list is already cached still get a chance below.
    }

    final refTasks = <_RefTask>[
      _RefTask('so_companies', 'Sale Order — Companies', SaleOrderApi.getCompanies),
      _RefTask('so_branches', 'Sale Order — Branches', SaleOrderApi.getBranches),
      _RefTask('so_products', 'Sale Order — Products', SaleOrderApi.getProducts),
      _RefTask('so_currencies', 'Sale Order — Currencies', SaleOrderApi.getCurrencies),
      _RefTask('so_employees', 'Sale Order — Employees', SaleOrderApi.getEmployees),
      _RefTask('so_projects', 'Sale Order — Projects', SaleOrderApi.getProjects),
      _RefTask('rv_companies', 'Receive Voucher — Companies', ReceiveVoucherApi.getCompanies),
      _RefTask('rv_currencies', 'Receive Voucher — Currencies', ReceiveVoucherApi.getCurrencies),
      _RefTask('rv_customers', 'Receive Voucher — Customers', ReceiveVoucherApi.getCustomers),
      _RefTask('rv_payment_methods', 'Receive Voucher — Payment Methods', ReceiveVoucherApi.getPaymentMethods),
      _RefTask('rv_bank_accounts', 'Receive Voucher — Bank Accounts', ReceiveVoucherApi.getBankAccounts),
      _RefTask('rv_employees', 'Receive Voucher — Employees', ReceiveVoucherApi.getEmployees),
      _RefTask('rv_projects', 'Receive Voucher — Projects', ReceiveVoucherApi.getProjects),
      _RefTask('sr_companies', 'Sale Return — Companies', SaleReturnApi.getCompanies),
      _RefTask('sr_branches', 'Sale Return — Branches', SaleReturnApi.getBranches),
      _RefTask('sr_customers', 'Sale Return — Customers', SaleReturnApi.getCustomers),
      _RefTask('sr_products', 'Sale Return — Products', SaleReturnApi.getProducts),
      _RefTask('sr_currencies', 'Sale Return — Currencies', SaleReturnApi.getCurrencies),
      _RefTask('sr_employees', 'Sale Return — Employees', SaleReturnApi.getEmployees),
      _RefTask('sr_bank_accounts', 'Sale Return — Bank Accounts', SaleReturnApi.getBankAccounts),
      _RefTask('sr_sale_invoices', 'Sale Return — Sale Invoices', SaleReturnApi.getSaleInvoices),
    ];

    final total = 1 + refTasks.length + customers.length;
    var step = 1;
    onProgress('Sale Order — Customers', step, total);

    for (final task in refTasks) {
      step++;
      onProgress(task.label, step, total);
      try {
        await ReferenceCache.cached(task.cacheKey, task.fetch);
      } catch (_) {
        // Skip — that screen keeps whatever it had cached before.
      }
    }

    var failedSummaries = 0;
    for (final c in customers) {
      step++;
      final name = c['customer_name']?.toString() ?? 'Customer';
      onProgress('Customer Ledger — $name', step, total);
      final id = c['id'];
      if (id is! int) continue;
      try {
        final summary = await CustomerLedgerApi.getSummary(customerId: id);
        await _cacheCustomerSummary(id, summary);
      } catch (_) {
        failedSummaries++;
      }
    }

    await _markSynced();
    return failedSummaries;
  }
}
