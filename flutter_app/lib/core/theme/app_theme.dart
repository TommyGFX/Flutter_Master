import 'package:flutter/material.dart';

class AppTheme {
  static ThemeData light() {
    final scheme = ColorScheme.fromSeed(seedColor: Colors.indigo, brightness: Brightness.light);
    return ThemeData(
      useMaterial3: true,
      colorScheme: scheme,
      scaffoldBackgroundColor: const Color(0xFFF6F8FB),
      appBarTheme: const AppBarTheme(centerTitle: false),
    );
  }

  static ThemeData dark() {
    final scheme = ColorScheme.fromSeed(seedColor: Colors.indigo, brightness: Brightness.dark);
    return ThemeData(useMaterial3: true, colorScheme: scheme);
  }
}
