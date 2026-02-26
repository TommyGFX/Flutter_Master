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
            const SizedBox(height: 12),
            _FlowStatusBanner(state: state),
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
            const SizedBox(height: 16),
            Expanded(
              child: state.steps.isEmpty
                  ? const Center(
                      child: Text('Noch keine Schritte vorhanden. Starte den Billing-Flow für eine Live-Timeline.'),
                    )
                  : ListView.separated(
                      itemCount: state.steps.length,
                      separatorBuilder: (_, __) => const Divider(height: 1),
                      itemBuilder: (context, index) {
                        return ListTile(
                          dense: true,
                          leading: CircleAvatar(radius: 12, child: Text('${index + 1}')),
                          title: Text(state.steps[index]),
                          trailing: Icon(
                            state.steps[index].startsWith('Fehler')
                                ? Icons.error_outline
                                : Icons.check_circle_outline,
                            color: state.steps[index].startsWith('Fehler')
                                ? Theme.of(context).colorScheme.error
                                : Theme.of(context).colorScheme.primary,
                          ),
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

class _FlowStatusBanner extends StatelessWidget {
  const _FlowStatusBanner({required this.state});

  final BillingFlowState state;

  @override
  Widget build(BuildContext context) {
    final colorScheme = Theme.of(context).colorScheme;
    final (icon, text, backgroundColor, foregroundColor) = switch ((state.isRunning, state.error, state.documentStatus)) {
      (true, _, _) => (Icons.sync, 'Flow wird ausgeführt …', colorScheme.primaryContainer, colorScheme.onPrimaryContainer),
      (_, final String _, _) => (Icons.warning_amber_rounded, state.error!, colorScheme.errorContainer, colorScheme.onErrorContainer),
      (_, _, final String status) when status.toLowerCase() == 'paid' => (
          Icons.verified,
          'Flow erfolgreich abgeschlossen (Status: $status).',
          colorScheme.tertiaryContainer,
          colorScheme.onTertiaryContainer,
        ),
      _ => (Icons.info_outline, 'Bereit für den nächsten Testlauf.', colorScheme.surfaceContainerHighest, colorScheme.onSurfaceVariant),
    };

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: backgroundColor,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        children: [
          Icon(icon, color: foregroundColor),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              text,
              style: TextStyle(color: foregroundColor, fontWeight: FontWeight.w600),
            ),
          ),
        ],
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
