import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Per-device show/hide preferences for optional Sale Order fields — mirrors
/// web's "Fields Settings" concept (not synced, not tenant-wide: just lets
/// each booker hide what they personally never use so the form stays short).
/// Turned-off fields are excluded from the order payload (treated as
/// empty/0), matching how web's Fields Settings actually behaves.
///
/// Company/Branch/Currency are a special case: the DATA is never optional
/// (every order needs one of each), but the FIELD can still be hidden once
/// a booker is happy with the remembered value (see SaleOrderRemembered) —
/// most bookers always work from the same branch/company/currency, so
/// re-picking it every single order is pure friction.
class SaleOrderFieldSettings {
  final bool showCompanyField;
  final bool showBranchField;
  final bool showCurrencyField;
  final bool showSalesOfficer;
  final bool showSupplierMan;
  final bool showProject;
  final bool showBiltyNo;
  final bool showTransportName;
  final bool showRemarks;
  final bool showCustomerInfo;
  final bool showDiscount;
  final bool showGst;
  final bool showTradeOffer;
  final bool showFoc;
  final bool showScheme;
  final bool showShipping;

  const SaleOrderFieldSettings({
    this.showCompanyField = true,
    this.showBranchField = true,
    this.showCurrencyField = true,
    this.showSalesOfficer = true,
    this.showSupplierMan = true,
    this.showProject = true,
    this.showBiltyNo = true,
    this.showTransportName = true,
    this.showRemarks = true,
    this.showCustomerInfo = true,
    this.showDiscount = true,
    this.showGst = true,
    this.showTradeOffer = true,
    this.showFoc = true,
    this.showScheme = true,
    this.showShipping = true,
  });

  static const _prefix = 'sale_order_field_';

  SaleOrderFieldSettings copyWith({
    bool? showCompanyField,
    bool? showBranchField,
    bool? showCurrencyField,
    bool? showSalesOfficer,
    bool? showSupplierMan,
    bool? showProject,
    bool? showBiltyNo,
    bool? showTransportName,
    bool? showRemarks,
    bool? showCustomerInfo,
    bool? showDiscount,
    bool? showGst,
    bool? showTradeOffer,
    bool? showFoc,
    bool? showScheme,
    bool? showShipping,
  }) {
    return SaleOrderFieldSettings(
      showCompanyField: showCompanyField ?? this.showCompanyField,
      showBranchField: showBranchField ?? this.showBranchField,
      showCurrencyField: showCurrencyField ?? this.showCurrencyField,
      showSalesOfficer: showSalesOfficer ?? this.showSalesOfficer,
      showSupplierMan: showSupplierMan ?? this.showSupplierMan,
      showProject: showProject ?? this.showProject,
      showBiltyNo: showBiltyNo ?? this.showBiltyNo,
      showTransportName: showTransportName ?? this.showTransportName,
      showRemarks: showRemarks ?? this.showRemarks,
      showCustomerInfo: showCustomerInfo ?? this.showCustomerInfo,
      showDiscount: showDiscount ?? this.showDiscount,
      showGst: showGst ?? this.showGst,
      showTradeOffer: showTradeOffer ?? this.showTradeOffer,
      showFoc: showFoc ?? this.showFoc,
      showScheme: showScheme ?? this.showScheme,
      showShipping: showShipping ?? this.showShipping,
    );
  }

  static Future<SaleOrderFieldSettings> load() async {
    final prefs = await SharedPreferences.getInstance();
    bool read(String key) => prefs.getBool('$_prefix$key') ?? true;
    return SaleOrderFieldSettings(
      showCompanyField: read('companyField'),
      showBranchField: read('branchField'),
      showCurrencyField: read('currencyField'),
      showSalesOfficer: read('salesOfficer'),
      showSupplierMan: read('supplierMan'),
      showProject: read('project'),
      showBiltyNo: read('biltyNo'),
      showTransportName: read('transportName'),
      showRemarks: read('remarks'),
      showCustomerInfo: read('customerInfo'),
      showDiscount: read('discount'),
      showGst: read('gst'),
      showTradeOffer: read('tradeOffer'),
      showFoc: read('foc'),
      showScheme: read('scheme'),
      showShipping: read('shipping'),
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
      prefs.setBool('${_prefix}project', showProject),
      prefs.setBool('${_prefix}biltyNo', showBiltyNo),
      prefs.setBool('${_prefix}transportName', showTransportName),
      prefs.setBool('${_prefix}remarks', showRemarks),
      prefs.setBool('${_prefix}customerInfo', showCustomerInfo),
      prefs.setBool('${_prefix}discount', showDiscount),
      prefs.setBool('${_prefix}gst', showGst),
      prefs.setBool('${_prefix}tradeOffer', showTradeOffer),
      prefs.setBool('${_prefix}foc', showFoc),
      prefs.setBool('${_prefix}scheme', showScheme),
      prefs.setBool('${_prefix}shipping', showShipping),
    ]);
  }
}

/// Opens the Field Settings dialog and returns the updated settings, or null
/// if the user cancelled without saving.
Future<SaleOrderFieldSettings?> showFieldSettingsDialog(
  BuildContext context,
  SaleOrderFieldSettings current,
) {
  return showDialog<SaleOrderFieldSettings>(
    context: context,
    builder: (context) => _FieldSettingsDialog(initial: current),
  );
}

class _FieldSettingsDialog extends StatefulWidget {
  final SaleOrderFieldSettings initial;
  const _FieldSettingsDialog({required this.initial});

  @override
  State<_FieldSettingsDialog> createState() => _FieldSettingsDialogState();
}

class _FieldSettingsDialogState extends State<_FieldSettingsDialog> {
  late SaleOrderFieldSettings _settings;

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
              (v) => setState(() => _settings = _settings.copyWith(showCompanyField: v)),
            ),
            _toggle(
              'Branch (remembers last pick)',
              _settings.showBranchField,
              (v) => setState(() => _settings = _settings.copyWith(showBranchField: v)),
            ),
            _toggle(
              'Currency (remembers last pick)',
              _settings.showCurrencyField,
              (v) => setState(() => _settings = _settings.copyWith(showCurrencyField: v)),
            ),
            const Divider(),
            _toggle('Sales Officer', _settings.showSalesOfficer, (v) => setState(() => _settings = _settings.copyWith(showSalesOfficer: v))),
            _toggle('Supplier Man', _settings.showSupplierMan, (v) => setState(() => _settings = _settings.copyWith(showSupplierMan: v))),
            _toggle('Project', _settings.showProject, (v) => setState(() => _settings = _settings.copyWith(showProject: v))),
            _toggle('Bilty No', _settings.showBiltyNo, (v) => setState(() => _settings = _settings.copyWith(showBiltyNo: v))),
            _toggle('Transport Name', _settings.showTransportName, (v) => setState(() => _settings = _settings.copyWith(showTransportName: v))),
            _toggle('Remarks', _settings.showRemarks, (v) => setState(() => _settings = _settings.copyWith(showRemarks: v))),
            _toggle('Customer Info (Balance/Address)', _settings.showCustomerInfo,
                (v) => setState(() => _settings = _settings.copyWith(showCustomerInfo: v))),
            _toggle('Discount', _settings.showDiscount, (v) => setState(() => _settings = _settings.copyWith(showDiscount: v))),
            _toggle('GST / Tax', _settings.showGst, (v) => setState(() => _settings = _settings.copyWith(showGst: v))),
            _toggle('Scheme', _settings.showScheme, (v) => setState(() => _settings = _settings.copyWith(showScheme: v))),
            _toggle('Trade Offer', _settings.showTradeOffer, (v) => setState(() => _settings = _settings.copyWith(showTradeOffer: v))),
            _toggle('FOC Quantity', _settings.showFoc, (v) => setState(() => _settings = _settings.copyWith(showFoc: v))),
            _toggle('Shipping Fees', _settings.showShipping, (v) => setState(() => _settings = _settings.copyWith(showShipping: v))),
          ],
        ),
      ),
      actions: [
        TextButton(onPressed: () => Navigator.of(context).pop(), child: const Text('Cancel')),
        ElevatedButton(onPressed: () => Navigator.of(context).pop(_settings), child: const Text('Save')),
      ],
    );
  }
}
