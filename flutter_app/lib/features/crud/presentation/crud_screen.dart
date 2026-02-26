import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/dio_client.dart';
import '../../../l10n/l10n.dart';

class CrudScreen extends ConsumerStatefulWidget {
  const CrudScreen({super.key});

  @override
  ConsumerState<CrudScreen> createState() => _CrudScreenState();
}

class _CrudScreenState extends ConsumerState<CrudScreen> {
  List<dynamic> items = [];
  final nameCtrl = TextEditingController();

  @override
  void initState() {
    super.initState();
    load();
  }

  @override
  void dispose() {
    nameCtrl.dispose();
    super.dispose();
  }

  Future<void> load() async {
    final dio = ref.read(dioProvider);
    final response = await dio.get('/crud/crm_items', options: Options(headers: {'X-Tenant-Id': 'tenant_1'}));
    setState(() => items = (response.data['data'] as List<dynamic>? ?? []));
  }

  Future<void> create() async {
    final dio = ref.read(dioProvider);
    await dio.post('/crud/crm_items', data: {'name': nameCtrl.text}, options: Options(headers: {'X-Tenant-Id': 'tenant_1'}));
    nameCtrl.clear();
    await load();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

    return Scaffold(
      appBar: AppBar(title: Text(l10n.crudTitle)),
      body: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          children: [
            Row(
              children: [
                Expanded(child: TextField(controller: nameCtrl, decoration: InputDecoration(labelText: l10n.nameLabel))),
                const SizedBox(width: 12),
                FilledButton(onPressed: create, child: Text(l10n.create)),
              ],
            ),
            const SizedBox(height: 20),
            Expanded(
              child: ListView.builder(
                itemCount: items.length,
                itemBuilder: (context, i) => ListTile(title: Text('${items[i]['id']} - ${items[i]['name']}')),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
