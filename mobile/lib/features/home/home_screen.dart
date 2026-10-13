import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/outbox_service.dart';
import '../../core/sync_everything_service.dart';
import '../../core/sync_service.dart';
import '../auth/auth_provider.dart';
import '../customer_ledger/customer_ledger_screen.dart';
import '../receive_voucher/receive_voucher_list_screen.dart';
import '../sale_order/add_customer_screen.dart';
import '../sale_order/sale_order_list_screen.dart';
import '../sale_return/sale_return_list_screen.dart';

const _primaryBlue = Color(0xFF1F7BFF);
const _primaryBlueDark = Color(0xFF155FD6);
const _bgColor = Color(0xFFF5F7FA);
const _textDark = Color(0xFF0E1A2B);
const _textMuted = Color(0xFF6B7280);

String _displayName(Map<String, dynamic>? user) {
  final fullName = user?['full_name']?.toString().trim() ?? '';
  if (fullName.isNotEmpty) return fullName;
  final email = user?['email']?.toString().trim() ?? '';
  if (email.contains('@')) return email.split('@').first;
  return 'there';
}

String _initials(String name) {
  final parts = name
      .trim()
      .split(RegExp(r'\s+'))
      .where((p) => p.isNotEmpty)
      .toList();
  if (parts.isEmpty) return '?';
  if (parts.length == 1) return parts.first.substring(0, 1).toUpperCase();
  return (parts.first.substring(0, 1) + parts.last.substring(0, 1))
      .toUpperCase();
}

String _greeting() {
  final hour = DateTime.now().hour;
  if (hour < 12) return 'Good morning';
  if (hour < 17) return 'Good afternoon';
  return 'Good evening';
}

class HomeScreen extends ConsumerWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final auth = ref.watch(authProvider);
    final name = _displayName(auth.user);

    return Scaffold(
      backgroundColor: _bgColor,
      body: SafeArea(
        child: ListView(
          padding: EdgeInsets.zero,
          children: [
            _Header(
              name: name,
              onLogout: () => ref.read(authProvider.notifier).logout(),
            ),
            const SizedBox(height: 18),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: const [
                  _SyncSummaryCard(),
                  SizedBox(height: 12),
                  _SyncEverythingCard(),
                  SizedBox(height: 22),
                  Text(
                    'Quick Actions',
                    style: TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w700,
                      color: _textDark,
                    ),
                  ),
                  SizedBox(height: 12),
                  _FilteredOutboxActionCard(
                    endpointFilter: 'sale_order',
                    icon: Icons.list_alt_rounded,
                    accent: _primaryBlue,
                    accentBg: Color(0xFFE8F1FF),
                    title: 'Sale Orders',
                    idleSubtitle: 'View orders or create a new one',
                    pendingSubtitle: _pendingLabel,
                    screen: SaleOrderListScreen(),
                  ),
                  SizedBox(height: 12),
                  _FilteredOutboxActionCard(
                    endpointFilter: 'sale_return',
                    icon: Icons.assignment_return_rounded,
                    accent: Color(0xFFD97706),
                    accentBg: Color(0xFFFFF3E0),
                    title: 'Sale Returns',
                    idleSubtitle: 'View returns or create a new one',
                    pendingSubtitle: _pendingLabel,
                    screen: SaleReturnListScreen(),
                  ),
                  SizedBox(height: 12),
                  _FilteredOutboxActionCard(
                    endpointFilter: 'receive_voucher',
                    icon: Icons.payments_rounded,
                    accent: Color(0xFF15966B),
                    accentBg: Color(0xFFE3F6EE),
                    title: 'Receive Vouchers',
                    idleSubtitle: 'Record a customer payment',
                    pendingSubtitle: _pendingLabel,
                    screen: ReceiveVoucherListScreen(),
                  ),
                  SizedBox(height: 12),
                  _AddCustomerActionCard(),
                  SizedBox(height: 12),
                  _CustomerLedgerActionCard(),
                  SizedBox(height: 28),
                ],
              ),
            ),
            const Padding(
              padding: EdgeInsets.only(bottom: 20),
              child: Center(
                child: Text(
                  'Works offline — changes sync automatically',
                  style: TextStyle(fontSize: 11.5, color: _textMuted),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

String _pendingLabel(int n) => '$n waiting to sync';

class _Header extends StatelessWidget {
  final String name;
  final VoidCallback onLogout;
  const _Header({required this.name, required this.onLogout});

  @override
  Widget build(BuildContext context) {
    final today = DateTime.now();
    const months = [
      'January',
      'February',
      'March',
      'April',
      'May',
      'June',
      'July',
      'August',
      'September',
      'October',
      'November',
      'December',
    ];
    final dateLabel = '${months[today.month - 1]} ${today.day}, ${today.year}';

    return Container(
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          colors: [_primaryBlue, _primaryBlueDark],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.only(
          bottomLeft: Radius.circular(28),
          bottomRight: Radius.circular(28),
        ),
      ),
      padding: const EdgeInsets.fromLTRB(20, 16, 20, 24),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Expanded(
                child: Text(
                  'LedgerOne ERP',
                  style: TextStyle(
                    color: Colors.white,
                    fontSize: 15,
                    fontWeight: FontWeight.w600,
                    letterSpacing: 0.2,
                  ),
                ),
              ),
              IconButton(
                icon: const Icon(
                  Icons.logout_rounded,
                  color: Colors.white,
                  size: 21,
                ),
                tooltip: 'Logout',
                onPressed: onLogout,
              ),
            ],
          ),
          const SizedBox(height: 14),
          Row(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              CircleAvatar(
                radius: 26,
                backgroundColor: Colors.white.withValues(alpha: 0.18),
                child: Text(
                  _initials(name),
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 18,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      '${_greeting()}, $name',
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 20,
                        fontWeight: FontWeight.bold,
                      ),
                      overflow: TextOverflow.ellipsis,
                    ),
                    const SizedBox(height: 2),
                    Text(
                      dateLabel,
                      style: TextStyle(
                        color: Colors.white.withValues(alpha: 0.85),
                        fontSize: 12.5,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

/// Aggregates the shared outbox across every feature so the dashboard can
/// show one "N items waiting to sync" summary instead of making the booker
/// open each list screen to find out. Pulls in the same changeTick pattern
/// each feature's own card already uses, so it stays live without polling.
class _SyncSummaryCard extends StatefulWidget {
  const _SyncSummaryCard();

  @override
  State<_SyncSummaryCard> createState() => _SyncSummaryCardState();
}

class _SyncSummaryCardState extends State<_SyncSummaryCard> {
  int _pending = 0;
  int _failed = 0;
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
    final entries = await OutboxService.instance.getAll();
    if (!mounted) return;
    setState(() {
      _pending = entries
          .where((e) => e.status != 'syncing' && e.status != 'failed')
          .length;
      _failed = entries.where((e) => e.status == 'failed').length;
    });
  }

  Future<void> _syncAll() async {
    setState(() => _syncing = true);
    await SyncService.instance.syncNow();
    if (mounted) setState(() => _syncing = false);
  }

  @override
  Widget build(BuildContext context) {
    final total = _pending + _failed;
    final allClear = total == 0;

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.04),
            blurRadius: 10,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: Row(
        children: [
          Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(
              color: allClear
                  ? const Color(0xFFE3F6EE)
                  : const Color(0xFFFFF3E0),
              shape: BoxShape.circle,
            ),
            child: Icon(
              allClear ? Icons.cloud_done_rounded : Icons.cloud_sync_rounded,
              color: allClear
                  ? const Color(0xFF15966B)
                  : const Color(0xFFD97706),
              size: 22,
            ),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  allClear
                      ? 'All caught up'
                      : '$total item${total == 1 ? '' : 's'} waiting to sync',
                  style: const TextStyle(
                    fontWeight: FontWeight.w700,
                    fontSize: 14,
                    color: _textDark,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  allClear
                      ? 'Everything is synced to the server'
                      : _failed > 0
                      ? '$_failed failed — tap Sync to retry'
                      : 'Will sync automatically once online',
                  style: TextStyle(
                    fontSize: 12,
                    color: _failed > 0 ? const Color(0xFFD64545) : _textMuted,
                  ),
                ),
              ],
            ),
          ),
          if (!allClear)
            SizedBox(
              height: 34,
              child: ElevatedButton(
                onPressed: _syncing ? null : _syncAll,
                style: ElevatedButton.styleFrom(
                  backgroundColor: _primaryBlue,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(horizontal: 14),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(8),
                  ),
                ),
                child: _syncing
                    ? const SizedBox(
                        width: 14,
                        height: 14,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: Colors.white,
                        ),
                      )
                    : const Text(
                        'Sync',
                        style: TextStyle(
                          fontSize: 12.5,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
              ),
            ),
        ],
      ),
    );
  }
}

String _timeAgo(DateTime t) {
  final diff = DateTime.now().difference(t);
  if (diff.inMinutes < 1) return 'just now';
  if (diff.inMinutes < 60) return '${diff.inMinutes} min ago';
  if (diff.inHours < 24) return '${diff.inHours} hour${diff.inHours == 1 ? '' : 's'} ago';
  return '${diff.inDays} day${diff.inDays == 1 ? '' : 's'} ago';
}

/// One-tap "refresh everything" — meant to be used once while on office
/// WiFi before heading into the field, so every screen's cached reference
/// data and every customer's Customer Ledger balance is as current as
/// possible. See SyncEverythingService's doc comment for the full
/// reasoning (manager/office requirement: offline Customer Ledger access
/// shouldn't require having opened every single customer online first).
class _SyncEverythingCard extends StatefulWidget {
  const _SyncEverythingCard();

  @override
  State<_SyncEverythingCard> createState() => _SyncEverythingCardState();
}

class _SyncEverythingCardState extends State<_SyncEverythingCard> {
  DateTime? _lastSync;
  bool _running = false;

  @override
  void initState() {
    super.initState();
    _loadLastSync();
  }

  Future<void> _loadLastSync() async {
    final t = await SyncEverythingService.getLastSyncTime();
    if (mounted) setState(() => _lastSync = t);
  }

  Future<void> _start() async {
    final online = await SyncService.instance.isOnline();
    if (!online) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Connect to the internet first, then try again.'),
          ),
        );
      }
      return;
    }
    if (!mounted) return;
    setState(() => _running = true);
    final failed = await showModalBottomSheet<int>(
      context: context,
      isDismissible: false,
      enableDrag: false,
      isScrollControlled: true,
      builder: (_) => const _SyncEverythingSheet(),
    );
    if (!mounted) return;
    setState(() => _running = false);
    await _loadLastSync();
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          failed == null
              ? 'Sync cancelled.'
              : failed > 0
              ? 'Synced — $failed customer balance${failed == 1 ? '' : 's'} could not be refreshed.'
              : 'Everything refreshed.',
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.04),
            blurRadius: 10,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: Row(
        children: [
          Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(
              color: const Color(0xFFE8F1FF),
              shape: BoxShape.circle,
            ),
            child: const Icon(
              Icons.sync_rounded,
              color: _primaryBlue,
              size: 22,
            ),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Sync Everything',
                  style: TextStyle(
                    fontWeight: FontWeight.w700,
                    fontSize: 14,
                    color: _textDark,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  _lastSync == null
                      ? 'Never synced — tap to refresh all data'
                      : 'Last synced: ${_timeAgo(_lastSync!)}',
                  style: const TextStyle(fontSize: 12, color: _textMuted),
                ),
              ],
            ),
          ),
          SizedBox(
            height: 34,
            child: ElevatedButton(
              onPressed: _running ? null : _start,
              style: ElevatedButton.styleFrom(
                backgroundColor: _primaryBlue,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(horizontal: 14),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(8),
                ),
              ),
              child: const Text(
                'Sync Now',
                style: TextStyle(fontSize: 12.5, fontWeight: FontWeight.w600),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Modal content for _SyncEverythingCard's run — a live progress bar plus
/// the current step's label, so a long sync (large tenant, many customers)
/// doesn't look stuck. Pops itself with the failure count once done.
class _SyncEverythingSheet extends StatefulWidget {
  const _SyncEverythingSheet();

  @override
  State<_SyncEverythingSheet> createState() => _SyncEverythingSheetState();
}

class _SyncEverythingSheetState extends State<_SyncEverythingSheet> {
  String _label = 'Starting...';
  int _current = 0;
  int _total = 1;

  @override
  void initState() {
    super.initState();
    _run();
  }

  Future<void> _run() async {
    final failed = await SyncEverythingService.run(
      onProgress: (label, current, total) {
        if (!mounted) return;
        setState(() {
          _label = label;
          _current = current;
          _total = total;
        });
      },
    );
    if (mounted) Navigator.of(context).pop(failed);
  }

  @override
  Widget build(BuildContext context) {
    final progress = _total > 0 ? _current / _total : 0.0;
    return Padding(
      padding: EdgeInsets.fromLTRB(
        24,
        24,
        24,
        24 + MediaQuery.of(context).viewInsets.bottom,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Syncing everything...',
            style: TextStyle(fontWeight: FontWeight.w700, fontSize: 16),
          ),
          const SizedBox(height: 4),
          const Text(
            'This may take a while for a large customer list — it\'s safe to '
            'leave this open.',
            style: TextStyle(fontSize: 12, color: _textMuted),
          ),
          const SizedBox(height: 18),
          ClipRRect(
            borderRadius: BorderRadius.circular(6),
            child: LinearProgressIndicator(
              value: progress.clamp(0.0, 1.0),
              minHeight: 8,
              backgroundColor: const Color(0xFFE8F1FF),
              valueColor: const AlwaysStoppedAnimation(_primaryBlue),
            ),
          ),
          const SizedBox(height: 10),
          Text(
            '$_current / $_total — $_label',
            style: const TextStyle(fontSize: 12.5, color: _textMuted),
            overflow: TextOverflow.ellipsis,
          ),
        ],
      ),
    );
  }
}

/// Same outbox, filtered to one feature's own endpoints — otherwise a
/// pending Sale Return would also bump the Sale Orders card's badge (and
/// vice versa), since OutboxService is one shared queue for every feature.
class _FilteredOutboxActionCard extends StatefulWidget {
  final String endpointFilter;
  final IconData icon;
  final Color accent;
  final Color accentBg;
  final String title;
  final String idleSubtitle;
  final String Function(int) pendingSubtitle;
  final Widget screen;

  const _FilteredOutboxActionCard({
    required this.endpointFilter,
    required this.icon,
    required this.accent,
    required this.accentBg,
    required this.title,
    required this.idleSubtitle,
    required this.pendingSubtitle,
    required this.screen,
  });

  @override
  State<_FilteredOutboxActionCard> createState() =>
      _FilteredOutboxActionCardState();
}

class _FilteredOutboxActionCardState extends State<_FilteredOutboxActionCard> {
  int _pending = 0;

  @override
  void initState() {
    super.initState();
    // changeTick, not pendingCount — see OutboxService.changeTick's doc
    // comment: a 'syncing' entry being removed after a successful sync
    // doesn't change the aggregate count (0 -> 0), so the badge could get
    // stuck showing a stale pending count.
    OutboxService.instance.changeTick.addListener(_refresh);
    _refresh();
  }

  @override
  void dispose() {
    OutboxService.instance.changeTick.removeListener(_refresh);
    super.dispose();
  }

  Future<void> _refresh() async {
    final entries = await OutboxService.instance.getAll();
    final count = entries
        .where(
          (e) =>
              e.endpoint.contains(widget.endpointFilter) &&
              e.status != 'syncing',
        )
        .length;
    if (mounted) setState(() => _pending = count);
  }

  @override
  Widget build(BuildContext context) {
    return _ActionCard(
      icon: widget.icon,
      accent: widget.accent,
      accentBg: widget.accentBg,
      title: widget.title,
      subtitle: _pending > 0
          ? widget.pendingSubtitle(_pending)
          : widget.idleSubtitle,
      badgeCount: _pending,
      onTap: () =>
          Navigator.of(context)
              .push(MaterialPageRoute(builder: (_) => widget.screen)),
    );
  }
}

/// Standalone "Add Customer" entry — no outbox badge (customer creation
/// is never queued offline; see add_customer_screen.dart), just a direct
/// link into the proper create-customer form.
class _AddCustomerActionCard extends StatelessWidget {
  const _AddCustomerActionCard();

  @override
  Widget build(BuildContext context) {
    return _ActionCard(
      icon: Icons.person_add_alt_1_rounded,
      accent: const Color(0xFF7C3AED),
      accentBg: const Color(0xFFF1E8FE),
      title: 'Add Customer',
      subtitle: 'Register a new customer',
      onTap: () => Navigator.of(context).push(
        MaterialPageRoute(builder: (_) => const AddCustomerScreen()),
      ),
    );
  }
}

/// Standalone "Customer Ledger" entry — read-only report, no outbox badge.
class _CustomerLedgerActionCard extends StatelessWidget {
  const _CustomerLedgerActionCard();

  @override
  Widget build(BuildContext context) {
    return _ActionCard(
      icon: Icons.receipt_long_rounded,
      accent: const Color(0xFF0E7490),
      accentBg: const Color(0xFFE0F5F9),
      title: 'Customer Ledger',
      subtitle: 'Check a customer\'s balance & history',
      onTap: () => Navigator.of(context).push(
        MaterialPageRoute(builder: (_) => const CustomerLedgerScreen()),
      ),
    );
  }
}

class _ActionCard extends StatelessWidget {
  final IconData icon;
  final Color accent;
  final Color accentBg;
  final String title;
  final String subtitle;
  final VoidCallback onTap;
  final int badgeCount;

  const _ActionCard({
    required this.icon,
    required this.accent,
    required this.accentBg,
    required this.title,
    required this.subtitle,
    required this.onTap,
    this.badgeCount = 0,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.05),
            blurRadius: 10,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: Material(
        color: Colors.transparent,
        borderRadius: BorderRadius.circular(16),
        child: InkWell(
          borderRadius: BorderRadius.circular(16),
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Row(
              children: [
                Container(
                  width: 46,
                  height: 46,
                  decoration: BoxDecoration(
                    color: accentBg,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Icon(icon, color: accent, size: 23),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        title,
                        style: const TextStyle(
                          fontWeight: FontWeight.w700,
                          fontSize: 15,
                          color: _textDark,
                        ),
                      ),
                      const SizedBox(height: 3),
                      Text(
                        subtitle,
                        style: const TextStyle(
                          color: _textMuted,
                          fontSize: 12.5,
                        ),
                      ),
                    ],
                  ),
                ),
                if (badgeCount > 0)
                  Container(
                    margin: const EdgeInsets.only(right: 6),
                    padding: const EdgeInsets.symmetric(
                      horizontal: 9,
                      vertical: 4,
                    ),
                    decoration: BoxDecoration(
                      color: const Color(0xFFFFF3E0),
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: Text(
                      '$badgeCount',
                      style: const TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w700,
                        color: Color(0xFFD97706),
                      ),
                    ),
                  ),
                const Icon(Icons.chevron_right_rounded, color: _textMuted),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
