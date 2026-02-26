import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../auth_controller.dart';

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});

  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  final emailCtrl = TextEditingController(text: 'demo@example.com');
  final passwordCtrl = TextEditingController(text: 'secret');
  final tenantCtrl = TextEditingController(text: 'tenant_1');

  String endpoint = 'login/company';

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 460),
          child: Card(
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Text('🚀 Flutter Master SaaS Login', style: TextStyle(fontSize: 22, fontWeight: FontWeight.bold)),
                  const SizedBox(height: 16),
                  DropdownButtonFormField<String>(
                    value: endpoint,
                    items: const [
                      DropdownMenuItem(value: 'login/company', child: Text('🏢 Company Login')),
                      DropdownMenuItem(value: 'login/employee', child: Text('👨‍💼 Mitarbeiter Login')),
                      DropdownMenuItem(value: 'login/portal', child: Text('🧑 Customer Portal Login')),
                      DropdownMenuItem(value: 'admin/login', child: Text('🛡️ Superadmin Login')),
                    ],
                    onChanged: (v) => setState(() => endpoint = v ?? endpoint),
                  ),
                  const SizedBox(height: 12),
                  TextField(controller: tenantCtrl, decoration: const InputDecoration(labelText: 'Tenant ID')),
                  TextField(controller: emailCtrl, decoration: const InputDecoration(labelText: 'E-Mail')),
                  TextField(controller: passwordCtrl, obscureText: true, decoration: const InputDecoration(labelText: 'Passwort')),
                  const SizedBox(height: 16),
                  FilledButton(
                    onPressed: () async {
                      await ref.read(authControllerProvider.notifier).login(
                            endpoint: endpoint,
                            email: emailCtrl.text,
                            password: passwordCtrl.text,
                            tenant: tenantCtrl.text,
                          );
                      if (mounted) {
                        Navigator.pushReplacementNamed(context, '/dashboard');
                      }
                    },
                    child: const Text('Anmelden'),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
