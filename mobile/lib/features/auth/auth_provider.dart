import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'dart:io' show Platform;

import 'package:flutter/foundation.dart' show kIsWeb;

import '../../core/device_id.dart';
import '../../core/location_tracking_service.dart';
import '../../core/secure_storage.dart';
import 'auth_api.dart';

class AuthState {
  final bool isLoading;
  final bool isLoggedIn;
  final bool isResuming;
  final String? error;
  final Map<String, dynamic>? user;
  final List<dynamic> companies;

  const AuthState({
    this.isLoading = false,
    this.isLoggedIn = false,
    this.isResuming = true,
    this.error,
    this.user,
    this.companies = const [],
  });

  AuthState copyWith({
    bool? isLoading,
    bool? isLoggedIn,
    bool? isResuming,
    String? error,
    Map<String, dynamic>? user,
    List<dynamic>? companies,
  }) {
    return AuthState(
      isLoading: isLoading ?? this.isLoading,
      isLoggedIn: isLoggedIn ?? this.isLoggedIn,
      isResuming: isResuming ?? this.isResuming,
      error: error,
      user: user ?? this.user,
      companies: companies ?? this.companies,
    );
  }
}

class AuthNotifier extends StateNotifier<AuthState> {
  AuthNotifier() : super(const AuthState()) {
    _resume();
  }

  /// Runs once on app start. If a token is already stored, tries to
  /// re-hydrate the session from it so the user lands on Home, not Login —
  /// a stale/expired/offline token just falls through to the login screen
  /// as before, it doesn't block the app.
  Future<void> _resume() async {
    final token = await SecureStorage.getToken();
    if (token == null || token.isEmpty) {
      state = state.copyWith(isResuming: false);
      return;
    }
    try {
      final result = await AuthApi.me();
      if (result['success'] == true) {
        state = state.copyWith(
          isResuming: false,
          isLoggedIn: true,
          user: Map<String, dynamic>.from(result['user'] as Map),
          companies: List<dynamic>.from(result['companies'] as List? ?? []),
        );
        return;
      }
    } catch (_) {
      // Offline at launch — keep the stored token and let the user straight
      // through; API calls will retry once connectivity returns rather than
      // forcing a login the device has no way to complete right now.
      state = state.copyWith(isResuming: false, isLoggedIn: true);
      return;
    }
    await SecureStorage.clearToken();
    state = state.copyWith(isResuming: false);
  }

  String _platformName() {
    if (kIsWeb) return 'android'; // dev-in-Chrome stands in for a phone build
    try {
      return Platform.isIOS ? 'ios' : 'android';
    } catch (_) {
      return 'android';
    }
  }

  Future<void> login(String email, String password) async {
    state = state.copyWith(isLoading: true, error: null);
    try {
      final deviceUuid = await DeviceId.get();
      final result = await AuthApi.login(
        email: email,
        password: password,
        deviceUuid: deviceUuid,
        deviceName: kIsWeb ? 'Chrome (dev)' : 'Mobile Device',
        platform: _platformName(),
      );

      if (result['success'] == true) {
        await SecureStorage.saveToken(result['token'] as String);
        state = state.copyWith(
          isLoading: false,
          isLoggedIn: true,
          user: Map<String, dynamic>.from(result['user'] as Map),
          companies: List<dynamic>.from(result['companies'] as List? ?? []),
          error: null,
        );
      } else {
        state = state.copyWith(
          isLoading: false,
          error: result['message']?.toString() ?? 'Login failed',
        );
      }
    } catch (e) {
      state = state.copyWith(isLoading: false, error: 'Network error: $e');
    }
  }

  Future<void> logout() async {
    LocationTrackingService.instance.stop();
    try {
      await AuthApi.logout();
    } catch (_) {
      // Even if the network call fails, clear local state so the user isn't stuck.
    }
    await SecureStorage.clearToken();
    // Not const AuthState() — that defaults isResuming to true (correct for
    // app startup, where _resume() is about to run), but nothing re-triggers
    // _resume() after logout, so that default left the UI stuck on the
    // _RootGate spinner forever. Logout always resolves straight to Login.
    state = const AuthState(isResuming: false);
  }
}

final authProvider = StateNotifierProvider<AuthNotifier, AuthState>(
  (ref) => AuthNotifier(),
);
