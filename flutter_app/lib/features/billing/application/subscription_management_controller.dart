import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/dio_client.dart';
import '../../auth/auth_controller.dart';

final subscriptionManagementControllerProvider = NotifierProvider.autoDispose<
    SubscriptionManagementController, SubscriptionManagementState>(
  SubscriptionManagementController.new,
);

class SubscriptionManagementState {
  const SubscriptionManagementState({
    this.isLoading = false,
    this.error,
    this.plans = const [],
    this.contracts = const [],
    this.lastRunSummary,
    this.lastPaymentMethodLink,
  });

  final bool isLoading;
  final String? error;
  final List<Map<String, dynamic>> plans;
  final List<Map<String, dynamic>> contracts;
  final String? lastRunSummary;
  final String? lastPaymentMethodLink;

  SubscriptionManagementState copyWith({
    bool? isLoading,
    String? error,
    bool clearError = false,
    List<Map<String, dynamic>>? plans,
    List<Map<String, dynamic>>? contracts,
    String? lastRunSummary,
    bool clearRunSummary = false,
    String? lastPaymentMethodLink,
  }) {
    return SubscriptionManagementState(
      isLoading: isLoading ?? this.isLoading,
      error: clearError ? null : (error ?? this.error),
      plans: plans ?? this.plans,
      contracts: contracts ?? this.contracts,
      lastRunSummary: clearRunSummary ? null : (lastRunSummary ?? this.lastRunSummary),
      lastPaymentMethodLink: lastPaymentMethodLink ?? this.lastPaymentMethodLink,
    );
  }
}

class SubscriptionManagementController extends Notifier<SubscriptionManagementState> {
  @override
  SubscriptionManagementState build() => const SubscriptionManagementState();

  Future<void> loadOverview() async {
    await _run(
      action: () async {
        final plansResponse = await _dio.get('/billing/subscriptions/plans', options: _options());
        final contractsResponse = await _dio.get('/billing/subscriptions/contracts', options: _options());

        final plansData = (plansResponse.data['data'] as List<dynamic>? ?? const [])
            .map((value) => Map<String, dynamic>.from(value as Map))
            .toList(growable: false);
        final contractsData = (contractsResponse.data['data'] as List<dynamic>? ?? const [])
            .map((value) => Map<String, dynamic>.from(value as Map))
            .toList(growable: false);

        state = state.copyWith(
          plans: plansData,
          contracts: contractsData,
          clearError: true,
        );
      },
    );
  }

  Future<void> runRecurringEngine() async {
    await _run(
      action: () async {
        final response = await _dio.post('/billing/subscriptions/run-recurring', options: _options());
        final data = Map<String, dynamic>.from(response.data['data'] as Map? ?? const {});
        state = state.copyWith(lastRunSummary: 'Recurring: ${data['processed'] ?? 0}/${data['due_contracts'] ?? 0} verarbeitet');
        await loadOverview();
      },
    );
  }

  Future<void> runAutoInvoicing() async {
    await _run(
      action: () async {
        final response = await _dio.post('/billing/subscriptions/auto-invoicing/run', options: _options());
        final data = Map<String, dynamic>.from(response.data['data'] as Map? ?? const {});
        state = state.copyWith(lastRunSummary: 'Auto-Invoicing: ${data['queued'] ?? 0}/${data['pending'] ?? 0} in Queue gestellt');
      },
    );
  }

  Future<void> runDunningRetention() async {
    await _run(
      action: () async {
        final response = await _dio.post('/billing/subscriptions/dunning/run', options: _options());
        final data = Map<String, dynamic>.from(response.data['data'] as Map? ?? const {});
        state = state.copyWith(lastRunSummary: 'Dunning: geprüft ${data['checked'] ?? 0}, retried ${data['retried'] ?? 0}, bezahlt ${data['resolved'] ?? 0}');
      },
    );
  }

  Future<void> createPaymentMethodUpdateLink({required int contractId, required String provider}) async {
    await _run(
      action: () async {
        final response = await _dio.post(
          '/billing/subscriptions/contracts/$contractId/payment-method-update-link',
          data: {'provider': provider},
          options: _options(),
        );
        final data = Map<String, dynamic>.from(response.data['data'] as Map? ?? const {});
        state = state.copyWith(lastPaymentMethodLink: data['update_url']?.toString());
      },
    );
  }

  Future<void> _run({required Future<void> Function() action}) async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      await action();
    } on DioException catch (error) {
      state = state.copyWith(error: error.response?.data.toString() ?? error.message);
    } catch (error) {
      state = state.copyWith(error: error.toString());
    } finally {
      state = state.copyWith(isLoading: false);
    }
  }

  Dio get _dio => ref.read(dioProvider);

  Options _options() {
    final authState = ref.read(authControllerProvider);
    return Options(
      headers: {
        'X-Tenant-Id': authState.tenantId ?? 'tenant_1',
        if ((authState.token ?? '').isNotEmpty) 'Authorization': 'Bearer ${authState.token}',
      },
    );
  }
}
