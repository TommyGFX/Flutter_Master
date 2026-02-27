import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../application/catalog_pricing_controller.dart';

class CatalogPricingScreen extends ConsumerStatefulWidget {
  const CatalogPricingScreen({super.key});

  @override
  ConsumerState<CatalogPricingScreen> createState() => _CatalogPricingScreenState();
}

class _CatalogPricingScreenState extends ConsumerState<CatalogPricingScreen> {
  final _skuController = TextEditingController(text: 'SKU-1000');
  final _nameController = TextEditingController(text: 'Onboarding Workshop');
  final _priceController = TextEditingController(text: '249.00');
  final _taxRateController = TextEditingController(text: '19');
  String _type = 'service';

  final List<_QuoteLineDraft> _lineDrafts = [const _QuoteLineDraft()];
  int? _selectedPriceListId;
  String? _selectedDiscountCode;
  String _saleType = 'one_time';

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(catalogPricingControllerProvider.notifier).loadCatalog();
    });
  }

  @override
  void dispose() {
    _skuController.dispose();
    _nameController.dispose();
    _priceController.dispose();
    _taxRateController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(catalogPricingControllerProvider);
    final controller = ref.read(catalogPricingControllerProvider.notifier);

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    'Produktkatalog + Angebotseditor (Phase 9)',
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                ),
                IconButton(
                  tooltip: 'Katalog neu laden',
                  onPressed: state.isLoading ? null : controller.loadCatalog,
                  icon: const Icon(Icons.refresh),
                ),
              ],
            ),
            const SizedBox(height: 8),
            Text(
              'SKU-/Preislistenlogik mit direkter Angebotspreis-Berechnung für Sales & Billing.',
              style: Theme.of(context).textTheme.bodyMedium,
            ),
            const SizedBox(height: 12),
            if (state.isLoading) const LinearProgressIndicator(),
            if (state.error != null)
              Padding(
                padding: const EdgeInsets.only(top: 8),
                child: Text(state.error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
              ),
            const SizedBox(height: 12),
            Expanded(
              child: Row(
                children: [
                  Expanded(
                    child: _CatalogPanel(
                      state: state,
                      skuController: _skuController,
                      nameController: _nameController,
                      priceController: _priceController,
                      taxRateController: _taxRateController,
                      type: _type,
                      onTypeChanged: (value) => setState(() => _type = value),
                      onCreateProduct: () => controller.createProduct(
                        sku: _skuController.text,
                        type: _type,
                        name: _nameController.text,
                        unitPrice: _priceController.text,
                        taxRate: _taxRateController.text,
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: _QuoteEditorPanel(
                      state: state,
                      lineDrafts: _lineDrafts,
                      selectedPriceListId: _selectedPriceListId,
                      selectedDiscountCode: _selectedDiscountCode,
                      saleType: _saleType,
                      onAddLine: () => setState(() => _lineDrafts.add(const _QuoteLineDraft())),
                      onRemoveLine: (index) {
                        setState(() {
                          if (_lineDrafts.length > 1) {
                            _lineDrafts.removeAt(index);
                          }
                        });
                      },
                      onUpdateLine: (index, draft) => setState(() => _lineDrafts[index] = draft),
                      onPriceListChanged: (value) => setState(() => _selectedPriceListId = value),
                      onDiscountCodeChanged: (value) => setState(() => _selectedDiscountCode = value),
                      onSaleTypeChanged: (value) => setState(() => _saleType = value),
                      onCalculate: () {
                        controller.calculateQuote(
                          priceListId: _selectedPriceListId,
                          discountCode: _selectedDiscountCode,
                          saleType: _saleType,
                          lines: _lineDrafts
                              .map((line) => CatalogQuoteLineInput(productId: line.productId, quantity: line.quantity))
                              .toList(growable: false),
                        );
                      },
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(child: _QuoteResultPanel(result: state.quote)),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _CatalogPanel extends StatelessWidget {
  const _CatalogPanel({
    required this.state,
    required this.skuController,
    required this.nameController,
    required this.priceController,
    required this.taxRateController,
    required this.type,
    required this.onTypeChanged,
    required this.onCreateProduct,
  });

  final CatalogPricingState state;
  final TextEditingController skuController;
  final TextEditingController nameController;
  final TextEditingController priceController;
  final TextEditingController taxRateController;
  final String type;
  final ValueChanged<String> onTypeChanged;
  final VoidCallback onCreateProduct;

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: BoxDecoration(border: Border.all(color: Theme.of(context).dividerColor), borderRadius: BorderRadius.circular(12)),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Katalog', style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 8),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                SizedBox(width: 120, child: TextField(controller: skuController, decoration: const InputDecoration(labelText: 'SKU'))),
                SizedBox(width: 180, child: TextField(controller: nameController, decoration: const InputDecoration(labelText: 'Name'))),
                SizedBox(width: 130, child: TextField(controller: priceController, decoration: const InputDecoration(labelText: 'Preis netto'))),
                SizedBox(width: 130, child: TextField(controller: taxRateController, decoration: const InputDecoration(labelText: 'Steuer %'))),
                DropdownButton<String>(
                  value: type,
                  items: const [
                    DropdownMenuItem(value: 'service', child: Text('Service')),
                    DropdownMenuItem(value: 'product', child: Text('Produkt')),
                  ],
                  onChanged: (value) {
                    if (value != null) {
                      onTypeChanged(value);
                    }
                  },
                ),
                FilledButton.tonal(onPressed: state.isLoading ? null : onCreateProduct, child: const Text('Produkt speichern')),
              ],
            ),
            const Divider(),
            Text('Produkte: ${state.products.length}'),
            const SizedBox(height: 8),
            Expanded(
              child: ListView.builder(
                itemCount: state.products.length,
                itemBuilder: (context, index) {
                  final product = state.products[index];
                  return ListTile(
                    dense: true,
                    leading: CircleAvatar(child: Text(product.id.toString())),
                    title: Text('${product.sku} · ${product.name}'),
                    subtitle: Text('€ ${product.unitPrice.toStringAsFixed(2)} · USt ${product.taxRate.toStringAsFixed(1)}% · ${product.type}'),
                    trailing: Icon(product.isActive ? Icons.check_circle_outline : Icons.pause_circle_outline),
                  );
                },
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _QuoteEditorPanel extends StatelessWidget {
  const _QuoteEditorPanel({
    required this.state,
    required this.lineDrafts,
    required this.selectedPriceListId,
    required this.selectedDiscountCode,
    required this.saleType,
    required this.onAddLine,
    required this.onRemoveLine,
    required this.onUpdateLine,
    required this.onPriceListChanged,
    required this.onDiscountCodeChanged,
    required this.onSaleTypeChanged,
    required this.onCalculate,
  });

  final CatalogPricingState state;
  final List<_QuoteLineDraft> lineDrafts;
  final int? selectedPriceListId;
  final String? selectedDiscountCode;
  final String saleType;
  final VoidCallback onAddLine;
  final ValueChanged<int> onRemoveLine;
  final void Function(int index, _QuoteLineDraft draft) onUpdateLine;
  final ValueChanged<int?> onPriceListChanged;
  final ValueChanged<String?> onDiscountCodeChanged;
  final ValueChanged<String> onSaleTypeChanged;
  final VoidCallback onCalculate;

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: BoxDecoration(border: Border.all(color: Theme.of(context).dividerColor), borderRadius: BorderRadius.circular(12)),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Text('Angebotseditor', style: Theme.of(context).textTheme.titleMedium),
                const Spacer(),
                FilledButton.icon(onPressed: onAddLine, icon: const Icon(Icons.add), label: const Text('Position')),
              ],
            ),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                DropdownButton<int?>(
                  value: selectedPriceListId,
                  hint: const Text('Preisliste'),
                  items: [
                    const DropdownMenuItem<int?>(value: null, child: Text('Standardpreise')),
                    ...state.priceLists.map(
                      (list) => DropdownMenuItem<int?>(
                        value: list.id,
                        child: Text('${list.name} (${list.currencyCode})${list.isActive ? '' : ' · inaktiv'}'),
                      ),
                    ),
                  ],
                  onChanged: onPriceListChanged,
                ),
                DropdownButton<String?>(
                  value: selectedDiscountCode,
                  hint: const Text('Rabattcode'),
                  items: [
                    const DropdownMenuItem<String?>(value: null, child: Text('Kein Rabattcode')),
                    ...state.discountCodes.map(
                      (code) => DropdownMenuItem<String?>(
                        value: code.code,
                        child: Text('${code.code} (${code.discountType}:${code.discountValue})'),
                      ),
                    ),
                  ],
                  onChanged: onDiscountCodeChanged,
                ),
                DropdownButton<String>(
                  value: saleType,
                  items: const [
                    DropdownMenuItem(value: 'one_time', child: Text('Einmalumsatz')),
                    DropdownMenuItem(value: 'subscription', child: Text('Subscription')),
                  ],
                  onChanged: (value) {
                    if (value != null) {
                      onSaleTypeChanged(value);
                    }
                  },
                ),
                FilledButton(
                  onPressed: state.isCalculating ? null : onCalculate,
                  child: const Text('Preislogik anwenden'),
                ),
              ],
            ),
            if (state.isCalculating) const Padding(padding: EdgeInsets.only(top: 8), child: LinearProgressIndicator()),
            const SizedBox(height: 8),
            Expanded(
              child: ListView.builder(
                itemCount: lineDrafts.length,
                itemBuilder: (context, index) {
                  final draft = lineDrafts[index];
                  return Row(
                    children: [
                      Expanded(
                        child: DropdownButton<int?>(
                          value: draft.productId,
                          hint: const Text('Produkt auswählen'),
                          isExpanded: true,
                          items: state.products
                              .map((product) => DropdownMenuItem<int?>(value: product.id, child: Text('${product.sku} · ${product.name}')))
                              .toList(growable: false),
                          onChanged: (value) => onUpdateLine(index, draft.copyWith(productId: value)),
                        ),
                      ),
                      const SizedBox(width: 8),
                      SizedBox(
                        width: 100,
                        child: TextFormField(
                          initialValue: draft.quantity.toString(),
                          decoration: const InputDecoration(labelText: 'Menge'),
                          keyboardType: TextInputType.number,
                          onChanged: (value) => onUpdateLine(index, draft.copyWith(quantity: int.tryParse(value) ?? 1)),
                        ),
                      ),
                      IconButton(onPressed: () => onRemoveLine(index), icon: const Icon(Icons.delete_outline)),
                    ],
                  );
                },
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _QuoteResultPanel extends StatelessWidget {
  const _QuoteResultPanel({required this.result});

  final CatalogQuoteResult? result;

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: BoxDecoration(border: Border.all(color: Theme.of(context).dividerColor), borderRadius: BorderRadius.circular(12)),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: result == null
            ? const Center(child: Text('Noch keine Kalkulation.'))
            : Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Preislogik-Ergebnis', style: Theme.of(context).textTheme.titleMedium),
                  Text('Währung: ${result!.currencyCode}'),
                  if (result!.discount != null)
                    Text('Rabatt: ${result!.discount!.code} · -${result!.discount!.discountAmount.toStringAsFixed(2)}'),
                  const Divider(),
                  ...result!.lines.map(
                    (line) => ListTile(
                      dense: true,
                      contentPadding: EdgeInsets.zero,
                      title: Text('${line.name} x${line.quantity}'),
                      subtitle: Text(
                        'Einheit ${line.unitPrice.toStringAsFixed(2)} · Netto ${line.lineNet.toStringAsFixed(2)} · Steuer ${line.lineTax.toStringAsFixed(2)}',
                      ),
                    ),
                  ),
                  const Spacer(),
                  Text('Zwischensumme: ${result!.totals.subtotalNet.toStringAsFixed(2)}'),
                  Text('Rabatt gesamt: ${result!.totals.discountTotal.toStringAsFixed(2)}'),
                  Text('Steuer: ${result!.totals.taxTotal.toStringAsFixed(2)}'),
                  Text(
                    'Gesamt: ${result!.totals.grandTotal.toStringAsFixed(2)}',
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                ],
              ),
      ),
    );
  }
}

class _QuoteLineDraft {
  const _QuoteLineDraft({this.productId, this.quantity = 1});

  final int? productId;
  final int quantity;

  _QuoteLineDraft copyWith({int? productId, int? quantity}) {
    return _QuoteLineDraft(productId: productId ?? this.productId, quantity: quantity ?? this.quantity);
  }
}
