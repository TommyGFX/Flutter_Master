import 'package:flutter/material.dart';

import '../features/admin/presentation/dashboard_screen.dart';
import '../features/auth/presentation/login_screen.dart';
import '../features/crud/presentation/crud_screen.dart';

class AppRouter {
  static Route<dynamic> onGenerateRoute(RouteSettings settings) {
    switch (settings.name) {
      case '/dashboard':
        return MaterialPageRoute(builder: (_) => const DashboardScreen());
      case '/crud':
        return MaterialPageRoute(builder: (_) => const CrudScreen());
      case '/login':
      default:
        return MaterialPageRoute(builder: (_) => const LoginScreen());
    }
  }
}
