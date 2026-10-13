import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:uuid/uuid.dart';

import '../../core/outbox_service.dart';
import '../../core/reference_cache.dart';
import '../../core/sync_service.dart';
import 'sale_order_api.dart';

/// "New customer on the spot" form. Calls the SAME
/// server/api/customer_supplier/customers/customer-add.php endpoint the
/// full web customer-add page uses — only a reduced field set is collected
/// here, since neither a field booker nor this quick-add flow needs the
/// ~90-field back-office form (tax registration, sub-accounts, opening
/// invoices, etc.) just to register someone they just met.
///
/// Two ways this screen is opened:
/// - Embedded, from the Sale Order form ([initialCompanyId] supplied): the
///   Company field is locked to the order's own company and the new
///   customer is handed straight back via Navigator.pop (shaped like
///   get-customers.php's rows) so the booker can keep taking that
///   customer's order immediately.
/// - Standalone, from the Home screen ([initialCompanyId] null): a proper
///   independent form with its own Company + Sales Officer pickers; on
///   success it shows a confirmation and offers "Add Another" or "Done"
///   instead of returning anything.
///
/// Works offline like Sale Order/Receive Voucher: a failed online attempt
/// queues into the shared outbox and the booker gets back a "pending"
/// customer (see SaleOrderApi.buildPendingCustomer) they can immediately
/// pick for an order — customer_code itself still only gets assigned once
/// the entry actually syncs (it's a server-side locked sequential counter,
/// same reason voucher/invoice numbers are never generated offline), but
/// the booker never has to wait for that to keep working.
class AddCustomerScreen extends StatefulWidget {
  final int? initialCompanyId;
  final int? defaultSalesOfficerId;
  final int? defaultSupplierManId;

  const AddCustomerScreen({
    super.key,
    this.initialCompanyId,
    this.defaultSalesOfficerId,
    this.defaultSupplierManId,
  });

  bool get embedded => initialCompanyId != null;

  @override
  State<AddCustomerScreen> createState() => _AddCustomerScreenState();
}

class _AddCustomerScreenState extends State<AddCustomerScreen> {
  final _formKey = GlobalKey<FormState>();
  final _nameController = TextEditingController();
  final _phoneController = TextEditingController();
  final _addressController = TextEditingController();

  List<dynamic> _companies = [];
  List<dynamic> _employees = [];
  int? _selectedCompanyId;
  int? _selectedSalesOfficerId;

  bool _loadingReference = false;
  bool _saving = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _selectedCompanyId = widget.initialCompanyId;
    _selectedSalesOfficerId = widget.defaultSalesOfficerId;
    if (!widget.embedded) {
      _loadReferenceData();
    }
  }

  Future<void> _loadReferenceData() async {
    setState(() => _loadingReference = true);
    try {
      final results = await Future.wait([
        ReferenceCache.cached('so_companies', SaleOrderApi.getCompanies),
        ReferenceCache.cached('so_employees', SaleOrderApi.getEmployees),
      ]);
      if (!mounted) return;
      setState(() {
        _companies = results[0];
        _employees = results[1];
        if (_selectedCompanyId == null && _companies.isNotEmpty) {
          _selectedCompanyId = _companies.first['id'] as int;
        }
      });
    } catch (e) {
      if (mounted) setState(() => _error = 'Could not load companies: $e');
    } finally {
      if (mounted) setState(() => _loadingReference = false);
    }
  }

  @override
  void dispose() {
    _nameController.dispose();
    _phoneController.dispose();
    _addressController.dispose();
    super.dispose();
  }

  void _resetFormForAnother() {
    _nameController.clear();
    _phoneController.clear();
    _addressController.clear();
    _formKey.currentState?.reset();
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;
    if (_selectedCompanyId == null) {
      setState(() => _error = 'Please select a company.');
      return;
    }
    setState(() {
      _saving = true;
      _error = null;
    });

    final clientUuid = const Uuid().v4();
    final name = _nameController.text.trim();
    final phone = _phoneController.text.trim();
    final address = _addressController.text.trim();

    // Offline-first: don't even attempt the network round trip if we
    // already know there's no connectivity — go straight to the outbox,
    // same as Sale Order/Receive Voucher.
    if (!await SyncService.instance.isOnline()) {
      await _queueOffline(clientUuid, name, phone, address);
      return;
    }

    try {
      final result = await SaleOrderApi.createCustomer(
        companyId: _selectedCompanyId!,
        customerName: name,
        primaryPhone: phone,
        address: address,
        salesOfficerId: _selectedSalesOfficerId,
        supplierManId: widget.defaultSupplierManId,
        clientUuid: clientUuid,
      );
      if (!mounted) return;
      setState(() => _saving = false);
      await _handleCreated(
        customerId: result['customer_id'],
        customerCode: result['customer_code']?.toString(),
        name: name,
        address: address,
      );
    } catch (e) {
      if (e is DioException) {
        // Connectivity check passed but the request still failed mid-flight
        // (dropped connection, server unreachable) — fall back to the
        // outbox instead of losing the booker's work, same as Sale Order.
        await _queueOffline(clientUuid, name, phone, address);
        return;
      }
      // Genuine server-side rejection (validation etc.) — show as an
      // error; nothing to queue, retrying would just fail the same way.
      setState(() {
        _saving = false;
        _error = 'Could not create customer.\n($e)';
      });
    }
  }

  Future<void> _queueOffline(
    String clientUuid,
    String name,
    String phone,
    String address,
  ) async {
    await OutboxService.instance.add(
      OutboxEntry(
        clientUuid: clientUuid,
        endpoint: SaleOrderApi.customerAddEndpoint,
        label: 'New customer: $name',
        payload: {
          'clientUuid': clientUuid,
          'customerName': name,
          'companyId': _selectedCompanyId,
          if (phone.isNotEmpty) 'primaryPhone': phone,
          if (address.isNotEmpty) 'address': address,
          if (_selectedSalesOfficerId != null)
            'salesOfficerId': _selectedSalesOfficerId,
          if (widget.defaultSupplierManId != null)
            'supplierManId': widget.defaultSupplierManId,
        },
        createdAt: DateTime.now(),
      ),
    );
    SyncService.instance.syncNow();
    if (!mounted) return;
    setState(() => _saving = false);
    await _handleCreated(
      customerId: null,
      customerCode: null,
      name: name,
      address: address,
      offline: true,
      clientUuid: clientUuid,
    );
  }

  Future<void> _handleCreated({
    required dynamic customerId,
    required String? customerCode,
    required String name,
    required String address,
    bool offline = false,
    String? clientUuid,
  }) async {
    if (!mounted) return;
    if (widget.embedded) {
      final customerMap = offline
          ? SaleOrderApi.buildPendingCustomer(
              clientUuid: clientUuid!,
              customerName: name,
              address: address,
              salesOfficerId: _selectedSalesOfficerId,
              supplierManId: widget.defaultSupplierManId,
            )
          : {
              'id': customerId,
              'customer_code': customerCode,
              'customer_name': name,
              'current_balance': 0,
              'address': address,
              'default_discount_percentage': 0,
              'credit_limit': 0,
              'associated_sales_officer_id': _selectedSalesOfficerId,
              'supplier_man_id': widget.defaultSupplierManId,
            };
      Navigator.pop(context, customerMap);
      return;
    }

    await showDialog<void>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(offline ? 'Saved Offline' : 'Customer Created'),
        content: Text(
          offline
              ? '"$name" saved offline — it will sync automatically once '
                  'you\'re back online. You can select it for an order right away.'
              : '"$name" was created successfully.\nCustomer Code: $customerCode',
        ),
        actions: [
          TextButton(
            onPressed: () {
              Navigator.pop(dialogContext);
              Navigator.pop(context);
            },
            child: const Text('Done'),
          ),
          ElevatedButton(
            onPressed: () {
              Navigator.pop(dialogContext);
              _resetFormForAnother();
            },
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFF1F7BFF),
              foregroundColor: Colors.white,
            ),
            child: const Text('Add Another'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF7F9FC),
      appBar: AppBar(
        title: const Text('Add New Customer'),
        backgroundColor: const Color(0xFF1F7BFF),
        foregroundColor: Colors.white,
      ),
      body: _loadingReference
          ? const Center(child: CircularProgressIndicator())
          : Form(
              key: _formKey,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  const Text(
                    'Create a new customer here, or do it on the spot from '
                    'the Sale Order screen while taking their order. More '
                    'details (tax info, credit terms, etc.) can be added '
                    'later from the office.',
                    style: TextStyle(fontSize: 13, color: Color(0xFF6B7280)),
                  ),
                  const SizedBox(height: 16),
                  if (!widget.embedded) ...[
                    DropdownButtonFormField<int>(
                      initialValue: _selectedCompanyId,
                      decoration: const InputDecoration(
                        labelText: 'Company *',
                        border: OutlineInputBorder(),
                        filled: true,
                        fillColor: Colors.white,
                      ),
                      items: _companies
                          .cast<Map<String, dynamic>>()
                          .map(
                            (c) => DropdownMenuItem<int>(
                              value: c['id'] as int,
                              child: Text(
                                c['company_name']?.toString() ?? '',
                                overflow: TextOverflow.ellipsis,
                              ),
                            ),
                          )
                          .toList(),
                      onChanged: (v) =>
                          setState(() => _selectedCompanyId = v),
                      validator: (v) =>
                          v == null ? 'Company is required' : null,
                    ),
                    const SizedBox(height: 12),
                  ],
                  TextFormField(
                    controller: _nameController,
                    decoration: const InputDecoration(
                      labelText: 'Customer Name *',
                      border: OutlineInputBorder(),
                      filled: true,
                      fillColor: Colors.white,
                    ),
                    validator: (v) => (v == null || v.trim().isEmpty)
                        ? 'Customer name is required'
                        : null,
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: _phoneController,
                    keyboardType: TextInputType.phone,
                    decoration: const InputDecoration(
                      labelText: 'Phone Number',
                      border: OutlineInputBorder(),
                      filled: true,
                      fillColor: Colors.white,
                    ),
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: _addressController,
                    maxLines: 2,
                    decoration: const InputDecoration(
                      labelText: 'Address',
                      border: OutlineInputBorder(),
                      filled: true,
                      fillColor: Colors.white,
                    ),
                  ),
                  if (!widget.embedded && _employees.isNotEmpty) ...[
                    const SizedBox(height: 12),
                    DropdownButtonFormField<int>(
                      initialValue: _selectedSalesOfficerId,
                      decoration: const InputDecoration(
                        labelText: 'Sales Officer (optional)',
                        border: OutlineInputBorder(),
                        filled: true,
                        fillColor: Colors.white,
                      ),
                      items: [
                        const DropdownMenuItem<int>(
                          value: null,
                          child: Text('— None —'),
                        ),
                        ..._employees.cast<Map<String, dynamic>>().map(
                          (e) => DropdownMenuItem<int>(
                            value: e['id'] as int,
                            child: Text(
                              e['full_name']?.toString() ?? '',
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                        ),
                      ],
                      onChanged: (v) =>
                          setState(() => _selectedSalesOfficerId = v),
                    ),
                  ],
                  if (_error != null) ...[
                    const SizedBox(height: 16),
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: const Color(0xFFFDE8E8),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Text(
                        _error!,
                        style: const TextStyle(color: Color(0xFFE34F4F)),
                      ),
                    ),
                  ],
                  const SizedBox(height: 20),
                  SizedBox(
                    height: 48,
                    child: ElevatedButton(
                      onPressed: _saving ? null : _save,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: const Color(0xFF1F7BFF),
                        foregroundColor: Colors.white,
                      ),
                      child: _saving
                          ? const SizedBox(
                              height: 20,
                              width: 20,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                color: Colors.white,
                              ),
                            )
                          : Text(
                              widget.embedded
                                  ? 'Save Customer & Continue'
                                  : 'Save Customer',
                            ),
                    ),
                  ),
                ],
              ),
            ),
    );
  }
}
