import '../../core/api_client.dart';

/// Calls the existing server/api/sale/sale_return/*.php endpoints — same
/// backend web uses. IMPORTANT: the web page `return-add.php`/`return-add.js`
/// is itself broken (confirmed against live production data) — it never
/// builds the `unitEntries` array the backend's item/stock-ledger insert
/// loops require, so every return saved through it loses all its line items
/// and never restocks. The only proven-correct reference on web is a sibling
/// page, `counter-return.js`, which DOES build `unitEntries` correctly. This
/// client follows `counter-return.js`'s contract, not `return-add.js`'s.
class SaleReturnApi {
  static Future<List<dynamic>> getCompanies() async {
    final res = await ApiClient.dio.get(
      '/server/api/sale/sale_return/get-companies.php',
    );
    final data = res.data as Map;
    if (data['success'] != true)
      throw Exception(data['message'] ?? 'Failed to load companies');
    return List<dynamic>.from(data['companies'] as List? ?? []);
  }

  static Future<List<dynamic>> getBranches() async {
    final res = await ApiClient.dio.get(
      '/server/api/sale/sale_return/get-branches.php',
    );
    final data = res.data as Map;
    if (data['success'] != true)
      throw Exception(data['message'] ?? 'Failed to load branches');
    return List<dynamic>.from(data['branches'] as List? ?? []);
  }

  static Future<List<dynamic>> getCustomers() async {
    final res = await ApiClient.dio.get(
      '/server/api/sale/sale_return/get-customers.php',
    );
    final data = res.data as Map;
    if (data['success'] != true)
      throw Exception(data['message'] ?? 'Failed to load customers');
    return List<dynamic>.from(data['customers'] as List? ?? []);
  }

  static Future<List<dynamic>> getProducts() async {
    final res = await ApiClient.dio.get(
      '/server/api/sale/sale_return/get-products.php',
    );
    final data = res.data as Map;
    if (data['success'] != true)
      throw Exception(data['message'] ?? 'Failed to load products');
    return List<dynamic>.from(data['products'] as List? ?? []);
  }

  static Future<List<dynamic>> getCurrencies() async {
    final res = await ApiClient.dio.get(
      '/server/api/sale/sale_return/get-currencies.php',
    );
    final data = res.data as Map;
    if (data['success'] != true)
      throw Exception(data['message'] ?? 'Failed to load currencies');
    return List<dynamic>.from(data['currencies'] as List? ?? []);
  }

  static Future<List<dynamic>> getEmployees() async {
    final res = await ApiClient.dio.get(
      '/server/api/sale/sale_return/get-employees.php',
    );
    final data = res.data as Map;
    if (data['success'] != true)
      throw Exception(data['message'] ?? 'Failed to load employees');
    return List<dynamic>.from(data['employees'] as List? ?? []);
  }

  static Future<List<dynamic>> getBankAccounts() async {
    final res = await ApiClient.dio.get(
      '/server/api/sale/sale_return/get-bank-accounts.php',
    );
    final data = res.data as Map;
    if (data['success'] != true) return [];
    return List<dynamic>.from(data['accounts'] as List? ?? []);
  }

  static Future<List<dynamic>> getSubAccounts(int customerId) async {
    final res = await ApiClient.dio.get(
      '/server/api/sale/sale_return/get-sub-accounts.php',
      queryParameters: {'customer_id': customerId},
    );
    final data = res.data as Map;
    if (data['success'] != true) return [];
    return List<dynamic>.from(data['sub_accounts'] as List? ?? []);
  }

  /// Posted sale invoices, optionally narrowed to one customer — backs the
  /// optional "link to original invoice" picker. Each row already carries
  /// customer_id/customer_name, which is enough to auto-fill the Customer
  /// field when a return is linked without a separate round trip.
  static Future<List<dynamic>> getSaleInvoices({int? customerId}) async {
    final res = await ApiClient.dio.get(
      '/server/api/sale/sale_return/get-sale-invoices.php',
      queryParameters: {if (customerId != null) 'customer_id': customerId},
    );
    final data = res.data as Map;
    if (data['success'] != true) return [];
    return List<dynamic>.from(data['invoices'] as List? ?? []);
  }

  /// Lists this booker's own returns (server/api/sale/sale_return/return-list.php)
  /// — always scoped to only_mine via the mobile-token auth path itself
  /// (see return-list.php), not a client-sent flag.
  static Future<Map<String, dynamic>> getReturns({
    int page = 1,
    int limit = 20,
    String? search,
    String status = 'Posted',
    String? dateFrom,
    String? dateTo,
  }) async {
    final res = await ApiClient.dio.get(
      '/server/api/sale/sale_return/return-list.php',
      queryParameters: {
        'page': page,
        'limit': limit,
        'status': status,
        if (search != null && search.isNotEmpty) 'search': search,
        if (dateFrom != null) 'date_from': dateFrom,
        if (dateTo != null) 'date_to': dateTo,
      },
    );
    final data = res.data as Map;
    if (data['success'] != true)
      throw Exception(data['message'] ?? 'Failed to load returns');
    return Map<String, dynamic>.from(data);
  }

  /// Full original-invoice data for the "link to Sale Invoice" prefill —
  /// matches web's loadSaleInvoiceData() in return-add.js, which calls this
  /// same cross-module pos_invoice endpoint (it's not duplicated under
  /// sale_return because it's the live, authoritative invoice record).
  static Future<Map<String, dynamic>> getSaleInvoiceDetail(
    int invoiceId,
  ) async {
    final res = await ApiClient.dio.get(
      '/server/api/sale/pos_invoice/pos-edit.php',
      queryParameters: {'id': invoiceId},
    );
    final data = res.data as Map;
    if (data['success'] != true)
      throw Exception(data['message'] ?? 'Failed to load invoice');
    return Map<String, dynamic>.from(data);
  }

  static Future<Map<String, dynamic>> getReturnForEdit(int returnId) async {
    final res = await ApiClient.dio.get(
      '/server/api/sale/sale_return/return-edit.php',
      queryParameters: {'id': returnId},
    );
    final data = res.data as Map;
    if (data['success'] != true)
      throw Exception(data['message'] ?? 'Failed to load return');
    return Map<String, dynamic>.from(data);
  }

  static const String editReturnEndpoint =
      '/server/api/sale/sale_return/return-edit.php';

  static Future<Map<String, dynamic>> updateReturn(
    int returnId,
    Map<String, dynamic> payload,
  ) async {
    final res = await ApiClient.dio.put(
      editReturnEndpoint,
      data: {...payload, 'invoice_id': returnId},
    );
    return Map<String, dynamic>.from(res.data as Map);
  }

  static Future<Map<String, dynamic>> deleteReturn(int returnId) async {
    final res = await ApiClient.dio.delete(
      '/server/api/sale/sale_return/return-delete.php',
      queryParameters: {'id': returnId},
    );
    return Map<String, dynamic>.from(res.data as Map);
  }

  static const String createReturnEndpoint =
      '/server/api/sale/sale_return/return-add.php';

  /// Builds the exact payload return-add.php/return-edit.php expect, per the
  /// contract proven correct by counter-return.js — NOT the broken flat
  /// shape return-add.js currently sends. See class doc comment.
  static Map<String, dynamic> buildReturnPayload({
    required String clientUuid,
    required String saleDate,
    required int customerId,
    String? customerPhone,
    required int branchId,
    required int companyId,
    required int currencyId,
    required double previousBalance,
    int? saleInvoiceId,
    int? salesOfficerId,
    int? supplierManId,
    int? subAccountId,
    int? projectId,
    String? remarks,
    required double totalBill,
    required double totalDiscountPercent,
    required double totalDiscountAmount,
    required double shippingFees,
    required double netAmount,
    required String paymentMethod,
    int? bankAccountId,
    required double amountPaid,
    required double remainingBalance,
    required String status,
    required List<Map<String, dynamic>> items,
  }) {
    return {
      'clientUuid': clientUuid,
      'saleDate': saleDate,
      'customerId': customerId,
      'customerPhone': customerPhone,
      'branchId': branchId,
      'companyId': companyId,
      'currencyId': currencyId,
      'previousBalance': previousBalance,
      'saleInvoiceId': saleInvoiceId,
      'salesOfficerId': salesOfficerId,
      'supplierManId': supplierManId,
      'subAccountId': subAccountId,
      'projectId': projectId,
      'remarks': remarks,
      'totalBill': totalBill,
      'totalDiscountPercent': totalDiscountPercent,
      'totalDiscountAmount': totalDiscountAmount,
      'shippingFees': shippingFees,
      'netAmount': netAmount,
      'paymentMethod': paymentMethod,
      'bankAccountId': bankAccountId,
      'amountPaid': amountPaid,
      'remainingBalance': remainingBalance,
      'status': status,
      'items': items,
    };
  }

  static Future<Map<String, dynamic>> createReturn(
    Map<String, dynamic> payload,
  ) async {
    final res = await ApiClient.dio.post(createReturnEndpoint, data: payload);
    return Map<String, dynamic>.from(res.data as Map);
  }
}
