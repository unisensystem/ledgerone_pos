import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../core/outbox_service.dart';
import '../../core/print_launcher.dart';
import '../../core/reference_cache.dart';
import '../../core/sync_service.dart';
import 'sale_order_api.dart';
import 'sale_order_screen.dart';

/// Mirrors web's Sale Order list page (client/pages/sale/sale_order/order-list.php)
/// via the same order-list.php API. Toggles between Posted orders and
/// "Needs Review" — Draft orders the hybrid auto-post rule flagged (stale
/// offline price, or not enough stock to cover what several field bookers
/// requested at once) — matching web's own "View Draft Orders" button,
/// which used to be an unimplemented "Coming soon!" stub and now calls the
/// same order_status=Draft filter this screen does.
class SaleOrderListScreen extends StatefulWidget {
  const SaleOrderListScreen({super.key});

  @override
  State<SaleOrderListScreen> createState() => _SaleOrderListScreenState();
}

class _SaleOrderListScreenState extends State<SaleOrderListScreen> {
  final _searchController = TextEditingController();
  List<dynamic> _orders = [];
  int _page = 1;
  int _totalPages = 1;
  bool _loading = true;
  bool _manualSyncing = false;
  String? _error;
  String _orderStatus = 'Posted';

  @override
  void initState() {
    super.initState();
    _load();
    // Refresh the Posted list once a background sync finishes — a
    // previously-offline order may have just landed server-side.
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
      final search = _searchController.text.trim().isEmpty
          ? null
          : _searchController.text.trim();
      final fetchOrders = () => SaleOrderApi.getOrders(
        page: page,
        search: search,
        orderStatus: _orderStatus,
      );
      final result = (page == 1 && search == null)
          ? await ReferenceCache.cached(
              'so_list_page1_$_orderStatus',
              fetchOrders,
            )
          : await fetchOrders();
      setState(() {
        _orders = result['invoices'] as List<dynamic>? ?? [];
        _page = result['pagination']?['page'] ?? 1;
        _totalPages = result['pagination']?['pages'] ?? 1;
        _loading = false;
      });
    } catch (e) {
      setState(() {
        _loading = false;
        _error = 'Could not load orders: $e';
      });
    }
  }

  Future<void> _editOrder(int orderId) async {
    await Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => SaleOrderScreen(editOrderId: orderId)),
    );
    _load(page: _page);
  }

  Future<void> _deleteOrder(int orderId, String billNo) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Delete Order'),
        content: Text('Delete order $billNo? This cannot be undone.'),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text('Cancel'),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFFE34F4F),
              foregroundColor: Colors.white,
            ),
            onPressed: () => Navigator.of(context).pop(true),
            child: const Text('Delete'),
          ),
        ],
      ),
    );
    if (confirmed != true) return;

    try {
      final result = await SaleOrderApi.deleteOrder(orderId);
      if (!mounted) return;
      if (result['success'] == true) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text('Order $billNo deleted')));
        _load(page: _page);
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              result['message']?.toString() ?? 'Could not delete order',
            ),
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text('Network error: $e')));
      }
    }
  }

  Future<void> _showPrintOptions(int orderId) async {
    final choice = await showDialog<String>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Select Print Format'),
        content: const Text('Choose how you want to print the order:'),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(),
            child: const Text('Cancel'),
          ),
          TextButton(
            onPressed: () => Navigator.of(context).pop('thermal'),
            child: const Text('Thermal'),
          ),
          ElevatedButton(
            onPressed: () => Navigator.of(context).pop('full'),
            child: const Text('Full Order'),
          ),
        ],
      ),
    );
    if (choice == null) return;
    final opened = await PrintLauncher.open(
      orderId: orderId,
      thermal: choice == 'thermal',
    );
    if (!opened && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'Could not open the print page — check your connection and try again.',
          ),
        ),
      );
    }
  }

  Future<void> _manualSync() async {
    setState(() => _manualSyncing = true);
    final pendingBefore = (await OutboxService.instance.getAll()).length;
    await SyncService.instance.syncNow();
    if (!mounted) return;
    final pendingAfter = (await OutboxService.instance.getAll()).length;
    setState(() => _manualSyncing = false);
    final message = pendingBefore == 0
        ? 'Nothing to sync'
        : pendingAfter == 0
        ? 'All orders synced'
        : pendingAfter < pendingBefore
        ? '${pendingBefore - pendingAfter} synced, $pendingAfter still waiting (no connection?)'
        : 'Still no connection — $pendingAfter order(s) waiting';
    if (mounted) {
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(message)));
    }
    _load(page: _page);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF7F9FC),
      appBar: AppBar(
        title: const Text('Sale Orders'),
        backgroundColor: const Color(0xFF1F7BFF),
        foregroundColor: Colors.white,
        actions: [
          IconButton(
            tooltip: 'Sync Now',
            icon: _manualSyncing
                ? const SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      color: Colors.white,
                    ),
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
        label: const Text('New Order'),
        onPressed: () async {
          await Navigator.of(context)
              .push(MaterialPageRoute(builder: (_) => const SaleOrderScreen()));
          _load();
        },
      ),
      body: Column(
        children: [
          const _PendingSyncBanner(),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
            child: SegmentedButton<String>(
              segments: const [
                ButtonSegment(value: 'Posted', label: Text('Posted')),
                ButtonSegment(value: 'Draft', label: Text('Needs Review')),
              ],
              selected: {_orderStatus},
              onSelectionChanged: (selection) {
                setState(() => _orderStatus = selection.first);
                _load();
              },
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(16),
            child: TextField(
              controller: _searchController,
              onSubmitted: (_) => _load(),
              decoration: InputDecoration(
                hintText: 'Search bill no. or customer',
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
                          ElevatedButton(
                            onPressed: () => _load(),
                            child: const Text('Retry'),
                          ),
                        ],
                      ),
                    ),
                  )
                : _orders.isEmpty
                ? const Center(
                    child: Text(
                      'No sale orders found.',
                      style: TextStyle(color: Color(0xFF6B7280)),
                    ),
                  )
                : RefreshIndicator(
                    onRefresh: () => _load(page: _page),
                    child: ListView.builder(
                      padding: const EdgeInsets.fromLTRB(16, 0, 16, 90),
                      itemCount: _orders.length + 1,
                      itemBuilder: (context, index) {
                        if (index == _orders.length) {
                          return _pager();
                        }
                        final order = _orders[index];
                        final orderId = order['id'] as int;
                        final billNo = order['bill_no']?.toString() ?? '';
                        final netAmount =
                            double.tryParse(
                              order['net_amount']?.toString() ?? '',
                            ) ??
                            0;
                        final saleDate = order['sale_date'] != null
                            ? DateFormat('dd MMM yyyy')
                                  .format(DateTime.parse(order['sale_date']))
                            : '';
                        final fulfillment =
                            order['fulfillment_status']?.toString() ??
                            'Pending';
                        Color badgeBg;
                        Color badgeFg;
                        switch (fulfillment) {
                          case 'Fully Fulfilled':
                            badgeBg = const Color(0xFFE6F9F0);
                            badgeFg = const Color(0xFF2FBF71);
                            break;
                          case 'Partially Fulfilled':
                            badgeBg = const Color(0xFFFFF4E0);
                            badgeFg = const Color(0xFFB8860B);
                            break;
                          default:
                            badgeBg = const Color(0xFFEFF2F7);
                            badgeFg = const Color(0xFF6B7280);
                        }
                        return Card(
                          margin: const EdgeInsets.only(bottom: 8),
                          child: Padding(
                            padding: const EdgeInsets.fromLTRB(16, 12, 8, 4),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Row(
                                  children: [
                                    Expanded(
                                      child: Text(
                                        billNo,
                                        style: const TextStyle(
                                          fontWeight: FontWeight.w600,
                                          fontSize: 15,
                                        ),
                                      ),
                                    ),
                                    Container(
                                      padding: const EdgeInsets.symmetric(
                                        horizontal: 8,
                                        vertical: 2,
                                      ),
                                      decoration: BoxDecoration(
                                        color: badgeBg,
                                        borderRadius: BorderRadius.circular(6),
                                      ),
                                      child: Text(
                                        fulfillment,
                                        style: TextStyle(
                                          fontSize: 10,
                                          color: badgeFg,
                                        ),
                                      ),
                                    ),
                                  ],
                                ),
                                const SizedBox(height: 2),
                                Text(
                                  '${order['customer_name'] ?? 'Unknown customer'} · $saleDate · ${order['item_count'] ?? 0} item(s)',
                                  style: const TextStyle(
                                    fontSize: 12,
                                    color: Color(0xFF6B7280),
                                  ),
                                ),
                                Row(
                                  children: [
                                    Expanded(
                                      child: Text(
                                        '${order['currency_symbol'] ?? ''} ${netAmount.toStringAsFixed(2)}',
                                        style: const TextStyle(
                                          fontWeight: FontWeight.w600,
                                        ),
                                      ),
                                    ),
                                    IconButton(
                                      icon: const Icon(
                                        Icons.edit_outlined,
                                        size: 19,
                                      ),
                                      tooltip: 'Edit Order',
                                      onPressed: () => _editOrder(orderId),
                                    ),
                                    IconButton(
                                      icon: const Icon(
                                        Icons.print_outlined,
                                        size: 19,
                                      ),
                                      tooltip: 'Print Order',
                                      onPressed: () =>
                                          _showPrintOptions(orderId),
                                    ),
                                    IconButton(
                                      icon: const Icon(
                                        Icons.delete_outline,
                                        size: 19,
                                        color: Color(0xFFE34F4F),
                                      ),
                                      tooltip: 'Delete Order',
                                      onPressed: () =>
                                          _deleteOrder(orderId, billNo),
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
            onPressed: _page < _totalPages
                ? () => _load(page: _page + 1)
                : null,
            child: const Text('Next'),
          ),
        ],
      ),
    );
  }
}

/// Shows orders still waiting to reach the server — created offline, or
/// whose live send failed — with their status and a manual retry. Hidden
/// entirely when the outbox is empty, which is the common case.
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
    // changeTick, not pendingCount — see its doc comment: a 'syncing' entry
    // being removed after a successful sync doesn't change the aggregate
    // count (0 -> 0), so pendingCount alone would leave this banner stuck
    // showing the just-synced entry as still "syncing…".
    OutboxService.instance.changeTick.addListener(_refresh);
    _refresh();
  }

  @override
  void dispose() {
    OutboxService.instance.changeTick.removeListener(_refresh);
    super.dispose();
  }

  Future<void> _refresh() async {
    // The outbox is shared across features (sale orders, sale returns, …) —
    // filter to this screen's own kind so "3 waiting" here can't actually
    // mean 2 returns + 1 order.
    final entries = (await OutboxService.instance.getAll())
        .where((e) => e.endpoint.contains('sale_order'))
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
              const Icon(
                Icons.sync_problem,
                size: 18,
                color: Color(0xFFB8860B),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  '${_entries.length} order(s) waiting to sync',
                  style: const TextStyle(
                    fontWeight: FontWeight.w600,
                    color: Color(0xFFB8860B),
                  ),
                ),
              ),
              TextButton(
                onPressed: _syncing ? null : _syncNow,
                child: _syncing
                    ? const SizedBox(
                        width: 16,
                        height: 16,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
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
