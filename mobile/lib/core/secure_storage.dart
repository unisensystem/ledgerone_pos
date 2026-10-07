import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Wraps the on-device secure storage for the mobile bearer token and this
/// device's own persistent UUID (sent to login.php so re-logins reuse/
/// refresh the same mobile_devices row instead of registering a new device
/// every time).
class SecureStorage {
  static const _storage = FlutterSecureStorage();
  static const _tokenKey = 'ledgerone_mobile_token';
  static const _deviceIdKey = 'ledgerone_device_uuid';

  static Future<void> saveToken(String token) => _storage.write(key: _tokenKey, value: token);
  static Future<String?> getToken() => _storage.read(key: _tokenKey);
  static Future<void> clearToken() => _storage.delete(key: _tokenKey);

  static Future<String?> getDeviceId() => _storage.read(key: _deviceIdKey);
  static Future<void> saveDeviceId(String id) => _storage.write(key: _deviceIdKey, value: id);
}
