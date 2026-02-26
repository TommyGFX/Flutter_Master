import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_master_app/features/billing/application/billing_flow_controller.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('BillingFlowController runs Angebot -> bezahlt sequence', () async {
    final container = ProviderContainer(
      overrides: [
        billingFlowRepositoryProvider.overrideWithValue(_FakeBillingFlowRepository()),
      ],
    );
    addTearDown(container.dispose);

    final controller = container.read(billingFlowControllerProvider.notifier);
    await controller.runQuoteToPaidFlow();

    final state = container.read(billingFlowControllerProvider);
    expect(state.error, isNull);
    expect(state.quoteId, 101);
    expect(state.invoiceId, 202);
    expect(state.documentStatus, 'paid');
    expect(state.historyEntries, 5);
    expect(state.pdfFilename, 'billing-document-202.pdf');
    expect(state.steps.length, greaterThanOrEqualTo(9));
  });

  test('BillingFlowController validates Nummernkreis/Mehrwährung in E2E flow', () async {
    final repository = _FakeBillingFlowRepository();
    final container = ProviderContainer(
      overrides: [
        billingFlowRepositoryProvider.overrideWithValue(repository),
      ],
    );
    addTearDown(container.dispose);

    await container.read(billingFlowControllerProvider.notifier).runQuoteToPaidFlow();

    final state = container.read(billingFlowControllerProvider);
    expect(state.error, isNull);
    expect(repository.quoteCurrencyCode, 'USD');
    expect(repository.quoteExchangeRate, 1.08);
    expect(state.steps.any((step) => step.contains('Nummer: Q-2026-0001')), isTrue);
    expect(state.steps.any((step) => step.contains('Nummer: R-2026-0001')), isTrue);
    expect(state.steps.any((step) => step.contains('Währung: USD/1.0800')), isTrue);
  });

  test('BillingFlowController reports contextual error for API failures', () async {
    final container = ProviderContainer(
      overrides: [
        billingFlowRepositoryProvider.overrideWithValue(_FailingBillingFlowRepository()),
      ],
    );
    addTearDown(container.dispose);

    final controller = container.read(billingFlowControllerProvider.notifier);
    await controller.runQuoteToPaidFlow();

    final state = container.read(billingFlowControllerProvider);
    expect(state.isRunning, isFalse);
    expect(state.error, contains('Angebot konnte nicht erstellt werden'));
    expect(state.error, contains('HTTP 422'));
    expect(state.steps.last, startsWith('Fehler:'));
  });

  test('BillingDocumentSnapshot parses exchange_rate from string payloads', () {
    final snapshot = BillingDocumentSnapshot.fromApiData({
      'document_number': 'Q-2026-0099',
      'currency_code': 'USD',
      'exchange_rate': '1.080000',
    });

    expect(snapshot.documentNumber, 'Q-2026-0099');
    expect(snapshot.currencyCode, 'USD');
    expect(snapshot.exchangeRate, 1.08);
  });


  test('BillingDocumentSnapshot throws on invalid exchange_rate payloads', () {
    expect(
      () => BillingDocumentSnapshot.fromApiData({
        'document_number': 'Q-2026-0100',
        'currency_code': 'USD',
        'exchange_rate': 'not-a-number',
      }),
      throwsA(isA<FormatException>()),
    );
  });
}

class _FakeBillingFlowRepository implements BillingFlowRepository {
  String? quoteCurrencyCode;
  double? quoteExchangeRate;

  @override
  Future<int> ensureCustomer() async => 1;

  @override
  Future<int> createQuote({
    required int customerId,
    required String currencyCode,
    required double exchangeRate,
  }) async {
    quoteCurrencyCode = currencyCode;
    quoteExchangeRate = exchangeRate;
    return 101;
  }

  @override
  Future<String> finalizeDocument(int documentId) async => 'sent';

  @override
  Future<BillingDocumentSnapshot> fetchDocumentSnapshot(int documentId) async {
    if (documentId == 101) {
      return const BillingDocumentSnapshot(
        documentNumber: 'Q-2026-0001',
        currencyCode: 'USD',
        exchangeRate: 1.08,
      );
    }

    return const BillingDocumentSnapshot(
      documentNumber: 'R-2026-0001',
      currencyCode: 'USD',
      exchangeRate: 1.08,
    );
  }

  @override
  Future<int> convertQuoteToInvoice(int quoteId) async => 202;

  @override
  Future<void> createPaymentLink(int invoiceId) async {}

  @override
  Future<String> recordPayment(int invoiceId) async => 'paid';

  @override
  Future<int> fetchHistoryEntries(int documentId) async => 5;

  @override
  Future<String?> exportPdf(int documentId) async => 'billing-document-202.pdf';
}

class _FailingBillingFlowRepository extends _FakeBillingFlowRepository {
  @override
  Future<int> createQuote({
    required int customerId,
    required String currencyCode,
    required double exchangeRate,
  }) async {
    throw DioException(
      requestOptions: RequestOptions(path: '/billing/documents'),
      response: Response(
        requestOptions: RequestOptions(path: '/billing/documents'),
        statusCode: 422,
        data: {'error': 'validation failed'},
      ),
      type: DioExceptionType.badResponse,
    );
  }
}
