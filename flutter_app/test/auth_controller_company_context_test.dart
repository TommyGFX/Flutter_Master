import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_master_app/features/auth/auth_controller.dart';

void main() {
  test('AuthController applies company context and permissions', () {
    final container = ProviderContainer();
    addTearDown(container.dispose);

    final controller = container.read(authControllerProvider.notifier);
    controller.applyCompanyContext(companyId: 'company_b', permissions: const ['org.read', 'billing.read']);

    final state = container.read(authControllerProvider);
    expect(state.companyId, 'company_b');
    expect(state.permissions, const ['org.read', 'billing.read']);
  });
}
