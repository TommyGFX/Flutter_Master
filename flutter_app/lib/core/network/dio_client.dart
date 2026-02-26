import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

const _defaultApiBaseUrl = 'https://api.ordentis.de/api';

final dioProvider = Provider<Dio>((ref) {
  const apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: _defaultApiBaseUrl,
  );

  final dio = Dio(BaseOptions(baseUrl: apiBaseUrl));
  dio.interceptors.add(LogInterceptor(requestBody: true, responseBody: true));
  return dio;
});
