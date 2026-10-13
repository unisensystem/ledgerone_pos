import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:uuid/uuid.dart';

import '../../core/outbox_service.dart';
import '../../core/reference_cache.dart';
import '../../core/sync_service.dart';
import 'sale_return_api.dart';
import 'sale_return_calc.dart';
import 'sale_return_field_settings.dart';
import 'sale_return_remembered.dart';

/// Same product-unit resolution as Sale Order's _productUnits() — shared
/// shape (uom_type/group_units/default_unit_id), so this is intentionally
/// identical logic, not a coincidence.
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

const _stockStatusOptions = [
  {'value': 'sellable', 'label': 'Sellable'},
  {'value': 'damaged', 'label': 'Damaged'},
];

class _ReturnLine {
  final Map<String, dynamic> product;
  final List<Map<String, dynamic>> unitQuantities;
  final double salePrice;
  final double discountPercent;
  final double tradeOfferPercent;
  final double taxPercent;
  final double focQuantity;
  final String stockStatus;
  final String? batchNo;
  final String? expiryDate;

  _ReturnLine({
    required this.product,
    required this.unitQuantities,
    required this.salePrice,
    this.discountPercent = 0,
    this.tradeOfferPercent = 0,
    this.taxPercent = 0,
    this.focQuantity = 0,
    this.stockStatus = 'sellable',
    this.batchNo,
    this.expiryDate,
  });

  double get totalQty => unitQuantities.fold(
    0.0,
    (sum, u) =>
        sum + (u['quantity'] as double) * (u['conversionFactor'] as double),
  );

  ReturnLineCalc get calc => ReturnLineCalc(
    quantity: totalQty,
    salePrice: salePrice,
    discountPercent: discountPercent,
    tradeOfferPercent: tradeOfferPercent,
    taxPercent: taxPercent,
    focQuantity: focQuantity,
  );
}

/// Same searchable-Autocomplete field as Sale Order's _searchableField() —
/// duplicated rather than shared across feature folders to avoid coupling
/// two otherwise-independent features to one file neither fully owns.
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

class SaleReturnScreen extends StatefulWidget {
  /// When set, edits this existing return (PUT to return-edit.php) instead
  /// of creating a new one.
  final int? editReturnId;

  const SaleReturnScreen({super.key, this.editReturnId});

  @override
  State<SaleReturnScreen> createState() => _SaleReturnScreenState();
}

class _SaleReturnScreenState extends State<SaleReturnScreen> {
  bool _loadingReferenceData = true;
  String? _loadError;
  SaleReturnFieldSettings _fieldSettings = const SaleReturnFieldSettings();

  List<dynamic> _companies = [];
  List<dynamic> _branches = [];
  List<dynamic> _customers = [];
  List<dynamic> _products = [];
  List<dynamic> _currencies = [];
  List<dynamic> _employees = [];
  List<dynamic> _bankAccounts = [];
  List<dynamic> _subAccounts = [];
  List<dynamic> _saleInvoices = [];

  int? _selectedCompanyId;
  int? _selectedBranchId;
  Map<String, dynamic>? _selectedCustomer;
  int? _selectedCurrencyId;
  int? _selectedSalesOfficerId;
  int? _selectedSupplierManId;
  int? _selectedSubAccountId;
  Map<String, dynamic>? _selectedInvoice;
  DateTime _selectedReturnDate = DateTime.now();
  String _paymentMethod = 'cash';
  int? _selectedBankAccountId;
  int _formResetCounter = 0;
  // Matches web's Price Type radio (return-add.php) — controls which
  // product price auto-fills in the Add Item dialog: Trade Price (default,
  // matching web's `checked` default) or MRP.
  String _priceType = 'tp';
  bool _loadingInvoicePrefill = false;

  bool get _isEditing => widget.editReturnId != null;

  final _remarksController = TextEditingController();
  final _totalDiscountPercentController = TextEditingController(text: '0');
  final _shippingFeesController = TextEditingController(text: '0');
  final _amountPaidController = TextEditingController(text: '0');

  final List<_ReturnLine> _lines = [];

  bool _submitting = false;
  String? _submitResult;

  @override
  void initState() {
    super.initState();
    _loadReferenceData();
    SaleReturnFieldSettings.load().then((s) {
      if (mounted) setState(() => _fieldSettings = s);
    });
  }

  Future<void> _openFieldSettings() async {
    final updated = await showReturnFieldSettingsDialog(
      context,
      _fieldSettings,
    );
    if (updated != null) {
      setState(() => _fieldSettings = updated);
      await updated.save();
    }
  }

  @override
  void dispose() {
    _remarksController.dispose();
    _totalDiscountPercentController.dispose();
    _shippingFeesController.dispose();
    _amountPaidController.dispose();
    super.dispose();
  }

  Future<void> _loadReferenceData() async {
    setState(() {
      _loadingReferenceData = true;
      _loadError = null;
    });
    try {
      final futures = <Future<dynamic>>[
        ReferenceCache.cached('sr_companies', SaleReturnApi.getCompanies),
        ReferenceCache.cached('sr_branches', SaleReturnApi.getBranches),
        ReferenceCache.cached('sr_customers', SaleReturnApi.getCustomers),
        ReferenceCache.cached('sr_products', SaleReturnApi.getProducts),
        ReferenceCache.cached('sr_currencies', SaleReturnApi.getCurrencies),
        ReferenceCache.cached('sr_employees', SaleReturnApi.getEmployees),
        ReferenceCache.cached(
          'sr_bank_accounts',
          SaleReturnApi.getBankAccounts,
        ),
        // Loaded unfiltered at init, matching web's own loadSaleInvoices() —
        // picking one is what SETS the customer (via _onInvoiceSelected),
        // not the other way around.
        ReferenceCache.cached(
          'sr_sale_invoices',
          SaleReturnApi.getSaleInvoices,
        ),
        SaleReturnRemembered.getCompanyId(),
        SaleReturnRemembered.getBranchId(),
        SaleReturnRemembered.getCurrencyId(),
      ];
      final results = await Future.wait(futures);
      _companies = results[0] as List<dynamic>;
      _branches = results[1] as List<dynamic>;
      _customers = results[2] as List<dynamic>;
      _products = results[3] as List<dynamic>;
      _currencies = results[4] as List<dynamic>;
      _employees = results[5] as List<dynamic>;
      _bankAccounts = results[6] as List<dynamic>;
      _saleInvoices = results[7] as List<dynamic>;
      final rememberedCompanyId = results[8] as int?;
      final rememberedBranchId = results[9] as int?;
      final rememberedCurrencyId = results[10] as int?;

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
        final base = _currencies.firstWhere(
          (c) => c['is_base_currency'].toString() == '1',
          orElse: () => _currencies.first,
        );
        _selectedCurrencyId = int.tryParse(base['currency_id'].toString());
      }

      if (_isEditing) {
        await _loadExistingReturn();
      }

      setState(() => _loadingReferenceData = false);
    } catch (e) {
      setState(() {
        _loadingReferenceData = false;
        _loadError = 'Could not load data: $e';
      });
    }
  }

  Future<void> _loadExistingReturn() async {
    final result = await ReferenceCache.cached(
      'sr_edit_${widget.editReturnId}',
      () => SaleReturnApi.getReturnForEdit(widget.editReturnId!),
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
    _selectedSubAccountId = invoice['sub_account_id'] != null
        ? int.tryParse(invoice['sub_account_id'].toString())
        : null;
    _selectedBankAccountId = invoice['bank_account_id'] != null
        ? int.tryParse(invoice['bank_account_id'].toString())
        : null;
    _paymentMethod =
        (invoice['payment_method']
                ?.toString()
                .toLowerCase()
                .replaceAll(' ', '_')
                .isNotEmpty ??
            false)
        ? invoice['payment_method'].toString().toLowerCase().replaceAll(
            ' ',
            '_',
          )
        : 'cash';
    _remarksController.text = invoice['remarks']?.toString() ?? '';
    _totalDiscountPercentController.text =
        invoice['total_discount_percent']?.toString() ?? '0';
    _shippingFeesController.text = invoice['shipping_fees']?.toString() ?? '0';
    _amountPaidController.text = invoice['amount_refunded']?.toString() ?? '0';
    if (invoice['sale_date'] != null) {
      _selectedReturnDate =
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
      _loadSubAccounts(customerId);
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
          return _ReturnLine(
            product: product,
            unitQuantities: unitQuantities,
            salePrice: double.tryParse(m['sale_price']?.toString() ?? '') ?? 0,
            discountPercent:
                double.tryParse(m['discount_percent']?.toString() ?? '') ?? 0,
            tradeOfferPercent:
                double.tryParse(m['trade_offer_percent']?.toString() ?? '') ??
                0,
            taxPercent:
                double.tryParse(m['tax_percent']?.toString() ?? '') ?? 0,
            focQuantity:
                double.tryParse(m['foc_quantity']?.toString() ?? '') ?? 0,
            stockStatus: m['stock_status']?.toString() ?? 'sellable',
            batchNo: m['batch_no']?.toString(),
            expiryDate: m['expiry_date']?.toString(),
          );
        }),
      );
  }

  Future<void> _loadSubAccounts(int customerId) async {
    List<dynamic> subs = [];
    try {
      subs = await ReferenceCache.cached(
        'sr_subaccounts_$customerId',
        () => SaleReturnApi.getSubAccounts(customerId),
      );
    } catch (_) {
      // No prior cache for this customer offline — proceed with none.
    }
    if (!mounted) return;
    setState(() => _subAccounts = subs);
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

  ReturnTotalsCalc get _totals => ReturnTotalsCalc(
    totalBill: _lines.fold(0, (sum, l) => sum + l.calc.netAmount),
    discountPercent: double.tryParse(_totalDiscountPercentController.text) ?? 0,
    shippingFees: double.tryParse(_shippingFeesController.text) ?? 0,
  );

  double get _amountPaid => double.tryParse(_amountPaidController.text) ?? 0;
  double get _remainingBalance => _totals.netAmount - _amountPaid;

  void _addLine() {
    if (_products.isEmpty) return;
    showDialog(
      context: context,
      builder: (context) => _AddReturnLineDialog(
        products: _products,
        fieldSettings: _fieldSettings,
        priceType: _priceType,
        onAdd: (line) => setState(() => _lines.add(line)),
      ),
    );
  }

  /// Matches web's loadSaleInvoiceData() in return-add.js — picking an
  /// original invoice fully replaces Customer/Branch/Currency/Company and
  /// the item list with that invoice's own data, not just a soft link.
  Future<void> _onInvoiceSelected(Map<String, dynamic> invoiceSummary) async {
    setState(() => _loadingInvoicePrefill = true);
    try {
      final invoiceId = invoiceSummary['id'] as int;
      final result = await ReferenceCache.cached(
        'sr_invoice_detail_$invoiceId',
        () => SaleReturnApi.getSaleInvoiceDetail(invoiceId),
      );
      final invoice = Map<String, dynamic>.from(result['invoice'] as Map);
      final rawItems = List<dynamic>.from(result['items'] as List);

      final customerId = invoice['customer_id'] != null
          ? int.tryParse(invoice['customer_id'].toString())
          : null;
      final customer = customerId != null
          ? _customers.cast<Map<String, dynamic>>().firstWhere(
              (c) => c['id'] == customerId,
              orElse: () => {
                'id': customerId,
                'customer_code': invoice['customer_code'] ?? '',
                'customer_name': invoice['customer_name'] ?? '',
                'current_balance': invoice['previous_balance'],
                'address': invoice['customer_address'],
              },
            )
          : null;

      // sale_invoice_items is one row per unit entry (siblings share a
      // product_id/parent_row_id) — group back into one _ReturnLine per
      // product, same grouping pattern used for edit-mode hydration.
      final byProduct = <dynamic, List<Map<String, dynamic>>>{};
      for (final raw in rawItems) {
        final m = Map<String, dynamic>.from(raw as Map);
        (byProduct[m['product_id']] ??= []).add(m);
      }

      final newLines = byProduct.entries.map((entry) {
        final rows = entry.value;
        final first = rows.first;
        final productId = entry.key;
        final product = _products.cast<Map<String, dynamic>>().firstWhere(
          (p) => p['id'] == productId,
          orElse: () => {'id': productId, 'name': first['product_name'] ?? ''},
        );
        final canonicalUnits = _productUnits(product);
        final unitQuantities = canonicalUnits.map((u) {
          final match = rows.firstWhere(
            (r) => r['uom_id'].toString() == u['id'].toString(),
            orElse: () => <String, dynamic>{},
          );
          return {
            'unitId': u['id'],
            'unitName': u['name'],
            'conversionFactor': u['conversionFactor'],
            'quantity': match.isNotEmpty
                ? double.tryParse(match['quantity'].toString()) ?? 0.0
                : 0.0,
          };
        }).toList();

        return _ReturnLine(
          product: product,
          unitQuantities: unitQuantities,
          salePrice:
              double.tryParse(first['sale_price']?.toString() ?? '') ?? 0,
          discountPercent:
              double.tryParse(first['discount_percent']?.toString() ?? '') ?? 0,
          tradeOfferPercent:
              double.tryParse(first['trade_offer_percent']?.toString() ?? '') ??
              0,
          taxPercent:
              double.tryParse(first['gst_percent']?.toString() ?? '') ?? 0,
          focQuantity:
              double.tryParse(first['foc_quantity']?.toString() ?? '') ?? 0,
          stockStatus: first['stock_status']?.toString() ?? 'sellable',
        );
      }).toList();

      if (!mounted) return;
      setState(() {
        _selectedInvoice = invoiceSummary;
        if (customer != null) {
          _selectedCustomer = customer;
          _loadSubAccounts(customer['id'] as int);
        }
        final branchId = invoice['branch_id'] != null
            ? int.tryParse(invoice['branch_id'].toString())
            : null;
        if (branchId != null) _selectedBranchId = branchId;
        final currencyId = invoice['currency_id'] != null
            ? int.tryParse(invoice['currency_id'].toString())
            : null;
        if (currencyId != null) _selectedCurrencyId = currencyId;
        final companyId = invoice['company_id'] != null
            ? int.tryParse(invoice['company_id'].toString())
            : null;
        if (companyId != null) _selectedCompanyId = companyId;
        _totalDiscountPercentController.text =
            invoice['total_discount_percent']?.toString() ?? '0';
        _lines
          ..clear()
          ..addAll(newLines);
        _loadingInvoicePrefill = false;
        // Fresh keys so Company/Currency/Branch/Customer widgets actually
        // show these programmatically-set values (Autocomplete/DropdownButtonFormField
        // only read their initial value once per widget instance).
        _formResetCounter++;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _loadingInvoicePrefill = false;
        _submitResult = 'Could not load invoice: $e';
      });
    }
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
    if (_paymentMethod == 'bank_transfer' &&
        _selectedBankAccountId == null &&
        _amountPaid > 0) {
      setState(
        () => _submitResult = 'Select a bank account for the refund, or switch payment method to Cash.',
      );
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
        'tradeOfferPercent': l.tradeOfferPercent,
        'tradeOfferAmount': c.tradeOfferAmount,
        'taxPercent': l.taxPercent,
        'taxAmount': c.taxAmount,
        'focQuantity': l.focQuantity,
        'netAmount': c.netAmount,
        'stockStatus': l.stockStatus,
        'batchNo': l.batchNo,
        'expiryDate': l.expiryDate,
        'unitEntries': l.unitQuantities
            .where((u) => (u['quantity'] as double) > 0)
            .map((u) => {'uomId': u['unitId'], 'quantity': u['quantity']})
            .toList(),
      };
    }).toList();

    final customerLabel =
        '${_selectedCustomer!['customer_code']} - ${_selectedCustomer!['customer_name']}';
    final payload = SaleReturnApi.buildReturnPayload(
      clientUuid: clientUuid,
      saleDate: DateFormat('yyyy-MM-dd').format(_selectedReturnDate),
      customerId: _selectedCustomer!['id'] as int,
      customerPhone: _selectedCustomer!['primary_phone']?.toString(),
      branchId: _selectedBranchId!,
      companyId: _selectedCompanyId!,
      currencyId: _selectedCurrencyId ?? 1,
      previousBalance: _previousBalance,
      saleInvoiceId: _selectedInvoice != null
          ? _selectedInvoice!['id'] as int
          : null,
      salesOfficerId: _selectedSalesOfficerId,
      supplierManId: _selectedSupplierManId,
      subAccountId: _selectedSubAccountId,
      remarks: _remarksController.text.trim().isEmpty
          ? null
          : _remarksController.text.trim(),
      totalBill: totals.totalBill,
      totalDiscountPercent: totals.discountPercent,
      totalDiscountAmount: totals.discountAmount,
      shippingFees: totals.shippingFees,
      netAmount: totals.netAmount,
      paymentMethod: _paymentMethod,
      bankAccountId: _paymentMethod == 'bank_transfer'
          ? _selectedBankAccountId
          : null,
      amountPaid: _amountPaid,
      remainingBalance: _remainingBalance,
      status: status,
      items: items,
    );

    if (_isEditing) {
      final editPayload = {...payload, 'invoice_id': widget.editReturnId};
      if (!await SyncService.instance.isOnline()) {
        await _saveEditToOutbox(editPayload);
        return;
      }
      try {
        final result = await SaleReturnApi.updateReturn(
          widget.editReturnId!,
          payload,
        );
        if (result['success'] == true) {
          setState(() => _submitting = false);
          await _showSuccessDialog(
            'Return Updated!',
            'Changes saved successfully.',
            isEdit: true,
          );
        } else {
          setState(() {
            _submitting = false;
            _submitResult =
                result['message']?.toString() ?? 'Failed to update return';
          });
        }
      } catch (e) {
        await _saveEditToOutbox(editPayload);
      }
      return;
    }

    if (!await SyncService.instance.isOnline()) {
      await _saveToOutbox(clientUuid, payload, customerLabel);
      return;
    }

    try {
      final result = await SaleReturnApi.createReturn(payload);
      if (result['success'] == true) {
        final billNo = result['bill_no']?.toString() ?? '';
        setState(() {
          _submitting = false;
          _lines.clear();
        });
        if (result['already_synced'] == true) {
          await _showSuccessDialog(
            'Already Synced',
            'This return was already synced earlier as $billNo.',
          );
        } else if (status == 'Draft') {
          await _showSuccessDialog(
            'Saved as Draft',
            'Return $billNo saved as draft.',
          );
        } else {
          await _showSuccessDialog(
            'Return Created!',
            'Return $billNo created successfully.',
          );
        }
      } else {
        setState(() {
          _submitting = false;
          _submitResult =
              result['message']?.toString() ?? 'Failed to save return';
        });
      }
    } catch (e) {
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
        endpoint: SaleReturnApi.createReturnEndpoint,
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
      'No connection — this return is saved on your device and will sync automatically once you\'re back online.',
    );
  }

  Future<void> _saveEditToOutbox(Map<String, dynamic> editPayload) async {
    final label = _selectedCustomer != null
        ? 'Edit: ${_selectedCustomer!['customer_code']} - ${_selectedCustomer!['customer_name']}'
        : 'Edit: return #${widget.editReturnId}';
    await OutboxService.instance.add(
      OutboxEntry(
        clientUuid: const Uuid().v4(),
        endpoint: SaleReturnApi.editReturnEndpoint,
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
                _resetForNewReturn();
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
              Navigator.of(context).pop();
            },
            child: Text(isEdit ? 'Done' : 'View Returns'),
          ),
        ],
      ),
    );
  }

  void _resetForNewReturn() {
    setState(() {
      _selectedCustomer = null;
      _selectedSalesOfficerId = null;
      _selectedSupplierManId = null;
      _selectedSubAccountId = null;
      _selectedInvoice = null;
      _selectedReturnDate = DateTime.now();
      _paymentMethod = 'cash';
      _selectedBankAccountId = null;
      _remarksController.clear();
      _totalDiscountPercentController.text = '0';
      _shippingFeesController.text = '0';
      _amountPaidController.text = '0';
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
        title: Text(_isEditing ? 'Edit Sale Return' : 'New Sale Return'),
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
                _sectionTitle('Return Details'),
                _priceTypeField(),
                const SizedBox(height: 12),
                _dateField(),
                const SizedBox(height: 12),
                if (_fieldSettings.showCompanyField) ...[
                  _dropdown<int>(
                    key: ValueKey('company-field-$_formResetCounter'),
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
                      if (v != null) SaleReturnRemembered.setCompanyId(v);
                    },
                  ),
                  const SizedBox(height: 12),
                ],
                if (_fieldSettings.showCurrencyField) ...[
                  _dropdown<int>(
                    key: ValueKey('currency-field-$_formResetCounter'),
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
                      if (v != null) SaleReturnRemembered.setCurrencyId(v);
                    },
                  ),
                  const SizedBox(height: 12),
                ],
                if (_fieldSettings.showBranchField) ...[
                  _searchableField<Map<String, dynamic>>(
                    key: ValueKey('branch-field-$_formResetCounter'),
                    label: 'Branch (type to search)',
                    options: _branches.cast<Map<String, dynamic>>(),
                    displayStringForOption: (b) => b['branch_name'].toString(),
                    matches: (b, q) =>
                        b['branch_name'].toString().toLowerCase().contains(q),
                    onSelected: (b) {
                      setState(() => _selectedBranchId = b['id'] as int);
                      SaleReturnRemembered.setBranchId(b['id'] as int);
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
                  onSelected: (c) {
                    setState(() {
                      _selectedCustomer = c;
                      _selectedSubAccountId = null;
                      _subAccounts = [];
                      final supplierManId = int.tryParse(
                        c['supplier_man_id']?.toString() ?? '',
                      );
                      if (supplierManId != null)
                        _selectedSupplierManId = supplierManId;
                    });
                    _loadSubAccounts(c['id'] as int);
                  },
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
                if (_fieldSettings.showOriginalInvoice) ...[
                  const SizedBox(height: 12),
                  _searchableField<Map<String, dynamic>>(
                    key: ValueKey('invoice-field-$_formResetCounter'),
                    label: 'Original Sale Invoice (optional, type to search)',
                    options: _saleInvoices.cast<Map<String, dynamic>>(),
                    displayStringForOption: (i) =>
                        '${i['bill_no']} — ${i['customer_name'] ?? ''}',
                    matches: (i, q) =>
                        i['bill_no'].toString().toLowerCase().contains(q) ||
                        (i['customer_name']?.toString().toLowerCase().contains(
                              q,
                            ) ??
                            false),
                    initialText: _selectedInvoice != null
                        ? _selectedInvoice!['bill_no']?.toString()
                        : null,
                    onSelected: (i) => _onInvoiceSelected(i),
                  ),
                  if (_loadingInvoicePrefill) ...[
                    const SizedBox(height: 6),
                    const Text(
                      'Loading invoice…',
                      style: TextStyle(fontSize: 11, color: Color(0xFF6B7280)),
                    ),
                  ],
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
                if (_fieldSettings.showSubAccount &&
                    _subAccounts.isNotEmpty) ...[
                  const SizedBox(height: 12),
                  _dropdown<int>(
                    key: ValueKey('sub-account-field-$_formResetCounter'),
                    label: 'Sub Account (optional)',
                    value: _selectedSubAccountId,
                    items: _subAccounts
                        .map(
                          (s) => DropdownMenuItem(
                            value: s['id'] as int,
                            child: Text(s['sub_account_name'].toString()),
                          ),
                        )
                        .toList(),
                    onChanged: (v) => setState(() => _selectedSubAccountId = v),
                    allowClear: true,
                  ),
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
                    if (line.stockStatus == 'damaged') 'Damaged',
                    if (line.discountPercent > 0)
                      'Disc ${line.discountPercent}% (-${c.discountAmount.toStringAsFixed(2)})',
                    if (line.tradeOfferPercent > 0)
                      'T.O ${line.tradeOfferPercent}% (-${c.tradeOfferAmount.toStringAsFixed(2)})',
                    if (line.taxPercent > 0)
                      'Tax ${line.taxPercent}% (+${c.taxAmount.toStringAsFixed(2)})',
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
                _sectionTitle('Return Summary'),
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

                if (_fieldSettings.showPaymentDetails) ...[
                  const SizedBox(height: 16),
                  _dropdown<String>(
                    label: 'Payment Method',
                    value: _paymentMethod,
                    items: const [
                      DropdownMenuItem(value: 'cash', child: Text('Cash')),
                      DropdownMenuItem(
                        value: 'bank_transfer',
                        child: Text('Bank Transfer'),
                      ),
                    ],
                    onChanged: (v) =>
                        setState(() => _paymentMethod = v ?? 'cash'),
                  ),
                  if (_paymentMethod == 'bank_transfer') ...[
                    const SizedBox(height: 12),
                    _dropdown<int>(
                      label: 'Bank Account',
                      value: _selectedBankAccountId,
                      items: _bankAccounts
                          .map(
                            (b) => DropdownMenuItem(
                              value: b['id'] as int,
                              child: Text(b['account_title'].toString()),
                            ),
                          )
                          .toList(),
                      onChanged: (v) =>
                          setState(() => _selectedBankAccountId = v),
                    ),
                  ],
                  const SizedBox(height: 12),
                  _smallNumberField('Amount Refunded', _amountPaidController),
                  const SizedBox(height: 8),
                  _readOnlyInfo(
                    'Remaining Balance',
                    _remainingBalance.toStringAsFixed(2),
                  ),
                ],

                const SizedBox(height: 24),
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
                              : const Text('Save Return'),
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

  // Matches web's Price Type radio (return-add.php) — decides whether the
  // Add Item dialog auto-fills a newly-picked product's Trade Price or MRP
  // as the default return price.
  Widget _priceTypeField() {
    return Row(
      children: [
        Expanded(
          child: RadioListTile<String>(
            dense: true,
            contentPadding: EdgeInsets.zero,
            title: const Text(
              'Trade Price (TP)',
              style: TextStyle(fontSize: 13),
            ),
            value: 'tp',
            groupValue: _priceType,
            onChanged: (v) => setState(() => _priceType = v ?? 'tp'),
          ),
        ),
        Expanded(
          child: RadioListTile<String>(
            dense: true,
            contentPadding: EdgeInsets.zero,
            title: const Text('MRP', style: TextStyle(fontSize: 13)),
            value: 'mrp',
            groupValue: _priceType,
            onChanged: (v) => setState(() => _priceType = v ?? 'tp'),
          ),
        ),
      ],
    );
  }

  // Field-use control: a return is always dated today, no picker — same
  // "today only" rule as Sale Order's Sale Date (see sale_order_screen.dart's
  // _dateField for the full reasoning). Doesn't apply to a line item's batch
  // expiry date elsewhere on this screen — that's a product attribute, not
  // a transaction date.
  Widget _dateField() {
    return InputDecorator(
      decoration: const InputDecoration(
        labelText: 'Return Date',
        border: OutlineInputBorder(),
        filled: true,
        fillColor: Color(0xFFF0F0F0),
        suffixIcon: Icon(Icons.lock_outline, size: 16),
      ),
      child: Text(DateFormat('dd MMM yyyy').format(_selectedReturnDate)),
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

class _AddReturnLineDialog extends StatefulWidget {
  final List<dynamic> products;
  final SaleReturnFieldSettings fieldSettings;
  final String priceType;
  final void Function(_ReturnLine) onAdd;

  const _AddReturnLineDialog({
    required this.products,
    required this.fieldSettings,
    required this.priceType,
    required this.onAdd,
  });

  @override
  State<_AddReturnLineDialog> createState() => _AddReturnLineDialogState();
}

class _AddReturnLineDialogState extends State<_AddReturnLineDialog> {
  Map<String, dynamic>? _selectedProduct;
  List<Map<String, dynamic>> _units = [];
  Map<dynamic, TextEditingController> _unitControllers = {};
  final _priceController = TextEditingController();
  final _discPercentController = TextEditingController(text: '0');
  final _tradeOfferPercentController = TextEditingController(text: '0');
  final _taxPercentController = TextEditingController(text: '0');
  final _focController = TextEditingController(text: '0');
  final _batchNoController = TextEditingController();
  // A real date picker, not free text — matches web's correct reference
  // implementation (counter-return.js uses <input type="date">; return-add.js,
  // which this app deliberately does NOT copy, has no expiry field at all).
  DateTime? _expiryDate;
  String _stockStatus = 'sellable';

  @override
  void dispose() {
    for (final c in _unitControllers.values) {
      c.dispose();
    }
    _priceController.dispose();
    _discPercentController.dispose();
    _tradeOfferPercentController.dispose();
    _taxPercentController.dispose();
    _focController.dispose();
    _batchNoController.dispose();
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
      // get-products.php aliases p.mrp AS sale_price — so "sale_price" here
      // is actually MRP, and "trade_price" is TP. Matches web's identical
      // priceType === 'tp' ? product.trade_price : product.sale_price.
      final price = widget.priceType == 'tp'
          ? product['trade_price']
          : product['sale_price'];
      _priceController.text = price != null
          ? double.tryParse(price.toString())?.toStringAsFixed(2) ?? ''
          : '';
    });
  }

  double _totalQty() {
    var total = 0.0;
    for (final u in _units) {
      final qty = double.tryParse(_unitControllers[u['id']]?.text ?? '') ?? 0;
      total += qty * (u['conversionFactor'] as double);
    }
    return total;
  }

  ReturnLineCalc? get _liveCalc {
    if (_units.isEmpty) return null;
    final price = double.tryParse(_priceController.text);
    if (price == null) return null;
    return ReturnLineCalc(
      quantity: _totalQty(),
      salePrice: price,
      discountPercent: double.tryParse(_discPercentController.text) ?? 0,
      tradeOfferPercent:
          double.tryParse(_tradeOfferPercentController.text) ?? 0,
      taxPercent: double.tryParse(_taxPercentController.text) ?? 0,
      focQuantity: double.tryParse(_focController.text) ?? 0,
    );
  }

  @override
  Widget build(BuildContext context) {
    final calc = _liveCalc;
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
            ],
            if (_units.isNotEmpty) ...[
              ..._pairRows(
                _units
                    .map(
                      (u) => _numField(
                        u['name'].toString(),
                        _unitControllers[u['id']]!,
                      ),
                    )
                    .toList(),
              ),
            ],
            const SizedBox(height: 12),
            _numField('Price', _priceController),
            if (widget.fieldSettings.showStockStatus) ...[
              const SizedBox(height: 12),
              DropdownButtonFormField<String>(
                initialValue: _stockStatus,
                decoration: const InputDecoration(
                  labelText: 'Stock Status',
                  border: OutlineInputBorder(),
                  isDense: true,
                ),
                items: _stockStatusOptions
                    .map(
                      (s) => DropdownMenuItem(
                        value: s['value'],
                        child: Text(s['label']!),
                      ),
                    )
                    .toList(),
                onChanged: (v) =>
                    setState(() => _stockStatus = v ?? 'sellable'),
              ),
            ],
            ..._pairRows([
              if (widget.fieldSettings.showDiscount)
                _numField('Disc %', _discPercentController),
              if (widget.fieldSettings.showTradeOffer)
                _numField('Trade Offer %', _tradeOfferPercentController),
              if (widget.fieldSettings.showTax)
                _numField('Tax %', _taxPercentController),
              if (widget.fieldSettings.showFoc)
                _numField('FOC Qty', _focController),
            ]),
            if (widget.fieldSettings.showBatchExpiry) ...[
              ..._pairRows([
                _textField('Batch No (optional)', _batchNoController),
                _expiryDateField(),
              ]),
            ],
            if (calc != null) ...[
              const Divider(height: 24),
              _calcLine('Gross Amount', calc.grossAmount),
              if (widget.fieldSettings.showDiscount)
                _calcLine('Discount Amount', calc.discountAmount),
              if (widget.fieldSettings.showTradeOffer)
                _calcLine('Trade Offer Amount', calc.tradeOfferAmount),
              if (widget.fieldSettings.showTax)
                _calcLine('Tax Amount', calc.taxAmount),
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
              _ReturnLine(
                product: _selectedProduct!,
                unitQuantities: unitQuantities,
                salePrice: price,
                discountPercent:
                    double.tryParse(_discPercentController.text) ?? 0,
                tradeOfferPercent:
                    double.tryParse(_tradeOfferPercentController.text) ?? 0,
                taxPercent: double.tryParse(_taxPercentController.text) ?? 0,
                focQuantity: double.tryParse(_focController.text) ?? 0,
                stockStatus: _stockStatus,
                batchNo: _batchNoController.text.trim().isEmpty
                    ? null
                    : _batchNoController.text.trim(),
                expiryDate: _expiryDate != null
                    ? DateFormat('yyyy-MM-dd').format(_expiryDate!)
                    : null,
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

  Widget _numField(String label, TextEditingController controller) {
    return TextField(
      controller: controller,
      keyboardType: const TextInputType.numberWithOptions(decimal: true),
      onChanged: (_) => setState(() {}),
      decoration: InputDecoration(
        labelText: label,
        border: const OutlineInputBorder(),
        isDense: true,
      ),
    );
  }

  Widget _textField(String label, TextEditingController controller) {
    return TextField(
      controller: controller,
      decoration: InputDecoration(
        labelText: label,
        border: const OutlineInputBorder(),
        isDense: true,
      ),
    );
  }

  Widget _expiryDateField() {
    return InkWell(
      onTap: () async {
        final picked = await showDatePicker(
          context: context,
          initialDate: _expiryDate ?? DateTime.now(),
          firstDate: DateTime(2020),
          lastDate: DateTime(2100),
        );
        if (picked != null) setState(() => _expiryDate = picked);
      },
      child: InputDecorator(
        decoration: InputDecoration(
          labelText: 'Expiry Date (optional)',
          border: const OutlineInputBorder(),
          isDense: true,
          suffixIcon: _expiryDate != null
              ? IconButton(
                  icon: const Icon(Icons.close, size: 16),
                  onPressed: () => setState(() => _expiryDate = null),
                )
              : const Icon(Icons.calendar_today_outlined, size: 16),
        ),
        child: Text(
          _expiryDate != null
              ? DateFormat('dd MMM yyyy').format(_expiryDate!)
              : 'Not set',
          style: TextStyle(
            color: _expiryDate != null ? null : const Color(0xFF9AA2AF),
          ),
        ),
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
}
