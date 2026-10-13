import '../../core/api_client.dart';
import '../../core/outbox_service.dart';

/// Calls the existing server/api/sale/sale_order/*.php endpoints — the SAME
/// backend the web app's Sale Order screen uses. No parallel mobile API.
class SaleOrderApi {
  static Future<List<dynamic>> getCompanies() async {
    final res = await ApiClient.dio.get(
      '/server/api/sale/sale_order/get-companies.php',
    );
    final data = res.data as Map;
    if (data['success'] != true)
      throw Exception(data['message'] ?? 'Failed to load companies');
    return List<dynamic>.from(data['companies'] as List? ?? []);
  }

  static Future<List<dynamic>> getBranches() async {
    final res = await ApiClient.dio.get(
      '/server/api/sale/sale_order/get-branches.php',
    );
    final data = res.data as Map;
    if (data['success'] != true)
      throw Exception(data['message'] ?? 'Failed to load branches');
    return List<dynamic>.from(data['branches'] as List? ?? []);
  }

  static Future<List<dynamic>> getCustomers() async {
    final res = await ApiClient.dio.get(
      '/server/api/sale/sale_order/get-customers.php',
    );
    final data = res.data as Map;
    if (data['success'] != true)
      throw Exception(data['message'] ?? 'Failed to load customers');
    return List<dynamic>.from(data['customers'] as List? ?? []);
  }

  static Future<List<dynamic>> getProducts() async {
    final res = await ApiClient.dio.get(
      '/server/api/sale/sale_order/get-products.php',
    );
    final data = res.data as Map;
    if (data['success'] != true)
      throw Exception(data['message'] ?? 'Failed to load products');
    return List<dynamic>.from(data['products'] as List? ?? []);
  }

  static Future<List<dynamic>> getCurrencies() async {
    final res = await ApiClient.dio.get(
      '/server/api/sale/sale_order/get-currencies.php',
    );
    final data = res.data as Map;
    if (data['success'] != true)
      throw Exception(data['message'] ?? 'Failed to load currencies');
    return List<dynamic>.from(data['currencies'] as List? ?? []);
  }

  /// Lists posted sale orders — same endpoint and fields web's Sale Order
  /// list page uses (server/api/sale/sale_order/order-list.php). Always
  /// scoped to the signed-in booker's own orders (only_mine=1) — a field
  /// opt-in param web never sends, so web's own "every order in the
  /// tenant" behavior is untouched; a booker on the phone should see their
  /// own work, not everyone's. [dateFrom]/[dateTo] (yyyy-MM-dd) default to
  /// today on both ends — see sale_order_list_screen.dart — so a booker's
  /// list only ever shows today's work, matching the "today only" control
  /// orders are created under.
  static Future<Map<String, dynamic>> getOrders({
    int page = 1,
    int limit = 20,
    String? search,
    String orderStatus = 'Posted',
    String? dateFrom,
    String? dateTo,
  }) async {
    final res = await ApiClient.dio.get(
      '/server/api/sale/sale_order/order-list.php',
      queryParameters: {
        'page': page,
        'limit': limit,
        'only_mine': '1',
        'order_status': orderStatus,
        if (search != null && search.isNotEmpty) 'search': search,
        if (dateFrom != null) 'date_from': dateFrom,
        if (dateTo != null) 'date_to': dateTo,
      },
    );
    final data = res.data as Map;
    if (data['success'] != true)
      throw Exception(data['message'] ?? 'Failed to load orders');
    return Map<String, dynamic>.from(data);
  }

  /// Full order + items for editing (server/api/sale/sale_order/order-edit.php, GET).
  static Future<Map<String, dynamic>> getOrderForEdit(int orderId) async {
    final res = await ApiClient.dio.get(
      '/server/api/sale/sale_order/order-edit.php',
      queryParameters: {'id': orderId},
    );
    final data = res.data as Map;
    if (data['success'] != true)
      throw Exception(data['message'] ?? 'Failed to load order');
    return Map<String, dynamic>.from(data);
  }

  static const String editOrderEndpoint =
      '/server/api/sale/sale_order/order-edit.php';

  static Future<Map<String, dynamic>> updateOrder(
    int orderId,
    Map<String, dynamic> payload,
  ) async {
    final res = await ApiClient.dio.put(
      editOrderEndpoint,
      data: {...payload, 'invoice_id': orderId},
    );
    return Map<String, dynamic>.from(res.data as Map);
  }

  static Future<Map<String, dynamic>> deleteOrder(int orderId) async {
    final res = await ApiClient.dio.delete(
      '/server/api/sale/sale_order/order-delete.php',
      queryParameters: {'id': orderId},
    );
    return Map<String, dynamic>.from(res.data as Map);
  }

  /// Live-ish stock for a product, in base-unit terms (server/api/sale/pos_invoice/get-product-stock.php
  /// — same formula web's pos-add.php "Available Stock" line uses). Only
  /// meaningful when actually online; the Add Item dialog treats a failed
  /// call as "stock unknown" rather than blocking anything, since this is
  /// advisory for the booker — the real safety net is the server-side
  /// conflict check order-add.php does at sync time regardless of what
  /// this showed offline.
  static Future<Map<String, dynamic>?> getProductStock(int productId) async {
    try {
      final res = await ApiClient.dio.get(
        '/server/api/sale/pos_invoice/get-product-stock.php',
        queryParameters: {'product_id': productId},
      );
      final data = res.data as Map;
      if (data['success'] != true) return null;
      return Map<String, dynamic>.from(data);
    } catch (_) {
      return null;
    }
  }

  /// Scheme rows (promo_qty/bonus_qty/to_qty/to_rs per unit) for a product —
  /// drives the Less/Less Special/Given auto-calculations, same source as
  /// web's fetchProductSchemes() in order-add-uom.js.
  static Future<List<dynamic>> getProductSchemes(int productId) async {
    final res = await ApiClient.dio.get(
      '/server/api/sale/pos_invoice/get-product-schemes.php',
      queryParameters: {'product_id': productId},
    );
    final data = res.data as Map;
    if (data['success'] != true) return [];
    return List<dynamic>.from(data['schemes'] as List? ?? []);
  }

  /// Matches web's Project dropdown (order-add.js's loadProjects()) — note
  /// web's own JS reads the wrong response key (data.data instead of
  /// data.projects) so its dropdown is always empty; we read the correct
  /// key here rather than reproduce that bug, since there's nothing to lose
  /// by having it actually work on mobile.
  static Future<List<dynamic>> getProjects() async {
    final res = await ApiClient.dio.get(
      '/server/api/master_setup/project_setup/get-projects-dropdown.php',
    );
    final data = res.data as Map;
    if (data['success'] != true) return [];
    return List<dynamic>.from(data['projects'] as List? ?? []);
  }

  static Future<List<dynamic>> getEmployees() async {
    final res = await ApiClient.dio.get(
      '/server/api/sale/sale_order/get-employees.php',
    );
    final data = res.data as Map;
    if (data['success'] != true)
      throw Exception(data['message'] ?? 'Failed to load employees');
    return List<dynamic>.from(data['employees'] as List? ?? []);
  }

  static const String createOrderEndpoint =
      '/server/api/sale/sale_order/order-add.php';

  /// Builds the exact payload order-add.php expects. Kept separate from the
  /// actual POST so the same map can either be sent immediately or — if
  /// there's no connectivity, or the live send fails — handed to
  /// [OutboxService] untouched and replayed later by [SyncService]. The
  /// [clientUuid] it carries makes the create idempotent either way: a
  /// retried send with the same uuid returns the already-created order
  /// instead of making a duplicate (see order-add.php). [totalBill]/[netAmount]
  /// are the already-computed order-level totals — matching exactly what
  /// web's order-add-uom.js computes, so a mobile-created order's numbers
  /// are never different from a web-created one.
  static Map<String, dynamic> buildOrderPayload({
    required String clientUuid,
    required String saleDate,
    // Exactly one of these two is set: a real customerId for a customer
    // that already exists server-side, or customerClientUuid when the
    // booker picked a customer they created offline moments ago (see
    // buildPendingCustomer) whose own outbox entry hasn't synced yet —
    // order-add.php resolves that uuid to the real id once it has.
    int? customerId,
    String? customerClientUuid,
    required int branchId,
    required int companyId,
    required int currencyId,
    required double previousBalance,
    int? salesOfficerId,
    int? supplierManId,
    int? projectId,
    String? biltyNo,
    String? transportName,
    String? remarks,
    required double totalBill,
    required double totalDiscountPercent,
    required double totalDiscountAmount,
    required double totalGstPercent,
    required double totalGstAmount,
    required double shippingFees,
    required double netAmount,
    required String status,
    required List<Map<String, dynamic>> items,
  }) {
    return {
      'clientUuid': clientUuid,
      'saleDate': saleDate,
      if (customerId != null) 'customerId': customerId,
      if (customerClientUuid != null) 'customerClientUuid': customerClientUuid,
      'branchId': branchId,
      'companyId': companyId,
      'currencyId': currencyId,
      'previousBalance': previousBalance,
      'salesOfficerId': salesOfficerId,
      'supplierManId': supplierManId,
      'projectId': projectId,
      'biltyNo': biltyNo,
      'transportName': transportName,
      'remarks': remarks,
      'totalBill': totalBill,
      'totalDiscountPercent': totalDiscountPercent,
      'totalDiscountAmount': totalDiscountAmount,
      // Matches web's exact payload key casing (order-add.js) — order-add.php
      // and order-edit.php read these specific keys.
      'totalGSTPercent': totalGstPercent,
      'totalGSTAmount': totalGstAmount,
      'shippingFees': shippingFees,
      'netAmount': netAmount,
      'status': status,
      'items': items,
    };
  }

  static Future<Map<String, dynamic>> createOrder(
    Map<String, dynamic> payload,
  ) async {
    final res = await ApiClient.dio.post(createOrderEndpoint, data: payload);
    return Map<String, dynamic>.from(res.data as Map);
  }

  static const String customerAddEndpoint =
      '/server/api/customer_supplier/customers/customer-add.php';

  /// Creates a brand-new customer on the spot (server/api/customer_supplier/
  /// customers/customer-add.php — same endpoint web's full customer-add page
  /// uses). [clientUuid] makes this idempotent on retry — if it's ever
  /// queued offline (see [buildPendingCustomer]) and the sync replays it,
  /// a repeat send with the same uuid returns the already-created customer
  /// instead of making a duplicate.
  static Future<Map<String, dynamic>> createCustomer({
    required int companyId,
    required String customerName,
    String? primaryPhone,
    String? address,
    int? salesOfficerId,
    int? supplierManId,
    String? clientUuid,
  }) async {
    final res = await ApiClient.dio.post(
      customerAddEndpoint,
      data: {
        'companyId': companyId,
        'customerName': customerName,
        if (primaryPhone != null && primaryPhone.isNotEmpty)
          'primaryPhone': primaryPhone,
        if (address != null && address.isNotEmpty) 'address': address,
        if (salesOfficerId != null) 'salesOfficerId': salesOfficerId,
        if (supplierManId != null) 'supplierManId': supplierManId,
        if (clientUuid != null) 'clientUuid': clientUuid,
      },
    );
    final data = res.data as Map;
    if (data['success'] != true) {
      throw Exception(data['message'] ?? 'Failed to create customer');
    }
    return Map<String, dynamic>.from(data);
  }

  /// Builds the local stand-in for a customer created while offline — same
  /// shape as a real row from [getCustomers] so it drops straight into the
  /// Customer search field, except `id` is null and `client_uuid` is set.
  /// Screens that build an order payload must check for `client_uuid` and
  /// send `customerClientUuid` instead of `customerId` when it's set — see
  /// order-add.php, which resolves it to the real id once this customer's
  /// own outbox entry has synced.
  static Map<String, dynamic> buildPendingCustomer({
    required String clientUuid,
    required String customerName,
    String? address,
    int? salesOfficerId,
    int? supplierManId,
  }) {
    return {
      'id': null,
      'client_uuid': clientUuid,
      'customer_code': 'PENDING',
      'customer_name': customerName,
      'current_balance': 0,
      'address': address ?? '',
      'default_discount_percentage': 0,
      'credit_limit': 0,
      'associated_sales_officer_id': salesOfficerId,
      'supplier_man_id': supplierManId,
    };
  }

  /// Customers created offline whose outbox entry hasn't synced yet — read
  /// live from the shared outbox (not a separate store) so this list can
  /// never drift out of sync with what's actually still pending. Lets a
  /// freshly reopened app still offer a just-created, not-yet-synced
  /// customer in the Sale Order picker.
  static Future<List<Map<String, dynamic>>> getPendingCustomers() async {
    final entries = await OutboxService.instance.getAll();
    return entries
        .where((e) => e.endpoint == customerAddEndpoint)
        .map(
          (e) => buildPendingCustomer(
            clientUuid: e.clientUuid,
            customerName: e.payload['customerName']?.toString() ?? '',
            address: e.payload['address']?.toString(),
            salesOfficerId: e.payload['salesOfficerId'] as int?,
            supplierManId: e.payload['supplierManId'] as int?,
          ),
        )
        .toList();
  }
}
