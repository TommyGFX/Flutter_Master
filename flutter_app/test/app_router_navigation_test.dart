import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:flutter_master_app/app/app_router.dart';
import 'package:flutter_master_app/features/auth/presentation/login_screen.dart';

void main() {
  test('AppRouter returns a MaterialPageRoute for /dashboard navigation contract', () {
    final route = AppRouter.onGenerateRoute(const RouteSettings(name: '/dashboard'));

    expect(route, isA<MaterialPageRoute<dynamic>>());
  });

  testWidgets('unknown route falls back to login for navigation safety', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        onGenerateRoute: AppRouter.onGenerateRoute,
        initialRoute: '/unknown',
      ),
    );

    await tester.pumpAndSettle();

    expect(find.byType(LoginScreen), findsOneWidget);
    expect(find.text('Sign in'), findsOneWidget);
  });
}
