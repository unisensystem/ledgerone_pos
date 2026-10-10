import '../../core/api_client.dart';

/// Calls the existing server/api/financial_reports/customer_ledger/
/// customer-ledger.php endpoint — the SAME backend the web app's Customer
/// Ledger report page uses. No parallel mobile API; this endpoint already
/// handles multi-currency conversion, PDCs, returns, adjustments, etc., so
/// mobile never has to reimplement any of that.
class CustomerLedgerApi {
  static const String _endpoint =
      '/server/api/financial_reports/customer_ledger/customer-ledger.php';

  /// Summary row for one customer (opening/debit/credit/closing balance).
  /// Mirrors web's "Summary Ledger" tab, but always scoped to a single
  /// customer_id here rather than the whole tenant list. Note: the backend
  /// omits the row entirely when every figure is exactly zero (see
  /// customer-ledger.php) — callers should treat a missing/empty result as
  /// "all zero", not as an error.
  static Future<Map<String, dynamic>?> getSummary({
    required int customerId,
    int? companyId,
    String? fromDate,
    String? toDate,
  }) async {
    final res = await ApiClient.dio.get(
      _endpoint,
      queryParameters: {
        'type': 'summary',
        'customer_id': customerId,
        if (companyId != null) 'company_id': companyId,
        if (fromDate != null) 'from_date': fromDate,
        if (toDate != null) 'to_date': toDate,
      },
    );
    final data = res.data as Map;
    if (data['success'] != true) {
      throw Exception(data['message'] ?? 'Failed to load ledger summary');
    }
    final rows = List<dynamic>.from(data['data'] as List? ?? []);
    return rows.isNotEmpty ? Map<String, dynamic>.from(rows.first) : null;
  }

  /// Full transaction list (mirrors web's "Detailed Ledger" tab) — each row
  /// already carries a running_balance computed server-side. Row `type` is
  /// one of: opening_balance, invoice, payment, return, pdc, rent,
  /// transfer_out, transfer_in, adjustment, payment_voucher, adjustment_rv,
  /// sub_account_header, sub_account_total.
  static Future<List<dynamic>> getDetailed({
    required int customerId,
    int? companyId,
    String? fromDate,
    String? toDate,
  }) async {
    final res = await ApiClient.dio.get(
      _endpoint,
      queryParameters: {
        'type': 'detailed',
        'customer_id': customerId,
        if (companyId != null) 'company_id': companyId,
        if (fromDate != null) 'from_date': fromDate,
        if (toDate != null) 'to_date': toDate,
      },
    );
    final data = res.data as Map;
    if (data['success'] != true) {
      throw Exception(data['message'] ?? 'Failed to load ledger');
    }
    return List<dynamic>.from(data['data'] as List? ?? []);
  }
}
