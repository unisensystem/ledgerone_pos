import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Per-device show/hide preferences for optional Sale Return fields — same
/// concept as SaleOrderFieldSettings (see its doc comment).
class SaleReturnFieldSettings {
  final bool showCompanyField;
  final bool showBranchField;
  final bool showCurrencyField;
  final bool showSalesOfficer;
  final bool showSupplierMan;
  final bool showSubAccount;
  final bool showOriginalInvoice;
  final bool showRemarks;
  final bool showCustomerInfo;
  final bool showDiscount;
  final bool showShipping;
  final bool showTradeOffer;
  final bool showTax;
  final bool showFoc;
  final bool showBatchExpiry;
  final bool showStockStatus;
  final bool showPaymentDetails;

  const SaleReturnFieldSettings({
    this.showCompanyField = true,
    this.showBranchField = true,
    this.showCurrencyField = true,
    this.showSalesOfficer = true,
    this.showSupplierMan = true,
    this.showSubAccount = true,
    this.showOriginalInvoice = true,
    this.showRemarks = true,
    // Off by default — matches web's own default (return-add.php's
    // "POS mode" ships with Previous Balance/Customer Address hidden
    // unless a booker explicitly turns the toggle on).
    this.showCustomerInfo = false,
    this.showDiscount = true,
    this.showShipping = true,
    this.showTradeOffer = true,
    this.showTax = true,
    this.showFoc = true,
    this.showBatchExpiry = true,
    this.showStockStatus = true,
    this.showPaymentDetails = true,
  });

  static const _prefix = 'sale_return_field_';

  SaleReturnFieldSettings copyWith({
    bool? showCompanyField,
    bool? showBranchField,
    bool? showCurrencyField,
    bool? showSalesOfficer,
    bool? showSupplierMan,
    bool? showSubAccount,
    bool? showOriginalInvoice,
    bool? showRemarks,
    bool? showCustomerInfo,
    bool? showDiscount,
    bool? showShipping,
    bool? showTradeOffer,
    bool? showTax,
    bool? showFoc,
    bool? showBatchExpiry,
    bool? showStockStatus,
    bool? showPaymentDetails,
  }) {
    return SaleReturnFieldSettings(
      showCompanyField: showCompanyField ?? this.showCompanyField,
      showBranchField: showBranchField ?? this.showBranchField,
      showCurrencyField: showCurrencyField ?? this.showCurrencyField,
      showSalesOfficer: showSalesOfficer ?? this.showSalesOfficer,
      showSupplierMan: showSupplierMan ?? this.showSupplierMan,
      showSubAccount: showSubAccount ?? this.showSubAccount,
      showOriginalInvoice: showOriginalInvoice ?? this.showOriginalInvoice,
      showRemarks: showRemarks ?? this.showRemarks,
      showCustomerInfo: showCustomerInfo ?? this.showCustomerInfo,
      showDiscount: showDiscount ?? this.showDiscount,
      showShipping: showShipping ?? this.showShipping,
      showTradeOffer: showTradeOffer ?? this.showTradeOffer,
      showTax: showTax ?? this.showTax,
      showFoc: showFoc ?? this.showFoc,
      showBatchExpiry: showBatchExpiry ?? this.showBatchExpiry,
      showStockStatus: showStockStatus ?? this.showStockStatus,
      showPaymentDetails: showPaymentDetails ?? this.showPaymentDetails,
    );
  }

  static Future<SaleReturnFieldSettings> load() async {
    final prefs = await SharedPreferences.getInstance();
    bool read(String key) => prefs.getBool('$_prefix$key') ?? true;
    return SaleReturnFieldSettings(
      showCompanyField: read('companyField'),
      showBranchField: read('branchField'),
      showCurrencyField: read('currencyField'),
      showSalesOfficer: read('salesOfficer'),
      showSupplierMan: read('supplierMan'),
      showSubAccount: read('subAccount'),
      showOriginalInvoice: read('originalInvoice'),
      showRemarks: read('remarks'),
      showCustomerInfo: prefs.getBool('${_prefix}customerInfo') ?? false,
      showDiscount: read('discount'),
      showShipping: read('shipping'),
      showTradeOffer: read('tradeOffer'),
      showTax: read('tax'),
      showFoc: read('foc'),
      showBatchExpiry: read('batchExpiry'),
      showStockStatus: read('stockStatus'),
      showPaymentDetails: read('paymentDetails'),
    );
  }

  Future<void> save() async {
    final prefs = await SharedPreferences.getInstance();
    await Future.wait([
      prefs.setBool('${_prefix}companyField', showCompanyField),
      prefs.setBool('${_prefix}branchField', showBranchField),
      prefs.setBool('${_prefix}currencyField', showCurrencyField),
      prefs.setBool('${_prefix}salesOfficer', showSalesOfficer),
      prefs.setBool('${_prefix}supplierMan', showSupplierMan),
      prefs.setBool('${_prefix}subAccount', showSubAccount),
      prefs.setBool('${_prefix}originalInvoice', showOriginalInvoice),
      prefs.setBool('${_prefix}remarks', showRemarks),
      prefs.setBool('${_prefix}customerInfo', showCustomerInfo),
      prefs.setBool('${_prefix}discount', showDiscount),
      prefs.setBool('${_prefix}shipping', showShipping),
      prefs.setBool('${_prefix}tradeOffer', showTradeOffer),
      prefs.setBool('${_prefix}tax', showTax),
      prefs.setBool('${_prefix}foc', showFoc),
      prefs.setBool('${_prefix}batchExpiry', showBatchExpiry),
      prefs.setBool('${_prefix}stockStatus', showStockStatus),
      prefs.setBool('${_prefix}paymentDetails', showPaymentDetails),
    ]);
  }
}

Future<SaleReturnFieldSettings?> showReturnFieldSettingsDialog(
  BuildContext context,
  SaleReturnFieldSettings current,
) {
  return showDialog<SaleReturnFieldSettings>(
    context: context,
    builder: (context) => _FieldSettingsDialog(initial: current),
  );
}

class _FieldSettingsDialog extends StatefulWidget {
  final SaleReturnFieldSettings initial;
  const _FieldSettingsDialog({required this.initial});

  @override
  State<_FieldSettingsDialog> createState() => _FieldSettingsDialogState();
}

class _FieldSettingsDialogState extends State<_FieldSettingsDialog> {
  late SaleReturnFieldSettings _settings;

  @override
  void initState() {
    super.initState();
    _settings = widget.initial;
  }

  Widget _toggle(String label, bool value, ValueChanged<bool> onChanged) {
    return SwitchListTile(
      dense: true,
      contentPadding: EdgeInsets.zero,
      title: Text(label),
      value: value,
      onChanged: onChanged,
    );
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Field Settings'),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Padding(
              padding: EdgeInsets.only(bottom: 8),
              child: Align(
                alignment: Alignment.centerLeft,
                child: Text(
                  'Turn off what you don\'t need — it stays off until you turn it back on.',
                  style: TextStyle(fontSize: 12, color: Color(0xFF6B7280)),
                ),
              ),
            ),
            _toggle(
              'Company (remembers last pick)',
              _settings.showCompanyField,
              (v) => setState(
                () => _settings = _settings.copyWith(showCompanyField: v),
              ),
            ),
            _toggle(
              'Branch (remembers last pick)',
              _settings.showBranchField,
              (v) => setState(
                () => _settings = _settings.copyWith(showBranchField: v),
              ),
            ),
            _toggle(
              'Currency (remembers last pick)',
              _settings.showCurrencyField,
              (v) => setState(
                () => _settings = _settings.copyWith(showCurrencyField: v),
              ),
            ),
            const Divider(),
            _toggle(
              'Original Sale Invoice (optional link)',
              _settings.showOriginalInvoice,
              (v) => setState(
                () => _settings = _settings.copyWith(showOriginalInvoice: v),
              ),
            ),
            _toggle(
              'Sales Officer',
              _settings.showSalesOfficer,
              (v) => setState(
                () => _settings = _settings.copyWith(showSalesOfficer: v),
              ),
            ),
            _toggle(
              'Supplier Man',
              _settings.showSupplierMan,
              (v) => setState(
                () => _settings = _settings.copyWith(showSupplierMan: v),
              ),
            ),
            _toggle(
              'Sub Account',
              _settings.showSubAccount,
              (v) => setState(
                () => _settings = _settings.copyWith(showSubAccount: v),
              ),
            ),
            _toggle(
              'Remarks',
              _settings.showRemarks,
              (v) => setState(
                () => _settings = _settings.copyWith(showRemarks: v),
              ),
            ),
            _toggle(
              'Customer Info (Balance/Address)',
              _settings.showCustomerInfo,
              (v) => setState(
                () => _settings = _settings.copyWith(showCustomerInfo: v),
              ),
            ),
            _toggle(
              'Discount',
              _settings.showDiscount,
              (v) => setState(
                () => _settings = _settings.copyWith(showDiscount: v),
              ),
            ),
            _toggle(
              'Tax',
              _settings.showTax,
              (v) => setState(() => _settings = _settings.copyWith(showTax: v)),
            ),
            _toggle(
              'Trade Offer',
              _settings.showTradeOffer,
              (v) => setState(
                () => _settings = _settings.copyWith(showTradeOffer: v),
              ),
            ),
            _toggle(
              'FOC Quantity',
              _settings.showFoc,
              (v) => setState(() => _settings = _settings.copyWith(showFoc: v)),
            ),
            _toggle(
              'Batch No / Expiry Date',
              _settings.showBatchExpiry,
              (v) => setState(
                () => _settings = _settings.copyWith(showBatchExpiry: v),
              ),
            ),
            _toggle(
              'Stock Status (Sellable/Damaged)',
              _settings.showStockStatus,
              (v) => setState(
                () => _settings = _settings.copyWith(showStockStatus: v),
              ),
            ),
            _toggle(
              'Shipping Fees',
              _settings.showShipping,
              (v) => setState(
                () => _settings = _settings.copyWith(showShipping: v),
              ),
            ),
            _toggle(
              'Payment Details (Method/Bank/Refund)',
              _settings.showPaymentDetails,
              (v) => setState(
                () => _settings = _settings.copyWith(showPaymentDetails: v),
              ),
            ),
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(context).pop(),
          child: const Text('Cancel'),
        ),
        ElevatedButton(
          onPressed: () => Navigator.of(context).pop(_settings),
          child: const Text('Save'),
        ),
      ],
    );
  }
}
