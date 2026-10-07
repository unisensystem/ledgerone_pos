import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Per-device show/hide preferences for optional Receive Voucher fields —
/// same concept as SaleOrderFieldSettings/SaleReturnFieldSettings.
class ReceiveVoucherFieldSettings {
  final bool showCompanyField;
  final bool showCurrencyField;
  final bool showRecoveryOfficer;
  final bool showSubAccount;
  final bool showBillNo;
  final bool showDsrNo;
  final bool showProject;
  final bool showAdjustAmount;
  final bool showAgainstPdc;
  final bool showDescription;

  const ReceiveVoucherFieldSettings({
    this.showCompanyField = true,
    this.showCurrencyField = true,
    this.showRecoveryOfficer = true,
    this.showSubAccount = true,
    this.showBillNo = true,
    this.showDsrNo = true,
    this.showProject = true,
    this.showAdjustAmount = true,
    this.showAgainstPdc = true,
    this.showDescription = true,
  });

  static const _prefix = 'receive_voucher_field_';

  ReceiveVoucherFieldSettings copyWith({
    bool? showCompanyField,
    bool? showCurrencyField,
    bool? showRecoveryOfficer,
    bool? showSubAccount,
    bool? showBillNo,
    bool? showDsrNo,
    bool? showProject,
    bool? showAdjustAmount,
    bool? showAgainstPdc,
    bool? showDescription,
  }) {
    return ReceiveVoucherFieldSettings(
      showCompanyField: showCompanyField ?? this.showCompanyField,
      showCurrencyField: showCurrencyField ?? this.showCurrencyField,
      showRecoveryOfficer: showRecoveryOfficer ?? this.showRecoveryOfficer,
      showSubAccount: showSubAccount ?? this.showSubAccount,
      showBillNo: showBillNo ?? this.showBillNo,
      showDsrNo: showDsrNo ?? this.showDsrNo,
      showProject: showProject ?? this.showProject,
      showAdjustAmount: showAdjustAmount ?? this.showAdjustAmount,
      showAgainstPdc: showAgainstPdc ?? this.showAgainstPdc,
      showDescription: showDescription ?? this.showDescription,
    );
  }

  static Future<ReceiveVoucherFieldSettings> load() async {
    final prefs = await SharedPreferences.getInstance();
    bool read(String key) => prefs.getBool('$_prefix$key') ?? true;
    return ReceiveVoucherFieldSettings(
      showCompanyField: read('companyField'),
      showCurrencyField: read('currencyField'),
      showRecoveryOfficer: read('recoveryOfficer'),
      showSubAccount: read('subAccount'),
      showBillNo: read('billNo'),
      showDsrNo: read('dsrNo'),
      showProject: read('project'),
      showAdjustAmount: read('adjustAmount'),
      showAgainstPdc: read('againstPdc'),
      showDescription: read('description'),
    );
  }

  Future<void> save() async {
    final prefs = await SharedPreferences.getInstance();
    await Future.wait([
      prefs.setBool('${_prefix}companyField', showCompanyField),
      prefs.setBool('${_prefix}currencyField', showCurrencyField),
      prefs.setBool('${_prefix}recoveryOfficer', showRecoveryOfficer),
      prefs.setBool('${_prefix}subAccount', showSubAccount),
      prefs.setBool('${_prefix}billNo', showBillNo),
      prefs.setBool('${_prefix}dsrNo', showDsrNo),
      prefs.setBool('${_prefix}project', showProject),
      prefs.setBool('${_prefix}adjustAmount', showAdjustAmount),
      prefs.setBool('${_prefix}againstPdc', showAgainstPdc),
      prefs.setBool('${_prefix}description', showDescription),
    ]);
  }
}

Future<ReceiveVoucherFieldSettings?> showReceiveVoucherFieldSettingsDialog(
  BuildContext context,
  ReceiveVoucherFieldSettings current,
) {
  return showDialog<ReceiveVoucherFieldSettings>(
    context: context,
    builder: (context) => _FieldSettingsDialog(initial: current),
  );
}

class _FieldSettingsDialog extends StatefulWidget {
  final ReceiveVoucherFieldSettings initial;
  const _FieldSettingsDialog({required this.initial});

  @override
  State<_FieldSettingsDialog> createState() => _FieldSettingsDialogState();
}

class _FieldSettingsDialogState extends State<_FieldSettingsDialog> {
  late ReceiveVoucherFieldSettings _settings;

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
              'Currency (remembers last pick)',
              _settings.showCurrencyField,
              (v) => setState(
                () => _settings = _settings.copyWith(showCurrencyField: v),
              ),
            ),
            const Divider(),
            _toggle(
              'Recovery Officer',
              _settings.showRecoveryOfficer,
              (v) => setState(
                () => _settings = _settings.copyWith(showRecoveryOfficer: v),
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
              'Bill No',
              _settings.showBillNo,
              (v) =>
                  setState(() => _settings = _settings.copyWith(showBillNo: v)),
            ),
            _toggle(
              'DSR#',
              _settings.showDsrNo,
              (v) =>
                  setState(() => _settings = _settings.copyWith(showDsrNo: v)),
            ),
            _toggle(
              'Project',
              _settings.showProject,
              (v) => setState(
                () => _settings = _settings.copyWith(showProject: v),
              ),
            ),
            _toggle(
              'Adjust Amount',
              _settings.showAdjustAmount,
              (v) => setState(
                () => _settings = _settings.copyWith(showAdjustAmount: v),
              ),
            ),
            _toggle(
              'Against PDC',
              _settings.showAgainstPdc,
              (v) => setState(
                () => _settings = _settings.copyWith(showAgainstPdc: v),
              ),
            ),
            _toggle(
              'Description',
              _settings.showDescription,
              (v) => setState(
                () => _settings = _settings.copyWith(showDescription: v),
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
