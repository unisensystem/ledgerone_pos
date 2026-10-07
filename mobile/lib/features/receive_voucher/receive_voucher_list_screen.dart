import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../../core/outbox_service.dart';
import '../../core/sync_service.dart';
import 'receive_voucher_api.dart';
import 'receive_voucher_screen.dart';

/// Mirrors web's Receive Voucher list page via the same receive-list.php
/// API. No Draft/Posted concept exists for this voucher type — every
/// voucher is final the moment it's created.
class ReceiveVoucherListScreen extends StatefulWidget {
  const ReceiveVoucherListScreen({super.key});

  @override
  State<ReceiveVoucherListScreen> createState() => _ReceiveVoucherListScreenState();
}

class _ReceiveVoucherListScreenState extends State<ReceiveVoucherListScreen> {
  final _searchController = TextEditingController();
  List<dynamic> _vouchers = [];
  int _page = 1;
  int _totalPages = 1;
  bool _loading = true;
  bool _manualSyncing = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
    SyncService.instance.lastSyncMessage.addListener(_onSyncFinished);
  }

  void _onSyncFinished() {
    if (mounted) _load(page: _page);
  }

  @override
  void dispose() {
    SyncService.instance.lastSyncMessage.removeListener(_onSyncFinished);
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _load({int page = 1}) async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final result = await ReceiveVoucherApi.getVouchers(
        page: page,
        search: _searchController.text.trim().isEmpty ? null : _searchController.text.trim(),
      );
      setState(() {
        _vouchers = result['data'] as List<dynamic>? ?? [];
        _page = result['pagination']?['current_page'] ?? 1;
        _totalPages = result['pagination']?['total_pages'] ?? 1;
        _loading = false;
      });
    } catch (e) {
      setState(() {
        _loading = false;
        _error = 'Could not load vouchers: $e';
      });
    }
  }

  Future<void> _editVoucher(int voucherId) async {
    await Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => ReceiveVoucherScreen(editVoucherId: voucherId)),
    );
    _load(page: _page);
  }

  Future<void> _deleteVoucher(int voucherId, String voucherNumber) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Delete Voucher'),
        content: Text('Delete voucher $voucherNumber? This cannot be undone.'),
        actions: [
          TextButton(onPressed: () => Navigator.of(context).pop(false), child: const Text('Cancel')),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFFE34F4F), foregroundColor: Colors.white),
            onPressed: () => Navigator.of(context).pop(true),
            child: const Text('Delete'),
          ),
        ],
      ),
    );
    if (confirmed != true) return;

    try {
      final result = await ReceiveVoucherApi.deleteVoucher(voucherId);
      if (!mounted) return;
      if (result['success'] == true) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Voucher $voucherNumber deleted')));
        _load(page: _page);
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(result['message']?.toString() ?? result['error']?.toString() ?? 'Could not delete voucher')),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Network error: $e')));
      }
    }
  }

  Future<void> _manualSync() async {
    setState(() => _manualSyncing = true);
    final pendingBefore = (await OutboxService.instance.getAll()).where((e) => e.endpoint.contains('receive_voucher')).length;
    await SyncService.instance.syncNow();
    if (!mounted) return;
    final pendingAfter = (await OutboxService.instance.getAll()).where((e) => e.endpoint.contains('receive_voucher')).length;
    setState(() => _manualSyncing = false);
    final message = pendingBefore == 0
        ? 'Nothing to sync'
        : pendingAfter == 0
            ? 'All vouchers synced'
            : pendingAfter < pendingBefore
                ? '${pendingBefore - pendingAfter} synced, $pendingAfter still waiting (no connection?)'
                : 'Still no connection — $pendingAfter voucher(s) waiting';
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
    }
    _load(page: _page);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF7F9FC),
      appBar: AppBar(
        title: const Text('Receive Vouchers'),
        backgroundColor: const Color(0xFF1F7BFF),
        foregroundColor: Colors.white,
        actions: [
          IconButton(
            tooltip: 'Sync Now',
            icon: _manualSyncing
                ? const SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                  )
                : const Icon(Icons.sync),
            onPressed: _manualSyncing ? null : _manualSync,
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        backgroundColor: const Color(0xFF1F7BFF),
        foregroundColor: Colors.white,
        icon: const Icon(Icons.add),
        label: const Text('New Voucher'),
        onPressed: () async {
          await Navigator.of(context).push(
            MaterialPageRoute(builder: (_) => const ReceiveVoucherScreen()),
          );
          _load();
        },
      ),
      body: Column(
        children: [
          const _PendingSyncBanner(),
          Padding(
            padding: const EdgeInsets.all(16),
            child: TextField(
              controller: _searchController,
              onSubmitted: (_) => _load(),
              decoration: InputDecoration(
                hintText: 'Search voucher no., customer or bill',
                border: const OutlineInputBorder(),
                filled: true,
                fillColor: Colors.white,
                prefixIcon: const Icon(Icons.search),
                suffixIcon: IconButton(
                  icon: const Icon(Icons.arrow_forward),
                  onPressed: () => _load(),
                ),
              ),
            ),
          ),
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator())
                : _error != null
                    ? Center(
                        child: Padding(
                          padding: const EdgeInsets.all(24),
                          child: Column(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Text(_error!, textAlign: TextAlign.center),
                              const SizedBox(height: 12),
                              ElevatedButton(onPressed: () => _load(), child: const Text('Retry')),
                            ],
                          ),
                        ),
                      )
                    : _vouchers.isEmpty
                        ? const Center(child: Text('No receive vouchers found.', style: TextStyle(color: Color(0xFF6B7280))))
                        : RefreshIndicator(
                            onRefresh: () => _load(page: _page),
                            child: ListView.builder(
                              padding: const EdgeInsets.fromLTRB(16, 0, 16, 90),
                              itemCount: _vouchers.length + 1,
                              itemBuilder: (context, index) {
                                if (index == _vouchers.length) {
                                  return _pager();
                                }
                                final voucher = _vouchers[index];
                                final voucherId = voucher['id'] as int;
                                final voucherNumber = voucher['voucher_number']?.toString() ?? '';
                                final amount = double.tryParse(voucher['amount']?.toString() ?? '') ?? 0;
                                final voucherDate = voucher['voucher_date'] != null
                                    ? DateFormat('dd MMM yyyy').format(DateTime.parse(voucher['voucher_date']))
                                    : '';
                                return Card(
                                  margin: const EdgeInsets.only(bottom: 8),
                                  child: Padding(
                                    padding: const EdgeInsets.fromLTRB(16, 12, 8, 4),
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Text(voucherNumber, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 15)),
                                        const SizedBox(height: 2),
                                        Text(
                                          '${voucher['customer_name'] ?? 'Unknown customer'} · $voucherDate · ${voucher['payment_method'] ?? ''}',
                                          style: const TextStyle(fontSize: 12, color: Color(0xFF6B7280)),
                                        ),
                                        Row(
                                          children: [
                                            Expanded(
                                              child: Text(
                                                '${voucher['currency_symbol'] ?? ''} ${amount.toStringAsFixed(2)}',
                                                style: const TextStyle(fontWeight: FontWeight.w600),
                                              ),
                                            ),
                                            IconButton(
                                              icon: const Icon(Icons.edit_outlined, size: 19),
                                              tooltip: 'Edit Voucher',
                                              onPressed: () => _editVoucher(voucherId),
                                            ),
                                            IconButton(
                                              icon: const Icon(Icons.delete_outline, size: 19, color: Color(0xFFE34F4F)),
                                              tooltip: 'Delete Voucher',
                                              onPressed: () => _deleteVoucher(voucherId, voucherNumber),
                                            ),
                                          ],
                                        ),
                                      ],
                                    ),
                                  ),
                                );
                              },
                            ),
                          ),
          ),
        ],
      ),
    );
  }

  Widget _pager() {
    if (_totalPages <= 1) return const SizedBox(height: 8);
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 16),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          TextButton(
            onPressed: _page > 1 ? () => _load(page: _page - 1) : null,
            child: const Text('Previous'),
          ),
          Text('Page $_page of $_totalPages'),
          TextButton(
            onPressed: _page < _totalPages ? () => _load(page: _page + 1) : null,
            child: const Text('Next'),
          ),
        ],
      ),
    );
  }
}

class _PendingSyncBanner extends StatefulWidget {
  const _PendingSyncBanner();

  @override
  State<_PendingSyncBanner> createState() => _PendingSyncBannerState();
}

class _PendingSyncBannerState extends State<_PendingSyncBanner> {
  List<OutboxEntry> _entries = [];
  bool _syncing = false;

  @override
  void initState() {
    super.initState();
    OutboxService.instance.changeTick.addListener(_refresh);
    _refresh();
  }

  @override
  void dispose() {
    OutboxService.instance.changeTick.removeListener(_refresh);
    super.dispose();
  }

  Future<void> _refresh() async {
    final entries = (await OutboxService.instance.getAll())
        .where((e) => e.endpoint.contains('receive_voucher'))
        .toList();
    if (mounted) setState(() => _entries = entries);
  }

  Future<void> _syncNow() async {
    setState(() => _syncing = true);
    await SyncService.instance.syncNow();
    if (mounted) setState(() => _syncing = false);
  }

  @override
  Widget build(BuildContext context) {
    if (_entries.isEmpty) return const SizedBox.shrink();
    return Container(
      width: double.infinity,
      color: const Color(0xFFFFF4E0),
      padding: const EdgeInsets.all(12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(Icons.sync_problem, size: 18, color: Color(0xFFB8860B)),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  '${_entries.length} voucher(s) waiting to sync',
                  style: const TextStyle(fontWeight: FontWeight.w600, color: Color(0xFFB8860B)),
                ),
              ),
              TextButton(
                onPressed: _syncing ? null : _syncNow,
                child: _syncing
                    ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2))
                    : const Text('Sync Now'),
              ),
            ],
          ),
          for (final e in _entries)
            Padding(
              padding: const EdgeInsets.only(top: 4, left: 26),
              child: Text(
                e.status == 'failed'
                    ? '${e.label} — failed${e.lastError != null ? ': ${e.lastError}' : ''}'
                    : e.status == 'syncing'
                        ? '${e.label} — syncing…'
                        : '${e.label} — pending',
                style: const TextStyle(fontSize: 12, color: Color(0xFF6B7280)),
                overflow: TextOverflow.ellipsis,
              ),
            ),
        ],
      ),
    );
  }
}
