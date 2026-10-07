import 'package:url_launcher/url_launcher.dart';
import 'api_client.dart';
import 'api_config.dart';

/// Opens web's own print pages (invoice-print.php / thermal-print.php) in
/// the device browser via server/api/mobile/auth/print-bridge.php, which
/// exchanges a short-lived nonce for a real PHP session so those unmodified
/// web pages just work. Deliberately not a second, native print layout to
/// build and keep in sync with web's.
///
/// Uses a one-time nonce (from print-nonce.php, requested the normal
/// authenticated way via the Authorization header) rather than putting the
/// actual mobile bearer token in the URL — this URL is a plain browser
/// navigation, so the token would otherwise sit in server logs, browser
/// history, and any Referer header the print page's subresources send.
class PrintLauncher {
  static Future<bool> open({required int orderId, required bool thermal}) async {
    String nonce;
    try {
      final res = await ApiClient.dio.post(
        '/server/api/mobile/auth/print-nonce.php',
        data: {'orderId': orderId},
      );
      final data = res.data as Map;
      if (data['success'] != true) return false;
      nonce = data['nonce'] as String;
    } catch (_) {
      return false;
    }

    final type = thermal ? 'thermal' : 'full';
    final url = Uri.parse(
      '${ApiConfig.baseUrl}/server/api/mobile/auth/print-bridge.php?nonce=$nonce&id=$orderId&type=$type',
    );
    return launchUrl(url, mode: LaunchMode.externalApplication);
  }
}
