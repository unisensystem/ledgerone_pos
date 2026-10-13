import '../../core/api_client.dart';

/// Calls server/api/mobile/auth/*.php directly.
class AuthApi {
  static Future<Map<String, dynamic>> login({
    required String email,
    required String password,
    required String deviceUuid,
    required String deviceName,
    required String platform,
  }) async {
    final res = await ApiClient.dio.post(
      '/server/api/mobile/auth/login.php',
      data: {
        'email': email,
        'password': password,
        'device_uuid': deviceUuid,
        'device_name': deviceName,
        'platform': platform,
      },
    );
    return Map<String, dynamic>.from(res.data as Map);
  }

  static Future<void> logout() async {
    await ApiClient.dio.post('/server/api/mobile/auth/logout.php');
  }

  /// Resumes a session from an already-stored token (see mobile_auth.php's
  /// sliding expiry) so app relaunch doesn't force a fresh login.
  static Future<Map<String, dynamic>> me() async {
    final res = await ApiClient.dio.get('/server/api/mobile/auth/me.php');
    return Map<String, dynamic>.from(res.data as Map);
  }
}
