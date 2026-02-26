import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../application/import_wizard_controller.dart';

class AutomationIntegrationsScreen extends ConsumerStatefulWidget {
  const AutomationIntegrationsScreen({super.key});

  @override
  ConsumerState<AutomationIntegrationsScreen> createState() => _AutomationIntegrationsScreenState();
}

class _AutomationIntegrationsScreenState extends ConsumerState<AutomationIntegrationsScreen> {
  String _dataset = 'customers';
  final TextEditingController _jsonController = TextEditingController(
    text: '[\n  {"company_name":"Acme GmbH","email":"billing@acme.example"}\n]',
  );

  @override
  void dispose() {
    _jsonController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(importWizardControllerProvider);
    final controller = ref.read(importWizardControllerProvider.notifier);

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
                    'Automation Integrations – Adapter Worker & Import Wizard (Phase 8)',
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                ),
                FilledButton.tonalIcon(
                  onPressed: state.isLoading ? null : () => controller.processWorkerQueue(limit: 25),
                  icon: const Icon(Icons.settings_backup_restore),
                  label: const Text('Adapter-Worker ausführen'),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Wrap(
              spacing: 12,
              runSpacing: 12,
              crossAxisAlignment: WrapCrossAlignment.center,
              children: [
                const Text('Datensatz'),
                DropdownButton<String>(
                  value: _dataset,
                  items: const [
                    DropdownMenuItem(value: 'customers', child: Text('Kunden')),
                    DropdownMenuItem(value: 'products', child: Text('Produkte')),
                    DropdownMenuItem(value: 'historical_invoices', child: Text('Historische Rechnungen')),
                  ],
                  onChanged: state.isLoading
                      ? null
                      : (value) {
                          if (value != null) {
                            setState(() => _dataset = value);
                          }
                        },
                ),
                FilledButton(
                  onPressed: state.isLoading
                      ? null
                      : () => controller.preview(dataset: _dataset, rawRowsJson: _jsonController.text),
                  child: const Text('1) Preview prüfen'),
                ),
                FilledButton.tonal(
                  onPressed: state.isLoading
                      ? null
                      : () => controller.execute(dataset: _dataset, rawRowsJson: _jsonController.text),
                  child: const Text('2) Import ausführen'),
                ),
              ],
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _jsonController,
              maxLines: 10,
              decoration: const InputDecoration(
                border: OutlineInputBorder(),
                labelText: 'JSON-Array',
                hintText: '[{"field":"value"}]',
              ),
            ),
            if (state.isLoading) const Padding(padding: EdgeInsets.only(top: 8), child: LinearProgressIndicator()),
            if (state.error != null)
              Padding(
                padding: const EdgeInsets.only(top: 8),
                child: Text(state.error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
              ),
            const SizedBox(height: 8),
            Expanded(
              child: Row(
                children: [
                  Expanded(child: _JsonPanel(title: 'Preview', value: state.preview)),
                  const SizedBox(width: 8),
                  Expanded(child: _JsonPanel(title: 'Import-Ergebnis', value: state.executeResult)),
                  const SizedBox(width: 8),
                  Expanded(child: _JsonPanel(title: 'Worker-Ergebnis', value: state.workerResult)),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _JsonPanel extends StatelessWidget {
  const _JsonPanel({required this.title, required this.value});

  final String title;
  final Map<String, dynamic>? value;

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: BoxDecoration(
        border: Border.all(color: Theme.of(context).dividerColor),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(title, style: Theme.of(context).textTheme.titleMedium),
            const Divider(),
            Expanded(
              child: SingleChildScrollView(
                child: SelectableText(
                  value == null ? 'Noch keine Daten.' : const JsonEncoder.withIndent('  ').convert(value),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
