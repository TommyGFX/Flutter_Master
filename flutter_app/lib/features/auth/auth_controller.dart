import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/models/auth_state.dart';
import '../../core/network/dio_client.dart';

final authControllerProvider = NotifierProvider<AuthController, AuthState>(AuthController.new);

class AuthController extends Notifier<AuthState> {
  @override
  AuthState build() => const AuthState();

  Future<void> login({
    required String endpoint,
    required String email,
    required String password,
    required String tenant,
  }) async {
    final dio = ref.read(dioProvider);
    final response = await dio.post('/$endpoint', data: {
      'email': email,
      'password': password,
      'tenant_id': tenant,
    });

    state = state.copyWith(
      token: response.data['token'] as String?,
      userId: response.data['user_id']?.toString(),
      entrypoint: response.data['entrypoint'] as String? ?? 'admin',
      tenantId: response.data['tenant_id'] as String? ?? tenant,
      permissions: (response.data['permissions'] as List<dynamic>? ?? const [])
          .map((permission) => permission.toString())
          .toList(growable: false),
    );
  }
}
