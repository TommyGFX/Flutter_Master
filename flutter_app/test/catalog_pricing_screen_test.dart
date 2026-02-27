import 'package:flutter/material.dart';
import 'package:flutter_master_app/features/catalog/application/catalog_pricing_controller.dart';
import 'package:flutter_master_app/features/catalog/presentation/catalog_pricing_screen.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  final fixtureState = CatalogPricingState(
    products: const [
      CatalogProduct(
        id: 101,
        sku: 'SKU-SVC-ONBOARD',
        name: 'Onboarding Workshop',
        type: 'service',
        unitPrice: 249,
        taxRate: 19,
        isActive: true,
      ),
      CatalogProduct(
        id: 102,
        sku: 'SKU-SUB-PRO',
        name: 'Pro Subscription',
        type: 'service',
        unitPrice: 89,
        taxRate: 19,
        isActive: true,
      ),
      CatalogProduct(
        id: 103,
        sku: 'SKU-ADD-SEATS',
        name: 'Seat Add-on',
        type: 'product',
        unitPrice: 15,
        taxRate: 19,
        isActive: true,
      ),
      CatalogProduct(
        id: 104,
        sku: 'SKU-LEGACY',
        name: 'Legacy Migration Package',
        type: 'service',
        unitPrice: 1299,
        taxRate: 19,
        isActive: false,
      ),
    ],
    priceLists: const [
      CatalogPriceList(
        id: 201,
        name: 'B2B Enterprise 2026',
        currencyCode: 'EUR',
        isActive: true,
      ),
      CatalogPriceList(
        id: 202,
        name: 'Partner Legacy',
        currencyCode: 'EUR',
        isActive: false,
      ),
    ],
    discountCodes: const [
      CatalogDiscountCode(
        code: 'Q4-B2B-15',
        discountType: 'percent',
        discountValue: 15,
        appliesTo: 'one_time',
        isActive: true,
      ),
      CatalogDiscountCode(
        code: 'SUB-50-3M',
        discountType: 'fixed',
        discountValue: 50,
        appliesTo: 'subscription',
        isActive: true,
      ),
    ],
    quote: const CatalogQuoteResult(
      currencyCode: 'EUR',
      discount: CatalogAppliedDiscount(
        code: 'Q4-B2B-15',
        discountType: 'percent',
        discountValue: 15,
        discountAmount: 37.35,
        appliesTo: 'one_time',
      ),
      lines: [
        CatalogQuoteLine(
          productId: 101,
          name: 'Onboarding Workshop',
          quantity: 1,
          unitPrice: 249,
          lineNet: 249,
          taxRate: 19,
          lineTax: 47.31,
        ),
      ],
      totals: CatalogQuoteTotals(
        subtotalNet: 249,
        discountTotal: 37.35,
        subtotalAfterDiscount: 211.65,
        taxTotal: 40.21,
        grandTotal: 251.86,
      ),
    ),
  );

  testWidgets(
    'CatalogPricingScreen matches golden with production-like Phase-9 fixture',
    (tester) async {
      await tester.binding.setSurfaceSize(const Size(1500, 900));
      addTearDown(() => tester.binding.setSurfaceSize(null));

      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            catalogPricingControllerProvider.overrideWith(
              () => _FakeCatalogPricingController(fixtureState),
            ),
          ],
          child: const MaterialApp(home: Scaffold(body: CatalogPricingScreen())),
        ),
      );

      await tester.pumpAndSettle();

      await expectLater(
        find.byType(CatalogPricingScreen),
        matchesGoldenFile('goldens/catalog_pricing_screen_phase9_fixture.png'),
      );
    },
    skip:
        'Golden-Baseline muss in CI/Dev mit installiertem Flutter-SDK erzeugt werden.',
  );

  testWidgets(
    'CatalogPricingScreen renders fixture-backed catalog and quote totals',
    (tester) async {
      await tester.pumpWidget(
        ProviderScope(
          overrides: [
            catalogPricingControllerProvider.overrideWith(
              () => _FakeCatalogPricingController(fixtureState),
            ),
          ],
          child: const MaterialApp(
            home: Scaffold(
              body: SizedBox(height: 900, child: CatalogPricingScreen()),
            ),
          ),
        ),
      );

      await tester.pumpAndSettle();

      expect(find.text('Produkte: 4'), findsOneWidget);
      expect(find.textContaining('B2B Enterprise 2026 (EUR)'), findsOneWidget);
      expect(find.text('Rabatt: Q4-B2B-15 · -37.35'), findsOneWidget);
      expect(find.text('Gesamt: 251.86'), findsOneWidget);
      expect(
        find.textContaining('SKU-LEGACY · Legacy Migration Package'),
        findsOneWidget,
      );
    },
  );
}

class _FakeCatalogPricingController extends CatalogPricingController {
  _FakeCatalogPricingController(this._seedState);

  final CatalogPricingState _seedState;

  @override
  CatalogPricingState build() => _seedState;

  @override
  Future<void> loadCatalog() async {}

  @override
  Future<void> createProduct({
    required String sku,
    required String type,
    required String name,
    required String unitPrice,
    required String taxRate,
  }) async {}

  @override
  Future<void> calculateQuote({
    required int? priceListId,
    required String? discountCode,
    required String saleType,
    required List<CatalogQuoteLineInput> lines,
  }) async {}
}
