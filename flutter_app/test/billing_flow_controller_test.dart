import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_master_app/features/billing/application/billing_flow_controller.dart';

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
    expect(state.steps.length, greaterThanOrEqualTo(7));
  });
}

class _FakeBillingFlowRepository implements BillingFlowRepository {
  @override
  Future<int> ensureCustomer() async => 1;

  @override
  Future<int> createQuote({required int customerId}) async => 101;

  @override
  Future<String> finalizeDocument(int documentId) async => 'sent';

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
