import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../application/subscription_management_controller.dart';

class SubscriptionManagementScreen extends ConsumerStatefulWidget {
  const SubscriptionManagementScreen({super.key});

  @override
  ConsumerState<SubscriptionManagementScreen> createState() => _SubscriptionManagementScreenState();
}

class _SubscriptionManagementScreenState extends ConsumerState<SubscriptionManagementScreen> {
  String _provider = 'stripe';

  @override
  void initState() {
    super.initState();
    Future.microtask(() => ref.read(subscriptionManagementControllerProvider.notifier).loadOverview());
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(subscriptionManagementControllerProvider);
    final controller = ref.read(subscriptionManagementControllerProvider.notifier);

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(child: Text('Abo-Management (Phase 4)', style: Theme.of(context).textTheme.titleLarge)),
                IconButton(onPressed: state.isLoading ? null : controller.loadOverview, icon: const Icon(Icons.refresh)),
              ],
            ),
            const SizedBox(height: 8),
            Text('Provider-Adapter inkl. Retry-Jobs und Payment-Method-Update-Link je Vertrag ausführen.'),
            const SizedBox(height: 12),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                FilledButton.icon(
                  onPressed: state.isLoading ? null : controller.runRecurringEngine,
                  icon: const Icon(Icons.repeat),
                  label: const Text('Recurring ausführen'),
                ),
                FilledButton.tonalIcon(
                  onPressed: state.isLoading ? null : controller.runAutoInvoicing,
                  icon: const Icon(Icons.email_outlined),
                  label: const Text('Auto-Invoicing'),
                ),
                FilledButton.tonalIcon(
                  onPressed: state.isLoading ? null : controller.runDunningRetention,
                  icon: const Icon(Icons.warning_amber_outlined),
                  label: const Text('Dunning/Retention'),
                ),
              ],
            ),
            if (state.error != null) ...[
              const SizedBox(height: 8),
              Text(state.error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
            ],
            if (state.lastRunSummary != null) ...[
              const SizedBox(height: 8),
              Text(state.lastRunSummary!, style: Theme.of(context).textTheme.bodyMedium),
            ],
            const Divider(height: 24),
            Expanded(
              child: Row(
                children: [
                  Expanded(
                    child: _DataList(
                      title: 'Pläne',
                      rows: state.plans
                          .map((plan) => '${plan['name']} · ${plan['amount']} ${plan['currency_code']} (${plan['billing_interval']})')
                          .toList(growable: false),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            const Text('Provider:'),
                            const SizedBox(width: 8),
                            DropdownButton<String>(
                              value: _provider,
                              items: const [
                                DropdownMenuItem(value: 'stripe', child: Text('Stripe')),
                                DropdownMenuItem(value: 'paypal', child: Text('PayPal')),
                              ],
                              onChanged: (value) => setState(() => _provider = value ?? 'stripe'),
                            ),
                          ],
                        ),
                        const SizedBox(height: 8),
                        Expanded(
                          child: ListView.separated(
                            itemCount: state.contracts.length,
                            separatorBuilder: (_, __) => const Divider(height: 1),
                            itemBuilder: (context, index) {
                              final contract = state.contracts[index];
                              return ListTile(
                                dense: true,
                                title: Text('Contract #${contract['id']} · ${contract['plan_name']}'),
                                subtitle: Text('Status: ${contract['status']} · Next Billing: ${contract['next_billing_at']}'),
                                trailing: TextButton(
                                  onPressed: state.isLoading
                                      ? null
                                      : () => controller.createPaymentMethodUpdateLink(
                                            contractId: (contract['id'] as num?)?.toInt() ?? 0,
                                            provider: _provider,
                                          ),
                                  child: const Text('Payment-Link'),
                                ),
                              );
                            },
                          ),
                        ),
                        if (state.lastPaymentMethodLink != null)
                          Padding(
                            padding: const EdgeInsets.only(top: 8),
                            child: SelectableText('Letzter Update-Link: ${state.lastPaymentMethodLink}'),
                          ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _DataList extends StatelessWidget {
  const _DataList({required this.title, required this.rows});

  final String title;
  final List<String> rows;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(title, style: Theme.of(context).textTheme.titleMedium),
        const SizedBox(height: 8),
        Expanded(
          child: rows.isEmpty
              ? const Center(child: Text('Keine Daten verfügbar'))
              : ListView.separated(
                  itemCount: rows.length,
                  separatorBuilder: (_, __) => const Divider(height: 1),
                  itemBuilder: (_, index) => ListTile(dense: true, title: Text(rows[index])),
                ),
        ),
      ],
    );
  }
}
