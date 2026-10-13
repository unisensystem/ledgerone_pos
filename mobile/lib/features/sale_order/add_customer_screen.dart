import 'package:flutter/material.dart';

import '../../core/reference_cache.dart';
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
/// Requires connectivity either way: customer_code is generated server-side
/// via a locked sequential counter (see customer-add.php), the same reason
/// voucher/invoice numbers are never generated offline elsewhere in this app.
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
    try {
      final result = await SaleOrderApi.createCustomer(
        companyId: _selectedCompanyId!,
        customerName: _nameController.text.trim(),
        primaryPhone: _phoneController.text.trim(),
        address: _addressController.text.trim(),
        salesOfficerId: _selectedSalesOfficerId,
        supplierManId: widget.defaultSupplierManId,
      );
      if (!mounted) return;

      if (widget.embedded) {
        Navigator.pop(context, {
          'id': result['customer_id'],
          'customer_code': result['customer_code'],
          'customer_name': _nameController.text.trim(),
          'current_balance': 0,
          'address': _addressController.text.trim(),
          'default_discount_percentage': 0,
          'credit_limit': 0,
          'associated_sales_officer_id': _selectedSalesOfficerId,
          'supplier_man_id': widget.defaultSupplierManId,
        });
        return;
      }

      final customerName = _nameController.text.trim();
      final customerCode = result['customer_code']?.toString() ?? '';
      await showDialog<void>(
        context: context,
        builder: (dialogContext) => AlertDialog(
          title: const Text('Customer Created'),
          content: Text(
            '"$customerName" was created successfully.\nCustomer Code: $customerCode',
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
    } catch (e) {
      setState(
        () => _error =
            'Could not create customer. Make sure you have an internet '
            'connection, then try again.\n($e)',
      );
    } finally {
      if (mounted) setState(() => _saving = false);
    }
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
