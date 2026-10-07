import 'package:dio/dio.dart';
import '../../core/api_client.dart';

/// Calls the existing server/api/vouchers/receive_voucher/*.php endpoints —
/// same backend web uses. Mobile only ever sends receive_type=customer (see
/// the Bulk Mode / Receive from Supplier scope discussion — both are
/// desk/office workflows, deliberately not built for mobile).
///
/// IMPORTANT asymmetry carried over from web's own backend: receive-add.php
/// reads $_POST (multipart form data, so it can also take a file attachment)
/// with keys `bank_account` / `recovery_officer`, while receive-edit.php
/// reads a JSON body with keys `bank_account_id` / `recovery_officer_id`.
/// Not a mistake to "fix" here — just two endpoints with different shapes
/// that must each be matched exactly.
class ReceiveVoucherApi {
  static Future<List<dynamic>> getCompanies() async {
    final res = await ApiClient.dio.get('/server/api/vouchers/receive_voucher/get-companies.php');
    final data = res.data as Map;
    if (data['success'] != true) throw Exception(data['message'] ?? data['error'] ?? 'Failed to load companies');
    return List<dynamic>.from(data['data'] as List? ?? []);
  }

  static Future<List<dynamic>> getCurrencies() async {
    final res = await ApiClient.dio.get('/server/api/vouchers/receive_voucher/get-currencies.php');
    final data = res.data as Map;
    if (data['success'] != true) throw Exception(data['error'] ?? 'Failed to load currencies');
    return List<dynamic>.from(data['data'] as List? ?? []);
  }

  /// get-suppliers.php is misleadingly named on web — it actually returns
  /// the CUSTOMER list (customer_code/customer_name/id). Not renamed here;
  /// matching the real, live backend file exactly.
  static Future<List<dynamic>> getCustomers() async {
    final res = await ApiClient.dio.get('/server/api/vouchers/receive_voucher/get-suppliers.php');
    final data = res.data as Map;
    if (data['success'] != true) throw Exception(data['error'] ?? 'Failed to load customers');
    return List<dynamic>.from(data['data'] as List? ?? []);
  }

  static Future<double> getCustomerBalance(String customerCode) async {
    final res = await ApiClient.dio.get(
      '/server/api/vouchers/receive_voucher/get-customer-balance.php',
      queryParameters: {'customer_code': customerCode},
    );
    final data = res.data as Map;
    if (data['success'] != true) return 0;
    return double.tryParse(data['balance'].toString()) ?? 0;
  }

  static Future<List<dynamic>> getSubAccounts(int customerId) async {
    final res = await ApiClient.dio.get(
      '/server/api/vouchers/receive_voucher/get-sub-accounts.php',
      queryParameters: {'customer_id': customerId},
    );
    final data = res.data as Map;
    if (data['success'] != true) return [];
    return List<dynamic>.from(data['data'] as List? ?? []);
  }

  /// Outstanding bills for this customer (remaining balance per bill,
  /// already net of prior receive vouchers and posted returns) — purely
  /// informational/matching, does not auto-fill Amount (matches web).
  static Future<List<dynamic>> getBills(String customerCode) async {
    final res = await ApiClient.dio.get(
      '/server/api/vouchers/receive_voucher/get-bills.php',
      queryParameters: {'customer_code': customerCode},
    );
    final data = res.data as Map;
    if (data['success'] != true) return [];
    return List<dynamic>.from(data['data'] as List? ?? []);
  }

  static Future<List<dynamic>> getPendingPdcs(int customerId) async {
    final res = await ApiClient.dio.get(
      '/server/api/vouchers/receive_voucher/get-pending-pdcs.php',
      queryParameters: {'customer_id': customerId},
    );
    final data = res.data as Map;
    if (data['success'] != true) return [];
    return List<dynamic>.from(data['data'] as List? ?? []);
  }

  static Future<List<dynamic>> getPaymentMethods() async {
    final res = await ApiClient.dio.get('/server/api/vouchers/receive_voucher/get-payment-methods.php');
    final data = res.data as Map;
    if (data['success'] != true) throw Exception(data['error'] ?? 'Failed to load payment methods');
    return List<dynamic>.from(data['data'] as List? ?? []);
  }

  static Future<List<dynamic>> getBankAccounts() async {
    final res = await ApiClient.dio.get('/server/api/vouchers/receive_voucher/get-bank-accounts.php');
    final data = res.data as Map;
    if (data['success'] != true) return [];
    return List<dynamic>.from(data['data'] as List? ?? []);
  }

  static Future<List<dynamic>> getEmployees() async {
    final res = await ApiClient.dio.get('/server/api/vouchers/receive_voucher/get-employees.php');
    final data = res.data as Map;
    if (data['success'] != true) throw Exception(data['error'] ?? 'Failed to load employees');
    return List<dynamic>.from(data['data'] as List? ?? []);
  }

  /// Same cross-module endpoint Sale Order's mobile Project dropdown
  /// already uses (already mobile_auth-shimmed).
  static Future<List<dynamic>> getProjects() async {
    final res = await ApiClient.dio.get('/server/api/master_setup/project_setup/get-projects-dropdown.php');
    final data = res.data as Map;
    if (data['success'] != true) return [];
    return List<dynamic>.from(data['projects'] as List? ?? []);
  }

  /// Vouchers this booker created (always only_mine for a mobile token —
  /// enforced server-side, see receive-list.php).
  static Future<Map<String, dynamic>> getVouchers({int page = 1, int limit = 20, String? search}) async {
    final res = await ApiClient.dio.get(
      '/server/api/vouchers/receive_voucher/receive-list.php',
      queryParameters: {
        'page': page,
        'limit': limit,
        'receive_type': 'customer',
        if (search != null && search.isNotEmpty) 'search': search,
      },
    );
    final data = res.data as Map;
    if (data['success'] != true) throw Exception(data['error'] ?? 'Failed to load vouchers');
    return Map<String, dynamic>.from(data);
  }

  static Future<Map<String, dynamic>> getVoucherForEdit(int voucherId) async {
    final res = await ApiClient.dio.get(
      '/server/api/vouchers/receive_voucher/receive-get.php',
      queryParameters: {'id': voucherId},
    );
    final data = res.data as Map;
    if (data['success'] != true) throw Exception(data['error'] ?? 'Failed to load voucher');
    return Map<String, dynamic>.from(data['data'] as Map);
  }

  static const String createVoucherEndpoint = '/server/api/vouchers/receive_voucher/receive-add.php';
  static const String editVoucherEndpoint = '/server/api/vouchers/receive_voucher/receive-edit.php';

  /// receive-add.php reads $_POST — every value must be a String for
  /// FormData, not a number/null, hence the .toString() / '' handling here
  /// rather than leaving real nulls like the JSON-body endpoints do.
  static Map<String, dynamic> buildCreatePayload({
    required String clientUuid,
    required String voucherDate,
    required String customerCode,
    required double previousBalance,
    int? subAccountId,
    String? billNo,
    String? dsrNo,
    int? projectId,
    required double adjustAmount,
    required double amount,
    required int currencyId,
    required int paymentMethodId,
    int? bankAccountId,
    String? chequeDate,
    String? chequeNo,
    String? description,
    int? recoveryOfficerId,
    int? againstPdcId,
  }) {
    return {
      'client_uuid': clientUuid,
      'receive_type': 'customer',
      'voucher_date': voucherDate,
      'customer_code': customerCode,
      'previous_balance': previousBalance.toString(),
      'sub_account_id': subAccountId?.toString() ?? '',
      'bill_no': billNo ?? '',
      'dsr_no': dsrNo ?? '',
      'project_id': projectId?.toString() ?? '',
      'adjust_amount': adjustAmount.toString(),
      'amount': amount.toString(),
      'currency_id': currencyId.toString(),
      'payment_method_id': paymentMethodId.toString(),
      'bank_account': bankAccountId?.toString() ?? '',
      'cheque_date': chequeDate ?? '',
      'cheque_no': chequeNo ?? '',
      'description': description ?? '',
      'recovery_officer': recoveryOfficerId?.toString() ?? '',
      'against_pdc_id': againstPdcId?.toString() ?? '',
    };
  }

  static Future<Map<String, dynamic>> createVoucher(
    Map<String, dynamic> payload, {
    List<int>? attachmentBytes,
    String? attachmentFileName,
  }) async {
    // receive-add.php reads $_POST — must go as multipart form data, not
    // Dio's default JSON body, or every field arrives empty server-side.
    final formData = FormData.fromMap(payload);
    if (attachmentBytes != null && attachmentFileName != null) {
      formData.files.add(MapEntry(
        'attachment',
        MultipartFile.fromBytes(attachmentBytes, filename: attachmentFileName),
      ));
    }
    final res = await ApiClient.dio.post(createVoucherEndpoint, data: formData);
    return Map<String, dynamic>.from(res.data as Map);
  }

  /// receive-edit.php reads a JSON body — unlike create, keys are the full
  /// `_id` form (bank_account_id, recovery_officer_id), and real nulls are
  /// fine here.
  static Map<String, dynamic> buildEditPayload({
    required String voucherDate,
    int? companyId,
    String? billNo,
    String? dsrNo,
    required double amount,
    required double adjustAmount,
    int? currencyId,
    required int paymentMethodId,
    int? bankAccountId,
    String? chequeDate,
    String? chequeNo,
    String? description,
    int? projectId,
    int? subAccountId,
    int? recoveryOfficerId,
    int? againstPdcId,
  }) {
    return {
      'voucher_date': voucherDate,
      'company_id': companyId,
      'bill_no': billNo,
      'dsr_no': dsrNo,
      'amount': amount,
      'adjust_amount': adjustAmount,
      'currency_id': currencyId,
      'payment_method_id': paymentMethodId,
      'bank_account_id': bankAccountId,
      'cheque_date': chequeDate,
      'cheque_no': chequeNo,
      'description': description,
      'project_id': projectId,
      'sub_account_id': subAccountId,
      'recovery_officer_id': recoveryOfficerId,
      'against_pdc_id': againstPdcId,
    };
  }

  static Future<Map<String, dynamic>> updateVoucher(int voucherId, Map<String, dynamic> payload) async {
    final res = await ApiClient.dio.put(
      editVoucherEndpoint,
      queryParameters: {'id': voucherId},
      data: payload,
    );
    return Map<String, dynamic>.from(res.data as Map);
  }

  static Future<Map<String, dynamic>> deleteVoucher(int voucherId) async {
    final res = await ApiClient.dio.delete(
      '/server/api/vouchers/receive_voucher/receive-delete.php',
      queryParameters: {'id': voucherId},
    );
    return Map<String, dynamic>.from(res.data as Map);
  }
}
