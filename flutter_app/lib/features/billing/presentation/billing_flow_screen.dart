import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../application/billing_flow_controller.dart';

class BillingFlowScreen extends ConsumerWidget {
  const BillingFlowScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(billingFlowControllerProvider);
    final controller = ref.read(billingFlowControllerProvider.notifier);

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Phase 1 Billing UI: Angebot → bezahlt', style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 8),
            Text(
              'Startet den End-to-End-MVP-Flow inkl. Angebot, Rechnungs-Konvertierung, Payment-Link, Zahlung, Historie und PDF.',
              style: Theme.of(context).textTheme.bodyMedium,
            ),
            const SizedBox(height: 16),
            FilledButton.icon(
              onPressed: state.isRunning ? null : controller.runQuoteToPaidFlow,
              icon: state.isRunning
                  ? const SizedBox(
                      width: 16,
                      height: 16,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.play_arrow),
              label: Text(state.isRunning ? 'Flow läuft…' : 'E2E-Flow ausführen'),
            ),
            const SizedBox(height: 16),
            Wrap(
              spacing: 12,
              runSpacing: 12,
              children: [
                _ResultChip(label: 'Angebot', value: state.quoteId?.toString() ?? '-'),
                _ResultChip(label: 'Rechnung', value: state.invoiceId?.toString() ?? '-'),
                _ResultChip(label: 'Status', value: state.documentStatus ?? '-'),
                _ResultChip(label: 'Historie', value: state.historyEntries.toString()),
                _ResultChip(label: 'PDF', value: state.pdfFilename ?? '-'),
              ],
            ),
            if (state.error != null) ...[
              const SizedBox(height: 12),
              Text(state.error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
            ],
            const SizedBox(height: 16),
            Expanded(
              child: ListView.builder(
                itemCount: state.steps.length,
                itemBuilder: (context, index) {
                  return ListTile(
                    dense: true,
                    leading: CircleAvatar(radius: 12, child: Text('${index + 1}')),
                    title: Text(state.steps[index]),
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

class _ResultChip extends StatelessWidget {
  const _ResultChip({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Chip(label: Text('$label: $value'));
  }
}
