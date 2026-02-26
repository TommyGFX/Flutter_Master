import 'package:flutter/widgets.dart';

class AppLocalizations {
  AppLocalizations(this.locale);

  final Locale locale;

  static const supportedLocales = [Locale('en'), Locale('de')];

  static const LocalizationsDelegate<AppLocalizations> delegate = _AppLocalizationsDelegate();

  static AppLocalizations? of(BuildContext context) {
    return Localizations.of<AppLocalizations>(context, AppLocalizations);
  }

  static const _localizedValues = <String, Map<String, String>>{
    'en': {
      'appTitle': 'Flutter Master SaaS',
      'loginHeadline': '🚀 Flutter Master SaaS Login',
      'tenantIdLabel': 'Tenant ID',
      'emailLabel': 'Email',
      'passwordLabel': 'Password',
      'signIn': 'Sign in',
      'companyLogin': '🏢 Company Login',
      'employeeLogin': '👨‍💼 Employee Login',
      'portalLogin': '🧑 Customer Portal Login',
      'superadminLogin': '🛡️ Superadmin Login',
      'controlCenter': 'SaaS Control Center',
      'adminNavigation': 'Admin Navigation',
      'overview': 'Overview',
      'pluginLifecycle': 'Plugin Lifecycle',
      'permissionsManagement': 'Permission Management',
      'approvalsAudit': 'Approvals & Audit',
      'platformInsights': 'Platform Insights',
      'usersCustomers': 'Users & Customers',
      'billingPdfMail': 'Billing / PDF / Mail',
      'crudPlayground': 'CRUD Playground',
      'tenantOverview': 'Tenant Overview',
      'notSet': 'not set',
      'none': 'none',
      'chipPlugins': 'Plugins + RBAC + Approval Flow',
      'chipInsights': 'Platform Insights + Impersonation',
      'chipUsers': 'Users + Customers + Self Profile',
      'chipAutomation': 'Stripe + PDF + Mail + CRUD',
      'noPlugins': 'No plugins registered for this tenant.',
      'permissionsCommaSeparated': 'Permissions (comma-separated)',
      'submitApproval': 'Submit change as approval',
      'loadApprovals': 'Load approvals',
      'approveDemo': 'Demo: Approve approval #1',
      'rejectDemo': 'Demo: Reject approval #1',
      'adminStats': 'Admin Stats',
      'auditLogs': 'Audit Logs',
      'reports': 'Reports',
      'tenantForImpersonation': 'Tenant for impersonation',
      'impersonate': 'Impersonate',
      'tenantAdminUserProfile': 'Tenant Admin, User, Customer, Self Profile',
      'adminUsersGet': 'Admin Users (GET)',
      'customersGet': 'Customers (GET)',
      'selfProfileGet': 'Self Profile (GET)',
      'createDemoUser': 'Create demo user',
      'createDemoCustomer': 'Create demo customer',
      'automationTitle': 'Stripe / PDF / Email Integrations',
      'stripeCheckout': 'Stripe Checkout Session',
      'stripePortal': 'Stripe Customer Portal',
      'pdfRender': 'PDF Render',
      'emailSend': 'Email Send',
      'noResponseYet': 'No response loaded yet.',
      'crudTitle': '🗂️ CRUD',
      'nameLabel': 'Name',
      'create': 'Create'
    },
    'de': {
      'appTitle': 'Flutter Master SaaS',
      'loginHeadline': '🚀 Flutter Master SaaS Login',
      'tenantIdLabel': 'Tenant-ID',
      'emailLabel': 'E-Mail',
      'passwordLabel': 'Passwort',
      'signIn': 'Anmelden',
      'companyLogin': '🏢 Firmen-Login',
      'employeeLogin': '👨‍💼 Mitarbeiter-Login',
      'portalLogin': '🧑 Kundenportal-Login',
      'superadminLogin': '🛡️ Superadmin-Login',
      'controlCenter': 'SaaS Control Center',
      'adminNavigation': 'Admin-Navigation',
      'overview': 'Übersicht',
      'pluginLifecycle': 'Plugin Lifecycle',
      'permissionsManagement': 'Rechteverwaltung',
      'approvalsAudit': 'Approvals & Audit',
      'platformInsights': 'Platform Insights',
      'usersCustomers': 'User & Customer',
      'billingPdfMail': 'Billing / PDF / Mail',
      'crudPlayground': 'CRUD Playground',
      'tenantOverview': 'Mandantenfähige Übersicht',
      'notSet': 'nicht gesetzt',
      'none': 'keine',
      'chipPlugins': 'Plugins + RBAC + Approval Flow',
      'chipInsights': 'Platform Insights + Impersonation',
      'chipUsers': 'Users + Customers + Self Profile',
      'chipAutomation': 'Stripe + PDF + Mail + CRUD',
      'noPlugins': 'Keine Plugins für diesen Tenant registriert.',
      'permissionsCommaSeparated': 'Permissions (kommagetrennt)',
      'submitApproval': 'Änderung als Approval einreichen',
      'loadApprovals': 'Approvals laden',
      'approveDemo': 'Demo: Approval #1 bestätigen',
      'rejectDemo': 'Demo: Approval #1 ablehnen',
      'adminStats': 'Admin Stats',
      'auditLogs': 'Audit Logs',
      'reports': 'Reports',
      'tenantForImpersonation': 'Tenant für Impersonation',
      'impersonate': 'Impersonate',
      'tenantAdminUserProfile': 'Tenant Admin, User, Customer, Self Profile',
      'adminUsersGet': 'Admin Users (GET)',
      'customersGet': 'Customers (GET)',
      'selfProfileGet': 'Self Profile (GET)',
      'createDemoUser': 'Demo-User erstellen',
      'createDemoCustomer': 'Demo-Customer erstellen',
      'automationTitle': 'Stripe / PDF / Email Integrationen',
      'stripeCheckout': 'Stripe Checkout Session',
      'stripePortal': 'Stripe Customer Portal',
      'pdfRender': 'PDF Render',
      'emailSend': 'Email Send',
      'noResponseYet': 'Noch keine Antwort geladen.',
      'crudTitle': '🗂️ CRUD',
      'nameLabel': 'Name',
      'create': 'Erstellen'
    },
  };

  String _value(String key) => _localizedValues[locale.languageCode]?[key] ?? _localizedValues['en']![key]!;

  String get appTitle => _value('appTitle');
  String get loginHeadline => _value('loginHeadline');
  String get tenantIdLabel => _value('tenantIdLabel');
  String get emailLabel => _value('emailLabel');
  String get passwordLabel => _value('passwordLabel');
  String get signIn => _value('signIn');
  String get companyLogin => _value('companyLogin');
  String get employeeLogin => _value('employeeLogin');
  String get portalLogin => _value('portalLogin');
  String get superadminLogin => _value('superadminLogin');
  String get controlCenter => _value('controlCenter');
  String get adminNavigation => _value('adminNavigation');
  String get overview => _value('overview');
  String get pluginLifecycle => _value('pluginLifecycle');
  String get permissionsManagement => _value('permissionsManagement');
  String get approvalsAudit => _value('approvalsAudit');
  String get platformInsights => _value('platformInsights');
  String get usersCustomers => _value('usersCustomers');
  String get billingPdfMail => _value('billingPdfMail');
  String get crudPlayground => _value('crudPlayground');
  String get tenantOverview => _value('tenantOverview');
  String get notSet => _value('notSet');
  String get none => _value('none');
  String get chipPlugins => _value('chipPlugins');
  String get chipInsights => _value('chipInsights');
  String get chipUsers => _value('chipUsers');
  String get chipAutomation => _value('chipAutomation');
  String get noPlugins => _value('noPlugins');
  String get permissionsCommaSeparated => _value('permissionsCommaSeparated');
  String get submitApproval => _value('submitApproval');
  String get loadApprovals => _value('loadApprovals');
  String get approveDemo => _value('approveDemo');
  String get rejectDemo => _value('rejectDemo');
  String get adminStats => _value('adminStats');
  String get auditLogs => _value('auditLogs');
  String get reports => _value('reports');
  String get tenantForImpersonation => _value('tenantForImpersonation');
  String get impersonate => _value('impersonate');
  String get tenantAdminUserProfile => _value('tenantAdminUserProfile');
  String get adminUsersGet => _value('adminUsersGet');
  String get customersGet => _value('customersGet');
  String get selfProfileGet => _value('selfProfileGet');
  String get createDemoUser => _value('createDemoUser');
  String get createDemoCustomer => _value('createDemoCustomer');
  String get automationTitle => _value('automationTitle');
  String get stripeCheckout => _value('stripeCheckout');
  String get stripePortal => _value('stripePortal');
  String get pdfRender => _value('pdfRender');
  String get emailSend => _value('emailSend');
  String get noResponseYet => _value('noResponseYet');
  String get crudTitle => _value('crudTitle');
  String get nameLabel => _value('nameLabel');
  String get create => _value('create');

  String tenantValue(String tenant) => 'Tenant: $tenant';
  String entrypointValue(String entrypoint) => 'Entrypoint: $entrypoint';
  String permissionsValue(String permissions) => '${_value('permissionsManagement') == 'Rechteverwaltung' ? 'Berechtigungen' : 'Permissions'}: $permissions';
  String errorWithMessage(String message) => '${locale.languageCode == 'de' ? 'Fehler' : 'Error'}: $message';
  String pluginSubtitle(String key) => 'Key: $key (Flow: Approval)';
}

class _AppLocalizationsDelegate extends LocalizationsDelegate<AppLocalizations> {
  const _AppLocalizationsDelegate();

  @override
  bool isSupported(Locale locale) => ['en', 'de'].contains(locale.languageCode);

  @override
  Future<AppLocalizations> load(Locale locale) async => AppLocalizations(locale);

  @override
  bool shouldReload(_AppLocalizationsDelegate old) => false;
}
