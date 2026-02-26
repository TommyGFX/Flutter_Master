import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/dio_client.dart';
import '../../auth/auth_controller.dart';

final billingFlowRepositoryProvider = Provider<BillingFlowRepository>((ref) {
  final dio = ref.watch(dioProvider);
  return ApiBillingFlowRepository(dio: dio, ref: ref);
});

final billingFlowControllerProvider =
    NotifierProvider.autoDispose<BillingFlowController, BillingFlowState>(
  BillingFlowController.new,
);

class BillingFlowState {
  static const _unchanged = Object();

  const BillingFlowState({
    this.isRunning = false,
    this.error,
    this.quoteId,
    this.invoiceId,
    this.documentStatus,
    this.historyEntries = 0,
    this.pdfFilename,
    this.steps = const [],
  });

  final bool isRunning;
  final String? error;
  final int? quoteId;
  final int? invoiceId;
  final String? documentStatus;
  final int historyEntries;
  final String? pdfFilename;
  final List<String> steps;

  BillingFlowState copyWith({
    bool? isRunning,
    Object? error = _unchanged,
    int? quoteId,
    int? invoiceId,
    String? documentStatus,
    int? historyEntries,
    String? pdfFilename,
    List<String>? steps,
  }) {
    return BillingFlowState(
      isRunning: isRunning ?? this.isRunning,
      error: identical(error, _unchanged) ? this.error : error as String?,
      quoteId: quoteId ?? this.quoteId,
      invoiceId: invoiceId ?? this.invoiceId,
      documentStatus: documentStatus ?? this.documentStatus,
      historyEntries: historyEntries ?? this.historyEntries,
      pdfFilename: pdfFilename ?? this.pdfFilename,
      steps: steps ?? this.steps,
    );
  }
}

class BillingFlowController extends Notifier<BillingFlowState> {
  static const _flowCurrencyCode = 'USD';
  static const _flowExchangeRate = 1.08;

  @override
  BillingFlowState build() => const BillingFlowState();

  Future<void> runQuoteToPaidFlow() async {
    if (state.isRunning) {
      return;
    }

    final repository = ref.read(billingFlowRepositoryProvider);

    state = state.copyWith(
      isRunning: true,
      error: null,
      quoteId: null,
      invoiceId: null,
      documentStatus: null,
      historyEntries: 0,
      pdfFilename: null,
      steps: ['Flow gestartet'],
    );

    try {
      final customerId = await _runStep(
        action: repository.ensureCustomer,
        errorContext: 'Kunde konnte nicht vorbereitet werden',
      );
      _appendStep('Kunde bereit: #$customerId');

      final quoteId = await _runStep(
        action: () => repository.createQuote(
          customerId: customerId,
          currencyCode: _flowCurrencyCode,
          exchangeRate: _flowExchangeRate,
        ),
        errorContext: 'Angebot konnte nicht erstellt werden',
      );
      _appendStep('Angebot erstellt: #$quoteId');

      await _runStep(
        action: () => repository.finalizeDocument(quoteId),
        errorContext: 'Angebot konnte nicht finalisiert werden',
      );
      final quoteSnapshot = await _runStep(
        action: () => repository.fetchDocumentSnapshot(quoteId),
        errorContext: 'Angebot konnte nach Finalisierung nicht geprüft werden',
      );
      _assertDocumentChecks(
        snapshot: quoteSnapshot,
        context: 'Angebot',
      );
      _appendStep(
        'Angebot finalisiert (Nummer: ${quoteSnapshot.documentNumber}, Währung: ${quoteSnapshot.currencyCode}/${quoteSnapshot.exchangeRate.toStringAsFixed(4)})',
      );

      final invoiceId = await _runStep(
        action: () => repository.convertQuoteToInvoice(quoteId),
        errorContext: 'Angebot konnte nicht in Rechnung konvertiert werden',
      );
      _appendStep('Angebot in Rechnung konvertiert: #$invoiceId');

      final invoiceStatus = await _runStep(
        action: () => repository.finalizeDocument(invoiceId),
        errorContext: 'Rechnung konnte nicht finalisiert werden',
      );
      final invoiceSnapshot = await _runStep(
        action: () => repository.fetchDocumentSnapshot(invoiceId),
        errorContext: 'Rechnung konnte nach Finalisierung nicht geprüft werden',
      );
      _assertDocumentChecks(
        snapshot: invoiceSnapshot,
        context: 'Rechnung',
      );
      _appendStep(
        'Rechnung finalisiert (Status: $invoiceStatus, Nummer: ${invoiceSnapshot.documentNumber}, Währung: ${invoiceSnapshot.currencyCode}/${invoiceSnapshot.exchangeRate.toStringAsFixed(4)})',
      );

      await _runStep(
        action: () => repository.createPaymentLink(invoiceId),
        errorContext: 'Zahlungslink konnte nicht erstellt werden',
      );
      _appendStep('Zahlungslink erstellt');

      final paidStatus = await _runStep(
        action: () => repository.recordPayment(invoiceId),
        errorContext: 'Zahlung konnte nicht verbucht werden',
      );
      _appendStep('Zahlung verbucht (Status: $paidStatus)');

      final historyEntries = await _runStep(
        action: () => repository.fetchHistoryEntries(invoiceId),
        errorContext: 'Dokumenthistorie konnte nicht geladen werden',
      );
      final pdfFilename = await _runStep(
        action: () => repository.exportPdf(invoiceId),
        errorContext: 'PDF-Export konnte nicht geladen werden',
      );

      state = state.copyWith(
        isRunning: false,
        quoteId: quoteId,
        invoiceId: invoiceId,
        documentStatus: paidStatus,
        historyEntries: historyEntries,
        pdfFilename: pdfFilename,
      );
    } on BillingFlowException catch (error) {
      state = state.copyWith(isRunning: false, error: error.message);
      _appendStep('Fehler: ${error.message}');
    } catch (error) {
      final fallbackMessage = 'Unerwarteter Fehler im Billing-Flow: $error';
      state = state.copyWith(isRunning: false, error: fallbackMessage);
      _appendStep('Fehler: $fallbackMessage');
    }
  }

  Future<T> _runStep<T>({required Future<T> Function() action, required String errorContext}) async {
    try {
      return await action();
    } on DioException catch (error) {
      throw BillingFlowException(_buildDioErrorMessage(errorContext, error));
    } catch (error) {
      throw BillingFlowException('$errorContext: $error');
    }
  }

  String _buildDioErrorMessage(String context, DioException error) {
    final statusCode = error.response?.statusCode;
    final response = error.response?.data;
    final responseMessage = switch (response) {
      {'error': final Object value} => value.toString(),
      {'message': final Object value} => value.toString(),
      _ => null,
    };

    return [
      context,
      if (statusCode != null) '(HTTP $statusCode)',
      if (responseMessage != null && responseMessage.isNotEmpty) ': $responseMessage',
    ].join(' ');
  }

  void _appendStep(String message) {
    state = state.copyWith(steps: [...state.steps, message]);
  }

  void _assertDocumentChecks({required BillingDocumentSnapshot snapshot, required String context}) {
    if ((snapshot.documentNumber ?? '').trim().isEmpty) {
      throw BillingFlowException('$context-Nummernkreis nicht vergeben');
    }
    if (snapshot.currencyCode != _flowCurrencyCode) {
      throw BillingFlowException('$context-Währung nicht fixiert: erwartet $_flowCurrencyCode, erhalten ${snapshot.currencyCode}');
    }
    if ((snapshot.exchangeRate - _flowExchangeRate).abs() > 0.0001) {
      throw BillingFlowException(
        '$context-Wechselkurs nicht fixiert: erwartet $_flowExchangeRate, erhalten ${snapshot.exchangeRate}',
      );
    }
  }
}

class BillingFlowException implements Exception {
  const BillingFlowException(this.message);

  final String message;

  @override
  String toString() => message;
}

abstract class BillingFlowRepository {
  Future<int> ensureCustomer();
  Future<int> createQuote({
    required int customerId,
    required String currencyCode,
    required double exchangeRate,
  });
  Future<String> finalizeDocument(int documentId);
  Future<BillingDocumentSnapshot> fetchDocumentSnapshot(int documentId);
  Future<int> convertQuoteToInvoice(int quoteId);
  Future<void> createPaymentLink(int invoiceId);
  Future<String> recordPayment(int invoiceId);
  Future<int> fetchHistoryEntries(int documentId);
  Future<String?> exportPdf(int documentId);
}

class ApiBillingFlowRepository implements BillingFlowRepository {
  ApiBillingFlowRepository({required Dio dio, required Ref ref})
      : _dio = dio,
        _ref = ref;

  final Dio _dio;
  final Ref _ref;

  Options _options() {
    final auth = _ref.read(authControllerProvider);
    return Options(headers: {
      'X-Tenant-Id': auth.tenantId ?? 'tenant_1',
      'X-Permissions': auth.permissions.join(','),
      if ((auth.userId ?? '').isNotEmpty) 'X-User-Id': auth.userId!,
      if ((auth.token ?? '').isNotEmpty) 'Authorization': 'Bearer ${auth.token}',
    });
  }

  @override
  Future<int> ensureCustomer() async {
    final listResponse = await _dio.get('/billing/customers', options: _options());
    final customers = (listResponse.data['data'] as List<dynamic>? ?? const []);
    if (customers.isNotEmpty) {
      return (customers.first as Map<String, dynamic>)['id'] as int;
    }

    final createResponse = await _dio.post(
      '/billing/customers',
      options: _options(),
      data: {
        'customer_type': 'company',
        'company_name': 'Roadmap Testkunde',
        'currency_code': 'EUR',
        'addresses': [
          {
            'address_type': 'billing',
            'street': 'Pluginstraße',
            'house_number': '1',
            'postal_code': '10115',
            'city': 'Berlin',
            'country': 'DE',
            'is_default': true,
          }
        ],
      },
    );

    return (createResponse.data['customer_id'] as num).toInt();
  }

  @override
  Future<int> createQuote({
    required int customerId,
    required String currencyCode,
    required double exchangeRate,
  }) async {
    final response = await _dio.post(
      '/billing/documents',
      options: _options(),
      data: {
        'document_type': 'quote',
        'customer_id': customerId,
        'currency_code': currencyCode,
        'exchange_rate': exchangeRate,
        'line_items': [
          {
            'position': 1,
            'name': 'SaaS Starter Plan',
            'quantity': 1,
            'unit_price': 99,
            'tax_rate': 19,
          }
        ],
        'due_date': DateTime.now().add(const Duration(days: 14)).toIso8601String(),
      },
    );

    return (response.data['document_id'] as num).toInt();
  }

  @override
  Future<String> finalizeDocument(int documentId) async {
    final response = await _dio.post('/billing/documents/$documentId/finalize', options: _options());
    return response.data['status']?.toString() ?? 'finalized';
  }

  @override
  Future<BillingDocumentSnapshot> fetchDocumentSnapshot(int documentId) async {
    final response = await _dio.get('/billing/documents/$documentId', options: _options());
    final data = response.data['data'] as Map<String, dynamic>? ?? const {};
    return BillingDocumentSnapshot.fromApiData(data);
  }

  @override
  Future<int> convertQuoteToInvoice(int quoteId) async {
    final response = await _dio.post('/billing/documents/$quoteId/convert-to-invoice', options: _options());
    return (response.data['document_id'] as num).toInt();
  }

  @override
  Future<void> createPaymentLink(int invoiceId) async {
    await _dio.post(
      '/billing/documents/$invoiceId/payment-links',
      options: _options(),
      data: {
        'provider': 'stripe',
        'payment_link_id': 'plink_$invoiceId',
        'url': 'https://pay.ordentis.de/$invoiceId',
        'status': 'open',
      },
    );
  }

  @override
  Future<String> recordPayment(int invoiceId) async {
    final response = await _dio.post(
      '/billing/documents/$invoiceId/payments',
      options: _options(),
      data: {
        'provider': 'manual',
        'status': 'received',
        'amount_paid': 117.81,
      },
    );
    return response.data['data']?['status']?.toString() ?? response.data['status']?.toString() ?? 'paid';
  }

  @override
  Future<int> fetchHistoryEntries(int documentId) async {
    final response = await _dio.get('/billing/documents/$documentId/history', options: _options());
    final entries = response.data['data'] as List<dynamic>? ?? const [];
    return entries.length;
  }

  @override
  Future<String?> exportPdf(int documentId) async {
    final response = await _dio.get('/billing/documents/$documentId/pdf', options: _options());
    return response.data['filename']?.toString();
  }
}

class BillingDocumentSnapshot {
  const BillingDocumentSnapshot({
    required this.documentNumber,
    required this.currencyCode,
    required this.exchangeRate,
  });

  factory BillingDocumentSnapshot.fromApiData(Map<String, dynamic> data) {
    return BillingDocumentSnapshot(
      documentNumber: data['document_number']?.toString(),
      currencyCode: data['currency_code']?.toString() ?? 'EUR',
      exchangeRate: _parseExchangeRate(data['exchange_rate']),
    );
  }

  final String? documentNumber;
  final String currencyCode;
  final double exchangeRate;

  static double _parseExchangeRate(Object? rawValue) {
    if (rawValue is num) {
      return rawValue.toDouble();
    }
    if (rawValue is String) {
      final parsed = double.tryParse(rawValue);
      if (parsed != null) {
        return parsed;
      }
    }

    throw FormatException('Invalid exchange_rate value: $rawValue');
  }
}
