import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:uuid/uuid.dart';

import '../../core/outbox_service.dart';
import '../../core/reference_cache.dart';
import '../../core/sync_service.dart';
import 'sale_order_api.dart';
import 'sale_order_calc.dart';
import 'sale_order_field_settings.dart';
import 'sale_order_remembered.dart';

/// Mirrors web's getProductUOMDetails() in order-add-uom.js — a product is
/// either single-unit (its default_unit_id/name) or, for uom_type 'group',
/// has several selectable units (Carton/Dozen/Piece, etc.) each with its own
/// conversion factor back to the base unit.
List<Map<String, dynamic>> _productUnits(Map<String, dynamic> product) {
  final uomType = product['uom_type']?.toString() ?? 'unit';
  if (uomType == 'group') {
    final groupUnits = product['group_units'] as List? ?? [];
    if (groupUnits.isNotEmpty) {
      return groupUnits
          .map(
            (u) => {
              'id': u['id'],
              'name': u['uom_name']?.toString() ?? 'Unit',
              'conversionFactor':
                  double.tryParse(u['conversion_factor']?.toString() ?? '1') ??
                  1.0,
            },
          )
          .toList()
          .cast<Map<String, dynamic>>();
    }
  }
  if (product['default_unit_id'] != null) {
    return [
      {
        'id': product['default_unit_id'],
        'name': product['default_unit_name']?.toString() ?? 'Unit',
        'conversionFactor': 1.0,
      },
    ];
  }
  return [];
}

String _schemeLabel(String scheme) {
  switch (scheme) {
    case 'less':
      return 'Less';
    case 'less_special':
      return 'Less Special';
    case 'given':
      return 'Given';
    default:
      return 'Sale On TP';
  }
}

const _schemeOptions = [
  {'value': 'sale_on_tp', 'label': 'Sale On TP'},
  {'value': 'less', 'label': 'Less'},
  {'value': 'less_special', 'label': 'Less Special'},
  {'value': 'given', 'label': 'Given'},
];

class _OrderLine {
  final Map<String, dynamic> product;
  // Each entry: {unitId, unitName, conversionFactor, quantity}
  final List<Map<String, dynamic>> unitQuantities;
  final double salePrice;
  final double discountPercent;
  final double tradeOfferAmount;
  final double gstPercent;
  final double focQuantity;
  final String scheme;

  _OrderLine({
    required this.product,
    required this.unitQuantities,
    required this.salePrice,
    this.discountPercent = 0,
    this.tradeOfferAmount = 0,
    this.gstPercent = 0,
    this.focQuantity = 0,
    this.scheme = 'sale_on_tp',
  });

  double get totalQty => unitQuantities.fold(
    0.0,
    (sum, u) =>
        sum + (u['quantity'] as double) * (u['conversionFactor'] as double),
  );

  OrderLineCalc get calc => OrderLineCalc(
    quantity: totalQty,
    salePrice: salePrice,
    discountPercent: discountPercent,
    tradeOfferAmount: tradeOfferAmount,
    gstPercent: gstPercent,
    focQuantity: focQuantity,
  );
}

/// A searchable, typeahead picker — matches the web app's searchable
/// Customer/Branch dropdowns (plain <select> fields stay plain dropdowns,
/// matching web). Built on Flutter's own Autocomplete widget rather than a
/// DropdownButtonFormField, which ALSO fixes a real bug the plain dropdowns
/// had: DropdownButtonFormField only shows a value as "selected" when it is
/// `==`-identical to one of the objects in its `items` list. Since our
/// Customer/Product options are freshly-built Map objects on every rebuild,
/// a picked value could never match by reference/equality again, so the
/// field silently reverted to showing nothing picked even though a real
/// selection was stored. Autocomplete manages its own display text
/// directly from the selected option, so this class of bug can't recur.
Widget _searchableField<T extends Object>({
  Key? key,
  required String label,
  required List<T> options,
  required String Function(T) displayStringForOption,
  required bool Function(T option, String query) matches,
  required ValueChanged<T> onSelected,
  String? initialText,
}) {
  return Autocomplete<T>(
    key: key,
    displayStringForOption: displayStringForOption,
    initialValue: initialText != null
        ? TextEditingValue(text: initialText)
        : null,
    optionsBuilder: (textEditingValue) {
      final query = textEditingValue.text.toLowerCase();
      if (query.isEmpty) return options;
      return options.where((o) => matches(o, query));
    },
    onSelected: onSelected,
    fieldViewBuilder: (context, controller, focusNode, onFieldSubmitted) {
      return TextField(
        controller: controller,
        focusNode: focusNode,
        decoration: InputDecoration(
          labelText: label,
          border: const OutlineInputBorder(),
          filled: true,
          fillColor: Colors.white,
          suffixIcon: const Icon(Icons.search, size: 20),
        ),
      );
    },
    optionsViewBuilder: (context, onSelectedCb, optionsList) {
      final list = optionsList.toList();
      return Align(
        alignment: Alignment.topLeft,
        child: Material(
          elevation: 4,
          borderRadius: BorderRadius.circular(8),
          child: ConstrainedBox(
            constraints: const BoxConstraints(
              maxHeight: 280,
              minWidth: 280,
              maxWidth: 560,
            ),
            child: list.isEmpty
                ? const Padding(
                    padding: EdgeInsets.all(16),
                    child: Text(
                      'No matches',
                      style: TextStyle(color: Color(0xFF6B7280)),
                    ),
                  )
                : ListView.builder(
                    padding: EdgeInsets.zero,
                    shrinkWrap: true,
                    itemCount: list.length,
                    itemBuilder: (context, index) {
                      final option = list[index];
                      return ListTile(
                        dense: true,
                        title: Text(
                          displayStringForOption(option),
                          overflow: TextOverflow.ellipsis,
                        ),
                        onTap: () => onSelectedCb(option),
                      );
                    },
                  ),
          ),
        ),
      );
    },
  );
}

class SaleOrderScreen extends StatefulWidget {
  /// When set, the screen loads this existing order and edits it (PUT to
  /// order-edit.php) instead of creating a new one — same form web reuses
  /// for its own `order-add.php?edit=<id>` flow.
  final int? editOrderId;

  const SaleOrderScreen({super.key, this.editOrderId});

  @override
  State<SaleOrderScreen> createState() => _SaleOrderScreenState();
}

class _SaleOrderScreenState extends State<SaleOrderScreen> {
  bool _loadingReferenceData = true;
  String? _loadError;
  SaleOrderFieldSettings _fieldSettings = const SaleOrderFieldSettings();

  List<dynamic> _companies = [];
  List<dynamic> _branches = [];
  List<dynamic> _customers = [];
  List<dynamic> _products = [];
  List<dynamic> _currencies = [];
  List<dynamic> _employees = [];
  List<dynamic> _projects = [];

  int? _selectedCompanyId;
  int? _selectedBranchId;
  Map<String, dynamic>? _selectedCustomer;
  int? _selectedCurrencyId;
  int? _selectedSalesOfficerId;
  int? _selectedSupplierManId;
  int? _selectedProjectId;
  DateTime _selectedSaleDate = DateTime.now();
  // Bumped on "Create Another" so the Customer field's Autocomplete widget
  // gets a fresh Key — Autocomplete only reads initialValue once per widget
  // instance, so just nulling _selectedCustomer wouldn't actually clear the
  // text already showing in the field without this.
  int _formResetCounter = 0;

  bool get _isEditing => widget.editOrderId != null;

  final _biltyNoController = TextEditingController();
  final _transportNameController = TextEditingController();
  final _remarksController = TextEditingController();
  final _totalDiscountPercentController = TextEditingController(text: '0');
  final _totalGstPercentController = TextEditingController(text: '0');
  final _shippingFeesController = TextEditingController(text: '0');

  final List<_OrderLine> _lines = [];

  bool _submitting = false;
  String? _submitResult;

  @override
  void initState() {
    super.initState();
    _loadReferenceData();
    SaleOrderFieldSettings.load().then((s) {
      if (mounted) setState(() => _fieldSettings = s);
    });
  }

  Future<void> _openFieldSettings() async {
    final updated = await showFieldSettingsDialog(context, _fieldSettings);
    if (updated != null) {
      setState(() => _fieldSettings = updated);
      await updated.save();
    }
  }

  @override
  void dispose() {
    _biltyNoController.dispose();
    _transportNameController.dispose();
    _remarksController.dispose();
    _totalDiscountPercentController.dispose();
    _totalGstPercentController.dispose();
    _shippingFeesController.dispose();
    super.dispose();
  }

  Future<void> _loadReferenceData() async {
    setState(() {
      _loadingReferenceData = true;
      _loadError = null;
    });
    try {
      final futures = <Future<dynamic>>[
        ReferenceCache.cached('so_companies', SaleOrderApi.getCompanies),
        ReferenceCache.cached('so_branches', SaleOrderApi.getBranches),
        ReferenceCache.cached('so_customers', SaleOrderApi.getCustomers),
        ReferenceCache.cached('so_products', SaleOrderApi.getProducts),
        ReferenceCache.cached('so_currencies', SaleOrderApi.getCurrencies),
        ReferenceCache.cached('so_employees', SaleOrderApi.getEmployees),
        ReferenceCache.cached('so_projects', SaleOrderApi.getProjects),
        SaleOrderRemembered.getCompanyId(),
        SaleOrderRemembered.getBranchId(),
        SaleOrderRemembered.getCurrencyId(),
      ];
      final results = await Future.wait(futures);
      _companies = results[0] as List<dynamic>;
      _branches = results[1] as List<dynamic>;
      _customers = results[2] as List<dynamic>;
      _products = results[3] as List<dynamic>;
      _currencies = results[4] as List<dynamic>;
      _employees = results[5] as List<dynamic>;
      _projects = results[6] as List<dynamic>;
      final rememberedCompanyId = results[7] as int?;
      final rememberedBranchId = results[8] as int?;
      final rememberedCurrencyId = results[9] as int?;

      // Remembered selections win when they still refer to a real option —
      // otherwise fall back to the same defaults as before (first company/
      // branch, tenant's base currency).
      if (rememberedCompanyId != null &&
          _companies.any((c) => c['id'] == rememberedCompanyId)) {
        _selectedCompanyId = rememberedCompanyId;
      } else if (_companies.isNotEmpty) {
        _selectedCompanyId = _companies.first['id'] as int;
      }
      if (rememberedBranchId != null &&
          _branches.any((b) => b['id'] == rememberedBranchId)) {
        _selectedBranchId = rememberedBranchId;
      } else if (_branches.isNotEmpty) {
        _selectedBranchId = _branches.first['id'] as int;
      }
      if (rememberedCurrencyId != null &&
          _currencies.any(
            (c) =>
                int.tryParse(c['currency_id'].toString()) ==
                rememberedCurrencyId,
          )) {
        _selectedCurrencyId = rememberedCurrencyId;
      } else if (_currencies.isNotEmpty) {
        // Matches web (order-add.js: option.selected = currency.is_base_currency == 1) —
        // defaults to the tenant's base currency, not just whichever came back first.
        final base = _currencies.firstWhere(
          (c) => c['is_base_currency'].toString() == '1',
          orElse: () => _currencies.first,
        );
        _selectedCurrencyId = int.tryParse(base['currency_id'].toString());
      }

      if (_isEditing) {
        await _loadExistingOrder();
      }

      setState(() => _loadingReferenceData = false);
    } catch (e) {
      setState(() {
        _loadingReferenceData = false;
        _loadError = 'Could not load data: $e';
      });
    }
  }

  Future<void> _loadExistingOrder() async {
    final result = await ReferenceCache.cached(
      'so_edit_${widget.editOrderId}',
      () => SaleOrderApi.getOrderForEdit(widget.editOrderId!),
    );
    final invoice = Map<String, dynamic>.from(result['invoice'] as Map);
    final items = List<dynamic>.from(result['items'] as List);

    _selectedCompanyId = invoice['company_id'] != null
        ? int.tryParse(invoice['company_id'].toString())
        : _selectedCompanyId;
    _selectedBranchId = invoice['branch_id'] != null
        ? int.tryParse(invoice['branch_id'].toString())
        : _selectedBranchId;
    _selectedCurrencyId = invoice['currency_id'] != null
        ? int.tryParse(invoice['currency_id'].toString())
        : _selectedCurrencyId;
    _selectedSalesOfficerId = invoice['sale_officer_id'] != null
        ? int.tryParse(invoice['sale_officer_id'].toString())
        : null;
    _selectedSupplierManId = invoice['supplier_man_id'] != null
        ? int.tryParse(invoice['supplier_man_id'].toString())
        : null;
    _selectedProjectId = invoice['project_id'] != null
        ? int.tryParse(invoice['project_id'].toString())
        : null;
    _biltyNoController.text = invoice['bilty_no']?.toString() ?? '';
    _transportNameController.text = invoice['transport_name']?.toString() ?? '';
    _remarksController.text = invoice['remarks']?.toString() ?? '';
    _totalDiscountPercentController.text =
        invoice['total_discount_percent']?.toString() ?? '0';
    _totalGstPercentController.text =
        invoice['total_gst_percent']?.toString() ?? '0';
    _shippingFeesController.text = invoice['shipping_fees']?.toString() ?? '0';
    if (invoice['sale_date'] != null) {
      _selectedSaleDate =
          DateTime.tryParse(invoice['sale_date'].toString()) ?? DateTime.now();
    }

    final customerId = invoice['customer_id'] != null
        ? int.tryParse(invoice['customer_id'].toString())
        : null;
    if (customerId != null) {
      _selectedCustomer = _customers.cast<Map<String, dynamic>>().firstWhere(
        (c) => c['id'] == customerId,
        orElse: () => {
          'id': customerId,
          'customer_code': invoice['customer_code'] ?? '',
          'customer_name': invoice['customer_name'] ?? '',
          'current_balance': invoice['previous_balance'],
          'address': invoice['customer_address'],
        },
      );
    }

    _lines
      ..clear()
      ..addAll(
        items.map((item) {
          final m = Map<String, dynamic>.from(item as Map);
          final productId = m['product_id'];
          final product = _products.cast<Map<String, dynamic>>().firstWhere(
            (p) => p['id'] == productId,
            orElse: () => {
              'id': productId,
              'name': m['product_name'] ?? '',
              'uom_type': m['uom_type'],
              'uom_group_id': m['uom_group_id'],
              'default_unit_id': m['default_unit_id'],
            },
          );
          final canonicalUnits = _productUnits(product);
          final savedEntries = List<dynamic>.from(
            m['unit_entries'] as List? ?? [],
          );
          final unitQuantities = canonicalUnits.map((u) {
            final saved = savedEntries.firstWhere(
              (e) => e['uom_id'].toString() == u['id'].toString(),
              orElse: () => null,
            );
            return {
              'unitId': u['id'],
              'unitName': u['name'],
              'conversionFactor': u['conversionFactor'],
              'quantity': saved != null
                  ? double.tryParse(saved['quantity'].toString()) ?? 0.0
                  : 0.0,
            };
          }).toList();
          return _OrderLine(
            product: product,
            unitQuantities: unitQuantities,
            salePrice: double.tryParse(m['sale_price']?.toString() ?? '') ?? 0,
            discountPercent:
                double.tryParse(m['discount_percent']?.toString() ?? '') ?? 0,
            tradeOfferAmount:
                double.tryParse(m['trade_offer_amount']?.toString() ?? '') ?? 0,
            gstPercent:
                double.tryParse(m['gst_percent']?.toString() ?? '') ?? 0,
            focQuantity:
                double.tryParse(m['foc_quantity']?.toString() ?? '') ?? 0,
            scheme: m['scheme']?.toString() ?? 'sale_on_tp',
          );
        }),
      );
  }

  double get _previousBalance =>
      double.tryParse(
        _selectedCustomer?['current_balance']?.toString() ?? '',
      ) ??
      0;
  String get _customerAddress =>
      (_selectedCustomer?['address']?.toString().trim().isNotEmpty ?? false)
      ? _selectedCustomer!['address'].toString()
      : 'No address on file';

  OrderTotalsCalc get _totals => OrderTotalsCalc(
    totalBill: _lines.fold(0, (sum, l) => sum + l.calc.netAmount),
    discountPercent: double.tryParse(_totalDiscountPercentController.text) ?? 0,
    gstPercent: double.tryParse(_totalGstPercentController.text) ?? 0,
    shippingFees: double.tryParse(_shippingFeesController.text) ?? 0,
  );

  void _addLine() {
    if (_products.isEmpty) return;
    showDialog(
      context: context,
      builder: (context) => _AddLineDialog(
        products: _products,
        fieldSettings: _fieldSettings,
        onAdd: (line) => setState(() => _lines.add(line)),
      ),
    );
  }

  Future<void> _submit({required String status}) async {
    if (_selectedCustomer == null ||
        _selectedBranchId == null ||
        _selectedCompanyId == null) {
      setState(
        () => _submitResult = 'Please select company, branch and customer.',
      );
      return;
    }
    if (_lines.isEmpty) {
      setState(() => _submitResult = 'Add at least one item.');
      return;
    }

    setState(() {
      _submitting = true;
      _submitResult = null;
    });

    final clientUuid = const Uuid().v4();
    final totals = _totals;
    final items = _lines.map((l) {
      final c = l.calc;
      return {
        'productId': l.product['id'],
        'salePrice': l.salePrice,
        'grossAmount': c.grossAmount,
        'discountPercent': l.discountPercent,
        'discountAmount': c.discountAmount,
        'tradeOfferPercent': 0,
        'tradeOfferAmount': l.tradeOfferAmount,
        'gstPercent': l.gstPercent,
        'gstAmount': c.gstAmount,
        'focQty': l.focQuantity,
        'netAmount': c.netAmount,
        'scheme': l.scheme,
        // Matches web: only non-zero unit quantities are sent (order-add.js
        // skips zero inputs the same way before building unitEntries).
        'unitEntries': l.unitQuantities
            .where((u) => (u['quantity'] as double) > 0)
            .map((u) => {'uomId': u['unitId'], 'quantity': u['quantity']})
            .toList(),
      };
    }).toList();

    final customerLabel =
        '${_selectedCustomer!['customer_code']} - ${_selectedCustomer!['customer_name']}';
    final payload = SaleOrderApi.buildOrderPayload(
      clientUuid: clientUuid,
      saleDate: DateFormat('yyyy-MM-dd').format(_selectedSaleDate),
      customerId: _selectedCustomer!['id'] as int,
      branchId: _selectedBranchId!,
      companyId: _selectedCompanyId!,
      currencyId: _selectedCurrencyId ?? 1,
      previousBalance: _previousBalance,
      salesOfficerId: _selectedSalesOfficerId,
      supplierManId: _selectedSupplierManId,
      projectId: _selectedProjectId,
      biltyNo: _biltyNoController.text.trim().isEmpty
          ? null
          : _biltyNoController.text.trim(),
      transportName: _transportNameController.text.trim().isEmpty
          ? null
          : _transportNameController.text.trim(),
      remarks: _remarksController.text.trim().isEmpty
          ? null
          : _remarksController.text.trim(),
      totalBill: totals.totalBill,
      totalDiscountPercent: totals.discountPercent,
      totalDiscountAmount: totals.discountAmount,
      totalGstPercent: totals.gstPercent,
      totalGstAmount: totals.gstAmount,
      shippingFees: totals.shippingFees,
      netAmount: totals.netAmount,
      status: status,
      items: items,
    );

    if (_isEditing) {
      final editPayload = {...payload, 'invoice_id': widget.editOrderId};

      // Offline-first, same as create: don't attempt a network round trip
      // we already know will fail.
      if (!await SyncService.instance.isOnline()) {
        await _saveEditToOutbox(editPayload);
        return;
      }

      try {
        final result = await SaleOrderApi.updateOrder(
          widget.editOrderId!,
          payload,
        );
        if (result['success'] == true) {
          setState(() => _submitting = false);
          if (result['needs_review'] == true) {
            await _showSuccessDialog(
              'Saved for Review',
              'Changes saved for office review. ${result['message'] ?? 'Details changed since this was cached offline.'}',
              isEdit: true,
            );
          } else {
            await _showSuccessDialog(
              'Order Updated!',
              'Changes saved successfully.',
              isEdit: true,
            );
          }
        } else {
          setState(() {
            _submitting = false;
            _submitResult =
                result['message']?.toString() ?? 'Failed to update order';
          });
        }
      } catch (e) {
        // Connectivity check passed but the request still failed mid-flight
        // — queue it rather than lose the edit, same as create does.
        await _saveEditToOutbox(editPayload);
      }
      return;
    }

    // Offline-first: don't even attempt the network round trip if we already
    // know there's no connectivity — go straight to the outbox so the booker
    // isn't stuck waiting on a guaranteed-fail request.
    if (!await SyncService.instance.isOnline()) {
      await _saveToOutbox(clientUuid, payload, customerLabel);
      return;
    }

    try {
      final result = await SaleOrderApi.createOrder(payload);

      if (result['success'] == true) {
        final billNo = result['bill_no']?.toString() ?? '';
        setState(() {
          _submitting = false;
          _lines.clear();
        });
        if (result['already_synced'] == true) {
          await _showSuccessDialog(
            'Already Synced',
            'This order was already synced earlier as $billNo.',
          );
        } else if (result['needs_review'] == true) {
          // Server's own message distinguishes price vs stock vs both —
          // see order-add.php's $reviewReason.
          await _showSuccessDialog(
            'Saved for Review',
            'Order $billNo saved for office review. ${result['message'] ?? 'Details changed since this was cached offline.'}',
          );
        } else if (status == 'Draft') {
          await _showSuccessDialog(
            'Saved as Draft',
            'Order $billNo saved as draft.',
          );
        } else {
          await _showSuccessDialog(
            'Order Created!',
            'Order $billNo created successfully.',
          );
        }
      } else {
        setState(() {
          _submitting = false;
          _submitResult =
              result['message']?.toString() ?? 'Failed to save order';
        });
      }
    } catch (e) {
      // The connectivity check above passed but the request still failed
      // (dropped mid-flight, server unreachable, timeout) — fall back to the
      // outbox instead of losing the booker's work.
      await _saveToOutbox(clientUuid, payload, customerLabel);
    }
  }

  Future<void> _saveToOutbox(
    String clientUuid,
    Map<String, dynamic> payload,
    String customerLabel,
  ) async {
    await OutboxService.instance.add(
      OutboxEntry(
        clientUuid: clientUuid,
        endpoint: SaleOrderApi.createOrderEndpoint,
        label: '$customerLabel — ${payload['items'].length} item(s)',
        payload: payload,
        createdAt: DateTime.now(),
      ),
    );
    if (!mounted) return;
    setState(() {
      _submitting = false;
      _lines.clear();
    });
    SyncService.instance.syncNow();
    await _showSuccessDialog(
      'Saved Offline',
      'No connection — this order is saved on your device and will sync automatically once you\'re back online.',
    );
  }

  Future<void> _saveEditToOutbox(Map<String, dynamic> editPayload) async {
    final label = _selectedCustomer != null
        ? 'Edit: ${_selectedCustomer!['customer_code']} - ${_selectedCustomer!['customer_name']}'
        : 'Edit: order #${widget.editOrderId}';
    await OutboxService.instance.add(
      OutboxEntry(
        clientUuid: const Uuid().v4(),
        endpoint: SaleOrderApi.editOrderEndpoint,
        method: 'PUT',
        label: label,
        payload: editPayload,
        createdAt: DateTime.now(),
      ),
    );
    if (!mounted) return;
    setState(() => _submitting = false);
    SyncService.instance.syncNow();
    await _showSuccessDialog(
      'Saved Offline',
      'No connection — these changes are saved on your device and will sync automatically once you\'re back online.',
      isEdit: true,
    );
  }

  Future<void> _showSuccessDialog(
    String title,
    String message, {
    bool isEdit = false,
  }) async {
    if (!mounted) return;
    await showDialog<void>(
      context: context,
      barrierDismissible: false,
      builder: (dialogContext) => AlertDialog(
        icon: const Icon(
          Icons.check_circle,
          color: Color(0xFF2FBF71),
          size: 48,
        ),
        title: Text(title),
        content: Text(message),
        actions: [
          if (!isEdit)
            TextButton(
              onPressed: () {
                Navigator.of(dialogContext).pop();
                _resetForNewOrder();
              },
              child: const Text('Create Another'),
            ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFF1F7BFF),
              foregroundColor: Colors.white,
            ),
            onPressed: () {
              Navigator.of(dialogContext).pop();
              // Pops back to the Sale Orders list, which reloads itself
              // (list screen awaits this push and re-fetches on return) —
              // the "soft reload" the new/edited order should show up in.
              Navigator.of(context).pop();
            },
            child: Text(isEdit ? 'Done' : 'View Orders'),
          ),
        ],
      ),
    );
  }

  /// A real soft-reload for "Create Another" — before this, the dialog just
  /// closed and left every per-order field (Customer, Sales Officer,
  /// Remarks, Discount%, …) still showing the PREVIOUS order's values, with
  /// only the item list actually cleared. Company/Branch/Currency are
  /// deliberately left alone — those are the sticky, remembered fields, not
  /// stale leftovers, and the whole point is not re-picking them.
  void _resetForNewOrder() {
    setState(() {
      _selectedCustomer = null;
      _selectedSalesOfficerId = null;
      _selectedSupplierManId = null;
      _selectedProjectId = null;
      _selectedSaleDate = DateTime.now();
      _biltyNoController.clear();
      _transportNameController.clear();
      _remarksController.clear();
      _totalDiscountPercentController.text = '0';
      _totalGstPercentController.text = '0';
      _shippingFeesController.text = '0';
      _lines.clear();
      _submitResult = null;
      _formResetCounter++;
    });
  }

  @override
  Widget build(BuildContext context) {
    final totals = _totals;
    return Scaffold(
      backgroundColor: const Color(0xFFF7F9FC),
      appBar: AppBar(
        title: Text(_isEditing ? 'Edit Sale Order' : 'New Sale Order'),
        backgroundColor: const Color(0xFF1F7BFF),
        foregroundColor: Colors.white,
        actions: [
          IconButton(
            icon: const Icon(Icons.tune),
            tooltip: 'Field Settings',
            onPressed: _openFieldSettings,
          ),
        ],
      ),
      body: _loadingReferenceData
          ? const Center(child: CircularProgressIndicator())
          : _loadError != null
          ? Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(_loadError!, textAlign: TextAlign.center),
                    const SizedBox(height: 12),
                    ElevatedButton(
                      onPressed: _loadReferenceData,
                      child: const Text('Retry'),
                    ),
                  ],
                ),
              ),
            )
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                _sectionTitle('Order Details'),
                _dateField(),
                const SizedBox(height: 12),
                if (_fieldSettings.showCompanyField) ...[
                  _dropdown<int>(
                    label: 'Company',
                    value: _selectedCompanyId,
                    items: _companies
                        .map(
                          (c) => DropdownMenuItem(
                            value: c['id'] as int,
                            child: Text(c['company_name'].toString()),
                          ),
                        )
                        .toList(),
                    onChanged: (v) {
                      setState(() => _selectedCompanyId = v);
                      if (v != null) SaleOrderRemembered.setCompanyId(v);
                    },
                  ),
                  const SizedBox(height: 12),
                ],
                if (_fieldSettings.showCurrencyField) ...[
                  _dropdown<int>(
                    label: 'Currency',
                    value: _selectedCurrencyId,
                    items: _currencies
                        .map(
                          (c) => DropdownMenuItem(
                            value: int.tryParse(c['currency_id'].toString()),
                            child: Text(
                              '${c['code']} - ${c['name']} (${c['symbol']})',
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                        )
                        .toList(),
                    onChanged: (v) {
                      setState(() => _selectedCurrencyId = v);
                      if (v != null) SaleOrderRemembered.setCurrencyId(v);
                    },
                  ),
                  const SizedBox(height: 12),
                ],
                if (_fieldSettings.showBranchField) ...[
                  _searchableField<Map<String, dynamic>>(
                    label: 'Branch (type to search)',
                    options: _branches.cast<Map<String, dynamic>>(),
                    displayStringForOption: (b) => b['branch_name'].toString(),
                    matches: (b, q) =>
                        b['branch_name'].toString().toLowerCase().contains(q),
                    onSelected: (b) {
                      setState(() => _selectedBranchId = b['id'] as int);
                      SaleOrderRemembered.setBranchId(b['id'] as int);
                    },
                    initialText: _branches
                        .cast<Map<String, dynamic>>()
                        .firstWhere(
                          (b) => b['id'] == _selectedBranchId,
                          orElse: () => _branches.isNotEmpty
                              ? _branches.first as Map<String, dynamic>
                              : const {},
                        )['branch_name']
                        ?.toString(),
                  ),
                  const SizedBox(height: 12),
                ],
                _searchableField<Map<String, dynamic>>(
                  key: ValueKey('customer-field-$_formResetCounter'),
                  label: 'Customer (type to search)',
                  options: _customers.cast<Map<String, dynamic>>(),
                  displayStringForOption: (c) =>
                      '${c['customer_code']} - ${c['customer_name']}',
                  matches: (c, q) =>
                      c['customer_name'].toString().toLowerCase().contains(q) ||
                      c['customer_code'].toString().toLowerCase().contains(q),
                  initialText: _selectedCustomer != null
                      ? '${_selectedCustomer!['customer_code']} - ${_selectedCustomer!['customer_name']}'
                      : null,
                  onSelected: (c) => setState(() {
                    _selectedCustomer = c;
                    // Matches web (order-add.js: data-sales-officer/data-supplier-man on
                    // the customer option) — only overwrite when the customer actually has
                    // one assigned, same as web's truthy-only check, so picking a customer
                    // with no assigned officer doesn't silently wipe a manual selection.
                    final officerId = int.tryParse(
                      c['associated_sales_officer_id']?.toString() ?? '',
                    );
                    if (officerId != null) _selectedSalesOfficerId = officerId;
                    final supplierManId = int.tryParse(
                      c['supplier_man_id']?.toString() ?? '',
                    );
                    if (supplierManId != null)
                      _selectedSupplierManId = supplierManId;
                  }),
                ),
                if (_selectedCustomer != null) ...[
                  const SizedBox(height: 12),
                  _readOnlyInfo(
                    'Selected Customer',
                    '${_selectedCustomer!['customer_code']} - ${_selectedCustomer!['customer_name']}',
                  ),
                  if (_fieldSettings.showCustomerInfo) ...[
                    const SizedBox(height: 8),
                    _readOnlyInfo(
                      'Previous Balance',
                      _previousBalance.toStringAsFixed(2),
                    ),
                    const SizedBox(height: 8),
                    _readOnlyInfo('Customer Address', _customerAddress),
                  ],
                ],
                if (_fieldSettings.showProject) ...[
                  const SizedBox(height: 12),
                  _dropdown<int>(
                    key: ValueKey('project-field-$_formResetCounter'),
                    label: 'Project (optional)',
                    value: _selectedProjectId,
                    items: _projects
                        .map(
                          (p) => DropdownMenuItem(
                            value: p['id'] as int,
                            child: Text(
                              '${p['project_code']} - ${p['project_name']}',
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                        )
                        .toList(),
                    onChanged: (v) => setState(() => _selectedProjectId = v),
                    allowClear: true,
                  ),
                ],
                if (_fieldSettings.showSalesOfficer) ...[
                  const SizedBox(height: 12),
                  _dropdown<int>(
                    key: ValueKey('sales-officer-field-$_formResetCounter'),
                    label: 'Sales Officer (optional)',
                    value: _selectedSalesOfficerId,
                    items: _employees
                        .map(
                          (e) => DropdownMenuItem(
                            value: e['id'] as int,
                            child: Text(e['full_name'].toString()),
                          ),
                        )
                        .toList(),
                    onChanged: (v) =>
                        setState(() => _selectedSalesOfficerId = v),
                    allowClear: true,
                  ),
                ],
                if (_fieldSettings.showSupplierMan) ...[
                  const SizedBox(height: 12),
                  _dropdown<int>(
                    key: ValueKey('supplier-man-field-$_formResetCounter'),
                    label: 'Supplier Man (optional)',
                    value: _selectedSupplierManId,
                    items: _employees
                        .map(
                          (e) => DropdownMenuItem(
                            value: e['id'] as int,
                            child: Text(e['full_name'].toString()),
                          ),
                        )
                        .toList(),
                    onChanged: (v) =>
                        setState(() => _selectedSupplierManId = v),
                    allowClear: true,
                  ),
                ],
                if (_fieldSettings.showBiltyNo) ...[
                  const SizedBox(height: 12),
                  _textField('Bilty No', _biltyNoController),
                ],
                if (_fieldSettings.showTransportName) ...[
                  const SizedBox(height: 12),
                  _textField('Transport Name', _transportNameController),
                ],
                if (_fieldSettings.showRemarks) ...[
                  const SizedBox(height: 12),
                  _textField('Remarks', _remarksController, maxLines: 3),
                ],

                const SizedBox(height: 24),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    _sectionTitle('Items'),
                    TextButton.icon(
                      onPressed: _addLine,
                      icon: const Icon(Icons.add),
                      label: const Text('Add Item'),
                    ),
                  ],
                ),
                if (_lines.isEmpty)
                  const Padding(
                    padding: EdgeInsets.symmetric(vertical: 16),
                    child: Text(
                      'No items added yet.',
                      style: TextStyle(color: Color(0xFF6B7280)),
                    ),
                  ),
                ..._lines.asMap().entries.map((entry) {
                  final i = entry.key;
                  final line = entry.value;
                  final c = line.calc;
                  final qtyLabel = line.unitQuantities
                      .where((u) => (u['quantity'] as double) > 0)
                      .map((u) => '${u['quantity']} ${u['unitName']}')
                      .join(', ');
                  final details = <String>[
                    '$qtyLabel x ${line.salePrice.toStringAsFixed(2)}',
                    if (line.scheme != 'sale_on_tp')
                      'Scheme: ${_schemeLabel(line.scheme)}',
                    if (line.discountPercent > 0)
                      'Disc ${line.discountPercent}% (-${c.discountAmount.toStringAsFixed(2)})',
                    if (line.tradeOfferAmount > 0)
                      'T.O -${line.tradeOfferAmount.toStringAsFixed(2)}',
                    if (line.gstPercent > 0)
                      'GST ${line.gstPercent}% (+${c.gstAmount.toStringAsFixed(2)})',
                    if (line.focQuantity > 0) 'FOC ${line.focQuantity}',
                  ];
                  return Card(
                    margin: const EdgeInsets.only(bottom: 8),
                    child: ListTile(
                      title: Text(line.product['name'].toString()),
                      subtitle: Text(details.join(' · ')),
                      trailing: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Text(
                            c.netAmount.toStringAsFixed(2),
                            style: const TextStyle(fontWeight: FontWeight.w600),
                          ),
                          IconButton(
                            icon: const Icon(Icons.close, size: 18),
                            onPressed: () => setState(() => _lines.removeAt(i)),
                          ),
                        ],
                      ),
                    ),
                  );
                }),

                const SizedBox(height: 24),
                _sectionTitle('Order Summary'),
                _summaryRow('Total Bill', totals.totalBill.toStringAsFixed(2)),
                if (_fieldSettings.showDiscount) ...[
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      Expanded(
                        child: _smallNumberField(
                          'Discount %',
                          _totalDiscountPercentController,
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: _readOnlyInfo(
                          'Discount Amt',
                          totals.discountAmount.toStringAsFixed(2),
                        ),
                      ),
                    ],
                  ),
                ],
                if (_fieldSettings.showGst) ...[
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      Expanded(
                        child: _smallNumberField(
                          'GST %',
                          _totalGstPercentController,
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: _readOnlyInfo(
                          'GST Amt',
                          totals.gstAmount.toStringAsFixed(2),
                        ),
                      ),
                    ],
                  ),
                ],
                if (_fieldSettings.showShipping) ...[
                  const SizedBox(height: 8),
                  _smallNumberField('Shipping Fees', _shippingFeesController),
                ],
                const SizedBox(height: 12),
                _summaryRow(
                  'Net Amount',
                  totals.netAmount.toStringAsFixed(2),
                  emphasize: true,
                ),

                const SizedBox(height: 24),
                // Success is now a modal (_showSuccessDialog) — this banner is
                // error-only: validation issues or a request that truly failed
                // (not offline, which is itself a handled success path above).
                if (_submitResult != null)
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(12),
                    margin: const EdgeInsets.only(bottom: 16),
                    decoration: BoxDecoration(
                      color: const Color(0xFFFDE8E8),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Text(
                      _submitResult!,
                      style: const TextStyle(color: Color(0xFFE34F4F)),
                    ),
                  ),
                Row(
                  children: [
                    Expanded(
                      child: SizedBox(
                        height: 48,
                        child: OutlinedButton(
                          onPressed: _submitting
                              ? null
                              : () => _submit(status: 'Draft'),
                          child: const Text('Save as Draft'),
                        ),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: SizedBox(
                        height: 48,
                        child: ElevatedButton(
                          onPressed: _submitting
                              ? null
                              : () => _submit(status: 'Posted'),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: const Color(0xFF1F7BFF),
                            foregroundColor: Colors.white,
                          ),
                          child: _submitting
                              ? const SizedBox(
                                  width: 22,
                                  height: 22,
                                  child: CircularProgressIndicator(
                                    strokeWidth: 2.5,
                                    color: Colors.white,
                                  ),
                                )
                              : const Text('Save Order'),
                        ),
                      ),
                    ),
                  ],
                ),
              ],
            ),
    );
  }

  Widget _sectionTitle(String text) => Padding(
    padding: const EdgeInsets.only(bottom: 12),
    child: Text(
      text,
      style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
    ),
  );

  // Matches web (order-add.php's <input type="date" id="saleDate" required>,
  // defaulted to today but editable) — the mobile form was silently always
  // sending today's date with no field to change it at all.
  Widget _dateField() {
    return InkWell(
      onTap: () async {
        final picked = await showDatePicker(
          context: context,
          initialDate: _selectedSaleDate,
          firstDate: DateTime(2020),
          lastDate: DateTime.now().add(const Duration(days: 365)),
        );
        if (picked != null) setState(() => _selectedSaleDate = picked);
      },
      child: InputDecorator(
        decoration: InputDecoration(
          labelText: 'Sale Date',
          border: const OutlineInputBorder(),
          filled: true,
          fillColor: Colors.white,
          suffixIcon: const Icon(Icons.calendar_today_outlined, size: 18),
        ),
        child: Text(DateFormat('dd MMM yyyy').format(_selectedSaleDate)),
      ),
    );
  }

  Widget _dropdown<T>({
    Key? key,
    required String label,
    required T? value,
    required List<DropdownMenuItem<T>> items,
    required ValueChanged<T?> onChanged,
    bool allowClear = false,
  }) {
    return DropdownButtonFormField<T>(
      key: key,
      initialValue: value,
      isExpanded: true,
      decoration: InputDecoration(
        labelText: label,
        border: const OutlineInputBorder(),
        filled: true,
        fillColor: Colors.white,
      ),
      items: [
        if (allowClear)
          const DropdownMenuItem(value: null, child: Text('— None —')),
        ...items,
      ],
      onChanged: onChanged,
    );
  }

  Widget _textField(
    String label,
    TextEditingController controller, {
    int maxLines = 1,
  }) {
    return TextField(
      controller: controller,
      maxLines: maxLines,
      decoration: InputDecoration(
        labelText: label,
        border: const OutlineInputBorder(),
        filled: true,
        fillColor: Colors.white,
      ),
    );
  }

  Widget _smallNumberField(String label, TextEditingController controller) {
    return TextField(
      controller: controller,
      keyboardType: const TextInputType.numberWithOptions(decimal: true),
      onChanged: (_) => setState(() {}),
      decoration: InputDecoration(
        labelText: label,
        border: const OutlineInputBorder(),
        filled: true,
        fillColor: Colors.white,
        isDense: true,
      ),
    );
  }

  Widget _readOnlyInfo(String label, String value) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
        color: const Color(0xFFEFF2F7),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: const Color(0xFFE1E6EE)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            style: const TextStyle(fontSize: 11, color: Color(0xFF6B7280)),
          ),
          const SizedBox(height: 2),
          Text(value, style: const TextStyle(fontSize: 14)),
        ],
      ),
    );
  }

  Widget _summaryRow(String label, String value, {bool emphasize = false}) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(
          label,
          style: TextStyle(
            fontWeight: emphasize ? FontWeight.bold : FontWeight.normal,
            fontSize: emphasize ? 16 : 14,
          ),
        ),
        Text(
          value,
          style: TextStyle(
            fontWeight: FontWeight.bold,
            fontSize: emphasize ? 16 : 14,
          ),
        ),
      ],
    );
  }
}

class _AddLineDialog extends StatefulWidget {
  final List<dynamic> products;
  final SaleOrderFieldSettings fieldSettings;
  final void Function(_OrderLine) onAdd;

  const _AddLineDialog({
    required this.products,
    required this.fieldSettings,
    required this.onAdd,
  });

  @override
  State<_AddLineDialog> createState() => _AddLineDialogState();
}

class _AddLineDialogState extends State<_AddLineDialog> {
  Map<String, dynamic>? _selectedProduct;
  List<Map<String, dynamic>> _units = [];
  Map<dynamic, TextEditingController> _unitControllers = {};
  final _priceController = TextEditingController();
  final _discPercentController = TextEditingController(text: '0');
  final _tradeOfferController = TextEditingController(text: '0');
  final _gstPercentController = TextEditingController(text: '0');
  final _focController = TextEditingController(text: '0');

  String _scheme = 'sale_on_tp';
  List<dynamic> _schemes = [];
  bool _loadingSchemes = false;

  // "Available Stock" / "Required" — same idea as web's pos-add.php stock
  // panel. Advisory only: a null _availableStock (offline, or the call
  // failed) just hides the panel rather than blocking anything — the real
  // enforcement happens server-side at sync time (order-add.php), since
  // that's the only place that can see every other booker's pending orders
  // too, not just whatever this one phone last cached.
  double? _availableStock;
  String? _stockUnitName;
  bool _loadingStock = false;

  @override
  void dispose() {
    for (final c in _unitControllers.values) {
      c.dispose();
    }
    _priceController.dispose();
    _discPercentController.dispose();
    _tradeOfferController.dispose();
    _gstPercentController.dispose();
    _focController.dispose();
    super.dispose();
  }

  void _onProductChanged(Map<String, dynamic> product) {
    for (final c in _unitControllers.values) {
      c.dispose();
    }
    final units = _productUnits(product);
    setState(() {
      _selectedProduct = product;
      _units = units;
      _unitControllers = {
        for (final u in units) u['id']: TextEditingController(text: '0'),
      };
      final price = product['sale_price'];
      _priceController.text = price != null
          ? double.tryParse(price.toString())?.toStringAsFixed(2) ?? ''
          : '';
      // Matches web (order-add.js on product select): default discount % and
      // sales tax % come from the product itself, not left at zero.
      final defaultDiscount = product['default_discount'];
      _discPercentController.text = defaultDiscount != null
          ? defaultDiscount.toString()
          : '0';
      final salesTax = product['sales_tax'];
      _gstPercentController.text = salesTax != null ? salesTax.toString() : '0';
      _scheme = 'sale_on_tp';
      _schemes = [];
      _tradeOfferController.text = '0';
      _focController.text = '0';
      _availableStock = null;
      _stockUnitName = null;
    });
    _loadStock(product['id'] as int);
  }

  Future<void> _loadStock(int productId) async {
    setState(() => _loadingStock = true);
    Map<String, dynamic>? result;
    try {
      result = await ReferenceCache.cached(
        'so_stock_$productId',
        () => SaleOrderApi.getProductStock(productId),
      );
    } catch (_) {
      // Offline with no prior cache for this product — leave stock unknown
      // rather than leaving the loading spinner stuck forever.
    }
    if (!mounted || _selectedProduct?['id'] != productId) return;
    setState(() {
      _loadingStock = false;
      _availableStock = result != null
          ? double.tryParse(result['stock'].toString())
          : null;
      _stockUnitName = result?['unit_name']?.toString();
    });
  }

  Future<void> _ensureSchemesLoaded() async {
    if (_selectedProduct == null || _schemes.isNotEmpty) return;
    setState(() => _loadingSchemes = true);
    final productId = _selectedProduct!['id'] as int;
    List<dynamic> schemes = [];
    try {
      schemes = await ReferenceCache.cached(
        'so_schemes_$productId',
        () => SaleOrderApi.getProductSchemes(productId),
      );
    } catch (_) {
      // No prior cache for this product offline — proceed with no schemes.
    }
    if (mounted) {
      setState(() {
        _schemes = schemes;
        _loadingSchemes = false;
      });
    }
  }

  double _totalQty() {
    var total = 0.0;
    for (final u in _units) {
      final qty = double.tryParse(_unitControllers[u['id']]?.text ?? '') ?? 0;
      total += qty * (u['conversionFactor'] as double);
    }
    return total;
  }

  /// Mirrors order-add-uom.js's calculateLessSchemeAmounts /
  /// calculateLessSpecialSchemeAmounts / calculateGivenSchemeAmounts exactly.
  void _recomputeSchemeAmounts() {
    if (_scheme == 'less') {
      var totalTO = 0.0;
      for (final u in _units) {
        final qty = double.tryParse(_unitControllers[u['id']]?.text ?? '') ?? 0;
        final scheme = _schemes.firstWhere(
          (s) => s['unit_id'].toString() == u['id'].toString(),
          orElse: () => null,
        );
        if (scheme != null && qty > 0) {
          final toQty = double.tryParse(scheme['to_qty'].toString()) ?? 0;
          final toRs = double.tryParse(scheme['to_rs'].toString()) ?? 0;
          if (toQty > 0) totalTO += (qty / toQty).floor() * toRs;
        }
      }
      setState(() {
        _tradeOfferController.text = totalTO.toStringAsFixed(2);
        _focController.text = '0';
      });
    } else if (_scheme == 'less_special') {
      final price = double.tryParse(_priceController.text) ?? 0;
      final totalQty = _totalQty();
      var totalTO = 0.0;
      if (_schemes.isNotEmpty && totalQty > 0 && price > 0) {
        final s = _schemes.first;
        final promoQty = double.tryParse(s['promo_qty'].toString()) ?? 0;
        final bonusQty = double.tryParse(s['bonus_qty'].toString()) ?? 0;
        final denom = promoQty + bonusQty;
        if (denom > 0) totalTO = (totalQty * price) / denom;
      }
      setState(() {
        _tradeOfferController.text = totalTO.toStringAsFixed(2);
        _focController.text = '0';
      });
    } else if (_scheme == 'given') {
      var totalFOC = 0.0;
      for (final u in _units) {
        final qty = double.tryParse(_unitControllers[u['id']]?.text ?? '') ?? 0;
        final scheme = _schemes.firstWhere(
          (s) => s['unit_id'].toString() == u['id'].toString(),
          orElse: () => null,
        );
        if (scheme != null && qty > 0) {
          final promoQty = double.tryParse(scheme['promo_qty'].toString()) ?? 0;
          final bonusQty = double.tryParse(scheme['bonus_qty'].toString()) ?? 0;
          if (promoQty > 0) totalFOC += (qty / promoQty).floor() * bonusQty;
        }
      }
      setState(() {
        _tradeOfferController.text = '0.00';
        _focController.text = totalFOC.toStringAsFixed(2);
      });
    }
  }

  Future<void> _onSchemeChanged(String? value) async {
    if (value == null) return;
    setState(() => _scheme = value);
    if (value == 'sale_on_tp') {
      setState(() {
        _tradeOfferController.text = '0.00';
        _focController.text = '0';
      });
      return;
    }
    await _ensureSchemesLoaded();
    _recomputeSchemeAmounts();
  }

  Future<void> _onUnitQtyChanged() async {
    if (_scheme != 'sale_on_tp') {
      await _ensureSchemesLoaded();
      _recomputeSchemeAmounts();
    }
    setState(() {});
  }

  OrderLineCalc? get _liveCalc {
    if (_units.isEmpty) return null;
    final price = double.tryParse(_priceController.text);
    if (price == null) return null;
    return OrderLineCalc(
      quantity: _totalQty(),
      salePrice: price,
      discountPercent: double.tryParse(_discPercentController.text) ?? 0,
      tradeOfferAmount: double.tryParse(_tradeOfferController.text) ?? 0,
      gstPercent: double.tryParse(_gstPercentController.text) ?? 0,
      focQuantity: double.tryParse(_focController.text) ?? 0,
    );
  }

  @override
  Widget build(BuildContext context) {
    final calc = _liveCalc;
    final schemeLocked = _scheme == 'sale_on_tp';
    return AlertDialog(
      title: const Text('Add Item'),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            _searchableField<Map<String, dynamic>>(
              label: 'Product (type to search)',
              options: widget.products.cast<Map<String, dynamic>>(),
              displayStringForOption: (p) => p['name'].toString(),
              matches: (p, q) =>
                  p['name'].toString().toLowerCase().contains(q) ||
                  (p['code']?.toString().toLowerCase().contains(q) ?? false),
              onSelected: _onProductChanged,
            ),
            if (_selectedProduct != null) ...[
              const SizedBox(height: 8),
              Text(
                'Selected: ${_selectedProduct!['name']}',
                style: const TextStyle(fontSize: 12, color: Color(0xFF6B7280)),
                overflow: TextOverflow.ellipsis,
              ),
              _stockPanel(),
            ],
            if (_units.isNotEmpty) ...[
              if (widget.fieldSettings.showScheme) ...[
                const SizedBox(height: 12),
                DropdownButtonFormField<String>(
                  initialValue: _scheme,
                  decoration: const InputDecoration(
                    labelText: 'Scheme',
                    border: OutlineInputBorder(),
                    isDense: true,
                  ),
                  items: _schemeOptions
                      .map(
                        (s) => DropdownMenuItem(
                          value: s['value'],
                          child: Text(s['label']!),
                        ),
                      )
                      .toList(),
                  onChanged: _onSchemeChanged,
                ),
                if (_loadingSchemes)
                  const Padding(
                    padding: EdgeInsets.only(top: 6),
                    child: Text(
                      'Loading scheme rates…',
                      style: TextStyle(fontSize: 11, color: Color(0xFF6B7280)),
                    ),
                  ),
              ],
              ..._pairRows(
                _units
                    .map(
                      (u) => _numField(
                        u['name'].toString(),
                        _unitControllers[u['id']]!,
                        onChanged: () => _onUnitQtyChanged(),
                      ),
                    )
                    .toList(),
              ),
            ],
            const SizedBox(height: 12),
            _numField('Price', _priceController),
            ..._pairRows([
              if (widget.fieldSettings.showDiscount)
                _numField('Disc %', _discPercentController),
              if (widget.fieldSettings.showTradeOffer)
                _numField(
                  'Trade Offer Amt',
                  _tradeOfferController,
                  readOnly: schemeLocked,
                ),
              if (widget.fieldSettings.showGst)
                _numField('GST %', _gstPercentController),
              if (widget.fieldSettings.showFoc)
                _numField('FOC Qty', _focController, readOnly: schemeLocked),
            ]),
            if (calc != null) ...[
              const Divider(height: 24),
              _calcLine('Gross Amount', calc.grossAmount),
              if (widget.fieldSettings.showDiscount)
                _calcLine('Discount Amount', calc.discountAmount),
              if (widget.fieldSettings.showGst)
                _calcLine('GST Amount', calc.gstAmount),
              _calcLine('Net Amount', calc.netAmount, bold: true),
            ],
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(context).pop(),
          child: const Text('Cancel'),
        ),
        ElevatedButton(
          onPressed: () {
            final price = double.tryParse(_priceController.text);
            final unitQuantities = _units
                .map(
                  (u) => {
                    'unitId': u['id'],
                    'unitName': u['name'],
                    'conversionFactor': u['conversionFactor'],
                    'quantity':
                        double.tryParse(
                          _unitControllers[u['id']]?.text ?? '',
                        ) ??
                        0.0,
                  },
                )
                .toList();
            final hasQty = unitQuantities.any(
              (u) => (u['quantity'] as double) > 0,
            );
            if (_selectedProduct == null || !hasQty || price == null) return;
            widget.onAdd(
              _OrderLine(
                product: _selectedProduct!,
                unitQuantities: unitQuantities,
                salePrice: price,
                discountPercent:
                    double.tryParse(_discPercentController.text) ?? 0,
                tradeOfferAmount:
                    double.tryParse(_tradeOfferController.text) ?? 0,
                gstPercent: double.tryParse(_gstPercentController.text) ?? 0,
                focQuantity: double.tryParse(_focController.text) ?? 0,
                scheme: _scheme,
              ),
            );
            Navigator.of(context).pop();
          },
          child: const Text('Add'),
        ),
      ],
    );
  }

  List<Widget> _pairRows(List<Widget> fields) {
    final rows = <Widget>[];
    for (var i = 0; i < fields.length; i += 2) {
      final hasSecond = i + 1 < fields.length;
      rows.add(
        Padding(
          padding: const EdgeInsets.only(top: 12),
          child: Row(
            children: [
              Expanded(child: fields[i]),
              if (hasSecond) ...[
                const SizedBox(width: 10),
                Expanded(child: fields[i + 1]),
              ],
            ],
          ),
        ),
      );
    }
    return rows;
  }

  Widget _numField(
    String label,
    TextEditingController controller, {
    bool readOnly = false,
    VoidCallback? onChanged,
  }) {
    return TextField(
      controller: controller,
      readOnly: readOnly,
      keyboardType: const TextInputType.numberWithOptions(decimal: true),
      onChanged: (_) => onChanged != null ? onChanged() : setState(() {}),
      decoration: InputDecoration(
        labelText: label,
        border: const OutlineInputBorder(),
        isDense: true,
        filled: readOnly,
        fillColor: readOnly ? const Color(0xFFF0F0F0) : null,
      ),
    );
  }

  Widget _calcLine(String label, double value, {bool bold = false}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 2),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            label,
            style: TextStyle(
              fontWeight: bold ? FontWeight.bold : FontWeight.normal,
            ),
          ),
          Text(
            value.toStringAsFixed(2),
            style: TextStyle(
              fontWeight: bold ? FontWeight.bold : FontWeight.normal,
            ),
          ),
        ],
      ),
    );
  }

  /// Matches web's pos-add.php stock panel (Available Stock / Required),
  /// advisory only — see _availableStock's doc comment for why this can't
  /// be the actual safety net by itself.
  Widget _stockPanel() {
    if (_loadingStock) {
      return const Padding(
        padding: EdgeInsets.only(top: 6),
        child: Text(
          'Checking stock…',
          style: TextStyle(fontSize: 11, color: Color(0xFF6B7280)),
        ),
      );
    }
    if (_availableStock == null) {
      return const Padding(
        padding: EdgeInsets.only(top: 6),
        child: Text(
          'Stock unknown — offline or unavailable',
          style: TextStyle(fontSize: 11, color: Color(0xFF6B7280)),
        ),
      );
    }
    final required = _totalQty();
    final unit = _stockUnitName ?? 'units';
    final short = required > _availableStock!;
    final color = short ? const Color(0xFFE34F4F) : const Color(0xFF2FBF71);
    return Padding(
      padding: const EdgeInsets.only(top: 6),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Available Stock: ${_availableStock!.toStringAsFixed(3)} $unit',
            style: TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w600,
              color: color,
            ),
          ),
          if (required > 0)
            Text(
              'Required: ${required.toStringAsFixed(3)} $unit${short ? ' — more than available' : ''}',
              style: TextStyle(
                fontSize: 11,
                color: short ? color : const Color(0xFF6B7280),
              ),
            ),
        ],
      ),
    );
  }
}
