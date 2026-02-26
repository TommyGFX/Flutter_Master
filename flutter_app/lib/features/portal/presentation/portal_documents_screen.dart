import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../application/portal_documents_controller.dart';

class PortalDocumentsScreen extends ConsumerStatefulWidget {
  const PortalDocumentsScreen({super.key});

  @override
  ConsumerState<PortalDocumentsScreen> createState() => _PortalDocumentsScreenState();
}

class _PortalDocumentsScreenState extends ConsumerState<PortalDocumentsScreen> {
  @override
  void initState() {
    super.initState();
    Future.microtask(() => ref.read(portalDocumentsControllerProvider.notifier).loadDocuments());
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(portalDocumentsControllerProvider);
    final controller = ref.read(portalDocumentsControllerProvider.notifier);

    return Scaffold(
      appBar: AppBar(title: const Text('Kundenportal · Dokumente')),
      body: Padding(
        padding: const EdgeInsets.all(16),
        child: Row(
          children: [
            Expanded(
              child: Card(
                child: state.isLoading && state.documents.isEmpty
                    ? const Center(child: CircularProgressIndicator())
                    : ListView.separated(
                        itemCount: state.documents.length,
                        separatorBuilder: (_, __) => const Divider(height: 1),
                        itemBuilder: (context, index) {
                          final item = state.documents[index];
                          return ListTile(
                            title: Text(item.documentNo),
                            subtitle: Text('Status: ${item.status} · Fällig: ${item.dueDate ?? '-'}'),
                            trailing: Text('${item.totalGross.toStringAsFixed(2)} ${item.currencyCode}'),
                            onTap: () => controller.loadDocument(item.id),
                          );
                        },
                      ),
              ),
            ),
            const SizedBox(width: 16),
            Expanded(
              child: Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: state.selectedDocument == null
                      ? const Center(child: Text('Bitte ein Dokument auswählen.'))
                      : _DetailPanel(document: state.selectedDocument!),
                ),
              ),
            ),
          ],
        ),
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: state.isLoading ? null : controller.loadDocuments,
        icon: const Icon(Icons.refresh),
        label: const Text('Aktualisieren'),
      ),
      bottomNavigationBar: state.error == null
          ? null
          : Material(
              color: Theme.of(context).colorScheme.errorContainer,
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: Text(state.error!),
              ),
            ),
    );
  }
}

class _DetailPanel extends StatelessWidget {
  const _DetailPanel({required this.document});

  final PortalDocumentDetail document;

  @override
  Widget build(BuildContext context) {
    return ListView(
      children: [
        Text(document.documentNo, style: Theme.of(context).textTheme.headlineSmall),
        const SizedBox(height: 8),
        Text('Kunde: ${document.customerName}'),
        Text('Status: ${document.status}'),
        const Divider(height: 24),
        Text('Gesamt: ${document.gross.toStringAsFixed(2)} ${document.currencyCode}'),
        Text('Netto: ${document.net.toStringAsFixed(2)} ${document.currencyCode}'),
        Text('Steuer: ${document.tax.toStringAsFixed(2)} ${document.currencyCode}'),
        Text('Rabatt: ${document.discount.toStringAsFixed(2)} ${document.currencyCode}'),
        Text('Versand: ${document.shipping.toStringAsFixed(2)} ${document.currencyCode}'),
        const Divider(height: 24),
        Text('Zahlungsoptionen', style: Theme.of(context).textTheme.titleMedium),
        const SizedBox(height: 8),
        ...document.paymentOptions.map(
          (payment) => Card(
            child: ListTile(
              leading: const Icon(Icons.credit_card),
              title: Text('${payment.provider} · ${payment.amount.toStringAsFixed(2)} ${payment.currencyCode}'),
              subtitle: Text('Status: ${payment.status}\n${payment.paymentUrl ?? 'Kein Zahlungslink verfügbar'}'),
            ),
          ),
        ),
      ],
    );
  }
}
