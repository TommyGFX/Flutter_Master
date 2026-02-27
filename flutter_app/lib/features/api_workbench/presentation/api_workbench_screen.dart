import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/dio_client.dart';
import '../../auth/auth_controller.dart';
import '../domain/backend_endpoints.dart';

class ApiWorkbenchScreen extends ConsumerStatefulWidget {
  const ApiWorkbenchScreen({super.key});

  @override
  ConsumerState<ApiWorkbenchScreen> createState() => _ApiWorkbenchScreenState();
}

class _ApiWorkbenchScreenState extends ConsumerState<ApiWorkbenchScreen> {
  BackendEndpoint selected = backendEndpoints.first;
  final pathCtrl = TextEditingController();
  final queryCtrl = TextEditingController(text: '{}');
  final bodyCtrl = TextEditingController(text: '{}');
  String responseText = '';
  String? errorText;
  bool isLoading = false;

  @override
  void initState() {
    super.initState();
    pathCtrl.text = selected.path;
  }

  @override
  void dispose() {
    pathCtrl.dispose();
    queryCtrl.dispose();
    bodyCtrl.dispose();
    super.dispose();
  }

  Future<void> callEndpoint() async {
    Map<String, dynamic> query;
    Map<String, dynamic> body;
    try {
      query = (jsonDecode(queryCtrl.text) as Map).cast<String, dynamic>();
      body = (jsonDecode(bodyCtrl.text) as Map).cast<String, dynamic>();
    } catch (_) {
      setState(() {
        errorText = 'Ungültiges JSON in Query oder Body.';
        responseText = '';
      });
      return;
    }

    if (pathCtrl.text.trim().isEmpty) {
      setState(() {
        errorText = 'Pfad darf nicht leer sein.';
      });
      return;
    }

    final auth = ref.read(authControllerProvider);
    final headers = {
      'X-Tenant-Id': auth.tenantId ?? '',
      if ((auth.companyId ?? '').isNotEmpty) 'X-Company-Id': auth.companyId!,
      if ((auth.userId ?? '').isNotEmpty) 'X-User-Id': auth.userId!,
      if ((auth.token ?? '').isNotEmpty) 'Authorization': 'Bearer ${auth.token}',
      if (auth.permissions.isNotEmpty) 'X-Permissions': auth.permissions.join(','),
    };

    final dio = ref.read(dioProvider);
    setState(() {
      isLoading = true;
      errorText = null;
      responseText = '';
    });

    try {
      final response = await dio.request<dynamic>(
        pathCtrl.text.trim(),
        queryParameters: query.isEmpty ? null : query,
        data: const {'GET', 'DELETE'}.contains(selected.method) ? null : body,
        options: Options(method: selected.method, headers: headers),
      );
      const encoder = JsonEncoder.withIndent('  ');
      setState(() {
        responseText = encoder.convert(response.data);
      });
    } on DioException catch (e) {
      setState(() {
        errorText = e.response?.data?.toString() ?? e.message ?? 'Unbekannter API-Fehler';
      });
    } finally {
      setState(() {
        isLoading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final groups = <String, List<BackendEndpoint>>{};
    for (final endpoint in backendEndpoints) {
      groups.putIfAbsent(endpoint.group, () => []).add(endpoint);
    }

    return Scaffold(
      appBar: AppBar(title: const Text('Endpoint Workbench (Admin)')),
      body: Row(
        children: [
          SizedBox(
            width: 340,
            child: ListView(
              children: groups.entries
                  .map(
                    (entry) => ExpansionTile(
                      title: Text('${entry.key} (${entry.value.length})'),
                      children: entry.value
                          .map(
                            (endpoint) => ListTile(
                              dense: true,
                              title: Text('${endpoint.method} ${endpoint.path}'),
                              selected: endpoint.key == selected.key,
                              onTap: () {
                                setState(() {
                                  selected = endpoint;
                                  pathCtrl.text = endpoint.path;
                                });
                              },
                            ),
                          )
                          .toList(),
                    ),
                  )
                  .toList(),
            ),
          ),
          const VerticalDivider(width: 1),
          Expanded(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Ausgewählt: ${selected.method} ${selected.path}', style: Theme.of(context).textTheme.titleMedium),
                  const SizedBox(height: 12),
                  TextField(controller: pathCtrl, decoration: const InputDecoration(labelText: 'Pfad (Path-Parameter ersetzen)')),
                  const SizedBox(height: 8),
                  TextField(controller: queryCtrl, decoration: const InputDecoration(labelText: 'Query JSON'), maxLines: 3),
                  const SizedBox(height: 8),
                  TextField(controller: bodyCtrl, decoration: const InputDecoration(labelText: 'Body JSON'), maxLines: 5),
                  const SizedBox(height: 12),
                  FilledButton(onPressed: isLoading ? null : callEndpoint, child: Text(isLoading ? 'Lädt…' : 'Request senden')),
                  const SizedBox(height: 12),
                  if (isLoading) const LinearProgressIndicator(),
                  if (!isLoading && errorText != null)
                    Card(
                      color: Theme.of(context).colorScheme.errorContainer,
                      child: Padding(
                        padding: const EdgeInsets.all(12),
                        child: Text(errorText!),
                      ),
                    ),
                  if (!isLoading && errorText == null && responseText.isEmpty)
                    const Text('Noch keine Antwort. Bitte Request absenden.'),
                  if (!isLoading && responseText.isNotEmpty)
                    Expanded(
                      child: Card(
                        child: SingleChildScrollView(
                          padding: const EdgeInsets.all(12),
                          child: SelectableText(responseText),
                        ),
                      ),
                    ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
