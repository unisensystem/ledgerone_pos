import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// A single offline-created record waiting to reach the server. [endpoint]
/// and [payload] are exactly what gets POSTed once connectivity returns —
/// the outbox doesn't know or care what kind of record this is, so the same
/// queue can carry sale orders today and other voucher types later.
class OutboxEntry {
  final String clientUuid;
  final String endpoint;
  final String label;
  final Map<String, dynamic> payload;
  final DateTime createdAt;
  // 'POST' (create — order-add.php) or 'PUT' (edit — order-edit.php). Edits
  // are naturally idempotent on retry (an UPDATE replaying the same values,
  // including the delete+reinsert of items, has no extra effect), unlike
  // creates, which is why only creates need the clientUuid dedup dance
  // server-side.
  final String method;
  // Most endpoints (Sale Order, Sale Return) read a JSON body. Receive
  // Voucher's create endpoint reads $_POST (multipart form data — it also
  // accepts a file attachment), so this entry needs to be sent as form data
  // instead of JSON. Defaults to false so every existing JSON-body feature
  // is unaffected.
  final bool isFormData;
  String status; // pending | syncing | failed
  String? lastError;

  OutboxEntry({
    required this.clientUuid,
    required this.endpoint,
    required this.label,
    required this.payload,
    required this.createdAt,
    this.method = 'POST',
    this.isFormData = false,
    this.status = 'pending',
    this.lastError,
  });

  Map<String, dynamic> toJson() => {
    'clientUuid': clientUuid,
    'endpoint': endpoint,
    'label': label,
    'payload': payload,
    'createdAt': createdAt.toIso8601String(),
    'method': method,
    'isFormData': isFormData,
    'status': status,
    'lastError': lastError,
  };

  factory OutboxEntry.fromJson(Map<String, dynamic> json) => OutboxEntry(
    clientUuid: json['clientUuid'] as String,
    endpoint: json['endpoint'] as String,
    label: json['label'] as String,
    payload: Map<String, dynamic>.from(json['payload'] as Map),
    createdAt: DateTime.parse(json['createdAt'] as String),
    method: json['method'] as String? ?? 'POST',
    isFormData: json['isFormData'] as bool? ?? false,
    status: json['status'] as String? ?? 'pending',
    lastError: json['lastError'] as String?,
  );
}

/// Local-first queue for records created while offline (or whose live POST
/// failed). Backed by SharedPreferences as a JSON array — plain and
/// synchronous-feeling enough for the volume a single booker generates in a
/// day, and works identically on web (where this app is being tested via
/// Playwright, since there's no Android/iOS build toolchain on this machine
/// yet) and on a real device, unlike a native-SQLite-only solution.
class OutboxService {
  OutboxService._();
  static final OutboxService instance = OutboxService._();

  static const _key = 'outbox_entries_v1';

  /// Notifies listeners whenever the pending count changes, so UI (e.g. the
  /// Sale Order list's "N pending sync" banner) can update without polling.
  final ValueNotifier<int> pendingCount = ValueNotifier<int>(0);

  /// Bumped on every persist, regardless of whether pendingCount's NUMERIC
  /// value actually changed. pendingCount alone isn't enough: a 'syncing'
  /// entry being removed after a successful sync goes 0 (excluded while
  /// syncing) -> 0 (gone) — the count never changes, so ValueNotifier's
  /// equality-gated setter silently skips notifying listeners, leaving a
  /// banner that reads individual entry status/label stuck showing a
  /// just-synced record as still "syncing…" forever. UI that cares about the
  /// entries themselves (not just the aggregate count) should listen here.
  final ValueNotifier<int> changeTick = ValueNotifier<int>(0);

  List<OutboxEntry> _entries = [];
  bool _loaded = false;

  Future<void> _ensureLoaded() async {
    if (_loaded) return;
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_key);
    if (raw != null && raw.isNotEmpty) {
      final list = jsonDecode(raw) as List;
      _entries = list
          .map((e) => OutboxEntry.fromJson(Map<String, dynamic>.from(e as Map)))
          .toList();
    }
    _loaded = true;
    _refreshCount();
  }

  Future<void> _persist() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(
      _key,
      jsonEncode(_entries.map((e) => e.toJson()).toList()),
    );
    _refreshCount();
  }

  void _refreshCount() {
    pendingCount.value = _entries.where((e) => e.status != 'syncing').length;
    changeTick.value++;
  }

  Future<List<OutboxEntry>> getAll() async {
    await _ensureLoaded();
    return List.unmodifiable(_entries);
  }

  Future<void> add(OutboxEntry entry) async {
    await _ensureLoaded();
    _entries.add(entry);
    await _persist();
  }

  Future<void> remove(String clientUuid) async {
    await _ensureLoaded();
    _entries.removeWhere((e) => e.clientUuid == clientUuid);
    await _persist();
  }

  Future<void> updateStatus(
    String clientUuid, {
    required String status,
    String? lastError,
  }) async {
    await _ensureLoaded();
    final entry = _entries.where((e) => e.clientUuid == clientUuid).firstOrNull;
    if (entry == null) return;
    entry.status = status;
    entry.lastError = lastError;
    await _persist();
  }
}

extension _FirstOrNull<T> on Iterable<T> {
  T? get firstOrNull => isEmpty ? null : first;
}
