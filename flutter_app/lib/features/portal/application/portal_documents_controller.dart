import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/dio_client.dart';
import '../../auth/auth_controller.dart';

final portalDocumentsRepositoryProvider = Provider<PortalDocumentsRepository>((ref) {
  final dio = ref.watch(dioProvider);
  return ApiPortalDocumentsRepository(dio: dio, ref: ref);
});

final portalDocumentsControllerProvider =
    NotifierProvider.autoDispose<PortalDocumentsController, PortalDocumentsState>(
  PortalDocumentsController.new,
);

class PortalDocumentsState {
  const PortalDocumentsState({
    this.isLoading = false,
    this.error,
    this.documents = const [],
    this.selectedDocument,
  });

  final bool isLoading;
  final String? error;
  final List<PortalDocumentListItem> documents;
  final PortalDocumentDetail? selectedDocument;

  PortalDocumentsState copyWith({
    bool? isLoading,
    String? error,
    bool clearError = false,
    List<PortalDocumentListItem>? documents,
    PortalDocumentDetail? selectedDocument,
  }) {
    return PortalDocumentsState(
      isLoading: isLoading ?? this.isLoading,
      error: clearError ? null : error ?? this.error,
      documents: documents ?? this.documents,
      selectedDocument: selectedDocument ?? this.selectedDocument,
    );
  }
}

class PortalDocumentsController extends Notifier<PortalDocumentsState> {
  @override
  PortalDocumentsState build() => const PortalDocumentsState();

  Future<void> loadDocuments() async {
    final repository = ref.read(portalDocumentsRepositoryProvider);

    state = state.copyWith(isLoading: true, clearError: true);

    try {
      final docs = await repository.listDocuments();
      state = state.copyWith(isLoading: false, documents: docs, selectedDocument: null);
      if (docs.isNotEmpty) {
        await loadDocument(docs.first.id);
      }
    } on DioException catch (error) {
      state = state.copyWith(isLoading: false, error: _dioMessage('Portal-Dokumente konnten nicht geladen werden', error));
    }
  }

  Future<void> loadDocument(int id) async {
    final repository = ref.read(portalDocumentsRepositoryProvider);

    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final document = await repository.loadDocument(id);
      state = state.copyWith(isLoading: false, selectedDocument: document);
    } on DioException catch (error) {
      state = state.copyWith(isLoading: false, error: _dioMessage('Dokumentdetails konnten nicht geladen werden', error));
    }
  }

  String _dioMessage(String prefix, DioException error) {
    final code = error.response?.statusCode;
    final responseMessage = error.response?.data is Map<String, dynamic>
        ? ((error.response!.data as Map<String, dynamic>)['error']?.toString())
        : null;

    return [
      prefix,
      if (code != null) '(HTTP $code)',
      if (responseMessage != null && responseMessage.isNotEmpty) ': $responseMessage',
    ].join(' ');
  }
}

class PortalDocumentListItem {
  const PortalDocumentListItem({
    required this.id,
    required this.documentNo,
    required this.status,
    required this.totalGross,
    required this.currencyCode,
    required this.dueDate,
  });

  final int id;
  final String documentNo;
  final String status;
  final double totalGross;
  final String currencyCode;
  final String? dueDate;
}

class PortalDocumentDetail {
  const PortalDocumentDetail({
    required this.id,
    required this.documentNo,
    required this.status,
    required this.customerName,
    required this.currencyCode,
    required this.gross,
    required this.net,
    required this.tax,
    required this.discount,
    required this.shipping,
    required this.paymentOptions,
  });

  final int id;
  final String documentNo;
  final String status;
  final String customerName;
  final String currencyCode;
  final double gross;
  final double net;
  final double tax;
  final double discount;
  final double shipping;
  final List<PortalPaymentOption> paymentOptions;
}

class PortalPaymentOption {
  const PortalPaymentOption({
    required this.provider,
    required this.status,
    required this.amount,
    required this.currencyCode,
    required this.paymentUrl,
  });

  final String provider;
  final String status;
  final double amount;
  final String currencyCode;
  final String? paymentUrl;
}

abstract class PortalDocumentsRepository {
  Future<List<PortalDocumentListItem>> listDocuments();
  Future<PortalDocumentDetail> loadDocument(int id);
}

class ApiPortalDocumentsRepository implements PortalDocumentsRepository {
  ApiPortalDocumentsRepository({required Dio dio, required Ref ref})
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
  Future<List<PortalDocumentListItem>> listDocuments() async {
    final response = await _dio.get('/portal/documents', options: _options());
    final raw = response.data['data'] as List<dynamic>? ?? const [];

    return raw
        .whereType<Map<String, dynamic>>()
        .map(
          (item) => PortalDocumentListItem(
            id: (item['id'] as num).toInt(),
            documentNo: item['document_no']?.toString() ?? '#${item['id']}',
            status: item['status']?.toString() ?? 'draft',
            totalGross: (item['total_gross'] as num?)?.toDouble() ?? 0,
            currencyCode: item['currency_code']?.toString() ?? 'EUR',
            dueDate: item['due_date']?.toString(),
          ),
        )
        .toList(growable: false);
  }

  @override
  Future<PortalDocumentDetail> loadDocument(int id) async {
    final response = await _dio.get('/portal/documents/$id', options: _options());
    final item = response.data['data'] as Map<String, dynamic>;
    final totals = item['totals'] as Map<String, dynamic>? ?? const {};
    final paymentRaw = item['payment_options'] as List<dynamic>? ?? const [];

    return PortalDocumentDetail(
      id: (item['id'] as num).toInt(),
      documentNo: item['document_no']?.toString() ?? '#${item['id']}',
      status: item['status']?.toString() ?? 'draft',
      customerName: item['customer_name']?.toString() ?? '-',
      currencyCode: item['currency_code']?.toString() ?? 'EUR',
      gross: (totals['gross'] as num?)?.toDouble() ?? 0,
      net: (totals['net'] as num?)?.toDouble() ?? 0,
      tax: (totals['tax'] as num?)?.toDouble() ?? 0,
      discount: (totals['discount'] as num?)?.toDouble() ?? 0,
      shipping: (totals['shipping'] as num?)?.toDouble() ?? 0,
      paymentOptions: paymentRaw
          .whereType<Map<String, dynamic>>()
          .map(
            (payment) => PortalPaymentOption(
              provider: payment['provider']?.toString() ?? 'manual',
              status: payment['status']?.toString() ?? 'created',
              amount: (payment['amount'] as num?)?.toDouble() ?? 0,
              currencyCode: payment['currency_code']?.toString() ?? 'EUR',
              paymentUrl: payment['payment_url']?.toString(),
            ),
          )
          .toList(growable: false),
    );
  }
}
