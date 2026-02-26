import 'package:flutter/material.dart';
import 'package:flutter_master_app/features/billing/presentation/billing_flow_screen.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('BillingFlowScreen matches golden in initial state', (tester) async {
    await tester.binding.setSurfaceSize(const Size(1100, 800));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(
      const ProviderScope(
        child: MaterialApp(
          home: Scaffold(body: BillingFlowScreen()),
        ),
      ),
    );

    await tester.pumpAndSettle();

    await expectLater(
      find.byType(BillingFlowScreen),
      matchesGoldenFile('goldens/billing_flow_screen_initial.png'),
    );
  });
}
