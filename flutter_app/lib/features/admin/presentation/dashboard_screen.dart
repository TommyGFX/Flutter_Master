import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/dio_client.dart';
import '../../auth/auth_controller.dart';

class DashboardScreen extends ConsumerStatefulWidget {
  const DashboardScreen({super.key});

  @override
  ConsumerState<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends ConsumerState<DashboardScreen> {
  int selectedIndex = 0;

  @override
  Widget build(BuildContext context) {
    final isMobile = MediaQuery.of(context).size.width < 980;

    return Scaffold(
      appBar: AppBar(title: const Text('SaaS Control Center')),
      drawer: isMobile
          ? Drawer(
              child: _SideNav(
                selectedIndex: selectedIndex,
                onSelect: (index) {
                  setState(() => selectedIndex = index);
                  Navigator.pop(context);
                },
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
                onSelect: (index) => setState(() => selectedIndex = index),
              ),
            ),
          Expanded(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: IndexedStack(
                index: selectedIndex,
                children: const [
                  _AdminOverview(),
                  _PluginLifecycleCard(),
                  _RolePermissionCard(),
                  _ApprovalCard(),
                  _PlatformInsightsCard(),
                  _AccountsCard(),
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
  const _SideNav({required this.selectedIndex, required this.onSelect});

  final int selectedIndex;
  final ValueChanged<int> onSelect;

  @override
  Widget build(BuildContext context) {
    return ListView(
      children: [
        const DrawerHeader(child: Text('Admin Navigation')),
        _NavTile(label: 'Übersicht', icon: Icons.home_outlined, selected: selectedIndex == 0, onTap: () => onSelect(0)),
        _NavTile(label: 'Plugin Lifecycle', icon: Icons.extension_outlined, selected: selectedIndex == 1, onTap: () => onSelect(1)),
        _NavTile(label: 'Rechteverwaltung', icon: Icons.lock_outline, selected: selectedIndex == 2, onTap: () => onSelect(2)),
        _NavTile(label: 'Approvals & Audit', icon: Icons.approval_outlined, selected: selectedIndex == 3, onTap: () => onSelect(3)),
        _NavTile(label: 'Platform Insights', icon: Icons.query_stats_outlined, selected: selectedIndex == 4, onTap: () => onSelect(4)),
        _NavTile(label: 'User & Customer', icon: Icons.groups_outlined, selected: selectedIndex == 5, onTap: () => onSelect(5)),
        _NavTile(label: 'Billing / PDF / Mail', icon: Icons.auto_awesome_outlined, selected: selectedIndex == 6, onTap: () => onSelect(6)),
        ListTile(
          leading: const Icon(Icons.dataset_outlined),
          title: const Text('CRUD Playground'),
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

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Mandantenfähige Übersicht', style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 12),
            Text('Tenant: ${authState.tenantId ?? 'nicht gesetzt'}'),
            Text('Entrypoint: ${authState.entrypoint ?? '-'}'),
            Text('Berechtigungen: ${authState.permissions.isEmpty ? 'keine' : authState.permissions.join(', ')}'),
            const SizedBox(height: 12),
            Wrap(
              spacing: 12,
              runSpacing: 12,
              children: const [
                Chip(label: Text('Plugins + RBAC + Approval Flow')),
                Chip(label: Text('Platform Insights + Impersonation')),
                Chip(label: Text('Users + Customers + Self Profile')),
                Chip(label: Text('Stripe + PDF + Mail + CRUD')),
              ],
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
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(child: Text('Plugin Lifecycle', style: Theme.of(context).textTheme.titleLarge)),
                IconButton(onPressed: loadPlugins, icon: const Icon(Icons.refresh)),
              ],
            ),
            if (error != null) Text('Fehler: $error', style: TextStyle(color: Theme.of(context).colorScheme.error)),
            const SizedBox(height: 8),
            if (isLoading)
              const Center(child: CircularProgressIndicator())
            else if (plugins.isEmpty)
              const Text('Keine Plugins für diesen Tenant registriert.')
            else
              Expanded(
                child: ListView.builder(
                  itemCount: plugins.length,
                  itemBuilder: (context, index) {
                    final plugin = plugins[index];
                    final isActive = plugin['is_active'] == 1 || plugin['is_active'] == true;
                    return SwitchListTile(
                      title: Text(plugin['display_name']?.toString() ?? plugin['plugin_key'].toString()),
                      subtitle: Text('Key: ${plugin['plugin_key']} (Flow: Approval)'),
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
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: isLoading
            ? const Center(child: CircularProgressIndicator())
            : Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Rechteverwaltung', style: Theme.of(context).textTheme.titleLarge),
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
                    decoration: const InputDecoration(labelText: 'Permissions (kommagetrennt)'),
                  ),
                  const SizedBox(height: 12),
                  FilledButton(onPressed: savePermissions, child: const Text('Änderung als Approval einreichen')),
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
    return _EndpointPanel(
      title: 'Approval Workflow + Audit Trail',
      actions: [
        FilledButton(onPressed: loadApprovals, child: const Text('Approvals laden')),
      ],
      loading: loading,
      result: result,
      extra: Row(
        children: [
          Expanded(
            child: OutlinedButton(
              onPressed: () => decide(1, true),
              child: const Text('Demo: Approval #1 bestätigen'),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: OutlinedButton(
              onPressed: () => decide(1, false),
              child: const Text('Demo: Approval #1 ablehnen'),
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
    return _EndpointPanel(
      title: 'Platform Insights / Superadmin',
      loading: loading,
      result: output,
      actions: [
        FilledButton(onPressed: () => runGet('/platform/admin-stats'), child: const Text('Admin Stats')),
        FilledButton(onPressed: () => runGet('/platform/audit-logs'), child: const Text('Audit Logs')),
        FilledButton(onPressed: () => runGet('/platform/reports'), child: const Text('Reports')),
      ],
      extra: Row(
        children: [
          Expanded(child: TextField(controller: companyCtrl, decoration: const InputDecoration(labelText: 'Tenant für Impersonation'))),
          const SizedBox(width: 12),
          FilledButton.tonal(onPressed: impersonate, child: const Text('Impersonate')),
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
    return _EndpointPanel(
      title: 'Tenant Admin, User, Customer, Self Profile',
      loading: loading,
      result: output,
      actions: [
        FilledButton(onPressed: () => runGet('/admin/users'), child: const Text('Admin Users (GET)')),
        FilledButton(onPressed: () => runGet('/customers'), child: const Text('Customers (GET)')),
        FilledButton(onPressed: () => runGet('/self/profile'), child: const Text('Self Profile (GET)')),
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
            child: const Text('Demo User erstellen'),
          ),
          OutlinedButton(
            onPressed: () => runPost('/customers', {
              'email': 'new.customer@example.com',
              'password': 'secret123',
              'name': 'New Customer',
            }),
            child: const Text('Demo Customer erstellen'),
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
    return _EndpointPanel(
      title: 'Stripe / PDF / Email Integrationen',
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
          child: const Text('Stripe Checkout Session'),
        ),
        FilledButton(
          onPressed: () => runPost('/stripe/customer-portal', {'customer_id': 'cus_demo'}),
          child: const Text('Stripe Customer Portal'),
        ),
        FilledButton(
          onPressed: () => runPost('/pdf/render', {'html': '<h1>Invoice Demo</h1>'}),
          child: const Text('PDF Render'),
        ),
        FilledButton(
          onPressed: () => runPost('/email/send', {
            'to': 'demo@example.com',
            'subject': 'Test Mail',
            'html': '<p>Mail Versand Test</p>',
          }),
          child: const Text('Email Send'),
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
                    child: SelectableText(result.isEmpty ? 'Noch keine Antwort geladen.' : result),
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
