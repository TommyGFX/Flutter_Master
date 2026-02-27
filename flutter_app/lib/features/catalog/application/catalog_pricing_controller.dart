import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/dio_client.dart';
import '../../auth/auth_controller.dart';

final catalogPricingControllerProvider =
    NotifierProvider.autoDispose<CatalogPricingController, CatalogPricingState>(CatalogPricingController.new);

class CatalogPricingState {
  const CatalogPricingState({
    this.isLoading = false,
    this.isCalculating = false,
    this.error,
    this.products = const [],
    this.priceLists = const [],
    this.discountCodes = const [],
    this.quote,
  });

  final bool isLoading;
  final bool isCalculating;
  final String? error;
  final List<CatalogProduct> products;
  final List<CatalogPriceList> priceLists;
  final List<CatalogDiscountCode> discountCodes;
  final CatalogQuoteResult? quote;

  CatalogPricingState copyWith({
    bool? isLoading,
    bool? isCalculating,
    String? error,
    bool clearError = false,
    List<CatalogProduct>? products,
    List<CatalogPriceList>? priceLists,
    List<CatalogDiscountCode>? discountCodes,
    CatalogQuoteResult? quote,
    bool clearQuote = false,
  }) {
    return CatalogPricingState(
      isLoading: isLoading ?? this.isLoading,
      isCalculating: isCalculating ?? this.isCalculating,
      error: clearError ? null : (error ?? this.error),
      products: products ?? this.products,
      priceLists: priceLists ?? this.priceLists,
      discountCodes: discountCodes ?? this.discountCodes,
      quote: clearQuote ? null : (quote ?? this.quote),
    );
  }
}

class CatalogPricingController extends Notifier<CatalogPricingState> {
  @override
  CatalogPricingState build() => const CatalogPricingState();

  Future<void> loadCatalog() async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      final responses = await Future.wait([
        _dio.get('/billing/catalog/products', options: _options()),
        _dio.get('/billing/catalog/price-lists', options: _options()),
        _dio.get('/billing/catalog/discount-codes', options: _options()),
      ]);

      state = state.copyWith(
        products: _asList(responses[0].data['data']).map(CatalogProduct.fromApi).toList(growable: false),
        priceLists: _asList(responses[1].data['data']).map(CatalogPriceList.fromApi).toList(growable: false),
        discountCodes: _asList(responses[2].data['data']).map(CatalogDiscountCode.fromApi).toList(growable: false),
      );
    } on DioException catch (error) {
      state = state.copyWith(error: error.response?.data.toString() ?? error.message);
    } catch (error) {
      state = state.copyWith(error: error.toString());
    } finally {
      state = state.copyWith(isLoading: false);
    }
  }

  Future<void> createProduct({
    required String sku,
    required String type,
    required String name,
    required String unitPrice,
    required String taxRate,
  }) async {
    state = state.copyWith(isLoading: true, clearError: true);
    try {
      await _dio.post(
        '/billing/catalog/products',
        data: {
          'sku': sku,
          'type': type,
          'name': name,
          'unit_price': double.parse(unitPrice.replaceAll(',', '.')),
          'tax_rate': double.parse(taxRate.replaceAll(',', '.')),
          'currency_code': 'EUR',
          'is_active': true,
        },
        options: _options(),
      );
      await loadCatalog();
    } on FormatException {
      state = state.copyWith(error: 'Ungültiges Zahlenformat für Preis/Steuersatz.');
    } on DioException catch (error) {
      state = state.copyWith(error: error.response?.data.toString() ?? error.message);
    } finally {
      state = state.copyWith(isLoading: false);
    }
  }

  Future<void> calculateQuote({
    required int? priceListId,
    required String? discountCode,
    required String saleType,
    required List<CatalogQuoteLineInput> lines,
  }) async {
    state = state.copyWith(isCalculating: true, clearError: true);
    try {
      final payload = {
        'currency_code': 'EUR',
        if (priceListId != null) 'price_list_id': priceListId,
        if ((discountCode ?? '').trim().isNotEmpty) 'discount_code': discountCode!.trim(),
        'sale_type': saleType,
        'lines': lines
            .where((line) => line.productId != null && line.quantity > 0)
            .map((line) => {'product_id': line.productId, 'quantity': line.quantity})
            .toList(growable: false),
      };
      final response = await _dio.post('/billing/catalog/quotes/calculate', data: payload, options: _options());
      state = state.copyWith(quote: CatalogQuoteResult.fromApi(response.data['data'] as Map<String, dynamic>));
    } on DioException catch (error) {
      state = state.copyWith(error: error.response?.data.toString() ?? error.message);
    } catch (error) {
      state = state.copyWith(error: error.toString());
    } finally {
      state = state.copyWith(isCalculating: false);
    }
  }

  Dio get _dio => ref.read(dioProvider);

  Options _options() {
    final authState = ref.read(authControllerProvider);
    return Options(headers: {
      'X-Tenant-Id': authState.tenantId ?? 'tenant_1',
      if ((authState.userId ?? '').isNotEmpty) 'X-User-Id': authState.userId!,
      if ((authState.token ?? '').isNotEmpty) 'Authorization': 'Bearer ${authState.token}',
    });
  }

  List<Map<String, dynamic>> _asList(dynamic raw) {
    if (raw is! List) {
      return const [];
    }

    return raw.whereType<Map>().map((item) => Map<String, dynamic>.from(item)).toList(growable: false);
  }
}

class CatalogProduct {
  const CatalogProduct({
    required this.id,
    required this.sku,
    required this.name,
    required this.type,
    required this.unitPrice,
    required this.taxRate,
    required this.isActive,
  });

  factory CatalogProduct.fromApi(Map<String, dynamic> data) {
    return CatalogProduct(
      id: (data['id'] as num?)?.toInt() ?? 0,
      sku: data['sku']?.toString() ?? '-',
      name: data['name']?.toString() ?? '-',
      type: data['type']?.toString() ?? 'service',
      unitPrice: (data['unit_price'] as num?)?.toDouble() ?? 0,
      taxRate: (data['tax_rate'] as num?)?.toDouble() ?? 0,
      isActive: data['is_active'] == true,
    );
  }

  final int id;
  final String sku;
  final String name;
  final String type;
  final double unitPrice;
  final double taxRate;
  final bool isActive;
}

class CatalogPriceList {
  const CatalogPriceList({required this.id, required this.name, required this.currencyCode, required this.isActive});

  factory CatalogPriceList.fromApi(Map<String, dynamic> data) {
    return CatalogPriceList(
      id: (data['id'] as num?)?.toInt() ?? 0,
      name: data['name']?.toString() ?? '-',
      currencyCode: data['currency_code']?.toString() ?? 'EUR',
      isActive: data['is_active'] == true,
    );
  }

  final int id;
  final String name;
  final String currencyCode;
  final bool isActive;
}

class CatalogDiscountCode {
  const CatalogDiscountCode({
    required this.code,
    required this.discountType,
    required this.discountValue,
    required this.appliesTo,
    required this.isActive,
  });

  factory CatalogDiscountCode.fromApi(Map<String, dynamic> data) {
    return CatalogDiscountCode(
      code: data['code']?.toString() ?? '-',
      discountType: data['discount_type']?.toString() ?? 'percent',
      discountValue: (data['discount_value'] as num?)?.toDouble() ?? 0,
      appliesTo: data['applies_to']?.toString() ?? 'one_time',
      isActive: data['is_active'] == true,
    );
  }

  final String code;
  final String discountType;
  final double discountValue;
  final String appliesTo;
  final bool isActive;
}

class CatalogQuoteLineInput {
  const CatalogQuoteLineInput({required this.productId, required this.quantity});

  final int? productId;
  final int quantity;
}

class CatalogQuoteResult {
  const CatalogQuoteResult({required this.currencyCode, required this.lines, required this.totals, required this.discount});

  factory CatalogQuoteResult.fromApi(Map<String, dynamic> data) {
    final totalsMap = Map<String, dynamic>.from(data['totals'] as Map? ?? const {});
    final discountMap = data['discount'] is Map<String, dynamic> ? data['discount'] as Map<String, dynamic> : null;

    return CatalogQuoteResult(
      currencyCode: data['currency_code']?.toString() ?? 'EUR',
      lines: (data['lines'] as List? ?? const [])
          .whereType<Map>()
          .map((line) => Map<String, dynamic>.from(line))
          .map(CatalogQuoteLine.fromApi)
          .toList(growable: false),
      totals: CatalogQuoteTotals.fromApi(totalsMap),
      discount: discountMap == null ? null : CatalogAppliedDiscount.fromApi(discountMap),
    );
  }

  final String currencyCode;
  final List<CatalogQuoteLine> lines;
  final CatalogQuoteTotals totals;
  final CatalogAppliedDiscount? discount;
}

class CatalogQuoteLine {
  const CatalogQuoteLine({
    required this.productId,
    required this.name,
    required this.quantity,
    required this.unitPrice,
    required this.lineNet,
    required this.taxRate,
    required this.lineTax,
  });

  factory CatalogQuoteLine.fromApi(Map<String, dynamic> data) {
    return CatalogQuoteLine(
      productId: (data['product_id'] as num?)?.toInt() ?? 0,
      name: data['name']?.toString() ?? '-',
      quantity: (data['quantity'] as num?)?.toInt() ?? 0,
      unitPrice: (data['unit_price'] as num?)?.toDouble() ?? 0,
      lineNet: (data['line_net'] as num?)?.toDouble() ?? 0,
      taxRate: (data['tax_rate'] as num?)?.toDouble() ?? 0,
      lineTax: (data['line_tax'] as num?)?.toDouble() ?? 0,
    );
  }

  final int productId;
  final String name;
  final int quantity;
  final double unitPrice;
  final double lineNet;
  final double taxRate;
  final double lineTax;
}

class CatalogQuoteTotals {
  const CatalogQuoteTotals({
    required this.subtotalNet,
    required this.discountTotal,
    required this.subtotalAfterDiscount,
    required this.taxTotal,
    required this.grandTotal,
  });

  factory CatalogQuoteTotals.fromApi(Map<String, dynamic> data) {
    return CatalogQuoteTotals(
      subtotalNet: (data['subtotal_net'] as num?)?.toDouble() ?? 0,
      discountTotal: (data['discount_total'] as num?)?.toDouble() ?? 0,
      subtotalAfterDiscount: (data['subtotal_after_discount'] as num?)?.toDouble() ?? 0,
      taxTotal: (data['tax_total'] as num?)?.toDouble() ?? 0,
      grandTotal: (data['grand_total'] as num?)?.toDouble() ?? 0,
    );
  }

  final double subtotalNet;
  final double discountTotal;
  final double subtotalAfterDiscount;
  final double taxTotal;
  final double grandTotal;
}

class CatalogAppliedDiscount {
  const CatalogAppliedDiscount({
    required this.code,
    required this.discountType,
    required this.discountValue,
    required this.discountAmount,
    required this.appliesTo,
  });

  factory CatalogAppliedDiscount.fromApi(Map<String, dynamic> data) {
    return CatalogAppliedDiscount(
      code: data['code']?.toString() ?? '-',
      discountType: data['discount_type']?.toString() ?? 'percent',
      discountValue: (data['discount_value'] as num?)?.toDouble() ?? 0,
      discountAmount: (data['discount_amount'] as num?)?.toDouble() ?? 0,
      appliesTo: data['applies_to']?.toString() ?? 'one_time',
    );
  }

  final String code;
  final String discountType;
  final double discountValue;
  final double discountAmount;
  final String appliesTo;
}
