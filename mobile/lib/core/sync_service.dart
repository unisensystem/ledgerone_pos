import 'dart:async';
import 'dart:convert';
import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'api_client.dart';
import 'outbox_service.dart';

// Reserved payload keys a form-data entry can carry to attach a file picked
// while offline — the file's bytes can't travel through SharedPreferences
// as a real MultipartFile, so they're base64-encoded into the JSON-safe
// outbox payload and reconstructed here at replay time. Any screen building
// an isFormData OutboxEntry with a file attachment uses these two keys.
const String kOutboxAttachmentBase64Key = '__attachment_base64';
const String kOutboxAttachmentFileNameKey = '__attachment_filename';

/// Walks the offline outbox and replays each entry against the real backend
/// once connectivity is available — same endpoints, same business logic as
/// an online create, just queued and retried instead of sent immediately.
/// Each payload carries its own `clientUuid`, so a retried POST that
/// actually reached the server before (e.g. the response was lost, not the
/// request) lands on the idempotency check server-side and comes back as
/// `already_synced` instead of creating a duplicate.
class SyncService {
  SyncService._();
  static final SyncService instance = SyncService._();

  bool _syncing = false;
  StreamSubscription<List<ConnectivityResult>>? _connectivitySub;

  final ValueNotifier<String?> lastSyncMessage = ValueNotifier<String?>(null);

  void start() {
    _connectivitySub ??= Connectivity().onConnectivityChanged.listen((results) {
      final online = results.any((r) => r != ConnectivityResult.none);
      if (online) syncNow();
    });
    syncNow();
  }

  void dispose() {
    _connectivitySub?.cancel();
    _connectivitySub = null;
  }

  Future<bool> isOnline() async {
    final results = await Connectivity().checkConnectivity();
    return results.any((r) => r != ConnectivityResult.none);
  }

  Future<void> syncNow() async {
    if (_syncing) return;
    _syncing = true;
    try {
      final entries = await OutboxService.instance.getAll();
      final pending = entries.where((e) => e.status != 'syncing').toList();
      if (pending.isEmpty) return;

      var succeeded = 0;
      var failed = 0;
      for (final entry in pending) {
        await OutboxService.instance.updateStatus(entry.clientUuid, status: 'syncing');
        try {
          dynamic body = entry.payload;
          if (entry.isFormData) {
            final fields = Map<String, dynamic>.from(entry.payload)
              ..remove(kOutboxAttachmentBase64Key)
              ..remove(kOutboxAttachmentFileNameKey);
            final formData = FormData.fromMap(fields);
            final base64Bytes = entry.payload[kOutboxAttachmentBase64Key] as String?;
            final fileName = entry.payload[kOutboxAttachmentFileNameKey] as String?;
            if (base64Bytes != null && fileName != null) {
              formData.files.add(MapEntry(
                'attachment',
                MultipartFile.fromBytes(base64Decode(base64Bytes), filename: fileName),
              ));
            }
            body = formData;
          }
          final res = entry.method == 'PUT'
              ? await ApiClient.dio.put(entry.endpoint, data: body)
              : await ApiClient.dio.post(entry.endpoint, data: body);
          final data = res.data as Map;
          if (data['success'] == true) {
            await OutboxService.instance.remove(entry.clientUuid);
            succeeded++;
          } else {
            await OutboxService.instance.updateStatus(
              entry.clientUuid,
              status: 'failed',
              // Most endpoints use 'message'; a few older ones (Receive
              // Voucher among them) use 'error' instead.
              lastError: data['message']?.toString() ?? data['error']?.toString() ?? 'Server rejected the record',
            );
            failed++;
          }
        } catch (e) {
          await OutboxService.instance.updateStatus(entry.clientUuid, status: 'pending', lastError: e.toString());
          // Network still down (or flaky) — stop this pass, the connectivity
          // listener or next manual Sync Now will pick up where we left off.
          break;
        }
      }
      if (succeeded > 0 || failed > 0) {
        final parts = <String>[];
        if (succeeded > 0) parts.add('$succeeded synced');
        if (failed > 0) parts.add('$failed failed');
        lastSyncMessage.value = parts.join(', ');
      }
    } finally {
      _syncing = false;
    }
  }
}
