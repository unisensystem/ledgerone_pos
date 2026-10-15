import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'core/location_tracking_service.dart';
import 'core/sync_service.dart';
import 'features/auth/auth_provider.dart';
import 'features/auth/login_screen.dart';
import 'features/home/home_screen.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  // Warm the singleton before any screen can fire several concurrent
  // SharedPreferences.getInstance() calls at once (e.g. a reference-data
  // Future.wait batch on a brand new origin/device) — shared_preferences_web
  // isn't guaranteed safe against a first-ever concurrent-init race, and an
  // unwarmed instance here silently drops the write rather than throwing.
  await SharedPreferences.getInstance();
  runApp(const ProviderScope(child: LedgerOneMobileApp()));
}

class LedgerOneMobileApp extends StatelessWidget {
  const LedgerOneMobileApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'LedgerOne ERP',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        colorSchemeSeed: const Color(0xFF1F7BFF),
        useMaterial3: true,
        scaffoldBackgroundColor: const Color(0xFFF7F9FC),
      ),
      home: const _RootGate(),
    );
  }
}

/// Decides Login vs Home based on whether a stored token could be resumed
/// (see AuthNotifier._resume), and starts the background sync engine once
/// there's a session to sync on behalf of.
class _RootGate extends ConsumerStatefulWidget {
  const _RootGate();

  @override
  ConsumerState<_RootGate> createState() => _RootGateState();
}

class _RootGateState extends ConsumerState<_RootGate>
    with WidgetsBindingObserver {
  bool _syncStarted = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    // Live Tracking is foreground-only by design (see
    // LocationTrackingService's doc comment) — pause/resume its ping timer
    // in lockstep with the app actually being visible to the officer.
    if (state == AppLifecycleState.resumed) {
      LocationTrackingService.instance.resume();
    } else if (state == AppLifecycleState.paused ||
        state == AppLifecycleState.inactive) {
      LocationTrackingService.instance.pause();
    }
  }

  @override
  Widget build(BuildContext context) {
    final auth = ref.watch(authProvider);

    if (auth.isLoggedIn && !_syncStarted) {
      _syncStarted = true;
      SyncService.instance.start();
      LocationTrackingService.instance.start();
    }

    if (auth.isResuming) {
      return const Scaffold(
        backgroundColor: Color(0xFFF7F9FC),
        body: Center(child: CircularProgressIndicator()),
      );
    }
    return auth.isLoggedIn ? const HomeScreen() : const LoginScreen();
  }
}
