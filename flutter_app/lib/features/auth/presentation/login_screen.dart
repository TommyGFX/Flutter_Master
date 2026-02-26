import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../l10n/l10n.dart';
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
  void dispose() {
    emailCtrl.dispose();
    passwordCtrl.dispose();
    tenantCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;

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
                  Text(l10n.loginHeadline, style: const TextStyle(fontSize: 22, fontWeight: FontWeight.bold)),
                  const SizedBox(height: 16),
                  DropdownButtonFormField<String>(
                    value: endpoint,
                    items: [
                      DropdownMenuItem(value: 'login/company', child: Text(l10n.companyLogin)),
                      DropdownMenuItem(value: 'login/employee', child: Text(l10n.employeeLogin)),
                      DropdownMenuItem(value: 'login/portal', child: Text(l10n.portalLogin)),
                      DropdownMenuItem(value: 'admin/login', child: Text(l10n.superadminLogin)),
                    ],
                    onChanged: (v) => setState(() => endpoint = v ?? endpoint),
                  ),
                  const SizedBox(height: 12),
                  TextField(controller: tenantCtrl, decoration: InputDecoration(labelText: l10n.tenantIdLabel)),
                  TextField(controller: emailCtrl, decoration: InputDecoration(labelText: l10n.emailLabel)),
                  TextField(
                    controller: passwordCtrl,
                    obscureText: true,
                    decoration: InputDecoration(labelText: l10n.passwordLabel),
                  ),
                  const SizedBox(height: 16),
                  FilledButton(
                    onPressed: () async {
                      await ref.read(authControllerProvider.notifier).login(
                            endpoint: endpoint,
                            email: emailCtrl.text,
                            password: passwordCtrl.text,
                            tenant: tenantCtrl.text,
                          );
                      if (context.mounted) {
                        Navigator.pushReplacementNamed(context, '/dashboard');
                      }
                    },
                    child: Text(l10n.signIn),
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
