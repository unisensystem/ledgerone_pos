import 'package:uuid/uuid.dart';

import 'secure_storage.dart';

/// A UUID generated once per install and reused forever after (persisted in
/// secure storage), so this device always re-registers as the SAME
/// mobile_devices row on every login instead of piling up a new row each time.
class DeviceId {
  static Future<String> get() async {
    final existing = await SecureStorage.getDeviceId();
    if (existing != null && existing.isNotEmpty) {
      return existing;
    }
    final fresh = const Uuid().v4();
    await SecureStorage.saveDeviceId(fresh);
    return fresh;
  }
}
