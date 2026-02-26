import 'package:flutter/material.dart';

class DashboardScreen extends StatelessWidget {
  const DashboardScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final isMobile = MediaQuery.of(context).size.width < 900;

    return Scaffold(
      appBar: AppBar(title: const Text('📊 SaaS Dashboard')),
      drawer: isMobile ? const Drawer(child: _SideNav()) : null,
      body: Row(
        children: [
          if (!isMobile) const SizedBox(width: 240, child: _SideNav()),
          const Expanded(
            child: Center(
              child: Text('Willkommen im Multi-Tenant Admin/Portal Bereich'),
            ),
          ),
        ],
      ),
    );
  }
}

class _SideNav extends StatelessWidget {
  const _SideNav();

  @override
  Widget build(BuildContext context) {
    return ListView(
      children: [
        const DrawerHeader(child: Text('🧭 Navigation')),
        ListTile(
          leading: const Text('🧩'),
          title: const Text('Plugins'),
          onTap: () {},
        ),
        ListTile(
          leading: const Text('🗂️'),
          title: const Text('CRUD'),
          onTap: () => Navigator.pushNamed(context, '/crud'),
        ),
        ListTile(
          leading: const Text('💳'),
          title: const Text('Stripe'),
          onTap: () {},
        ),
      ],
    );
  }
}
