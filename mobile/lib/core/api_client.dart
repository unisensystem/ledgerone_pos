import 'package:dio/dio.dart';

import 'api_config.dart';
import 'secure_storage.dart';

/// Single shared Dio instance for every API call in the app. Automatically
/// attaches the mobile bearer token (if one is stored) to every request, so
/// individual screens never have to think about auth headers.
class ApiClient {
  static final Dio dio =
      Dio(
          BaseOptions(
            baseUrl: ApiConfig.baseUrl,
            connectTimeout: const Duration(seconds: 15),
            receiveTimeout: const Duration(seconds: 15),
            validateStatus: (status) => status != null && status < 500,
          ),
        )
        ..interceptors.add(
          InterceptorsWrapper(
            onRequest: (options, handler) async {
              final token = await SecureStorage.getToken();
              if (token != null && token.isNotEmpty) {
                options.headers['Authorization'] = 'Bearer $token';
              }
              handler.next(options);
            },
          ),
        );
}
