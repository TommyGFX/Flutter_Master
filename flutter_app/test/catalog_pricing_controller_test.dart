import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_master_app/features/catalog/application/catalog_pricing_controller.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('CatalogQuoteResult parses quote totals and discount metadata', () {
    final result = CatalogQuoteResult.fromApi({
      'currency_code': 'EUR',
      'discount': {
        'code': 'SPRING25',
        'discount_type': 'percent',
        'discount_value': 25,
        'discount_amount': 50,
        'applies_to': 'one_time',
      },
      'lines': [
        {
          'product_id': 10,
          'name': 'Workshop',
          'quantity': 2,
          'unit_price': 100,
          'line_net': 200,
          'tax_rate': 19,
          'line_tax': 38,
        },
      ],
      'totals': {
        'subtotal_net': 200,
        'discount_total': 50,
        'subtotal_after_discount': 150,
        'tax_total': 28.5,
        'grand_total': 178.5,
      },
    });

    expect(result.currencyCode, 'EUR');
    expect(result.discount?.code, 'SPRING25');
    expect(result.lines.single.productId, 10);
    expect(result.totals.grandTotal, 178.5);
  });

  test('CatalogPricingState copyWith resets quote and error when requested', () {
    const initial = CatalogPricingState(
      error: 'boom',
      quote: CatalogQuoteResult(
        currencyCode: 'EUR',
        lines: [],
        totals: CatalogQuoteTotals(
          subtotalNet: 1,
          discountTotal: 0,
          subtotalAfterDiscount: 1,
          taxTotal: 0.19,
          grandTotal: 1.19,
        ),
        discount: null,
      ),
    );

    final next = initial.copyWith(clearError: true, clearQuote: true);

    expect(next.error, isNull);
    expect(next.quote, isNull);
  });

  test('CatalogPricingController starts with empty state', () {
    final container = ProviderContainer();
    addTearDown(container.dispose);

    final state = container.read(catalogPricingControllerProvider);
    expect(state.products, isEmpty);
    expect(state.priceLists, isEmpty);
    expect(state.discountCodes, isEmpty);
    expect(state.quote, isNull);
  });
}
