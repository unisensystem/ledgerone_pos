import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:geolocator/geolocator.dart';

import 'api_client.dart';

/// Pings this device's GPS location to the server every [_pingInterval]
/// while — and only while — the app is open and in the foreground. Powers
/// the web Admin Panel's Live Tracking map.
///
/// Deliberately foreground-only, per office direction: no background
/// location permission is ever requested, so there's no battery drain or
/// privacy concern once the officer closes or minimizes the app — tracking
/// simply stops the moment they do, same as any other screen's data
/// stopping when the app isn't open. [pause]/[resume] are driven by the
/// app's lifecycle state (see main.dart's _RootGateState), not by this
/// class watching lifecycle itself, so there's one obvious place that owns
/// "is the app in the foreground right now".
///
/// Two independent switches, both enforced server-side (see ping.php) so a
/// stale/tampered client can never force a ping through:
/// - adminEnabled: set by the office from the web Admin Panel.
/// - officerEnabled: the officer's own "pause for personal time" switch.
/// Either one being off stops pings; admin always wins (the officer's own
/// switch is locked in the UI when admin has turned tracking off for them).
/// [state] is a ValueNotifier so the Home screen can show the right banner
/// reactively without this service needing to know about any widget.
class LocationTrackingService {
  LocationTrackingService._();
  static final instance = LocationTrackingService._();

  static const _pingInterval = Duration(seconds: 30);
  static const _settingsRefreshEvery = 5; // every 5th ping (~2.5 min)
  static const String _pingEndpoint = '/server/api/mobile/location/ping.php';
  static const String _settingsEndpoint =
      '/server/api/mobile/location/tracking-settings.php';

  Timer? _timer;
  bool _started = false;
  int _tickCount = 0;

  bool _adminEnabled = true;
  bool _officerEnabled = true;

  /// 'active' | 'paused_by_officer' | 'disabled_by_admin' — what the Home
  /// screen banner (if any) should reflect right now.
  final ValueNotifier<String> state = ValueNotifier('active');

  bool get isLockedByAdmin => !_adminEnabled;

  Future<void> start() async {
    if (_started) return;
    _started = true;
    await _refreshSettings();
    await _ensurePermission();
    _tick();
    _timer = Timer.periodic(_pingInterval, (_) => _tick());
  }

  void pause() {
    _timer?.cancel();
    _timer = null;
  }

  void resume() {
    if (!_started || _timer != null) return;
    _tick();
    _timer = Timer.periodic(_pingInterval, (_) => _tick());
  }

  void stop() {
    _timer?.cancel();
    _timer = null;
    _started = false;
    _tickCount = 0;
    state.value = 'active';
  }

  /// Officer's own switch from the Home screen. No-op (silently reverts)
  /// if admin has disabled tracking — the UI should already disable the
  /// control in that case, but this guards the server call either way.
  Future<void> setOfficerEnabled(bool enabled) async {
    if (!_adminEnabled) return;
    try {
      await ApiClient.dio.post(_settingsEndpoint, data: {'enabled': enabled});
      _officerEnabled = enabled;
      _updateState();
    } catch (e) {
      debugPrint('LocationTrackingService setOfficerEnabled failed: $e');
    }
  }

  Future<void> _tick() async {
    _tickCount++;
    if (_tickCount % _settingsRefreshEvery == 1) {
      await _refreshSettings();
    }
    await _sendPing();
  }

  Future<void> _refreshSettings() async {
    try {
      final res = await ApiClient.dio.get(_settingsEndpoint);
      final data = res.data as Map;
      if (data['success'] == true) {
        _adminEnabled = data['admin_enabled'] == true;
        _officerEnabled = data['officer_enabled'] == true;
        _updateState();
      }
    } catch (e) {
      debugPrint('LocationTrackingService settings refresh failed: $e');
    }
  }

  void _updateState() {
    if (!_adminEnabled) {
      state.value = 'disabled_by_admin';
    } else if (!_officerEnabled) {
      state.value = 'paused_by_officer';
    } else {
      state.value = 'active';
    }
  }

  Future<void> _ensurePermission() async {
    try {
      final serviceEnabled = await Geolocator.isLocationServiceEnabled();
      if (!serviceEnabled) return;

      var permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
      }
    } catch (e) {
      debugPrint('LocationTrackingService permission check failed: $e');
    }
  }

  Future<void> _sendPing() async {
    if (!_adminEnabled || !_officerEnabled) return;

    try {
      final serviceEnabled = await Geolocator.isLocationServiceEnabled();
      if (!serviceEnabled) {
        await _reportStatus('gps_off');
        return;
      }

      final permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied ||
          permission == LocationPermission.deniedForever) {
        await _reportStatus('permission_denied');
        return;
      }

      final position = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.medium,
        ),
      );

      await ApiClient.dio.post(
        _pingEndpoint,
        data: {
          'latitude': position.latitude,
          'longitude': position.longitude,
          'accuracy': position.accuracy,
        },
      );
    } catch (e) {
      // Best-effort — a missed ping just means a slightly stale dot on the
      // web map, never something the officer sees or needs to act on.
      debugPrint('LocationTrackingService ping failed: $e');
    }
  }

  Future<void> _reportStatus(String status) async {
    try {
      await ApiClient.dio.post(_pingEndpoint, data: {'status': status});
    } catch (e) {
      debugPrint('LocationTrackingService status report failed: $e');
    }
  }
}
