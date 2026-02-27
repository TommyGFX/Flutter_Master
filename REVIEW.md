# REVIEW – Vollständiger Code-Review (Backend + Flutter)

## 1) Scope & Vorgehen

Dieses Review deckt die aktuelle Repository-Struktur in `backend/` und `flutter_app/` ab.
Geprüft wurden:
- Projektstruktur und Feature-Schnitt
- Controller-/Service-Funktionen (Backend)
- Feature-Controller und relevante UI-Flows (Flutter)
- Abhängigkeiten (inkl. Composer)

---

## 2) High-Level Architektur

## Backend (`backend/`)
- **Core-Layer**: Request/Response/Router/App/Error-Handling/DB-Bootstrap
- **Controller-Layer**: API-Endpunkte pro Domäne (Auth, Billing, Automation, Security, etc.)
- **Service-Layer**: Business-Logik, Payment-Provider-Adapter, Token, Mail/PDF, Integrationen
- **Plugin-Layer**: Plugin-Contracts + Plugin-Manager + Lifecycle-Verwaltung
- **Tests**: Contract + Regression-Tests mit klarer Domänenabdeckung

## Frontend (`flutter_app/`)
- **Core-Layer**: Theme, Dio-Client, globale Modelle
- **Feature-Layer**: Auth, Admin, Billing, Catalog, CRUD, Automation, Portal
- **App-Layer**: Router + Einstiegspunkt + i18n
- **Tests**: Navigation, Controller, Screen und Golden-Test

---

## 3) Feature-Review mit Funktionen & Struktur (inkl. JSON je Funktionsgruppe)

## 3.1 Authentifizierung & Session-Management

**Struktur**
- Backend: `AuthController`, `JwtService`, `RefreshTokenService`
- Frontend: `auth_controller.dart`, `login_screen.dart`

**Review-Notiz**
- Gute Trennung zwischen Login-Endpunkten (Company/Employee/Portal/Admin) und Token-Services.
- Refresh/Logout bereits vorgesehen; geeignet für produktive Session-Rotation.

```json
{
  "feature": "auth_session",
  "backend_functions": {
    "AuthController": [
      "loginCompany",
      "loginEmployee",
      "loginPortal",
      "loginAdmin",
      "refresh",
      "logout"
    ],
    "JwtService": ["issueToken", "verify"],
    "RefreshTokenService": ["issue", "consumeAndRotate", "revoke"]
  },
  "frontend_functions": {
    "AuthController": ["login", "applyCompanyContext"]
  }
}
```

## 3.2 Organisation, Rollen, Berechtigungen, Approvals

**Struktur**
- Backend: `OrgManagementController`, `AccountManagementController`, `AdminPluginController`, `ApprovalService`, `RbacService`, `AuditLogService`
- Frontend: `dashboard_screen.dart` (Role-Matrix, Audit-Log, Membership)

**Review-Notiz**
- RBAC + Audit + Approval-Flow sind als tragfähige Enterprise-Basis erkennbar.
- Kontextwechsel auf Company-Ebene ist sauber als eigener Anwendungsfall vorhanden.

```json
{
  "feature": "org_rbac_approvals",
  "backend_functions": {
    "OrgManagementController": [
      "listCompanies",
      "upsertCompany",
      "listMemberships",
      "assignMembership",
      "switchContext",
      "listRoles",
      "upsertRole",
      "listRoleCapabilities",
      "listAuditLogs",
      "exportAuditLogs"
    ],
    "AccountManagementController": [
      "listUsers",
      "createUser",
      "updateUser",
      "deleteUser",
      "selfProfile",
      "updateSelfProfile"
    ],
    "AdminPluginController": [
      "listRolePermissions",
      "updateRolePermissions",
      "listApprovals",
      "approve",
      "reject"
    ],
    "services": ["RbacService.can", "ApprovalService.decide", "AuditLogService.log"]
  },
  "frontend_functions": {
    "DashboardScreen": [
      "loadRoles",
      "savePermissions",
      "loadAuditLogs",
      "exportAuditLogs",
      "loadMemberships",
      "assignMembership",
      "switchContext",
      "loadApprovals",
      "decide"
    ]
  }
}
```

## 3.3 Plugin-Foundation & Plattform-Administration

**Struktur**
- Backend: `PluginFoundationController`, `PluginManager`, `PlatformAdminController`
- Frontend: Admin-Dashboard mit Plugin-Lifecycle

**Review-Notiz**
- Plugin-Verträge + Navigation-Contract + Lifecycle-Endpunkte vorhanden.
- Gute Basis für modulare SaaS-Erweiterbarkeit.

```json
{
  "feature": "plugin_platform_admin",
  "backend_functions": {
    "PluginFoundationController": [
      "pluginShell",
      "setFeatureFlag",
      "listFeatureFlags",
      "publishDomainEvent",
      "processOutbox",
      "outboxMetrics",
      "updateLifecycle"
    ],
    "PluginManager": ["registerPluginRoute", "hooks", "upsertDefinition"],
    "PlatformAdminController": ["impersonateCompany", "adminStats", "globalAuditLogs", "platformReports"]
  },
  "frontend_functions": {
    "DashboardScreen": ["loadShell", "loadPlugins", "togglePlugin", "impersonate"]
  }
}
```

## 3.4 Billing Core, Zahlungen, Abos, Stripe/PayPal

**Struktur**
- Backend: `BillingCoreController/Service`, `BillingPaymentsController/Service`, `SubscriptionsBillingController/Service`, `StripeController/Service`, Provider-Registries
- Frontend: `billing_flow_controller.dart`, `subscription_management_controller.dart`

**Review-Notiz**
- Sehr starker Ausbaugrad: Quote→Invoice→Payment inkl. Dunning und Provider-Abstraktion.
- Adapter/Registry-Muster reduziert Provider-Kopplung.

```json
{
  "feature": "billing_payments_subscriptions",
  "backend_functions": {
    "BillingCoreController": [
      "listDocuments",
      "getDocument",
      "createDocument",
      "updateDocument",
      "finalizeDocument",
      "convertToInvoice",
      "createCreditNote",
      "setStatus",
      "exportPdf",
      "history"
    ],
    "BillingPaymentsController": [
      "createPaymentLink",
      "listPaymentLinks",
      "recordPayment",
      "listPayments",
      "saveDunningConfig",
      "getDunningConfig",
      "runDunning",
      "listDunningCases",
      "saveBankAccount",
      "getBankAccount"
    ],
    "SubscriptionsBillingController": [
      "listPlans",
      "savePlan",
      "listContracts",
      "createContract",
      "updateContract",
      "changePlan",
      "runRecurring",
      "runAutoInvoicing",
      "runDunningRetention",
      "createPaymentMethodUpdateLink",
      "completePaymentMethodUpdate",
      "providerWebhook"
    ],
    "StripeController": ["createCheckoutSession", "createCustomerPortalSession", "webhook"]
  },
  "frontend_functions": {
    "BillingFlowController": [
      "runQuoteToPaidFlow",
      "ensureCustomer",
      "createQuote",
      "finalizeDocument",
      "convertQuoteToInvoice",
      "createPaymentLink",
      "recordPayment",
      "fetchHistoryEntries",
      "exportPdf"
    ],
    "SubscriptionManagementController": [
      "loadOverview",
      "runRecurringEngine",
      "runAutoInvoicing",
      "runDunningRetention",
      "createPaymentMethodUpdateLink"
    ]
  }
}
```

## 3.5 Dokumente, Versand, Templates, Portal

**Struktur**
- Backend: `DocumentController`, `DocumentDeliveryController`, `PdfRendererService`, `TenantMailerService`, `EmailQueueService`, `TemplateRendererService`
- Frontend: `portal_documents_controller.dart`, `portal_documents_screen.dart`

**Review-Notiz**
- End-to-End Dokumentfluss vorhanden (Rendern, E-Mail, Portal-Auslieferung, Event-Tracking).
- Queue-Basis vorhanden; für High-Load kann Worker-Orchestrierung ausgebaut werden.

```json
{
  "feature": "document_delivery_portal",
  "backend_functions": {
    "DocumentController": ["renderPdf", "sendEmail"],
    "DocumentDeliveryController": [
      "listTemplates",
      "upsertTemplate",
      "getProviderConfig",
      "upsertProviderConfig",
      "listPortalDocuments",
      "getPortalDocument",
      "trackEvent",
      "processQueue"
    ],
    "services": [
      "PdfRendererService.render",
      "TemplateRendererService.render",
      "TenantMailerService.send",
      "EmailQueueService.enqueue"
    ]
  },
  "frontend_functions": {
    "PortalDocumentsController": ["loadDocuments", "loadDocument"]
  }
}
```

## 3.6 Katalog & Pricing

**Struktur**
- Backend: `CatalogPricingController`, `CatalogPricingService`
- Frontend: `catalog_pricing_controller.dart`, `catalog_pricing_screen.dart`

**Review-Notiz**
- Produkt-/Preisliste-/Bundle-/Discount-Code-Management vollständig als API und UI vorbereitet.
- Quote-Berechnung als zentraler Endpunkt vorhanden.

```json
{
  "feature": "catalog_pricing",
  "backend_functions": {
    "CatalogPricingController": [
      "listProducts",
      "saveProduct",
      "updateProduct",
      "listPriceLists",
      "savePriceList",
      "updatePriceList",
      "listPriceListItems",
      "savePriceListItem",
      "listBundles",
      "saveBundle",
      "updateBundle",
      "listDiscountCodes",
      "saveDiscountCode",
      "calculateQuote"
    ]
  },
  "frontend_functions": {
    "CatalogPricingController": ["loadCatalog", "createProduct", "calculateQuote"]
  }
}
```

## 3.7 Automation & Integrationen

**Struktur**
- Backend: `AutomationIntegrationsController/Service`
- Frontend: `import_wizard_controller.dart`, `automation_integrations_screen.dart`

**Review-Notiz**
- Gute Abdeckung von API-Versionierung, Idempotency, Connector-Sync, Worker-Queue und Import-Wizard.

```json
{
  "feature": "automation_integrations",
  "backend_functions": {
    "AutomationIntegrationsController": [
      "listApiVersions",
      "registerApiVersion",
      "claimIdempotency",
      "listCrmConnectors",
      "upsertCrmConnector",
      "syncCrmEntity",
      "listTimeEntries",
      "upsertTimeEntry",
      "invoiceTimeEntries",
      "listAutomationCatalog",
      "enqueueAutomationRun",
      "processAutomationRuns",
      "importPreview",
      "executeImport"
    ]
  },
  "frontend_functions": {
    "ImportWizardController": ["preview", "execute", "processWorkerQueue"]
  }
}
```

## 3.8 Finance Reporting & Tax Compliance (DE)

**Struktur**
- Backend: `FinanceReportingController/Service`, `TaxComplianceDeController/Service`
- Frontend: indirekt über API-Nutzung (Admin/Billing-Flows)

**Review-Notiz**
- KPI/Open-Items/Tax-Reporting + Export/Connector Sync sind gut modularisiert.
- DE-Tax-Compliance enthält Preflight/Seal/Correction/eInvoice Import/Export.

```json
{
  "feature": "finance_reporting_tax_compliance",
  "backend_functions": {
    "FinanceReportingController": [
      "kpis",
      "openItems",
      "taxReport",
      "export",
      "exportStream",
      "listConnectors",
      "upsertConnector",
      "publishWebhook",
      "syncConnectors"
    ],
    "TaxComplianceDeController": [
      "getConfig",
      "saveConfig",
      "preflight",
      "seal",
      "createCorrection",
      "exportEInvoice",
      "importEInvoice"
    ]
  }
}
```

## 3.9 Security, GDPR, Backup/Restore, Reliability

**Struktur**
- Backend: `PlatformSecurityOpsController/Service`

**Review-Notiz**
- Enthält zentrale Security-/Ops-Funktionen inkl. Datenexport/-löschung und Backup-Routinen.
- Für Enterprise-Readiness bereits ungewöhnlich breit aufgestellt.

```json
{
  "feature": "platform_security_ops",
  "backend_functions": {
    "PlatformSecurityOpsController": [
      "gdprOverview",
      "upsertRetentionRule",
      "requestDataExport",
      "requestDeletion",
      "listAuthPolicies",
      "upsertAuthPolicy",
      "listBackups",
      "triggerBackup",
      "restoreBackup",
      "listArchiveRecords",
      "createArchiveRecord",
      "listReliabilityPolicies",
      "upsertReliabilityPolicy"
    ]
  }
}
```

## 3.10 Generic CRUD & Upload

**Struktur**
- Backend: `CrudController`, `UploadController`
- Frontend: `crud_screen.dart`

**Review-Notiz**
- Schneller Feature-Onboarding-Pfad für neue Ressourcen.
- Upload mit MIME-Prüfung sinnvoll als Mindestabsicherung.

```json
{
  "feature": "crud_upload",
  "backend_functions": {
    "CrudController": ["index", "store", "update", "destroy"],
    "UploadController": ["uploadImage", "uploadFile"]
  },
  "frontend_functions": {
    "CrudScreen": ["load", "create"]
  }
}
```

---

## 4) Qualitätsbewertung (Kurzfazit)

**Stärken**
- Saubere Layer-Aufteilung (Controller ↔ Service ↔ Core).
- Hohe Domänenabdeckung (Billing, Automation, Tax, Security, Plugins).
- Gute Testbasis mit Regression-/Contract-Tests.
- Frontend-Feature-Schnitt ist konsistent und API-nah.

**Risiken / Verbesserungen**
- Bei wachsender API-Größe empfiehlt sich OpenAPI-Spezifikation pro Modul.
- Für kritische Endpunkte sollten standardisierte Request-Validatoren ausgebaut werden.
- Queue/Worker-Pfade (Mail, Automation, Outbox) sollten mit Retry/Dead-letter-Konzept dokumentiert werden.
- Zusätzlich E2E-Szenarien für Multi-Tenant + RBAC + Billing-Webhooks aufnehmen.

---

## 5) Composer – Benötigte Pakete

Für das Backend wird laut `backend/composer.json` benötigt:

```json
{
  "require": {
    "php": ">=8.1",
    "firebase/php-jwt": "^6.10",
    "stripe/stripe-php": "^16.0",
    "dompdf/dompdf": "^2.0",
    "symfony/mailer": "^7.0",
    "symfony/mime": "^7.0"
  }
}
```

**Zweck je Paket (Kurz):**
- `firebase/php-jwt`: JWT erstellen/verifizieren.
- `stripe/stripe-php`: Stripe Checkout/Portal/Webhooks.
- `dompdf/dompdf`: PDF-Rendering für Dokumente.
- `symfony/mailer` + `symfony/mime`: E-Mail-Versand inkl. MIME-Verarbeitung.

---

## 6) Empfohlene nächste Schritte

1. Pro Feature einen API-Contract (OpenAPI) erzeugen.
2. Einheitliche Validation/Error-Schemas zentralisieren.
3. Observability erweitern (Metriken für Queue, Webhooks, Dunning, Retry).
4. Architektur-Entscheidungen (ADR) für Plugin-Lifecycle & Multi-Tenant-Security dokumentieren.

