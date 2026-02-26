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
    final isMobile = MediaQuery.of(context).size.width < 900;

    return Scaffold(
      appBar: AppBar(title: const Text('📊 SaaS Dashboard (Multi-Tenant Admin)')),
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
              width: 260,
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
        const DrawerHeader(child: Text('🧭 Admin Navigation')),
        ListTile(
          leading: const Text('🏠'),
          title: const Text('Übersicht'),
          selected: selectedIndex == 0,
          onTap: () => onSelect(0),
        ),
        ListTile(
          leading: const Text('🧩'),
          title: const Text('Plugin Lifecycle'),
          selected: selectedIndex == 1,
          onTap: () => onSelect(1),
        ),
        ListTile(
          leading: const Text('🔐'),
          title: const Text('Rechteverwaltung'),
          selected: selectedIndex == 2,
          onTap: () => onSelect(2),
        ),
        ListTile(
          leading: const Text('🗂️'),
          title: const Text('CRUD'),
          onTap: () => Navigator.pushNamed(context, '/crud'),
        ),
      ],
    );
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
            const Text('Willkommen im Multi-Tenant Admin-Bereich', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
            const SizedBox(height: 8),
            Text('Tenant-Kontext: ${authState.tenantId ?? 'nicht gesetzt'}'),
            Text('Entrypoint: ${authState.entrypoint ?? '-'}'),
            Text('Berechtigungen: ${authState.permissions.isEmpty ? 'keine' : authState.permissions.join(', ')}'),
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

class _PluginLifecycleCardState extends ConsumerState<_PluginLifecycleCard> {
  bool isLoading = true;
  String? error;
  List<Map<String, dynamic>> plugins = [];

  @override
  void initState() {
    super.initState();
    loadPlugins();
  }

  Future<void> loadPlugins() async {
    final authState = ref.read(authControllerProvider);
    final tenantId = authState.tenantId ?? 'tenant_1';

    setState(() {
      isLoading = true;
      error = null;
    });

    try {
      final dio = ref.read(dioProvider);
      final response = await dio.get(
        '/admin/plugins',
        options: Options(headers: {
          'X-Tenant-Id': tenantId,
          'X-Permissions': authState.permissions.join(','),
        }),
      );

      final list = (response.data['data'] as List<dynamic>? ?? const []);
      setState(() {
        plugins = list
            .map((item) => Map<String, dynamic>.from(item as Map))
            .toList(growable: false);
      });
    } on DioException catch (exception) {
      setState(() => error = exception.response?.data.toString() ?? exception.message);
    } finally {
      setState(() => isLoading = false);
    }
  }

  Future<void> togglePlugin(Map<String, dynamic> plugin) async {
    final authState = ref.read(authControllerProvider);
    final tenantId = authState.tenantId ?? 'tenant_1';
    final pluginKey = plugin['plugin_key']?.toString() ?? '';
    final isActive = (plugin['is_active'] == 1 || plugin['is_active'] == true);

    if (pluginKey.isEmpty) {
      return;
    }

    final dio = ref.read(dioProvider);
    await dio.post(
      '/admin/plugins/$pluginKey/status',
      data: {'is_active': !isActive},
      options: Options(headers: {
        'X-Tenant-Id': tenantId,
        'X-Permissions': authState.permissions.join(','),
      }),
    );

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
                const Expanded(
                  child: Text('Plugin Lifecycle', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                ),
                IconButton(onPressed: loadPlugins, icon: const Icon(Icons.refresh)),
              ],
            ),
            const SizedBox(height: 8),
            if (error != null) Text('Fehler: $error', style: const TextStyle(color: Colors.red)),
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
                      subtitle: Text('Key: ${plugin['plugin_key']}'),
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

class _RolePermissionCardState extends ConsumerState<_RolePermissionCard> {
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
    final authState = ref.read(authControllerProvider);
    final tenantId = authState.tenantId ?? 'tenant_1';
    setState(() => isLoading = true);

    final dio = ref.read(dioProvider);
    final response = await dio.get(
      '/admin/roles/permissions',
      options: Options(headers: {
        'X-Tenant-Id': tenantId,
        'X-Permissions': authState.permissions.join(','),
      }),
    );

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
    Map<String, dynamic>? role;
    for (final item in roles) {
      if (item['role_key'] == roleKey) {
        role = item;
        break;
      }
    }

    if (role == null) {
      return const [];
    }

    return (role['permissions'] as List<dynamic>? ?? const [])
        .map((permission) => permission.toString())
        .toList(growable: false);
  }

  Future<void> savePermissions() async {
    if (selectedRole.isEmpty) {
      return;
    }

    final authState = ref.read(authControllerProvider);
    final tenantId = authState.tenantId ?? 'tenant_1';
    final permissions = permissionsCtrl.text
        .split(',')
        .map((permission) => permission.trim())
        .where((permission) => permission.isNotEmpty)
        .toList(growable: false);

    final dio = ref.read(dioProvider);
    await dio.put(
      '/admin/roles/$selectedRole/permissions',
      data: {'permissions': permissions},
      options: Options(headers: {
        'X-Tenant-Id': tenantId,
        'X-Permissions': authState.permissions.join(','),
      }),
    );

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
                  const Text('Rechteverwaltung (Role → Permissions)', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                  const SizedBox(height: 12),
                  DropdownButtonFormField<String>(
                    value: selectedRole.isEmpty ? null : selectedRole,
                    items: roles
                        .map(
                          (role) => DropdownMenuItem<String>(
                            value: role['role_key'].toString(),
                            child: Text('${role['name']} (${role['role_key']})'),
                          ),
                        )
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
                    decoration: const InputDecoration(
                      labelText: 'Permissions (kommagetrennt)',
                      hintText: 'z. B. plugins.manage, rbac.manage, crud.read',
                    ),
                  ),
                  const SizedBox(height: 12),
                  FilledButton(onPressed: savePermissions, child: const Text('Berechtigungen speichern')),
                ],
              ),
      ),
    );
  }
}
