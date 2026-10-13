import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

/// Caches the last successful response of a read-only fetch (company list,
/// customer list, a voucher's own data for Edit, a list screen's first
/// page, etc.) so a screen that's been opened at least once while online
/// can still render — with stale-but-usable data — when the device later
/// has no connectivity at all.
///
/// Without this, every screen re-fetches its dropdowns/lists from scratch
/// on every open, so a booker who's offline from a cold start (hasn't just
/// come from a live session) sees "Could not load data" instead of the app
/// opening at all — defeating the actual point of an offline-capable app.
///
/// This is strictly about READ data. It has nothing to do with
/// OutboxService/SyncService, which separately queue what the app itself
/// creates or edits while offline.
class ReferenceCache {
  static const _prefix = 'ref_cache_';

  /// Runs [fetcher]; on success, caches and returns the fresh result. On
  /// failure (typically: no network), falls back to the last cached value
  /// for [key] if one exists, so the caller still gets something to render
  /// instead of an error screen. Rethrows the original error only when
  /// there's truly no cache yet (this exact screen/key has never
  /// succeeded online on this device).
  static Future<T> cached<T>(String key, Future<T> Function() fetcher) async {
    try {
      final fresh = await fetcher();
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString('$_prefix$key', jsonEncode(fresh));
      return fresh;
    } catch (e) {
      final prefs = await SharedPreferences.getInstance();
      final raw = prefs.getString('$_prefix$key');
      if (raw != null) {
        return jsonDecode(raw) as T;
      }
      rethrow;
    }
  }
}
