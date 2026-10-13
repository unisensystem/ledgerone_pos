import 'dart:convert';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:uuid/uuid.dart';

import '../../core/outbox_service.dart';
import '../../core/reference_cache.dart';
import '../../core/sync_service.dart';
import 'receive_voucher_api.dart';
import 'receive_voucher_calc.dart';
import 'receive_voucher_field_settings.dart';
import 'receive_voucher_remembered.dart';

/// Matches receive-add.php's own validation exactly (5MB limit, same
/// accepted extensions) — checking client-side too so a booker finds out
/// immediately rather than after a round trip (or, offline, not at all
/// until sync fails).
const _maxAttachmentBytes = 5 * 1024 * 1024;
const _allowedAttachmentExtensions = [
  'png',
  'jpg',
  'jpeg',
  'gif',
  'pdf',
  'webp',
  'avif',
];

/// Same searchable-Autocomplete pattern as Sale Order/Sale Return.
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

class ReceiveVoucherScreen extends StatefulWidget {
  final int? editVoucherId;

  const ReceiveVoucherScreen({super.key, this.editVoucherId});

  @override
  State<ReceiveVoucherScreen> createState() => _ReceiveVoucherScreenState();
}

class _ReceiveVoucherScreenState extends State<ReceiveVoucherScreen> {
  bool _loadingReferenceData = true;
  String? _loadError;
  ReceiveVoucherFieldSettings _fieldSettings =
      const ReceiveVoucherFieldSettings();

  List<dynamic> _companies = [];
  List<dynamic> _currencies = [];
  List<dynamic> _customers = [];
  List<dynamic> _paymentMethods = [];
  List<dynamic> _bankAccounts = [];
  List<dynamic> _employees = [];
  List<dynamic> _projects = [];
  List<dynamic> _subAccounts = [];
  List<dynamic> _bills = [];
  List<dynamic> _pendingPdcs = [];

  int? _selectedCompanyId;
  int? _selectedCurrencyId;
  Map<String, dynamic>? _selectedCustomer;
  int? _selectedPaymentMethodId;
  int? _selectedBankAccountId;
  int? _selectedRecoveryOfficerId;
  int? _selectedProjectId;
  int? _selectedSubAccountId;
  Map<String, dynamic>? _selectedPdc;
  DateTime _selectedVoucherDate = DateTime.now();
  DateTime? _selectedChequeDate;
  double _previousBalance = 0;
  int _formResetCounter = 0;
  bool _loadingBalance = false;

  bool get _isEditing => widget.editVoucherId != null;

  final _amountController = TextEditingController(text: '0');
  final _adjustAmountController = TextEditingController(text: '0');
  final _billNoController = TextEditingController();
  final _dsrNoController = TextEditingController();
  final _chequeNoController = TextEditingController();
  final _descriptionController = TextEditingController();

  PlatformFile? _selectedAttachment;

  bool _submitting = false;
  String? _submitResult;

  Future<void> _pickAttachment() async {
    final result = await FilePicker.pickFiles(
      type: FileType.custom,
      allowedExtensions: _allowedAttachmentExtensions,
      withData: true, // need raw bytes on web, and for offline base64 encoding
    );
    if (result == null || result.files.isEmpty) return;
    final file = result.files.single;
    if (file.bytes == null) return;
    if (file.size > _maxAttachmentBytes) {
      setState(() => _submitResult = 'Attachment must be 5MB or smaller.');
      return;
    }
    setState(() {
      _selectedAttachment = file;
      _submitResult = null;
    });
  }

  @override
  void initState() {
    super.initState();
    _loadReferenceData();
    ReceiveVoucherFieldSettings.load().then((s) {
      if (mounted) setState(() => _fieldSettings = s);
    });
  }

  Future<void> _openFieldSettings() async {
    final updated = await showReceiveVoucherFieldSettingsDialog(
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
    _amountController.dispose();
    _adjustAmountController.dispose();
    _billNoController.dispose();
    _dsrNoController.dispose();
    _chequeNoController.dispose();
    _descriptionController.dispose();
    super.dispose();
  }

  Future<void> _loadReferenceData() async {
    setState(() {
      _loadingReferenceData = true;
      _loadError = null;
    });
    try {
      final futures = <Future<dynamic>>[
        ReferenceCache.cached('rv_companies', ReceiveVoucherApi.getCompanies),
        ReferenceCache.cached('rv_currencies', ReceiveVoucherApi.getCurrencies),
        ReferenceCache.cached('rv_customers', ReceiveVoucherApi.getCustomers),
        ReferenceCache.cached(
          'rv_payment_methods',
          ReceiveVoucherApi.getPaymentMethods,
        ),
        ReferenceCache.cached(
          'rv_bank_accounts',
          ReceiveVoucherApi.getBankAccounts,
        ),
        ReferenceCache.cached('rv_employees', ReceiveVoucherApi.getEmployees),
        ReferenceCache.cached('rv_projects', ReceiveVoucherApi.getProjects),
        ReceiveVoucherRemembered.getCompanyId(),
        ReceiveVoucherRemembered.getCurrencyId(),
      ];
      final results = await Future.wait(futures);
      _companies = results[0] as List<dynamic>;
      _currencies = results[1] as List<dynamic>;
      _customers = results[2] as List<dynamic>;
      _paymentMethods = results[3] as List<dynamic>;
      _bankAccounts = results[4] as List<dynamic>;
      _employees = results[5] as List<dynamic>;
      _projects = results[6] as List<dynamic>;
      final rememberedCompanyId = results[7] as int?;
      final rememberedCurrencyId = results[8] as int?;

      if (rememberedCompanyId != null &&
          _companies.any((c) => c['id'] == rememberedCompanyId)) {
        _selectedCompanyId = rememberedCompanyId;
      } else if (_companies.isNotEmpty) {
        _selectedCompanyId = _companies.first['id'] as int;
      }
      if (rememberedCurrencyId != null &&
          _currencies.any(
            (c) =>
                int.tryParse(c['currency_id'].toString()) ==
                rememberedCurrencyId,
          )) {
        _selectedCurrencyId = rememberedCurrencyId;
      } else if (_currencies.isNotEmpty) {
        _selectedCurrencyId = int.tryParse(
          _currencies.first['currency_id'].toString(),
        );
      }

      if (_isEditing) {
        await _loadExistingVoucher();
      }

      setState(() => _loadingReferenceData = false);
    } catch (e) {
      setState(() {
        _loadingReferenceData = false;
        _loadError = 'Could not load data: $e';
      });
    }
  }

  Future<void> _loadExistingVoucher() async {
    final voucher = await ReferenceCache.cached(
      'rv_edit_${widget.editVoucherId}',
      () => ReceiveVoucherApi.getVoucherForEdit(widget.editVoucherId!),
    );

    _selectedCompanyId = voucher['company_id'] != null
        ? int.tryParse(voucher['company_id'].toString())
        : _selectedCompanyId;
    _selectedCurrencyId = voucher['currency_id'] != null
        ? int.tryParse(voucher['currency_id'].toString())
        : _selectedCurrencyId;
    // receive-add.php/receive-edit.php both store Cash as account id 1,
    // but the Payment Method dropdown (from get-payment-methods.php) lists
    // Cash under its real accounts.id, 7 — web's own edit screen remaps
    // 1 back to 7 when populating the dropdown (receive-list.js), so mirror
    // that here or a saved Cash voucher shows a blank Payment Method field.
    final rawPaymentMethodId = voucher['payment_method_id'] != null
        ? int.tryParse(voucher['payment_method_id'].toString())
        : null;
    _selectedPaymentMethodId = rawPaymentMethodId == 1 ? 7 : rawPaymentMethodId;
    _selectedBankAccountId = voucher['bank_account_id'] != null
        ? int.tryParse(voucher['bank_account_id'].toString())
        : null;
    _selectedRecoveryOfficerId = voucher['recovery_officer_id'] != null
        ? int.tryParse(voucher['recovery_officer_id'].toString())
        : null;
    _selectedProjectId = voucher['project_id'] != null
        ? int.tryParse(voucher['project_id'].toString())
        : null;
    _selectedSubAccountId = voucher['sub_account_id'] != null
        ? int.tryParse(voucher['sub_account_id'].toString())
        : null;
    _amountController.text = voucher['amount']?.toString() ?? '0';
    _adjustAmountController.text = voucher['adjust_amount']?.toString() ?? '0';
    _billNoController.text = voucher['bill_no']?.toString() ?? '';
    _dsrNoController.text = voucher['dsr_no']?.toString() ?? '';
    _chequeNoController.text = voucher['cheque_no']?.toString() ?? '';
    _descriptionController.text = voucher['description']?.toString() ?? '';
    if (voucher['voucher_date'] != null) {
      _selectedVoucherDate =
          DateTime.tryParse(voucher['voucher_date'].toString()) ??
          DateTime.now();
    }
    if (voucher['cheque_date'] != null) {
      _selectedChequeDate = DateTime.tryParse(
        voucher['cheque_date'].toString(),
      );
    }

    final customerId = voucher['customer_id'] != null
        ? int.tryParse(voucher['customer_id'].toString())
        : null;
    if (customerId != null) {
      _selectedCustomer = _customers.cast<Map<String, dynamic>>().firstWhere(
        (c) => c['id'] == customerId,
        orElse: () => {
          'id': customerId,
          'customer_code': voucher['customer_code'] ?? '',
          'customer_name': voucher['customer_name'] ?? '',
        },
      );
      _previousBalance =
          double.tryParse(voucher['previous_balance']?.toString() ?? '') ?? 0;
      await _loadCustomerDependents(_selectedCustomer!, skipBalance: true);
    }

    final pdcId = voucher['against_pdc_id'] != null
        ? int.tryParse(voucher['against_pdc_id'].toString())
        : null;
    if (pdcId != null) {
      final alreadyListed = _pendingPdcs.any((p) => p['id'] == pdcId);
      if (!alreadyListed) {
        // The PDC this voucher is linked to may no longer be "pending"
        // (often because this very voucher is what settled it), so it
        // won't come back from get-pending-pdcs.php. Add it to the list
        // from the voucher's own data so Edit still shows/keeps the link
        // instead of silently hiding or dropping it.
        _pendingPdcs = [
          ..._pendingPdcs,
          {
            'id': pdcId,
            'cheque_no': voucher['against_pdc_cheque_no']?.toString() ?? '',
            'remaining': voucher['against_pdc_amount'] ?? 0,
          },
        ];
      }
      _selectedPdc = _pendingPdcs.cast<Map<String, dynamic>>().firstWhere(
        (p) => p['id'] == pdcId,
      );
    }
  }

  Future<void> _loadCustomerDependents(
    Map<String, dynamic> customer, {
    bool skipBalance = false,
  }) async {
    final customerId = customer['id'] as int;
    final customerCode = customer['customer_code'].toString();

    if (!skipBalance) {
      setState(() => _loadingBalance = true);
      final balance = await ReferenceCache.cached(
        'rv_balance_$customerCode',
        () => ReceiveVoucherApi.getCustomerBalance(customerCode),
      );
      if (!mounted) return;
      setState(() {
        _previousBalance = balance;
        _loadingBalance = false;
      });
    }

    final results = await Future.wait([
      ReferenceCache.cached(
        'rv_subaccounts_$customerId',
        () => ReceiveVoucherApi.getSubAccounts(customerId),
      ),
      ReferenceCache.cached(
        'rv_bills_$customerCode',
        () => ReceiveVoucherApi.getBills(customerCode),
      ),
      ReferenceCache.cached(
        'rv_pdcs_$customerId',
        () => ReceiveVoucherApi.getPendingPdcs(customerId),
      ),
    ]);
    if (!mounted) return;
    setState(() {
      _subAccounts = results[0];
      _bills = results[1];
      _pendingPdcs = results[2];
    });
  }

  ReceiveVoucherCalc get _calc => ReceiveVoucherCalc(
    previousBalance: _previousBalance,
    amount: double.tryParse(_amountController.text) ?? 0,
    adjustAmount: double.tryParse(_adjustAmountController.text) ?? 0,
  );

  bool get _isCash => _selectedPaymentMethodId == 7;
  bool get _isCheque => _selectedPaymentMethodId == 6;

  Future<void> _submit() async {
    if (_selectedCustomer == null ||
        _selectedCompanyId == null ||
        _selectedCurrencyId == null) {
      setState(
        () => _submitResult = 'Please select company, currency and customer.',
      );
      return;
    }
    final amount = double.tryParse(_amountController.text) ?? 0;
    if (amount <= 0) {
      setState(() => _submitResult = 'Enter an amount greater than 0.');
      return;
    }
    if (_selectedPaymentMethodId == null) {
      setState(() => _submitResult = 'Select a payment method.');
      return;
    }
    if (!_isCash && _selectedBankAccountId == null) {
      setState(
        () => _submitResult = 'Select a bank account for this payment method.',
      );
      return;
    }
    if (_isCheque &&
        (_selectedChequeDate == null ||
            _chequeNoController.text.trim().isEmpty)) {
      setState(
        () => _submitResult = 'Enter the cheque date and cheque number.',
      );
      return;
    }

    setState(() {
      _submitting = true;
      _submitResult = null;
    });

    final customerLabel =
        '${_selectedCustomer!['customer_code']} - ${_selectedCustomer!['customer_name']}';
    final voucherDateStr = DateFormat('yyyy-MM-dd')
        .format(_selectedVoucherDate);
    final chequeDateStr = _selectedChequeDate != null
        ? DateFormat('yyyy-MM-dd').format(_selectedChequeDate!)
        : null;
    final adjustAmount = double.tryParse(_adjustAmountController.text) ?? 0;

    if (_isEditing) {
      final payload = ReceiveVoucherApi.buildEditPayload(
        voucherDate: voucherDateStr,
        companyId: _selectedCompanyId,
        billNo: _billNoController.text.trim().isEmpty
            ? null
            : _billNoController.text.trim(),
        dsrNo: _dsrNoController.text.trim().isEmpty
            ? null
            : _dsrNoController.text.trim(),
        amount: amount,
        adjustAmount: adjustAmount,
        currencyId: _selectedCurrencyId,
        paymentMethodId: _selectedPaymentMethodId!,
        bankAccountId: _isCash ? null : _selectedBankAccountId,
        chequeDate: _isCheque ? chequeDateStr : null,
        chequeNo: _isCheque ? _chequeNoController.text.trim() : null,
        description: _descriptionController.text.trim().isEmpty
            ? null
            : _descriptionController.text.trim(),
        projectId: _selectedProjectId,
        subAccountId: _selectedSubAccountId,
        recoveryOfficerId: _selectedRecoveryOfficerId,
        againstPdcId: _selectedPdc != null ? _selectedPdc!['id'] as int : null,
      );

      if (!await SyncService.instance.isOnline()) {
        await _saveEditToOutbox(payload, customerLabel);
        return;
      }
      try {
        final result = await ReceiveVoucherApi.updateVoucher(
          widget.editVoucherId!,
          payload,
        );
        if (result['success'] == true) {
          setState(() => _submitting = false);
          await _showSuccessDialog(
            'Voucher Updated!',
            'Changes saved successfully.',
            isEdit: true,
          );
        } else {
          setState(() {
            _submitting = false;
            _submitResult =
                result['message']?.toString() ??
                result['error']?.toString() ??
                'Failed to update voucher';
          });
        }
      } catch (e) {
        await _saveEditToOutbox(payload, customerLabel);
      }
      return;
    }

    final clientUuid = const Uuid().v4();
    final payload = ReceiveVoucherApi.buildCreatePayload(
      clientUuid: clientUuid,
      voucherDate: voucherDateStr,
      customerCode: _selectedCustomer!['customer_code'].toString(),
      previousBalance: _previousBalance,
      subAccountId: _selectedSubAccountId,
      billNo: _billNoController.text.trim().isEmpty
          ? null
          : _billNoController.text.trim(),
      dsrNo: _dsrNoController.text.trim().isEmpty
          ? null
          : _dsrNoController.text.trim(),
      projectId: _selectedProjectId,
      adjustAmount: adjustAmount,
      amount: amount,
      currencyId: _selectedCurrencyId!,
      paymentMethodId: _selectedPaymentMethodId!,
      bankAccountId: _isCash ? null : _selectedBankAccountId,
      chequeDate: _isCheque ? chequeDateStr : null,
      chequeNo: _isCheque ? _chequeNoController.text.trim() : null,
      description: _descriptionController.text.trim().isEmpty
          ? null
          : _descriptionController.text.trim(),
      recoveryOfficerId: _selectedRecoveryOfficerId,
      againstPdcId: _selectedPdc != null ? _selectedPdc!['id'] as int : null,
    );

    if (!await SyncService.instance.isOnline()) {
      await _saveToOutbox(clientUuid, payload, customerLabel);
      return;
    }

    try {
      final result = await ReceiveVoucherApi.createVoucher(
        payload,
        attachmentBytes: _selectedAttachment?.bytes,
        attachmentFileName: _selectedAttachment?.name,
      );
      if (result['success'] == true) {
        final voucherNumber = result['voucher_number']?.toString() ?? '';
        setState(() => _submitting = false);
        if (result['already_synced'] == true) {
          await _showSuccessDialog(
            'Already Synced',
            'This voucher was already synced earlier${voucherNumber.isNotEmpty ? ' as $voucherNumber' : ''}.',
          );
        } else {
          await _showSuccessDialog(
            'Voucher Created!',
            'Payment recorded successfully.',
          );
        }
      } else {
        setState(() {
          _submitting = false;
          _submitResult =
              result['message']?.toString() ??
              result['error']?.toString() ??
              'Failed to save voucher';
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
    final outboxPayload = Map<String, dynamic>.from(payload);
    // File bytes can't travel through SharedPreferences as-is — base64 into
    // the JSON-safe payload, reconstructed by SyncService at replay time.
    if (_selectedAttachment?.bytes != null) {
      outboxPayload[kOutboxAttachmentBase64Key] = base64Encode(
        _selectedAttachment!.bytes!,
      );
      outboxPayload[kOutboxAttachmentFileNameKey] = _selectedAttachment!.name;
    }
    await OutboxService.instance.add(
      OutboxEntry(
        clientUuid: clientUuid,
        endpoint: ReceiveVoucherApi.createVoucherEndpoint,
        isFormData: true,
        label: '$customerLabel — ${_amountController.text}',
        payload: outboxPayload,
        createdAt: DateTime.now(),
      ),
    );
    if (!mounted) return;
    setState(() => _submitting = false);
    SyncService.instance.syncNow();
    await _showSuccessDialog(
      'Saved Offline',
      'No connection — this voucher is saved on your device and will sync automatically once you\'re back online.',
    );
  }

  Future<void> _saveEditToOutbox(
    Map<String, dynamic> payload,
    String customerLabel,
  ) async {
    await OutboxService.instance.add(
      OutboxEntry(
        clientUuid: const Uuid().v4(),
        // receive-edit.php reads the voucher id from the URL query string, not
        // the JSON body — bake it into the endpoint so the generic SyncService
        // replay (which only knows endpoint + payload) still hits the right row.
        endpoint:
            '${ReceiveVoucherApi.editVoucherEndpoint}?id=${widget.editVoucherId}',
        method: 'PUT',
        label: 'Edit: $customerLabel',
        payload: payload,
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
                _resetForNewVoucher();
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
            child: Text(isEdit ? 'Done' : 'View Vouchers'),
          ),
        ],
      ),
    );
  }

  void _resetForNewVoucher() {
    setState(() {
      _selectedCustomer = null;
      _selectedPaymentMethodId = null;
      _selectedBankAccountId = null;
      _selectedRecoveryOfficerId = null;
      _selectedProjectId = null;
      _selectedSubAccountId = null;
      _selectedPdc = null;
      _selectedChequeDate = null;
      _selectedVoucherDate = DateTime.now();
      _previousBalance = 0;
      _subAccounts = [];
      _bills = [];
      _pendingPdcs = [];
      _amountController.text = '0';
      _adjustAmountController.text = '0';
      _billNoController.clear();
      _dsrNoController.clear();
      _chequeNoController.clear();
      _descriptionController.clear();
      _selectedAttachment = null;
      _submitResult = null;
      _formResetCounter++;
    });
  }

  @override
  Widget build(BuildContext context) {
    final calc = _calc;
    return Scaffold(
      backgroundColor: const Color(0xFFF7F9FC),
      appBar: AppBar(
        title: Text(
          _isEditing ? 'Edit Receive Voucher' : 'New Receive Voucher',
        ),
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
                _sectionTitle('Voucher Details'),
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
                      if (v != null) ReceiveVoucherRemembered.setCompanyId(v);
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
                      if (v != null) ReceiveVoucherRemembered.setCurrencyId(v);
                    },
                  ),
                  const SizedBox(height: 12),
                ],
                if (_fieldSettings.showRecoveryOfficer) ...[
                  _dropdown<int>(
                    key: ValueKey('recovery-officer-field-$_formResetCounter'),
                    label: 'Recovery Officer (optional)',
                    value: _selectedRecoveryOfficerId,
                    items: _employees
                        .map(
                          (e) => DropdownMenuItem(
                            value: e['id'] as int,
                            child: Text(e['full_name'].toString()),
                          ),
                        )
                        .toList(),
                    onChanged: (v) =>
                        setState(() => _selectedRecoveryOfficerId = v),
                    allowClear: true,
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
                      _selectedPdc = null;
                      _billNoController.clear();
                    });
                    _loadCustomerDependents(c);
                  },
                ),
                if (_selectedCustomer != null) ...[
                  const SizedBox(height: 12),
                  _readOnlyInfo(
                    'Previous Balance',
                    _loadingBalance
                        ? 'Loading…'
                        : _previousBalance.toStringAsFixed(2),
                  ),
                  const SizedBox(height: 8),
                  _readOnlyInfo(
                    'Remaining Balance',
                    calc.remainingBalance.toStringAsFixed(2),
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
                if (_fieldSettings.showBillNo) ...[
                  const SizedBox(height: 12),
                  _searchableField<Map<String, dynamic>>(
                    key: ValueKey('bill-field-$_formResetCounter'),
                    label: 'Bill No (optional, type to search)',
                    options: _bills.cast<Map<String, dynamic>>(),
                    displayStringForOption: (b) =>
                        '${b['bill_no']} — ${b['net_amount']}',
                    matches: (b, q) =>
                        b['bill_no'].toString().toLowerCase().contains(q),
                    initialText: _billNoController.text.isEmpty
                        ? null
                        : _billNoController.text,
                    onSelected: (b) => setState(
                      () => _billNoController.text = b['bill_no'].toString(),
                    ),
                  ),
                ],
                if (_fieldSettings.showDsrNo) ...[
                  const SizedBox(height: 12),
                  _textField('DSR# (optional)', _dsrNoController),
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

                const SizedBox(height: 24),
                _sectionTitle('Payment'),
                if (_fieldSettings.showAdjustAmount) ...[
                  _smallNumberField('Adjust Amount', _adjustAmountController),
                  const SizedBox(height: 12),
                ],
                _smallNumberField('Amount', _amountController),
                const SizedBox(height: 12),
                _dropdown<int>(
                  key: ValueKey('payment-method-field-$_formResetCounter'),
                  label: 'Payment Method',
                  value: _selectedPaymentMethodId,
                  items: _paymentMethods
                      .map(
                        (m) => DropdownMenuItem(
                          value: m['id'] as int,
                          child: Text(m['name'].toString()),
                        ),
                      )
                      .toList(),
                  onChanged: (v) => setState(() {
                    _selectedPaymentMethodId = v;
                    if (v == 7) _selectedBankAccountId = null;
                    if (v != 6) {
                      _selectedChequeDate = null;
                      _chequeNoController.clear();
                    }
                  }),
                ),
                if (_selectedPaymentMethodId != null && !_isCash) ...[
                  const SizedBox(height: 12),
                  _dropdown<int>(
                    label: 'Bank Account',
                    value: _selectedBankAccountId,
                    items: _bankAccounts
                        .map(
                          (b) => DropdownMenuItem(
                            value: b['id'] as int,
                            child: Text(
                              '${b['bank_name']} - ${b['account_number']}',
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                        )
                        .toList(),
                    onChanged: (v) =>
                        setState(() => _selectedBankAccountId = v),
                  ),
                ],
                if (_isCheque) ...[
                  const SizedBox(height: 12),
                  _chequeDateField(),
                  const SizedBox(height: 12),
                  _textField('Cheque No', _chequeNoController),
                ],
                if (_fieldSettings.showAgainstPdc &&
                    _pendingPdcs.isNotEmpty) ...[
                  const SizedBox(height: 12),
                  _dropdown<int>(
                    key: ValueKey('pdc-field-$_formResetCounter'),
                    label: 'Against PDC (optional)',
                    value: _selectedPdc != null
                        ? _selectedPdc!['id'] as int
                        : null,
                    items: _pendingPdcs
                        .map(
                          (p) => DropdownMenuItem(
                            value: p['id'] as int,
                            child: Text(
                              '${p['cheque_no']} — remaining ${p['remaining']}',
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                        )
                        .toList(),
                    onChanged: (v) => setState(
                      () => _selectedPdc = v == null
                          ? null
                          : _pendingPdcs
                                .cast<Map<String, dynamic>>()
                                .firstWhere((p) => p['id'] == v),
                    ),
                    allowClear: true,
                  ),
                ],
                const SizedBox(height: 12),
                _attachmentField(),
                if (_fieldSettings.showDescription) ...[
                  const SizedBox(height: 12),
                  _textField(
                    'Description',
                    _descriptionController,
                    maxLines: 3,
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
                SizedBox(
                  width: double.infinity,
                  height: 48,
                  child: ElevatedButton(
                    onPressed: _submitting ? null : _submit,
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
                        : Text(_isEditing ? 'Save Changes' : 'Post Voucher'),
                  ),
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

  // Field-use control: a voucher is always dated today, no picker — same
  // "today only" rule as Sale Order's Sale Date (see sale_order_screen.dart's
  // _dateField for the full reasoning). Doesn't apply to the cheque date
  // below — a post-dated cheque is, by definition, dated in the future.
  Widget _dateField() {
    return InputDecorator(
      decoration: const InputDecoration(
        labelText: 'Voucher Date',
        border: OutlineInputBorder(),
        filled: true,
        fillColor: Color(0xFFF0F0F0),
        suffixIcon: Icon(Icons.lock_outline, size: 16),
      ),
      child: Text(DateFormat('dd MMM yyyy').format(_selectedVoucherDate)),
    );
  }

  Widget _chequeDateField() {
    return InkWell(
      onTap: () async {
        final picked = await showDatePicker(
          context: context,
          initialDate: _selectedChequeDate ?? DateTime.now(),
          firstDate: DateTime(2020),
          lastDate: DateTime.now().add(const Duration(days: 730)),
        );
        if (picked != null) setState(() => _selectedChequeDate = picked);
      },
      child: InputDecorator(
        decoration: InputDecoration(
          labelText: 'Cheque Date',
          border: const OutlineInputBorder(),
          filled: true,
          fillColor: Colors.white,
          suffixIcon: const Icon(Icons.calendar_today_outlined, size: 18),
        ),
        child: Text(
          _selectedChequeDate != null
              ? DateFormat('dd MMM yyyy').format(_selectedChequeDate!)
              : 'Not set',
        ),
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

  // Not an edit-mode field — receive-edit.php's JSON body has no $_FILES
  // handling at all, matching web's own Edit form (attachments are create-only).
  Widget _attachmentField() {
    if (_isEditing) return const SizedBox.shrink();
    if (_selectedAttachment == null) {
      return OutlinedButton.icon(
        onPressed: _pickAttachment,
        icon: const Icon(Icons.attach_file, size: 18),
        label: const Text('Attach File (optional)'),
      );
    }
    final sizeKb = (_selectedAttachment!.size / 1024).toStringAsFixed(0);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
      decoration: BoxDecoration(
        color: const Color(0xFFEFF2F7),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: const Color(0xFFE1E6EE)),
      ),
      child: Row(
        children: [
          const Icon(
            Icons.insert_drive_file_outlined,
            size: 20,
            color: Color(0xFF6B7280),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _selectedAttachment!.name,
                  style: const TextStyle(fontSize: 13),
                  overflow: TextOverflow.ellipsis,
                ),
                Text(
                  '$sizeKb KB',
                  style: const TextStyle(
                    fontSize: 11,
                    color: Color(0xFF6B7280),
                  ),
                ),
              ],
            ),
          ),
          IconButton(
            icon: const Icon(Icons.close, size: 18),
            onPressed: () => setState(() => _selectedAttachment = null),
          ),
        ],
      ),
    );
  }
}
