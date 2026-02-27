import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final dioProvider = Provider<Dio>((ref) {
  const apiBaseUrl = String.fromEnvironment('API_BASE_URL');

  if (apiBaseUrl.isEmpty) {
    throw StateError('API_BASE_URL muss via --dart-define gesetzt werden.');
  }

  final dio = Dio(BaseOptions(baseUrl: apiBaseUrl));
  if (!kReleaseMode) {
    dio.interceptors.add(LogInterceptor(requestBody: true, responseBody: true));
  }
  return dio;
});
