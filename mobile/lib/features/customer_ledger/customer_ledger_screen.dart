import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../core/reference_cache.dart';
import '../../core/searchable_field.dart';
import '../sale_order/sale_order_api.dart';
import 'customer_ledger_api.dart';

const _primaryBlue = Color(0xFF1F7BFF);
const _success = Color(0xFF2FBF71);
const _error = Color(0xFFE34F4F);
const _subtext = Color(0xFF6B7280);

/// Read-only Customer Ledger check — the mobile cut of web's Customer
/// Ledger report (client/pages/financial_reports/customer_ledger/
/// customer-ledger.php). Lets a field salesman/recovery officer look up a
/// customer's balance and recent transactions before visiting them, without
/// needing the office. Deliberately leaves out web's city/zone/area/
/// salesman filters, sub-account/distribution breakdown, currency override
/// and export/share/print tools — those are back-office report tooling, not
/// something a field check needs; the balance + transaction list is.
///
/// Matches web's Summary Ledger / Detailed Ledger split, per office
/// direction: a full line-by-line transaction history is only meaningful
/// read live (it's an audit trail — a stale offline copy inviting a booker
/// to make a decision off outdated transactions is worse than no copy), so
/// the Detailed tab always requires connectivity. The Summary (just the
/// opening/debit/credit/closing balance) is much lower-risk to show
/// slightly stale, and is what a booker needs 90% of the time ("what does
/// this customer owe me") — so it's cached after every successful load and
/// falls back to that cache when offline, clearly labeled with when it was
/// last fetched.
class CustomerLedgerScreen extends StatefulWidget {
  const CustomerLedgerScreen({super.key});

  @override
  State<CustomerLedgerScreen> createState() => _CustomerLedgerScreenState();
}

class _CustomerLedgerScreenState extends State<CustomerLedgerScreen> {
  bool _loadingReference = true;
  String? _loadError;

  List<dynamic> _companies = [];
  List<dynamic> _customers = [];

  Map<String, dynamic>? _selectedCustomer;
  int? _selectedCompanyId;
  DateTime? _fromDate;
  DateTime? _toDate;

  // 'summary' | 'detailed' — matches web's Summary Ledger / Detailed Ledger
  // tabs. Summary can fall back to a cached balance when offline; Detailed
  // always needs a live connection (see class doc comment for why).
  String _ledgerType = 'summary';

  bool _loadingLedger = false;
  String? _ledgerError;
  Map<String, dynamic>? _summary;
  bool _hasLoadedSummary = false;
  // Set when the summary on screen is a cached fallback from a previous
  // successful load, not what was just requested (that request failed —
  // typically no connectivity).
  DateTime? _cachedAsOf;

  List<dynamic> _transactions = [];
  bool _hasLoadedDetailed = false;
  String? _detailedError;

  @override
  void initState() {
    super.initState();
    _loadReferenceData();
  }

  Future<void> _loadReferenceData() async {
    setState(() => _loadingReference = true);
    try {
      final results = await Future.wait([
        ReferenceCache.cached('so_companies', SaleOrderApi.getCompanies),
        ReferenceCache.cached('so_customers', SaleOrderApi.getCustomers),
      ]);
      if (!mounted) return;
      setState(() {
        _companies = results[0];
        _customers = results[1];
      });
    } catch (e) {
      if (mounted) setState(() => _loadError = 'Could not load data: $e');
    } finally {
      if (mounted) setState(() => _loadingReference = false);
    }
  }

  Future<void> _pickDate({required bool isFrom}) async {
    final picked = await showDatePicker(
      context: context,
      initialDate: (isFrom ? _fromDate : _toDate) ?? DateTime.now(),
      firstDate: DateTime(2020),
      lastDate: DateTime.now().add(const Duration(days: 365)),
    );
    if (picked == null) return;
    setState(() {
      if (isFrom) {
        _fromDate = picked;
      } else {
        _toDate = picked;
      }
    });
  }

  String _fmtDate(DateTime d) => DateFormat('yyyy-MM-dd').format(d);

  Future<void> _loadLedger() async {
    final customer = _selectedCustomer;
    if (customer == null) {
      setState(() => _ledgerError = 'Please select a customer first.');
      return;
    }
    setState(() {
      _loadingLedger = true;
      _ledgerError = null;
      _detailedError = null;
      _hasLoadedSummary = false;
      _hasLoadedDetailed = false;
      _cachedAsOf = null;
    });
    final customerId = customer['id'] as int;
    final fromStr = _fromDate != null ? _fmtDate(_fromDate!) : null;
    final toStr = _toDate != null ? _fmtDate(_toDate!) : null;

    // Summary: always fetched (it's the quick balance header either tab
    // shows), with an offline-cache fallback.
    try {
      final summary = await CustomerLedgerApi.getSummary(
        customerId: customerId,
        companyId: _selectedCompanyId,
        fromDate: fromStr,
        toDate: toStr,
      );
      if (!mounted) return;
      setState(() {
        _summary = summary;
        _hasLoadedSummary = true;
      });
      await _cacheSummary(customerId, summary);
    } catch (e) {
      final cached = await _loadCachedSummary(customerId);
      if (cached != null) {
        if (!mounted) return;
        setState(() {
          _summary = cached.$1;
          _cachedAsOf = cached.$2;
          _hasLoadedSummary = true;
        });
      } else {
        debugPrint('Customer Ledger summary load failed: $e');
        setState(
          () => _ledgerError =
              'Could not load balance. Make sure you have an internet '
              'connection, then try again.',
        );
      }
    }

    // Detailed: only fetched when that tab is active, and never falls back
    // to a cache — a stale transaction history is misleading in a way a
    // stale balance figure isn't (see class doc comment).
    if (_ledgerType == 'detailed') {
      try {
        final transactions = await CustomerLedgerApi.getDetailed(
          customerId: customerId,
          companyId: _selectedCompanyId,
          fromDate: fromStr,
          toDate: toStr,
        );
        if (!mounted) return;
        setState(() {
          _transactions = transactions;
          _hasLoadedDetailed = true;
        });
      } catch (e) {
        debugPrint('Customer Ledger detailed load failed: $e');
        setState(
          () => _detailedError =
              'Detailed transactions need an internet connection. '
              'The balance above is still available offline.',
        );
      }
    }

    if (mounted) setState(() => _loadingLedger = false);
  }

  String _summaryCacheKey(int customerId) =>
      'customer_ledger_summary_$customerId';

  Future<void> _cacheSummary(
    int customerId,
    Map<String, dynamic>? summary,
  ) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(
        _summaryCacheKey(customerId),
        jsonEncode({
          'summary': summary,
          'cachedAt': DateTime.now().toIso8601String(),
        }),
      );
    } catch (_) {
      // Caching is best-effort — a failure here shouldn't affect the
      // screen the booker is actually looking at.
    }
  }

  Future<(Map<String, dynamic>?, DateTime)?> _loadCachedSummary(
    int customerId,
  ) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final raw = prefs.getString(_summaryCacheKey(customerId));
      if (raw == null) return null;
      final decoded = jsonDecode(raw) as Map<String, dynamic>;
      final summary = decoded['summary'] as Map<String, dynamic>?;
      final cachedAt = DateTime.parse(decoded['cachedAt'] as String);
      return (summary, cachedAt);
    } catch (_) {
      return null;
    }
  }

  double _num(dynamic v) => double.tryParse(v?.toString() ?? '') ?? 0;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF7F9FC),
      appBar: AppBar(
        title: const Text('Customer Ledger'),
        backgroundColor: _primaryBlue,
        foregroundColor: Colors.white,
      ),
      body: _loadingReference
          ? const Center(child: CircularProgressIndicator())
          : _loadError != null
          ? Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Text(
                  _loadError!,
                  textAlign: TextAlign.center,
                  style: const TextStyle(color: _error),
                ),
              ),
            )
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                searchableField<Map<String, dynamic>>(
                  label: 'Customer (type to search)',
                  options: _customers.cast<Map<String, dynamic>>(),
                  displayStringForOption: (c) =>
                      '${c['customer_code']} - ${c['customer_name']}',
                  matches: (c, q) =>
                      c['customer_name'].toString().toLowerCase().contains(
                        q,
                      ) ||
                      c['customer_code'].toString().toLowerCase().contains(
                        q,
                      ),
                  onSelected: (c) => setState(() => _selectedCustomer = c),
                ),
                const SizedBox(height: 12),
                DropdownButtonFormField<int>(
                  initialValue: _selectedCompanyId,
                  decoration: const InputDecoration(
                    labelText: 'Company (optional)',
                    border: OutlineInputBorder(),
                    filled: true,
                    fillColor: Colors.white,
                  ),
                  items: [
                    const DropdownMenuItem<int>(
                      value: null,
                      child: Text('All Companies'),
                    ),
                    ..._companies.cast<Map<String, dynamic>>().map(
                      (c) => DropdownMenuItem<int>(
                        value: c['id'] as int,
                        child: Text(
                          c['company_name']?.toString() ?? '',
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    ),
                  ],
                  onChanged: (v) => setState(() => _selectedCompanyId = v),
                ),
                const SizedBox(height: 12),
                Row(
                  children: [
                    Expanded(
                      child: OutlinedButton(
                        onPressed: () => _pickDate(isFrom: true),
                        child: Text(
                          _fromDate != null
                              ? 'From: ${_fmtDate(_fromDate!)}'
                              : 'From Date (optional)',
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: OutlinedButton(
                        onPressed: () => _pickDate(isFrom: false),
                        child: Text(
                          _toDate != null
                              ? 'To: ${_fmtDate(_toDate!)}'
                              : 'To Date (optional)',
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 16),
                _ledgerTypeToggle(),
                const SizedBox(height: 16),
                SizedBox(
                  height: 46,
                  child: ElevatedButton.icon(
                    onPressed: _loadingLedger ? null : _loadLedger,
                    style: ElevatedButton.styleFrom(
                      backgroundColor: _primaryBlue,
                      foregroundColor: Colors.white,
                    ),
                    icon: _loadingLedger
                        ? const SizedBox(
                            height: 16,
                            width: 16,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              color: Colors.white,
                            ),
                          )
                        : const Icon(Icons.search, size: 18),
                    label: const Text('Load Ledger'),
                  ),
                ),
                if (_ledgerError != null) ...[
                  const SizedBox(height: 16),
                  Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: const Color(0xFFFDE8E8),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Text(
                      _ledgerError!,
                      style: const TextStyle(color: _error),
                    ),
                  ),
                ],
                if (_hasLoadedSummary) ...[
                  const SizedBox(height: 20),
                  if (_cachedAsOf != null) ...[
                    Container(
                      padding: const EdgeInsets.all(10),
                      decoration: BoxDecoration(
                        color: const Color(0xFFFFF3E0),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Row(
                        children: [
                          const Icon(
                            Icons.history_rounded,
                            size: 16,
                            color: Color(0xFFD97706),
                          ),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Text(
                              'Offline — showing balance as of '
                              '${DateFormat('dd MMM, hh:mm a').format(_cachedAsOf!)}. '
                              'May not reflect the latest transactions.',
                              style: const TextStyle(
                                fontSize: 12,
                                color: Color(0xFFD97706),
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 12),
                  ],
                  _buildSummaryCard(),
                  if (_ledgerType == 'detailed') ...[
                    const SizedBox(height: 16),
                    const Text(
                      'Transactions',
                      style: TextStyle(
                        fontWeight: FontWeight.w700,
                        fontSize: 15,
                      ),
                    ),
                    const SizedBox(height: 8),
                    if (_detailedError != null)
                      Container(
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: const Color(0xFFFDE8E8),
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Icon(
                              Icons.wifi_off_rounded,
                              size: 18,
                              color: _error,
                            ),
                            const SizedBox(width: 8),
                            Expanded(
                              child: Text(
                                _detailedError!,
                                style: const TextStyle(color: _error),
                              ),
                            ),
                          ],
                        ),
                      )
                    else if (_hasLoadedDetailed && _transactions.isEmpty)
                      const Padding(
                        padding: EdgeInsets.symmetric(vertical: 24),
                        child: Center(
                          child: Text(
                            'No transactions in this period.',
                            style: TextStyle(color: _subtext),
                          ),
                        ),
                      )
                    else if (_hasLoadedDetailed)
                      ..._transactions.map(_buildTransactionTile),
                  ],
                ],
              ],
            ),
    );
  }

  Widget _ledgerTypeToggle() {
    Widget tab(String value, String label) {
      final selected = _ledgerType == value;
      return Expanded(
        child: GestureDetector(
          onTap: () => setState(() => _ledgerType = value),
          child: Container(
            padding: const EdgeInsets.symmetric(vertical: 10),
            decoration: BoxDecoration(
              color: selected ? _primaryBlue : Colors.white,
              border: Border.all(
                color: selected ? _primaryBlue : const Color(0xFFE1E6EE),
              ),
              borderRadius: BorderRadius.horizontal(
                left: value == 'summary'
                    ? const Radius.circular(8)
                    : Radius.zero,
                right: value == 'detailed'
                    ? const Radius.circular(8)
                    : Radius.zero,
              ),
            ),
            child: Text(
              label,
              textAlign: TextAlign.center,
              style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w600,
                color: selected ? Colors.white : _subtext,
              ),
            ),
          ),
        ),
      );
    }

    return Row(
      children: [
        tab('summary', 'Summary (works offline)'),
        tab('detailed', 'Detailed (needs internet)'),
      ],
    );
  }

  Widget _buildSummaryCard() {
    final opening = _num(_summary?['opening_balance']);
    final debit = _num(_summary?['total_debit']);
    final credit = _num(_summary?['total_credit']);
    final closing = _num(_summary?['closing_balance']);
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.05),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        children: [
          Row(
            children: [
              Expanded(
                child: _summaryStat('Opening Balance', opening, neutral: true),
              ),
              Expanded(child: _summaryStat('Debit (Dr)', debit, isDebit: true)),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: _summaryStat('Credit (Cr)', credit, isDebit: false),
              ),
              Expanded(
                child: _summaryStat(
                  'Closing Balance',
                  closing,
                  neutral: true,
                  bold: true,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _summaryStat(
    String label,
    double value, {
    bool isDebit = false,
    bool neutral = false,
    bool bold = false,
  }) {
    final color = neutral
        ? (value >= 0 ? _success : _error)
        : (isDebit ? _success : _error);
    final suffix = neutral ? (value >= 0 ? ' Dr' : ' Cr') : '';
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label, style: const TextStyle(fontSize: 11.5, color: _subtext)),
        const SizedBox(height: 2),
        Text(
          '${value.abs().toStringAsFixed(2)}$suffix',
          style: TextStyle(
            fontSize: bold ? 16 : 14,
            fontWeight: bold ? FontWeight.w700 : FontWeight.w600,
            color: color,
          ),
        ),
      ],
    );
  }

  Widget _buildTransactionTile(dynamic raw) {
    final t = Map<String, dynamic>.from(raw as Map);
    final type = t['type']?.toString() ?? '';

    if (type == 'sub_account_header') {
      return Padding(
        padding: const EdgeInsets.only(top: 12, bottom: 4),
        child: Text(
          t['sub_account_name']?.toString() ?? 'Sub Account',
          style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13),
        ),
      );
    }
    if (type == 'sub_account_total') {
      return Padding(
        padding: const EdgeInsets.symmetric(vertical: 6),
        child: Text(
          'Sub Total — Dr ${_num(t['total_debit']).toStringAsFixed(2)} / '
          'Cr ${_num(t['total_credit']).toStringAsFixed(2)}',
          style: const TextStyle(
            fontSize: 12,
            fontStyle: FontStyle.italic,
            color: _subtext,
          ),
        ),
      );
    }

    final debit = _num(t['debit']);
    final credit = _num(t['credit']);
    final running = _num(t['running_balance']);
    final coveredAmount = _num(t['covered_amount']);
    final pdcDisplay = _num(t['pdc_display']);
    final receivedDate = type == 'pdc' ? t['received_date']?.toString() : null;
    final againstRvs = type == 'pdc' ? t['against_rvs']?.toString() : null;
    final againstChequeNo =
        type == 'payment' ? t['against_cheque_no']?.toString() : null;

    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: const Color(0xFFE1E6EE)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  t['description']?.toString() ?? '',
                  style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13),
                ),
                const SizedBox(height: 2),
                Text(
                  '${receivedDate != null && receivedDate.isNotEmpty ? 'Received ${t['date']}, cheque dated $receivedDate' : t['date'] ?? ''}'
                  '${t['reference'] != null ? ' · ${t['reference']}' : ''}',
                  style: const TextStyle(fontSize: 11.5, color: _subtext),
                ),
                if (againstRvs != null && againstRvs.isNotEmpty)
                  Padding(
                    padding: const EdgeInsets.only(top: 2),
                    child: Text(
                      'Against $againstRvs',
                      style: const TextStyle(fontSize: 11, color: _subtext, fontStyle: FontStyle.italic),
                    ),
                  ),
                if (againstChequeNo != null && againstChequeNo.isNotEmpty)
                  Padding(
                    padding: const EdgeInsets.only(top: 2),
                    child: Text(
                      'Against Cheque $againstChequeNo',
                      style: const TextStyle(fontSize: 11, color: _subtext, fontStyle: FontStyle.italic),
                    ),
                  ),
                if (coveredAmount > 0)
                  Padding(
                    padding: const EdgeInsets.only(top: 2),
                    child: Text(
                      'Covered by PDC',
                      style: const TextStyle(fontSize: 11, color: _subtext, fontStyle: FontStyle.italic),
                    ),
                  ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              if (debit > 0)
                Text(
                  'Dr ${debit.toStringAsFixed(2)}',
                  style: const TextStyle(color: _success, fontWeight: FontWeight.w600, fontSize: 13),
                ),
              if (credit > 0)
                Text(
                  'Cr ${credit.toStringAsFixed(2)}',
                  style: const TextStyle(color: _error, fontWeight: FontWeight.w600, fontSize: 13),
                ),
              if (debit == 0 && credit == 0 && (pdcDisplay > 0 || coveredAmount > 0))
                Text(
                  '(${(pdcDisplay > 0 ? pdcDisplay : coveredAmount).toStringAsFixed(2)})',
                  style: const TextStyle(color: _subtext, fontWeight: FontWeight.w600, fontSize: 13),
                ),
              const SizedBox(height: 2),
              Text(
                'Bal: ${running.abs().toStringAsFixed(2)} ${running >= 0 ? 'Dr' : 'Cr'}',
                style: const TextStyle(fontSize: 11, color: _subtext),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
