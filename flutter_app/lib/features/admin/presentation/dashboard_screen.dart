import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/dio_client.dart';
import '../../../l10n/l10n.dart';
import '../../auth/auth_controller.dart';
import '../../billing/presentation/billing_flow_screen.dart';
import '../../billing/presentation/subscription_management_screen.dart';

class DashboardScreen extends ConsumerStatefulWidget {
  const DashboardScreen({super.key});

  @override
  ConsumerState<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends ConsumerState<DashboardScreen> {
  int selectedIndex = 0;
  String? selectedPluginKey;
  String selectedPluginTitle = 'Plugin Shell';
  List<Map<String, dynamic>> shellNavigation = const [];

  void selectPluginNavigation(Map<String, dynamic> plugin) {
    setState(() {
      selectedPluginKey = plugin['plugin_key']?.toString();
      selectedPluginTitle = plugin['display_name']?.toString() ?? 'Plugin Shell';
      selectedIndex = 1;
    });
  }

  void updateShellNavigation(List<Map<String, dynamic>> navigation) {
    setState(() {
      shellNavigation = navigation;

      final hasSelection = selectedPluginKey != null &&
          navigation.any((plugin) => plugin['plugin_key']?.toString() == selectedPluginKey);
      if (!hasSelection) {
        selectedPluginKey = navigation.isEmpty ? null : navigation.first['plugin_key']?.toString();
        selectedPluginTitle = navigation.isEmpty
            ? 'Plugin Shell'
            : navigation.first['display_name']?.toString() ?? 'Plugin Shell';
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final isMobile = MediaQuery.of(context).size.width < 980;
    final l10n = context.l10n;

    return Scaffold(
      appBar: AppBar(title: Text(l10n.controlCenter)),
      drawer: isMobile
          ? Drawer(
              child: _SideNav(
                selectedIndex: selectedIndex,
                shellNavigation: shellNavigation,
                onSelect: (index) {
                  setState(() => selectedIndex = index);
                  Navigator.pop(context);
                },
                onSelectPlugin: selectPluginNavigation,
              ),
            )
          : null,
      body: Row(
        children: [
          if (!isMobile)
            SizedBox(
              width: 280,
              child: _SideNav(
                selectedIndex: selectedIndex,
                shellNavigation: shellNavigation,
                onSelect: (index) => setState(() => selectedIndex = index),
                onSelectPlugin: selectPluginNavigation,
              ),
            ),
          Expanded(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: IndexedStack(
                index: selectedIndex,
                children: [
                  _AdminOverview(),
                  _PluginShellCard(
                    selectedPluginKey: selectedPluginKey,
                    selectedPluginTitle: selectedPluginTitle,
                    onNavigationLoaded: updateShellNavigation,
                  ),
                  _PluginLifecycleCard(),
                  _RolePermissionCard(),
                  _ApprovalCard(),
                  _PlatformInsightsCard(),
                  _AccountsCard(),
                  const BillingFlowScreen(),
                  const SubscriptionManagementScreen(),
                  _AutomationCard(),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _SideNav extends StatelessWidget {
  const _SideNav({
    required this.selectedIndex,
    required this.shellNavigation,
    required this.onSelect,
    required this.onSelectPlugin,
  });

  final int selectedIndex;
  final List<Map<String, dynamic>> shellNavigation;
  final ValueChanged<int> onSelect;
  final ValueChanged<Map<String, dynamic>> onSelectPlugin;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    return ListView(
      children: [
        DrawerHeader(child: Text(l10n.adminNavigation)),
        _NavTile(label: l10n.overview, icon: Icons.home_outlined, selected: selectedIndex == 0, onTap: () => onSelect(0)),
        _NavTile(label: 'Plugin Shell', icon: Icons.view_sidebar_outlined, selected: selectedIndex == 1, onTap: () => onSelect(1)),
        if (shellNavigation.isNotEmpty)
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            child: Text(
              'Plugin Navigation (RBAC + aktiv)',
              style: Theme.of(context).textTheme.labelMedium,
            ),
          ),
        ...shellNavigation.map(
          (plugin) => ListTile(
            dense: true,
            visualDensity: VisualDensity.compact,
            contentPadding: const EdgeInsets.only(left: 28, right: 16),
            leading: const Icon(Icons.subdirectory_arrow_right_outlined, size: 18),
            title: Text(plugin['display_name']?.toString() ?? plugin['plugin_key']?.toString() ?? '-'),
            subtitle: Text(plugin['plugin_key']?.toString() ?? '-'),
            onTap: () => onSelectPlugin(plugin),
          ),
        ),
        _NavTile(label: l10n.pluginLifecycle, icon: Icons.extension_outlined, selected: selectedIndex == 2, onTap: () => onSelect(2)),
        _NavTile(label: l10n.permissionsManagement, icon: Icons.lock_outline, selected: selectedIndex == 3, onTap: () => onSelect(3)),
        _NavTile(label: l10n.approvalsAudit, icon: Icons.approval_outlined, selected: selectedIndex == 4, onTap: () => onSelect(4)),
        _NavTile(label: l10n.platformInsights, icon: Icons.query_stats_outlined, selected: selectedIndex == 5, onTap: () => onSelect(5)),
        _NavTile(label: l10n.usersCustomers, icon: Icons.groups_outlined, selected: selectedIndex == 6, onTap: () => onSelect(6)),
        _NavTile(label: 'Billing E2E (Phase 1)', icon: Icons.receipt_long_outlined, selected: selectedIndex == 7, onTap: () => onSelect(7)),
        _NavTile(label: 'Abo-Management (Phase 4)', icon: Icons.subscriptions_outlined, selected: selectedIndex == 8, onTap: () => onSelect(8)),
        _NavTile(label: l10n.billingPdfMail, icon: Icons.auto_awesome_outlined, selected: selectedIndex == 9, onTap: () => onSelect(9)),
        ListTile(
          leading: const Icon(Icons.dataset_outlined),
          title: Text(l10n.crudPlayground),
          onTap: () => Navigator.pushNamed(context, '/crud'),
        ),
      ],
    );
  }
}

class _NavTile extends StatelessWidget {
  const _NavTile({
    required this.label,
    required this.icon,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final IconData icon;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return ListTile(leading: Icon(icon), title: Text(label), selected: selected, onTap: onTap);
  }
}

mixin _ApiClientMixin<T extends ConsumerStatefulWidget> on ConsumerState<T> {
  Options buildOptions({Map<String, String> extraHeaders = const {}}) {
    final authState = ref.read(authControllerProvider);
    final headers = <String, String>{
      'X-Tenant-Id': authState.tenantId ?? 'tenant_1',
      'X-Permissions': authState.permissions.join(','),
      if ((authState.token ?? '').isNotEmpty) 'Authorization': 'Bearer ${authState.token}',
      ...extraHeaders,
    };
    return Options(headers: headers);
  }

  String prettyJson(dynamic value) {
    const encoder = JsonEncoder.withIndent('  ');
    try {
      return encoder.convert(value);
    } catch (_) {
      return value.toString();
    }
  }
}

class _AdminOverview extends ConsumerWidget {
  const _AdminOverview();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final authState = ref.watch(authControllerProvider);
    final l10n = context.l10n;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(l10n.tenantOverview, style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 12),
            Text(l10n.tenantValue(authState.tenantId ?? l10n.notSet)),
            Text(l10n.entrypointValue(authState.entrypoint ?? '-')),
            Text(l10n.permissionsValue(authState.permissions.isEmpty ? l10n.none : authState.permissions.join(', '))),
            const SizedBox(height: 12),
            Wrap(
              spacing: 12,
              runSpacing: 12,
              children: [
                Chip(label: Text(l10n.chipPlugins)),
                Chip(label: Text(l10n.chipInsights)),
                Chip(label: Text(l10n.chipUsers)),
                Chip(label: Text(l10n.chipAutomation)),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _PluginShellCard extends ConsumerStatefulWidget {
  const _PluginShellCard({
    required this.selectedPluginKey,
    required this.selectedPluginTitle,
    required this.onNavigationLoaded,
  });

  final String? selectedPluginKey;
  final String selectedPluginTitle;
  final ValueChanged<List<Map<String, dynamic>>> onNavigationLoaded;

  @override
  ConsumerState<_PluginShellCard> createState() => _PluginShellCardState();
}

class _PluginShellCardState extends ConsumerState<_PluginShellCard> with _ApiClientMixin {
  bool isLoading = true;
  String? error;
  List<Map<String, dynamic>> plugins = [];
  List<Map<String, dynamic>> navigation = [];

  @override
  void initState() {
    super.initState();
    loadShell();
  }

  Future<void> loadShell() async {
    setState(() {
      isLoading = true;
      error = null;
    });

    try {
      final dio = ref.read(dioProvider);
      final response = await dio.get('/admin/plugin-shell', options: buildOptions());
      final data = (response.data['data'] as List<dynamic>? ?? const [])
          .map((plugin) => Map<String, dynamic>.from(plugin as Map))
          .toList(growable: false);
      final navigationData = (response.data['navigation'] as List<dynamic>? ?? const [])
          .map((plugin) => Map<String, dynamic>.from(plugin as Map))
          .toList(growable: false);

      setState(() {
        plugins = data;
        navigation = navigationData;
      });
      widget.onNavigationLoaded(navigationData);
    } on DioException catch (exception) {
      setState(() => error = exception.response?.data.toString() ?? exception.message);
    } finally {
      setState(() => isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final selectedPlugin = widget.selectedPluginKey == null
        ? null
        : plugins.cast<Map<String, dynamic>?>().firstWhere(
            (plugin) => plugin?['plugin_key']?.toString() == widget.selectedPluginKey,
            orElse: () => null,
          );

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(child: Text('Plugin Shell', style: Theme.of(context).textTheme.titleLarge)),
                IconButton(onPressed: loadShell, icon: const Icon(Icons.refresh)),
              ],
            ),
            if (navigation.isNotEmpty)
              Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: Text('${navigation.length} Plugin(s) in Navigation freigeschaltet.'),
              ),
            if (selectedPlugin != null)
              Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: ListTile(
                  tileColor: Theme.of(context).colorScheme.surfaceContainerHighest,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  leading: const Icon(Icons.space_dashboard_outlined),
                  title: Text('Ausgewählt: ${widget.selectedPluginTitle}'),
                  subtitle: Text(
                    'Key: ${selectedPlugin['plugin_key'] ?? '-'} · Status: ${selectedPlugin['lifecycle_status'] ?? 'installed'}',
                  ),
                ),
              ),
            const SizedBox(height: 8),
            if (error != null) Text(error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
            if (isLoading)
              const Center(child: CircularProgressIndicator())
            else if (plugins.isEmpty)
              const Text('Keine sichtbaren Plugins vorhanden.')
            else
              Expanded(
                child: ListView.builder(
                  itemCount: plugins.length,
                  itemBuilder: (context, index) {
                    final plugin = plugins[index];
                    final capabilities = (plugin['capabilities'] as List<dynamic>? ?? const []).join(', ');
                    final lifecycleStatus = plugin['lifecycle_status']?.toString() ?? 'installed';
                    return ListTile(
                      leading: const Icon(Icons.extension),
                      title: Text('${plugin['display_name']} (${plugin['version'] ?? '1.0.0'})'),
                      subtitle: Text('Status: $lifecycleStatus\nCapabilities: ${capabilities.isEmpty ? '-' : capabilities}'),
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

class _PluginLifecycleCard extends ConsumerStatefulWidget {
  const _PluginLifecycleCard();

  @override
  ConsumerState<_PluginLifecycleCard> createState() => _PluginLifecycleCardState();
}

class _PluginLifecycleCardState extends ConsumerState<_PluginLifecycleCard> with _ApiClientMixin {
  bool isLoading = true;
  String? error;
  List<Map<String, dynamic>> plugins = [];

  @override
  void initState() {
    super.initState();
    loadPlugins();
  }

  Future<void> loadPlugins() async {
    setState(() {
      isLoading = true;
      error = null;
    });

    try {
      final dio = ref.read(dioProvider);
      final response = await dio.get('/admin/plugins', options: buildOptions());
      final list = (response.data['data'] as List<dynamic>? ?? const []);
      setState(() {
        plugins = list.map((item) => Map<String, dynamic>.from(item as Map)).toList(growable: false);
      });
    } on DioException catch (exception) {
      setState(() => error = exception.response?.data.toString() ?? exception.message);
    } finally {
      setState(() => isLoading = false);
    }
  }

  Future<void> togglePlugin(Map<String, dynamic> plugin) async {
    final pluginKey = plugin['plugin_key']?.toString() ?? '';
    final isActive = plugin['is_active'] == 1 || plugin['is_active'] == true;
    if (pluginKey.isEmpty) {
      return;
    }

    final dio = ref.read(dioProvider);
    await dio.post('/admin/plugins/$pluginKey/status', data: {'is_active': !isActive}, options: buildOptions());
    await loadPlugins();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(child: Text(l10n.pluginLifecycle, style: Theme.of(context).textTheme.titleLarge)),
                IconButton(onPressed: loadPlugins, icon: const Icon(Icons.refresh)),
              ],
            ),
            if (error != null)
              Text(l10n.errorWithMessage(error!), style: TextStyle(color: Theme.of(context).colorScheme.error)),
            const SizedBox(height: 8),
            if (isLoading)
              const Center(child: CircularProgressIndicator())
            else if (plugins.isEmpty)
              Text(l10n.noPlugins)
            else
              Expanded(
                child: ListView.builder(
                  itemCount: plugins.length,
                  itemBuilder: (context, index) {
                    final plugin = plugins[index];
                    final isActive = plugin['is_active'] == 1 || plugin['is_active'] == true;
                    return SwitchListTile(
                      title: Text(plugin['display_name']?.toString() ?? plugin['plugin_key'].toString()),
                      subtitle: Text(l10n.pluginSubtitle(plugin['plugin_key'].toString())),
                      value: isActive,
                      onChanged: (_) => togglePlugin(plugin),
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

class _RolePermissionCard extends ConsumerStatefulWidget {
  const _RolePermissionCard();

  @override
  ConsumerState<_RolePermissionCard> createState() => _RolePermissionCardState();
}

class _RolePermissionCardState extends ConsumerState<_RolePermissionCard> with _ApiClientMixin {
  final permissionsCtrl = TextEditingController();
  bool isLoading = true;
  String selectedRole = '';
  List<Map<String, dynamic>> roles = [];

  @override
  void initState() {
    super.initState();
    loadRoles();
  }

  @override
  void dispose() {
    permissionsCtrl.dispose();
    super.dispose();
  }

  Future<void> loadRoles() async {
    setState(() => isLoading = true);
    final dio = ref.read(dioProvider);
    final response = await dio.get('/admin/roles/permissions', options: buildOptions());
    final data = (response.data['data'] as List<dynamic>? ?? const [])
        .map((role) => Map<String, dynamic>.from(role as Map))
        .toList(growable: false);

    setState(() {
      roles = data;
      selectedRole = data.isNotEmpty ? data.first['role_key'].toString() : '';
      permissionsCtrl.text = _permissionsFor(selectedRole).join(', ');
      isLoading = false;
    });
  }

  List<String> _permissionsFor(String roleKey) {
    for (final role in roles) {
      if (role['role_key'] == roleKey) {
        return (role['permissions'] as List<dynamic>? ?? const [])
            .map((permission) => permission.toString())
            .toList(growable: false);
      }
    }
    return const [];
  }

  Future<void> savePermissions() async {
    if (selectedRole.isEmpty) {
      return;
    }

    final permissions = permissionsCtrl.text
        .split(',')
        .map((permission) => permission.trim())
        .where((permission) => permission.isNotEmpty)
        .toList(growable: false);

    final dio = ref.read(dioProvider);
    await dio.put('/admin/roles/$selectedRole/permissions', data: {'permissions': permissions}, options: buildOptions());
    await loadRoles();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: isLoading
            ? const Center(child: CircularProgressIndicator())
            : Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(l10n.permissionsManagement, style: Theme.of(context).textTheme.titleLarge),
                  const SizedBox(height: 12),
                  DropdownButtonFormField<String>(
                    value: selectedRole.isEmpty ? null : selectedRole,
                    items: roles
                        .map((role) => DropdownMenuItem<String>(
                              value: role['role_key'].toString(),
                              child: Text('${role['name']} (${role['role_key']})'),
                            ))
                        .toList(growable: false),
                    onChanged: (value) {
                      if (value == null) {
                        return;
                      }
                      setState(() {
                        selectedRole = value;
                        permissionsCtrl.text = _permissionsFor(value).join(', ');
                      });
                    },
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: permissionsCtrl,
                    minLines: 2,
                    maxLines: 4,
                    decoration: InputDecoration(labelText: l10n.permissionsCommaSeparated),
                  ),
                  const SizedBox(height: 12),
                  FilledButton(onPressed: savePermissions, child: Text(l10n.submitApproval)),
                ],
              ),
      ),
    );
  }
}

class _ApprovalCard extends ConsumerStatefulWidget {
  const _ApprovalCard();

  @override
  ConsumerState<_ApprovalCard> createState() => _ApprovalCardState();
}

class _ApprovalCardState extends ConsumerState<_ApprovalCard> with _ApiClientMixin {
  bool loading = false;
  String result = '';

  Future<void> loadApprovals() async {
    setState(() => loading = true);
    final dio = ref.read(dioProvider);
    try {
      final response = await dio.get('/admin/approvals', options: buildOptions());
      setState(() => result = prettyJson(response.data));
    } finally {
      setState(() => loading = false);
    }
  }

  Future<void> decide(int id, bool approve) async {
    final dio = ref.read(dioProvider);
    await dio.post('/admin/approvals/$id/${approve ? 'approve' : 'reject'}', options: buildOptions());
    await loadApprovals();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    return _EndpointPanel(
      title: l10n.approvalsAudit,
      actions: [
        FilledButton(onPressed: loadApprovals, child: Text(l10n.loadApprovals)),
      ],
      loading: loading,
      result: result,
      extra: Row(
        children: [
          Expanded(
            child: OutlinedButton(
              onPressed: () => decide(1, true),
              child: Text(l10n.approveDemo),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: OutlinedButton(
              onPressed: () => decide(1, false),
              child: Text(l10n.rejectDemo),
            ),
          ),
        ],
      ),
    );
  }
}

class _PlatformInsightsCard extends ConsumerStatefulWidget {
  const _PlatformInsightsCard();

  @override
  ConsumerState<_PlatformInsightsCard> createState() => _PlatformInsightsCardState();
}

class _PlatformInsightsCardState extends ConsumerState<_PlatformInsightsCard> with _ApiClientMixin {
  bool loading = false;
  String output = '';
  final companyCtrl = TextEditingController(text: 'tenant_1');

  @override
  void dispose() {
    companyCtrl.dispose();
    super.dispose();
  }

  Future<void> runGet(String path) async {
    setState(() => loading = true);
    final dio = ref.read(dioProvider);
    try {
      final response = await dio.get(path, options: buildOptions());
      setState(() => output = prettyJson(response.data));
    } finally {
      setState(() => loading = false);
    }
  }

  Future<void> impersonate() async {
    setState(() => loading = true);
    final dio = ref.read(dioProvider);
    try {
      final response = await dio.post(
        '/platform/impersonate/company',
        data: {'tenant_id': companyCtrl.text.trim()},
        options: buildOptions(),
      );
      setState(() => output = prettyJson(response.data));
    } finally {
      setState(() => loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    return _EndpointPanel(
      title: '${l10n.platformInsights} / Superadmin',
      loading: loading,
      result: output,
      actions: [
        FilledButton(onPressed: () => runGet('/platform/admin-stats'), child: Text(l10n.adminStats)),
        FilledButton(onPressed: () => runGet('/platform/audit-logs'), child: Text(l10n.auditLogs)),
        FilledButton(onPressed: () => runGet('/platform/reports'), child: Text(l10n.reports)),
      ],
      extra: Row(
        children: [
          Expanded(child: TextField(controller: companyCtrl, decoration: InputDecoration(labelText: l10n.tenantForImpersonation))),
          const SizedBox(width: 12),
          FilledButton.tonal(onPressed: impersonate, child: Text(l10n.impersonate)),
        ],
      ),
    );
  }
}

class _AccountsCard extends ConsumerStatefulWidget {
  const _AccountsCard();

  @override
  ConsumerState<_AccountsCard> createState() => _AccountsCardState();
}

class _AccountsCardState extends ConsumerState<_AccountsCard> with _ApiClientMixin {
  bool loading = false;
  String output = '';

  Future<void> runGet(String path) async {
    setState(() => loading = true);
    final dio = ref.read(dioProvider);
    try {
      final response = await dio.get(path, options: buildOptions());
      setState(() => output = prettyJson(response.data));
    } finally {
      setState(() => loading = false);
    }
  }

  Future<void> runPost(String path, Map<String, dynamic> data) async {
    setState(() => loading = true);
    final dio = ref.read(dioProvider);
    try {
      final response = await dio.post(path, data: data, options: buildOptions());
      setState(() => output = prettyJson(response.data));
    } finally {
      setState(() => loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    return _EndpointPanel(
      title: l10n.tenantAdminUserProfile,
      loading: loading,
      result: output,
      actions: [
        FilledButton(onPressed: () => runGet('/admin/users'), child: Text(l10n.adminUsersGet)),
        FilledButton(onPressed: () => runGet('/customers'), child: Text(l10n.customersGet)),
        FilledButton(onPressed: () => runGet('/self/profile'), child: Text(l10n.selfProfileGet)),
      ],
      extra: Wrap(
        spacing: 12,
        runSpacing: 12,
        children: [
          OutlinedButton(
            onPressed: () => runPost('/admin/users', {
              'email': 'new.user@example.com',
              'password': 'secret123',
              'name': 'New User',
            }),
            child: Text(l10n.createDemoUser),
          ),
          OutlinedButton(
            onPressed: () => runPost('/customers', {
              'email': 'new.customer@example.com',
              'password': 'secret123',
              'name': 'New Customer',
            }),
            child: Text(l10n.createDemoCustomer),
          ),
        ],
      ),
    );
  }
}

class _AutomationCard extends ConsumerStatefulWidget {
  const _AutomationCard();

  @override
  ConsumerState<_AutomationCard> createState() => _AutomationCardState();
}

class _AutomationCardState extends ConsumerState<_AutomationCard> with _ApiClientMixin {
  bool loading = false;
  String output = '';

  Future<void> runPost(String path, Map<String, dynamic> payload) async {
    setState(() => loading = true);
    final dio = ref.read(dioProvider);
    try {
      final response = await dio.post(path, data: payload, options: buildOptions());
      setState(() => output = prettyJson(response.data));
    } finally {
      setState(() => loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    return _EndpointPanel(
      title: l10n.automationTitle,
      loading: loading,
      result: output,
      actions: [
        FilledButton(
          onPressed: () => runPost('/stripe/checkout-session', {
            'mode': 'subscription',
            'line_items': [
              {'price': 'price_demo', 'quantity': 1},
            ],
          }),
          child: Text(l10n.stripeCheckout),
        ),
        FilledButton(
          onPressed: () => runPost('/stripe/customer-portal', {'customer_id': 'cus_demo'}),
          child: Text(l10n.stripePortal),
        ),
        FilledButton(
          onPressed: () => runPost('/pdf/render', {'html': '<h1>Invoice Demo</h1>'}),
          child: Text(l10n.pdfRender),
        ),
        FilledButton(
          onPressed: () => runPost('/email/send', {
            'to': 'demo@example.com',
            'subject': 'Test Mail',
            'html': '<p>Mail Versand Test</p>',
          }),
          child: Text(l10n.emailSend),
        ),
      ],
    );
  }
}

class _EndpointPanel extends StatelessWidget {
  const _EndpointPanel({
    required this.title,
    required this.actions,
    required this.loading,
    required this.result,
    this.extra,
  });

  final String title;
  final List<Widget> actions;
  final bool loading;
  final String result;
  final Widget? extra;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(title, style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 12),
            Wrap(spacing: 8, runSpacing: 8, children: actions),
            if (extra != null) ...[
              const SizedBox(height: 12),
              extra!,
            ],
            const SizedBox(height: 12),
            if (loading) const LinearProgressIndicator(),
            const SizedBox(height: 12),
            Expanded(
              child: DecoratedBox(
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(12),
                  color: Theme.of(context).colorScheme.surfaceContainerHighest,
                ),
                child: Padding(
                  padding: const EdgeInsets.all(12),
                  child: SingleChildScrollView(
                    child: SelectableText(result.isEmpty ? l10n.noResponseYet : result),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
