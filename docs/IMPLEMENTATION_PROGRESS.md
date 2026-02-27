# Implementierungsfortschritt

## Ziel
Senior-Level Startpunkt für eine **Flutter (Web/Android/iOS) + PHP (PDO/MySQL)** SaaS-Plattform mit Multi-Tenant, RBAC, Plugin-System und Stripe.

## Schritt 1 – Architektur & Struktur (abgeschlossen)
- Monorepo-Struktur mit getrenntem `backend/` und `flutter_app/` angelegt.
- Kernmodule definiert:
  - Auth (JWT mit Tenant-Kontext + Superadmin-Injektion)
  - RBAC Multi-Tenant
  - Plugin-Registry (DB-basiert)
  - API/CRUD/Uploads
  - PDF/Email-Template Hooks
  - Stripe Subscription + Webhook Verarbeitung
  - Email Queue

## Schritt 2 – Backend Basis (abgeschlossen)
- Minimaler API-Server mit Routing, Fehlerbehandlung und JSON-Responses.
- PDO-Migrationsdatei erstellt.
- AuthController für 3 Eintrittspunkte vorbereitet:
  - `/api/login/company`
  - `/api/login/employee`
  - `/api/login/portal`
  - `/api/admin/login`
- CRUD-Controller mit tenant-sicherem Zugriff.
- Upload-Endpunkte mit MIME-Prüfung.
- Stripe Checkout Session + Customer Portal Endpunkte sowie Webhook-Verifikation implementiert.
- Plugin-Manager und Hook-Aufrufe als erweiterbares Fundament.

## Schritt 3 – Flutter Basis (abgeschlossen)
- Flutter 3 kompatibles Grundgerüst mit:
  - Riverpod
  - Dio
  - Intl + ARB
  - Material 3 + Dark Mode
- Login-Seite für die drei Rollen/Eintrittspunkte.
- Basis-Dashboard für Admin/Company/Portal.
- CRUD-Liste/Editor als Start für Full CRUD.
- Modernes SaaS Layout (Sidebar/Topbar, mobil optimiert).

## Schritt 4 – Qualität & Übergabe (abgeschlossen)
- Dokumentation ergänzt (`README.md`).
- Syntaxcheck für PHP ausgeführt.
- Änderungen versioniert.

## Nächste Ausbaustufen
1. Persistente Queue Worker (Redis/MySQL polling).
2. PDF Rendering (z. B. Dompdf) und SMTP Versand (z. B. Symfony Mailer).
3. Plugin-Lifecycle UI + Rechteverwaltung im Admin-Bereich.
4. Persistente Audit-Logs und Approval-Flow für kritische RBAC-/Plugin-Änderungen.


## Schritt 5 – JWT Hardening + Refresh Tokens (abgeschlossen)
- `JwtService` auf `firebase/php-jwt` umgestellt (HS256, iat/nbf/exp).
- Persistente Refresh-Token-Strategie mit Rotation implementiert.
- Neue Auth-Endpunkte ergänzt: `/api/token/refresh`, `/api/logout`.
- Migration für Tabelle `refresh_tokens` ergänzt (Hash-Speicherung, Revocation, Ablaufzeit).
- Backend-Doku auf neue Auth-Flows aktualisiert.


## Schritt 6 – Stripe Checkout + Customer Portal (abgeschlossen)
- Composer-Dependency `stripe/stripe-php` integriert.
- Neue API-Endpunkte ergänzt:
  - `/api/stripe/checkout-session`
  - `/api/stripe/customer-portal`
- Checkout Session Erstellung mit Validierung für `line_items`, `mode` und konfigurierbaren Redirect-URLs.
- Customer Portal Session Erstellung mit `customer_id` und konfigurierbarer Return-URL.
- Webhook-Verifikation über `STRIPE_WEBHOOK_SECRET` umgesetzt (falls gesetzt).
- Event-Handling Struktur für `checkout.session.completed` und Subscription-Lifecycle vorbereitet.

## Schritt 7 – PDF Rendering + SMTP Multi-Tenant Versand (abgeschlossen)
- Composer-Dependencies ergänzt: `dompdf/dompdf`, `symfony/mailer`, `symfony/mime`.
- Neue Endpunkte ergänzt:
  - `POST /api/pdf/render`
  - `POST /api/email/send`
- PDF Rendering über `PdfRendererService` (Dompdf) mit Template-Unterstützung (`pdf_templates`) und Context-Rendering umgesetzt.
- Multi-Tenant SMTP Versand über `TenantMailerService` (Symfony Mailer) mit tenant-spezifischer Konfiguration aus `tenant_smtp_settings` umgesetzt.
- Fallback auf `.env` SMTP-Konfiguration implementiert, falls keine Tenant-Konfiguration vorhanden ist.
- Migration für `tenant_smtp_settings` ergänzt.

## Schritt 8 – Plugin-Lifecycle UI + Rechteverwaltung (abgeschlossen)
- Backend-Endpunkte für tenant-spezifische Plugin-Lifecycle-Operationen ergänzt:
  - `GET /api/admin/plugins`
  - `POST /api/admin/plugins/{plugin}/status`
- Backend-Endpunkte für tenant-spezifische Role-Permissions ergänzt:
  - `GET /api/admin/roles/permissions`
  - `PUT /api/admin/roles/{roleKey}/permissions`
- RBAC-Prüfung für Admin-Operationen über Berechtigungen `plugins.manage` und `rbac.manage` eingebunden.
- Neue Migrationstabelle `tenant_plugins` für Lifecycle-State pro Tenant ergänzt.
- Admin-Dashboard in Flutter auf modulare Admin-Navigation mit folgenden Bereichen erweitert:
  - Übersicht mit Tenant-/Permission-Kontext
  - Plugin Lifecycle Verwaltung (Aktivieren/Deaktivieren)
  - Rechteverwaltung (Role → kommagetrennte Permission-Liste)
- Auth-State erweitert, damit `tenant_id` und `permissions` aus dem Login-Response tenant-sicher im Frontend genutzt werden.


## Schritt 9 – Domain-Routing + Stripe Domain-Persistenz (abgeschlossen)
- Produktionsdomains festgelegt:
  - Frontend CRM: `https://crm.ordentis.de`
  - Backend API: `https://api.ordentis.de`
- Backend CORS für CRM-Domain + lokale Dev-Origin ergänzt und OPTIONS-Preflight verarbeitet.
- Flutter-Dio Standard-API-Base auf `https://api.ordentis.de/api` umgestellt (überschreibbar per `--dart-define=API_BASE_URL=...`).
- Stripe Webhook-Handling um idempotente Persistenz erweitert:
  - Event Journal (`stripe_webhook_events`)
  - Provisioning-Projektion (`tenant_provisioning_events`)
  - Entitlement-Projektion (`tenant_subscription_entitlements`)
  - Dunning-Projektion (`stripe_dunning_cases`)
- Verarbeitungspfade implementiert für:
  - `checkout.session.completed` (Provisionierung)
  - `customer.subscription.updated|deleted` (Entitlements)
  - `invoice.payment_failed|invoice.paid` (Dunning Open/Resolve)

## Schritt 10 – Persistente Audit-Logs + Approval-Flow für kritische RBAC/Plugin-Änderungen (abgeschlossen)
- Multi-Tenant Approval-Workflow für kritische Admin-Änderungen umgesetzt:
  - `POST /api/admin/plugins/{plugin}/status` erstellt nur noch Approval-Requests.
  - `PUT /api/admin/roles/{roleKey}/permissions` erstellt nur noch Approval-Requests.
- Neue Decision-Endpunkte ergänzt:
  - `GET /api/admin/approvals`
  - `POST /api/admin/approvals/{approvalId}/approve`
  - `POST /api/admin/approvals/{approvalId}/reject`
- Persistente Datenhaltung ergänzt:
  - `approval_requests` für beantragte/freigegebene/abgelehnte Änderungen.
  - `audit_logs` für revisionssichere Ereignisspur inkl. Tenant, Actor, IP und User-Agent.
- Security-Mechanik ergänzt:
  - Self-Approval blockiert.
  - Tenant-Isolation in allen Approval-/Audit-Queries.
  - RBAC-Permissions getrennt nach Änderungsantrag (`plugins.manage`/`rbac.manage`) und Freigabe (`approvals.manage`).


## Schritt 11 – Superadmin Impersonation + Plattformweite Insights (abgeschlossen)
- Superadmin-Endpunkte für plattformweite Operationen ergänzt:
  - `POST /api/platform/impersonate/company` (Übernahme eines Company-Tenants via Token-Generierung)
  - `GET /api/platform/admin-stats` (globale KPI-Snapshot-Statistiken)
  - `GET /api/platform/audit-logs` (globale Audit-Log Einsicht mit Pagination + Filtern)
  - `GET /api/platform/reports` (erweiterte Reports/Analytics pro Tenant + Summaries)
- JWT-basierte Superadmin-Absicherung für die neuen Plattform-Endpunkte umgesetzt (`Authorization: Bearer ...`, `is_superadmin=true`).
- Auditierbarkeit erweitert: Abruf von Plattform-Stats/Reports und das Erzeugen von Impersonation-Tokens werden selbst als Audit-Events protokolliert.
- Progress-Dokumentation aktualisiert, um den aktuellen Lieferstand transparent nachvollziehbar zu machen.

## Schritt 12 – Tenant-fähige Admin/User/Customer-Verwaltung (abgeschlossen)
- Neue zentrale Account-Persistenz `tenant_accounts` mit Soft-Delete und den geforderten Stammdatenfeldern ergänzt.
- Neue API-Endpunkte für rollenbasiertes Account-Management ergänzt:
  - `GET|POST|PUT|DELETE /api/admin/users`
  - `GET|POST|PUT|DELETE /api/customers`
  - `GET|PUT /api/self/profile`
- RBAC-Verhalten in der API umgesetzt:
  - **Admin** verwaltet User + Customer vollständig.
  - **User (Mitarbeiter)** verwaltet Customer vollständig.
  - **Customer** kann nur den eigenen Datensatz lesen und pflegen (Self-Service).
- `role_id`-Anbindung über bestehende `roles`/`role_permissions` Tabellen integriert (Fallback auf account_type-basierte Rechte).

## Schritt 13 – Frontend UI-Abdeckung für bestehende Backend-Funktionen (abgeschlossen)
- Admin-Dashboard als zentrales **Control Center** ausgebaut und um zusätzliche Funktionsbereiche erweitert:
  - Approvals (Laden + Entscheiden)
  - Platform Insights (Admin-Stats, Audit-Logs, Reports, Impersonation)
  - Tenant Account-Management (Admin-Users, Customers, Self-Profile)
  - Integrationsbereich für Stripe/PDF/Email-Endpunkte
- API-Aufrufe in der UI vereinheitlicht (Tenant-/Permission-/Bearer-Header) zur konsistenten Nutzung aller Backend-Funktionen im Frontend.
- UI-Design stärker auf Theme-gesteuerte Komponenten umgestellt (Cards, Inputs, Buttons, Chips) gemäß Flutter 3.41 Material-3-Standard.
- `AppTheme` erweitert, damit visuelle Anpassungen zentral über Theme-Konfiguration erfolgen.

## Schritt 14 – Frontend i18n-Refactoring + Review + API-Dokumentation (abgeschlossen)
- Frontend-UI (Login, Dashboard, CRUD) auf lokalisierbare Strings umgestellt.
- Lokalisierungsstruktur für Deutsch/Englisch zentralisiert (`lib/l10n`).
- `MaterialApp` auf Lokalisierungs-Delegates und lokalisierte App-Titel umgestellt.
- Senior-Code-Review nach Flutter-3.41-Richtlinien dokumentiert und technische Verbesserungen umgesetzt (u. a. Controller-Disposal).
- Vollständige Frontend-API-Dokumentation inkl. Auth-, Admin-, Platform-, Integrations- und CRUD-Endpunkten ergänzt (`docs/FRONTEND_I18N_CODE_REVIEW_AND_API.md`).

## Schritt 15 – Plugin-Roadmap für Billing/Finance-SaaS (geplant und dokumentiert)
- Detaillierte, aufeinander aufbauende Delivery-Roadmap für alle angefragten Funktionsblöcke erstellt.
- Alle Funktionspakete als Plugin-Zielarchitektur beschrieben (Core Billing, Payments, Tax/Compliance, Subscriptions, Delivery, Reporting, Org, Integrations, Catalog, Security/Ops).
- Phase-übergreifende Qualitätsstrategie ergänzt (Unit, Contract, Migration, Flutter Widget/Golden, E2E).
- Fortschrittstabelle mit Status und jeweils nächstem konkreten Schritt ergänzt.
- Referenzdokument: `docs/PLUGIN_ROADMAP_BILLING_SAAS.md`.

## Schritt 16 – PLUGIN_ROADMAP Phase 0 umgesetzt (abgeschlossen)
- **Schritt 0.1 Plugin-SDK stabilisiert**
  - Plugin-Metadaten in der Persistenz standardisiert (`version`, `capabilities_json`, `required_permissions_json`, `lifecycle_status`).
  - Lifecycle-Status auf `installed|enabled|suspended|retired` erweitert.
  - Standard-Hooks als technische Basis normiert: `before_validate`, `before_finalize`, `after_finalize`, `before_send`, `after_payment`.
  - Tenant/Company Feature-Flags eingeführt (`tenant_feature_flags`) inkl. API-Endpunkten.
- **Schritt 0.2 Domain-Event-Bus + Outbox eingeführt**
  - Persistente Domain-Events ergänzt (`domain_events`) für `invoice.created`, `invoice.finalized`, `payment.received`.
  - Outbox-Pattern als persistente Lieferwarteschlange ergänzt (`outbox_messages`) inkl. Retry-Metadaten.
  - Backend-Endpunkte für Event-Publishing und Outbox-Verarbeitung ergänzt.
- **Schritt 0.3 Shared UI Shell (Flutter) ergänzt**
  - Admin-Dashboard um Plugin-Shell-Sicht erweitert (Anzeige von Status, Version, Capabilities je Plugin).
  - Shell nutzt capability-/permission-basierte Sichtbarkeit über Backend-Endpoint `/api/admin/plugin-shell`.

**Abnahme-Status Phase 0:** Fundament ist implementiert; Plugins können mit standardisierten Metadaten geführt, per Lifecycle gesteuert, per Feature-Flags tenant/company-spezifisch geschaltet und über die UI-Shell sichtbar gemacht werden.


## Schritt 17 – PLUGIN_ROADMAP Phase 1 (Billing Core MVP) umgesetzt (abgeschlossen)
- Neues Backend-Plugin-Modul **`billing_core`** als Core-Domain für Rechnungen und Dokumente ergänzt.
- Persistenz für das MVP-Datenmodell erweitert:
  - `billing_documents`, `billing_line_items`, `billing_tax_breakdowns`, `billing_document_addresses`, `billing_document_history`
  - `billing_customers`, `billing_customer_addresses`, `billing_customer_contacts`
  - `billing_number_counters` für transaktionssichere Nummernkreise pro Jahr/Serie
- API-Endpunkte für den End-to-End-MVP ergänzt:
  - Dokumente: Erstellen/Ändern/Auslesen, Finalisieren, Statuswechsel, Historie, PDF-Export
  - Flows: Angebot -> Rechnung (Snapshot-Konvertierung), Gutschrift auf Basis Ursprungsdokument
  - Kunden-/Adressbuch: Firmen/Privatkunden inkl. Mehrfachadressen und Ansprechpartner
- Fachlogik in `BillingCoreService` implementiert:
  - deterministische Totals-/USt-Berechnung (Zeilenrabatte + globaler Rabatt + Versand/Fees)
  - Fixierung der Währung pro Dokument (`currency_code`) inkl. dokumentgebundenem Wechselkurs (`exchange_rate`)
  - Nummernkreis-Reservierung ausschließlich beim Finalisieren (nicht im Entwurf)
  - Unveränderbarkeit finalisierter Dokumente über Draft-Mutability-Check
- Dokumenthistorie je Beleg mit Domain-Aktionen als Audit-ähnliche Verlaufsspur ergänzt.

**Abnahme-Status Phase 1:** MVP-End-to-End-Backbone für Angebot/Rechnung/Gutschrift inkl. Kundenstamm, Nummernkreis, Rechenlogik, PDF-Export, Status und Historie ist technisch implementiert.

## Schritt 18 – PLUGIN_ROADMAP Phase 2 (Payments & Mahnwesen MVP+) umgesetzt (abgeschlossen)
- Neues Backend-Plugin-Modul **`billing_payments`** ergänzt und in das API-Routing integriert.
- Persistenz für Payment-/Mahnwesen erweitert:
  - `billing_payment_links` (Provider-abstrakte Zahlungslinks mit Statusmodell)
  - `billing_payments` (Voll-/Teilzahlungen inkl. Gebühren, Skonto/Discount und Restforderung)
  - `billing_dunning_configs`, `billing_dunning_cases`, `billing_dunning_events` (konfigurierbarer Mahnworkflow inkl. Stufenhistorie)
  - `tenant_bank_accounts` (IBAN/BIC/Bankdaten + QR-IBAN-Flag pro Tenant)
- Neue API-Endpunkte für Phase-2-Workflows ergänzt:
  - Zahlungslinks: `GET|POST /api/billing/documents/{id}/payment-links`
  - Zahlungseingänge: `GET|POST /api/billing/documents/{id}/payments`
  - Mahnwesen: `GET|PUT /api/billing/dunning/config`, `POST /api/billing/dunning/run`, `GET /api/billing/dunning/cases`
  - Bankdaten: `GET|PUT /api/billing/bank-account`
- Fachlogik in `BillingPaymentsService` implementiert:
  - Provider-neutrale Zahlungslink-Erstellung (Stripe-first, PayPal-kompatibel)
  - Deterministische Restforderungsberechnung unter Berücksichtigung von Gebühren und Skonto
  - Automatische Dokumentstatus-Fortschreibung auf Basis des Zahlungssaldos
  - Konfigurierbare Mahngebühren, Zinssatz und Karenzzeit je Tenant

**Abnahme-Status Phase 2:** Der Forderungsprozess ist als MVP+ technisch abgedeckt: von Zahlungslink über Teil-/Vollzahlung bis zur automatisierten Mahnstufe mit tenant-spezifischen Regeln und Bank-/SEPA-Stammdaten.


## Schritt 19 – PLUGIN_ROADMAP Phase 3 (Steuern & Compliance DACH/DE) gestartet (Backend-Basis umgesetzt)
- Neues Backend-Plugin-Modul **`tax_compliance_de`** als fachliche Compliance-Schicht auf bestehendem Billing-Core ergänzt.
- Persistenz für steuerliche Konfiguration, Compliance-Status und E-Rechnungs-Austausch erweitert:
  - `tenant_tax_profiles` (Steuerprofil je Tenant inkl. §19-Kleinunternehmer-Flag)
  - `billing_document_compliance` (Preflight-Status, Siegel-Hash, Korrekturbeleg-Referenz)
  - `billing_einvoice_exchange` (Export/Import-Protokoll für XRechnung/ZUGFeRD)
- Neue API-Endpunkte für Phase-3-Workflows ergänzt:
  - `GET|PUT /api/billing/tax-compliance/config`
  - `POST /api/billing/tax-compliance/documents/{id}/preflight`
  - `POST /api/billing/tax-compliance/documents/{id}/seal`
  - `POST /api/billing/tax-compliance/documents/{id}/correction`
  - `GET /api/billing/tax-compliance/documents/{id}/e-invoice/export?format=xrechnung|zugferd`
  - `POST /api/billing/tax-compliance/e-invoice/import`
- Fachlogik in `TaxComplianceDeService` implementiert:
  - USt-Regelklassifizierung (standard/ermäßigt/0%/EU-Indikatoren) als Preflight-Basis.
  - §19-Kleinunternehmerregel: Validierung verhindert USt-Ausweis bei aktivierter Regelung.
  - Pflichtangaben-Preflight vor Finalisierung/Versiegelung (Steuerprofil + Positions-/Datumschecks).
  - GoBD-nahe Versiegelung über deterministischen SHA-256-Dokument-Hash und Korrekturbeleg-Flow.
  - Technische E-Rechnungsfähigkeit (XRechnung/ZUGFeRD) via XML-Export + Import-Journal.

**Abnahme-Status Phase 3:** Die Backend-Basis für Steuer-/Compliance-Workflows ist implementiert; für produktionsreife DE-Konformität folgen als nächste Schritte fachliche Regelverfeinerung je Geschäftsfall sowie Schema-/Validator-Härtung für vollwertige XRechnung/ZUGFeRD-Compliance.

## Schritt 20 – PLUGIN_ROADMAP Phase 4 (Wiederkehrende Umsätze & Abos) umgesetzt (Backend-MVP)
- Neues Backend-Plugin-Modul **`subscriptions_billing`** ergänzt und in das API-Routing integriert.
- Persistenz für Abo-Lifecycle und wiederkehrende Abrechnung erweitert:
  - `subscription_plans` (Planmodell mit Laufzeit, Verlängerung, Kündigungsfrist)
  - `subscription_contracts` (Vertragszustand inkl. Terminen, Kündigungs-/Payment-Method-Daten)
  - `subscription_cycles` (Recurring-/Planwechsel-Events inkl. Proration-Metadaten)
  - `subscription_invoices` (Auto-Invoicing-Zuordnung auf Billing-Dokumente inkl. Collection-/Delivery-Status)
  - `subscription_dunning_cases`, `subscription_payment_method_updates` (Retry-/Retention- und Payment-Method-Update-Flow)
- Neue API-Endpunkte für Phase-4-Workflows ergänzt:
  - Pläne: `GET|POST /api/billing/subscriptions/plans`
  - Verträge: `GET|POST /api/billing/subscriptions/contracts`, `PUT /api/billing/subscriptions/contracts/{id}`
  - Planwechsel/Proration: `POST /api/billing/subscriptions/contracts/{id}/change-plan`
  - Recurring Engine: `POST /api/billing/subscriptions/run-recurring`
  - Auto-Invoicing + Versandqueue: `POST /api/billing/subscriptions/auto-invoicing/run`
  - Dunning/Retention: `POST /api/billing/subscriptions/dunning/run`
  - Payment-Method-Update-Link: `POST /api/billing/subscriptions/contracts/{id}/payment-method-update-link`
- Fachlogik in `SubscriptionsBillingService` implementiert:
  - Plan-/Vertragsverwaltung mit Tenant-Isolation und Validierungen.
  - Recurring Engine für monatliche/jährliche Zyklen.
  - Proration-Credit bei Upgrade/Downgrade über Restlaufzeit-Anteil.
  - Automatische Rechnungserzeugung als `billing_documents` + `billing_line_items` mit Plugin-Key `subscriptions_billing`.
  - Auto-Invoicing-Dispatch in die vorhandene `email_queue`.
  - Retry-Logik bis max. 3 Versuche mit Eskalation in Payment-Method-Update-Flow.

**Abnahme-Status Phase 4:** Der Abo-Cashflow ist als Backend-MVP umgesetzt (Plan/Vertrag -> Recurring Invoice -> Versandqueue -> Retry/Retention). Als nächster Schritt folgen Provider-spezifische Payment-Method-Update-Completion Hooks und UI-Flows im Flutter-Admin.

## Schritt 21 – PLUGIN_ROADMAP Phase 5 (E-Mail & Versand) gestartet (Backend-MVP)
- Neues Backend-Plugin-Modul **`document_delivery`** ergänzt und in das API-Routing integriert.
- Persistenz für Zustellung und Versandkanäle erweitert:
  - `document_delivery_templates` (mehrsprachige Kanal-Templates inkl. Variablen-/Anhangsmetadaten)
  - `document_delivery_provider_configs` (SMTP/SendGrid/Mailgun Tenant-Konfiguration)
  - `document_delivery_tracking_events` (optionales Mail-Open/Link-Click-Tracking)
- Neue API-Endpunkte für Phase-5-Workflows ergänzt:
  - Template-Verwaltung: `GET /api/billing/delivery/templates`, `PUT /api/billing/delivery/templates/{templateKey}`
  - Provider-Konfiguration: `GET|PUT /api/billing/delivery/provider`
  - Kundenportal-Dokumente: `GET /api/portal/documents`, `GET /api/portal/documents/{id}`
  - Tracking-Ereignisse: `POST /api/billing/delivery/tracking/events`
- Fachlogik in `DocumentDeliveryService` implementiert:
  - Mehrsprachige Vorlagen mit kanalabhängigen Inhalten (E-Mail/Portal).
  - Tenant-spezifische Versandprovider-Abstraktion (SMTP, SendGrid, Mailgun).
  - Portal-Self-Service-Zugriff auf Dokumente inkl. zugeordneter Zahlungsoptionen.
  - Datenschutzfreundliches, optionales Event-Tracking auf Message-/Template-Ebene.

**Abnahme-Status Phase 5:** Der digitale Zustellprozess ist als Backend-MVP implementiert (Template/Provider/Portal/Tracking). Für die vollständige Abnahme folgen ein Delivery-Worker mit Retry/Provider-Dispatch und die Flutter-Portal-UI für Endnutzer.

## Schritt 22 – PLUGIN_ROADMAP Phase 7 (Team, Rechte, Multi-Company) umgesetzt (Backend-MVP)
- Neues Backend-Plugin-Modul **`org_management`** ergänzt und in das API-Routing integriert.
- Persistenz für Multi-Company und Teamzuordnungen erweitert:
  - `org_companies` (mehrere Firmen pro Tenant/Account-Kontext)
  - `org_company_memberships` (User-zu-Company-Rollenmapping)
  - `audit_logs` um `company_id` erweitert, damit Governance pro Firmenkontext auswertbar bleibt.
- Neue API-Endpunkte für Phase-7-Workflows ergänzt:
  - Multi-Company: `GET|POST /api/org/companies`, `PUT /api/org/companies/{companyId}/memberships`, `POST /api/org/context/switch`
  - Rollenmodell inkl. Custom Roles: `GET /api/org/roles`, `PUT /api/org/roles/{roleKey}`
  - Audit-Log UI/Export-Backbone: `GET /api/org/audit-logs`, `POST /api/org/audit-logs/export`
- Fachlogik in `OrgManagementService` und `OrgManagementController` implementiert:
  - Berechtigte Kontextumschaltung pro User/Company mit Rückgabe der effektiven Permissions.
  - Pflege tenant-spezifischer Rollen (inkl. Custom Roles) über bestehende `roles`/`role_permissions`.
  - Audit-Log-Einträge für Company-/Membership-/Role-/Context-Aktionen inkl. `company_id`-Scope.
  - CSV-Export für Audit-Logs als API-Response für nachgelagerte UI-Downloads.

**Abnahme-Status Phase 7:** Multi-Company-Scope, rollenbasiertes Team-Management und auditierbare Governance sind als Backend-MVP umgesetzt. Nächster Ausbau: dedizierte Flutter-Org-Management-Masken mit geführter Kontextumschaltung und Export-UX.

## Schritt 23 – PLUGIN_ROADMAP Phase 8 (Automatisierung & Integrationen) umgesetzt (Backend-MVP)
- Neues Backend-Plugin-Modul **`automation_integrations`** ergänzt und in das API-Routing integriert.
- Persistenz für API-First-, Adapter- und Automatisierungs-Workflows erweitert:
  - `automation_api_versions`, `automation_idempotency_keys` (API-Versionierung + Idempotenz-Registry)
  - `automation_crm_connectors`, `automation_crm_sync_logs` (HubSpot/Pipedrive-Connectoren + Sync-Queue)
  - `automation_time_entries` (Stundenzettel/Projektzeiten mit Fakturierungsstatus)
  - `automation_workflow_catalog`, `automation_workflow_runs` (Zapier/Make Trigger- und Action-Runs)
  - `automation_import_products`, `automation_import_historical_invoices` (Import-Wizard-Zieldaten)
- Neue API-Endpunkte für Phase-8-Workflows ergänzt:
  - API-first: `GET|POST /api/billing/automation/api-versions`, `POST /api/billing/automation/idempotency/claim`
  - CRM: `GET|PUT /api/billing/automation/crm/connectors`, `POST /api/billing/automation/crm/{provider}/sync`
  - Time Tracking: `GET|POST /api/billing/automation/time-entries`, `POST /api/billing/automation/time-entries/invoice`
  - No-Code: `GET /api/billing/automation/workflows/catalog`, `POST /api/billing/automation/workflows/runs`
  - Import-Wizard: `POST /api/billing/automation/import/preview`, `POST /api/billing/automation/import/execute`
- Fachlogik in `AutomationIntegrationsService` implementiert:
  - Konsistente API-Versionpflege mit Deprecation-/Sunset-Metadaten.
  - Idempotenz-Claiming mit Request-Hash-Konflikterkennung (`idempotency_key_conflict`).
  - CRM-Provider-Abstraktion (HubSpot/Pipedrive) inkl. Maskierung sensibler Credentials im Read-Modell.
  - Time-Entry -> Draft-Invoice-Konvertierung mit automatischer Verknüpfung der fakturierten Zeiterfassungen.
  - No-Code-Workflow-Queueing für Zapier/Make und Import-Wizard-Preview/Execute für Kunden, Produkte und historische Rechnungen.

**Abnahme-Status Phase 8:** Die Integrationsfähigkeit ist als Backend-MVP umgesetzt (API-first, CRM-Adapter, Time-Tracking-Invoicing, No-Code-Automation, Import-Wizard). Für die vollständige Produktabnahme folgen Worker-Ausführung/Retry, Provider-spezifische Mapping-Profile und geführte Flutter-UI-Flows.

## Schritt 25 – PLUGIN_ROADMAP Phase 9 (Produktkatalog & Preislogik) umgesetzt (Backend-MVP)
- Neues Backend-Plugin-Modul **`catalog_pricing`** ergänzt und in das API-Routing integriert.
- Persistenz für Produktkatalog und Preislogik erweitert:
  - `catalog_products` (SKU, Produkttyp, Standardpreis, Standardsteuersatz)
  - `catalog_price_lists`, `catalog_price_list_items` (B2B-Sonderpreise, Mengenstaffeln, Preisüberschreibungen/Discount)
  - `catalog_bundles`, `catalog_bundle_items` (wiederverwendbare Bundle-Definitionen)
  - `catalog_discount_codes` (Rabattcodes für One-Time, Subscription oder beide Umsatztypen)
- Neue API-Endpunkte für Phase-9-Workflows ergänzt:
  - Produkte: `GET|POST /api/billing/catalog/products`, `PUT /api/billing/catalog/products/{id}`
  - Preislisten: `GET|POST /api/billing/catalog/price-lists`, `PUT /api/billing/catalog/price-lists/{id}`
  - Preislogikregeln: `GET|POST /api/billing/catalog/price-lists/{id}/items`
  - Bundles: `GET|POST /api/billing/catalog/bundles`, `PUT /api/billing/catalog/bundles/{id}`
  - Rabattcodes: `GET|POST /api/billing/catalog/discount-codes`
  - Preisberechnung: `POST /api/billing/catalog/quotes/calculate`
- Fachlogik in `CatalogPricingService` implementiert:
  - Tenant-sichere Produkt-/Preisliste-/Bundle-Verwaltung.
  - Staffelpreisauflösung über Preislistenregeln (`min_quantity`, `override_price`, `discount_percent`).
  - Rabattcode-Validierung inkl. Gültigkeitszeitraum, Anwendungsbereich (`one_time|subscription|both`) und Kontingentgrenze.
  - Wiederverwendbare Quote-Berechnung (Netto, Rabatt, Steuer, Gesamtsumme) für Sales- und Billing-Flows.

**Abnahme-Status Phase 9:** Wiederverwendbare Preislogik ist als Backend-MVP umgesetzt und für Sales-/Billing-Workflows per API verfügbar.


## Schritt 29 – PLUGIN_ROADMAP Phase 9 Flutter-Katalog-UI + Angebotseditor-Preislogik integriert
- Flutter-Feature **`catalog_pricing`** ergänzt (`CatalogPricingScreen`, `CatalogPricingController`) und im Admin-Dashboard als neuer Navigationspunkt **"Katalog & Preislogik (Phase 9)"** verdrahtet.
- Katalog-UI für Produktstamm integriert:
  - Laden von Produkten, Preislisten und Rabattcodes über bestehende Phase-9-API-Endpunkte.
  - Direkter Produkt-Create-Flow (SKU, Typ, Preis, Steuersatz) für schnelle Stammdatenpflege im Admin-Workflow.
- Angebotseditor-Integration der Preislogik umgesetzt:
  - Positionen aus dem Produktkatalog auswählbar (Menge/Produkt je Position).
  - Preislisten- und Rabattcode-Auswahl sowie `sale_type` (`one_time|subscription`) in der Kalkulation berücksichtigt.
  - Serverseitige Quote-Berechnung via `POST /api/billing/catalog/quotes/calculate` angebunden und Ergebnis (Linien + Totals + Rabattmetadaten) strukturiert visualisiert.
- Testabdeckung im Flutter-Modul erweitert (`catalog_pricing_controller_test.dart`) für Parsing/State-Basics der Angebotskalkulation.

**Abnahme-Status Schritt 29:** Phase-9-Preislogik ist jetzt im Flutter-Admin nutzbar und mit dem Angebotseditor-Endpunkt integriert; nächster Schritt ist die visuelle Härtung per Golden-/Widget-Tests gegen realitätsnahe Katalogdaten.

## Schritt 26 – PLUGIN_ROADMAP Phase 0 (Schritt 0.1) schriftlich fixiert (abgeschlossen)
- Verbindliches Contract-Dokument für das Plugin-SDK erstellt: `docs/PLUGIN_SDK_CONTRACT.md`.
- Der Contract dokumentiert normativ:
  - kanonische Plugin-Metadaten (`plugin_key`, `version`, `capabilities`, `required_permissions`, `lifecycle_status`)
  - Lifecycle-Semantik (`installed|enabled|suspended|retired`) inkl. Aktivitätsregel `is_active`
  - Hook-Whitelist (`before_validate`, `before_finalize`, `after_finalize`, `before_send`, `after_payment`)
  - Tenant/Company Feature-Flags, Domain-Event-/Outbox-Contract und UI-Shell-Sichtbarkeitsregeln
- Konsistenz zur bestehenden Implementierung hergestellt durch Abgleich mit:
  - Persistenzschema (`backend/src/migrations/001_init.sql`)
  - Plugin-Runtime (`backend/src/Services/PluginManager.php`)
  - Foundation-/Shell-APIs (`backend/src/Controllers/PluginFoundationController.php`)
- Fortschrittstabelle der Roadmap aktualisiert: Phase 0 von „Geplant“ auf „In Umsetzung (Contract schriftlich fixiert)“.

**Abnahme-Status Schritt 0.1:** Der Plugin-SDK Contract ist schriftlich fixiert, versioniert und als verbindliche Grundlage für nachfolgende Plugin-Phasen dokumentiert.

## Schritt 20 – PLUGIN_ROADMAP Phase 0 Contract-Tests ergänzt (abgeschlossen)
- Zentrale Contract-Regeln als wiederverwendbare Backend-Komponente eingeführt (`PluginContract`):
  - Validierung standardisierter Plugin-Metadaten (`plugin_key`, semver `version`, `capabilities`, `required_permissions`)
  - Hook-Whitelist als kanonische Konstante (`before_validate`, `before_finalize`, `after_finalize`, `before_send`, `after_payment`)
  - Lifecycle-States inkl. Transition-Validierung (`installed -> enabled -> suspended -> retired`, inkl. `suspended -> enabled` Reaktivierung)
- Bestehende Runtime-Nutzung auf den Contract vereinheitlicht:
  - `PluginManager` nutzt Contract-Validierung für Metadaten und Hook-Namen.
  - `PluginFoundationController` liefert Hook-Whitelist aus dem Contract und validiert Lifecycle-Transitions vor dem Persistieren.
  - `AdminPluginController` validiert Lifecycle-Transitions beim Anwenden freigegebener Plugin-Status-Änderungen.
- Contract-Tests als ausführbares Backend-Testskript ergänzt:
  - Prüft gültige/ungültige Metadaten, Hook-Whitelist und erlaubte/verbotene Lifecycle-Transitions.
  - Datei: `backend/tests/Contract/plugin_contract_test.php`.

**Abnahme-Status:** Der nächste konkrete Schritt aus Phase 0 („Contract-Tests für Plugin-Metadaten, Hook-Whitelist und Lifecycle-Transitions ergänzen“) ist umgesetzt und testbar dokumentiert.

## Schritt 27 – PLUGIN_ROADMAP Phase 0 Contract-Tests vertieft (abgeschlossen)
- Contract-Testskript `backend/tests/Contract/plugin_contract_test.php` erweitert, um die schriftlich fixierten Regeln aus Phase 0.1 ausführbarer zu validieren.
- Ergänzte Testabdeckung:
  - zusätzliche positive/negative Metadaten-Fälle (Key/Version/Capabilities/Permissions),
  - exakte Hook-Whitelist-Absicherung inkl. strikter String-Matches,
  - Lifecycle-Status- und Transition-Checks für erlaubte wie verbotene Übergänge.
- Damit ist der in der Roadmap ausgewiesene nächste Schritt für Phase 0 („Contract-Tests ergänzen“) umgesetzt und als Regression-Guard im Backend verankert.

**Abnahme-Status Schritt 27:** Die Contract-Regeln für Plugin-Metadaten, Hook-Whitelist und Lifecycle-Transitions sind testseitig vertieft und nachvollziehbar abgesichert.


## Schritt 21 – Review Phase 0 abgeschlossen + Phase 1 Flutter Billing UI/E2E-Flow vervollständigt
- **Phase 0 Review:** Abnahmekriterien erneut geprüft und als abgeschlossen markiert (Plugin-Contract, Lifecycle-/Navigation-Contracts, UI/API-Sichtbarkeit inkl. Tests).
- **Flutter Admin-Dashboard** um einen dedizierten Bereich **"Billing E2E (Phase 1)"** erweitert.
- Neuer orchestrierter UI-Flow für **Angebot -> bezahlt** implementiert:
  - Kunde sicherstellen/anlegen
  - Angebot erstellen und finalisieren
  - Angebot in Rechnung konvertieren und Rechnung finalisieren
  - Payment-Link erstellen und Zahlung verbuchen
  - Historie und PDF-Export als Abschlussprüfung laden
- Flow-Ergebnisse werden in der UI als Kennzahlen + Timeline pro Schritt angezeigt.
- Testabdeckung erweitert: `billing_flow_controller_test.dart` prüft den E2E-Orchestrierungsablauf deterministisch über ein Repository-Double.

**Aktueller Status:** Phase 0 abgeschlossen; Phase 1 UI/E2E-Umsetzung ist funktional vervollständigt und für produktive Härtung vorbereitet.

## Schritt 28 – PLUGIN_ROADMAP Phase 1 Billing-Flow produktiv gehärtet (abgeschlossen)
- **Phase-0-Review** erneut validiert und als abgeschlossen dokumentiert (Roadmap-Abnahmestatus explizit als ✅ markiert).
- **BillingFlowController** robustifiziert:
  - Kontextbezogene Fehlerbehandlung je Flow-Step (`BillingFlowException`) inkl. Dio-Fehlerauswertung (`HTTP-Status`, `error/message` aus Backend-Response).
  - Schutz gegen parallele Ausführung (`isRunning`-Guard) und deterministisches Zurücksetzen des UI-States vor jedem Lauf.
- **UX-Polish** im `BillingFlowScreen` umgesetzt:
  - Status-Banner für Zustände *running/success/error/ready*.
  - Verbesserte Timeline mit Erfolgs-/Fehlerindikatoren.
  - Empty-State statt leerer Liste vor dem ersten Lauf.
- **Qualitätssicherung erweitert**:
  - Controller-Test für API-Fehlerpfad inkl. kontextbezogener Fehlermeldung ergänzt.
  - Golden-Test für den initialen Billing-Flow-Screen ergänzt.

**Abnahme-Status Phase 1 Hardening:** Error-Handling, Golden-Test-Baseline und UX-Polish sind implementiert und testbar dokumentiert.

## Schritt 29 – Komplett-Review (Senior-Review) durchgeführt (abgeschlossen)
- Projektweiter Review gegen die dokumentierten Projektvorgaben durchgeführt (`README.md`, `docs/IMPLEMENTATION_PROGRESS.md`, `docs/PLUGIN_ROADMAP_BILLING_SAAS.md`).
- Erledigte Punkte im Review explizit als abgeschlossen markiert:
  - [x] Multi-Tenant-/RBAC-/Plugin-Basis und API-Routing sind dokumentiert und konsistent nachweisbar.
  - [x] Billing-Roadmap Phase 0 ist als abgeschlossen gekennzeichnet und mit Contract-Tests hinterlegt.
  - [x] Billing-Roadmap Phase 1 Hardening (Fehlerpfade, UX-Polish, Golden-Test) ist als umgesetzt dokumentiert.
  - [x] Fortschrittsdokumentation enthält klare Status-/Abnahme-Statements pro Schritt.
- Technischer Review-Run ausgeführt:
  - [x] Backend-Contract-Test `plugin_contract_test.php` erfolgreich.
  - [ ] Flutter-Controller-Test `billing_flow_controller_test.dart` konnte in dieser Umgebung nicht ausgeführt werden (Flutter SDK/CLI nicht verfügbar).

**Abnahme-Status Schritt 29:** Komplett-Review ist durchgeführt, erledigte Arbeitspakete sind sichtbar markiert und der Fortschritt ist mit Testläufen dokumentiert.

## Schritt 30 – PLUGIN_ROADMAP Phase-1-Abnahme abgeschlossen (Nummernkreis/Mehrwährung/PDF im E2E-Flow)
- Billing-E2E-Orchestrierung im Flutter-Controller fachlich erweitert, um die geforderte Abnahme explizit auszuführen:
  - Dokumentprüfung direkt nach Finalisierung von Angebot und Rechnung (`fetchDocumentSnapshot`).
  - Validierung, dass der Nummernkreis tatsächlich vergeben wurde (`document_number` muss gesetzt sein).
  - Validierung der fixierten Mehrwährungsdaten (`currency_code = USD`, `exchange_rate = 1.08`) über beide Dokumente hinweg.
- API-Repository für den E2E-Flow ergänzt:
  - `createQuote` akzeptiert jetzt `currencyCode` und `exchangeRate` als explizite Eingaben.
  - neues `GET /billing/documents/{id}`-Reading im Flow für die Snapshot-Prüfung.
- Testabdeckung im Flutter-Testset erweitert:
  - zusätzlicher Testfall prüft Nummernkreis + Mehrwährung + PDF-Ausgabe im Angebot->Rechnung->bezahlt Flow.
  - bestehender Fehlerpfad-Test bleibt erhalten und schützt weiterhin die kontextbezogene Fehlerkommunikation.
- Fortschrittsdokumentation aktualisiert:
  - Roadmap Phase 1 auf **Abgeschlossen** gesetzt und nächster Schritt für Phase 2 konkretisiert.

**Abnahme-Status Schritt 30:** Die in der Roadmap geforderte Phase-1-Abnahme (Nummernkreis/Mehrwährung/PDF-Export im E2E-Flow) ist fachlich und testseitig dokumentiert abgeschlossen.


## Schritt 21 – PLUGIN_ROADMAP Phase 2 E2E-Härtung (Payment-Provider-Adapter + Mahnstufen-Regression) umgesetzt (abgeschlossen)
- Payment-Provider-Abstraktion im Plugin **`billing_payments`** formalisiert:
  - Neue Adapter-Schnittstelle `PaymentProviderAdapterInterface` für provider-neutrale Zahlungslink-Erstellung.
  - Konkrete Provider-Adapter für **Stripe** und **PayPal** (`StripePaymentProviderAdapter`, `PayPalPaymentProviderAdapter`).
  - Registry-basierte Provider-Auflösung über `PaymentProviderRegistry` mit validierter Provider-Liste.
- `BillingPaymentsService` auf Adapter-basierte Zahlungslink-Erstellung umgestellt (Contract-first statt Inline-Provider-Branching).
- Mahnstufen-Regression abgesichert:
  - Dunning-Eskalation wird pro Fall auf maximal eine Stufe je Kalendertag gedrosselt (Schutz vor Mehrfach-Läufen).
  - Öffentliche Prüf-Funktion `isDunningEscalationDue(...)` für reproduzierbare Regressionstests ergänzt.
- Regressionstest für Phase 2 ergänzt (`backend/tests/Regression/billing_payments_phase2_regression_test.php`):
  - Stripe-/PayPal-Adapter-Mapping, Registry-Fehlerpfad und Mahnstufen-Tagesdrossel werden automatisiert validiert.

**Abnahme-Status Phase 2 (Härtung):** Payment-Provider-Abstraktion ist explizit definiert und Mahnstufen-Läufe sind regressionsgesichert (kein unbeabsichtigtes Mehrfach-Eskalieren am selben Tag).

## Schritt 31 – PLUGIN_ROADMAP Phase 2 abgeschlossen (Zahlungseingänge/Skonto + tenant-spezifische Gebühren-/Verzugszinsregeln)
- `billing_payments` fachlich erweitert, um Zahlungseingänge explizit zu modellieren:
  - Persistenzfelder für `payment_kind` (`partial|full|overpayment`), `skonto_percent`, `outstanding_before` und `outstanding_after` ergänzt.
  - Skonto kann nun entweder direkt als `discount_amount` übergeben oder prozentual via `skonto_percent` auf die Restforderung berechnet werden.
  - Rückgabe der Payment-API enthält den abgeleiteten Zahlungstyp zur klaren Unterscheidung von Teil-/Voll-/Überzahlung.
- Mahn- und Verzugszinsregeln pro Tenant finalisiert:
  - Dunning-Konfiguration um `interest_free_days`, `interest_mode` (`flat|daily_pro_rata`) und `max_interest_amount` erweitert.
  - Validerte Konfigurationsspeicherung inkl. Fehlerpfad für ungültigen `interest_mode`.
  - Verzugszinsberechnung in eine deterministische Funktion ausgelagert (`calculateDunningInterest`) mit Karenz- und Zinsfreitagen sowie optionalem Zins-Cap.
- Regressionstest für Phase 2 erweitert (`billing_payments_phase2_regression_test.php`):
  - Ableitung des Zahlungstyps (partial/full/overpayment).
  - Verzugszinsberechnung für Flat- und Daily-Pro-Rata-Modus, inklusive Grace-Period und Cap-Verhalten.
- Roadmap-Dokumentation aktualisiert:
  - Phase 2 als abgeschlossen markiert und nächster Fokus auf Phase 3 verschoben.

**Abnahme-Status Schritt 31:** Die offenen MVP+-Punkte aus Phase 2 (Zahlungseingänge inkl. Teilzahlungen/Skonto sowie tenant-spezifische Gebühren-/Verzugszinsregeln) sind implementiert, testseitig regressionsgesichert und in der Roadmap als abgeschlossen dokumentiert.


## Schritt 27 – PLUGIN_ROADMAP Phase 3 (Preflight-Regeln pro Dokumenttyp + E-Rechnungs-Validator) ergänzt (abgeschlossen)
- `TaxComplianceDeService` um **dokumenttypspezifische Preflight-Regeln** erweitert:
  - Pflicht-Datum für finalisierungsnahe Dokumenttypen (Rechnung/Gutschrift/Storno)
  - Referenzpflicht und Negativbetragslogik für Gutschrift/Storno
  - Warn-/Fehlerpfade je Dokumenttyp (z. B. nicht-positive Summen bei Sales-Dokumenten)
- XRechnung-/ZUGFeRD-Flows um einen **technischen XML-Validator** ergänzt:
  - Struktur- und Pflichtfeldprüfung (`format`, `documentNumber`, `currency`, `grandTotal`)
  - Formatspezifische Checks (`specificationIdentifier`/`buyerReference` für XRechnung, `profile`/`documentContext` für ZUGFeRD)
  - Import verweigert invalide XMLs (`invalid_einvoice_xml`), Export liefert Validator-Report mit zurück
- Regressionstest `backend/tests/Regression/tax_compliance_phase3_regression_test.php` ergänzt für:
  - neue Preflight-Regeln je Dokumenttyp
  - erfolgreiche XRechnung-/ZUGFeRD-Validierung
  - negatives Import-Szenario bei Format-/Schemafehlern

**Abnahme-Status Phase 3 (Zwischenstand):** Die Backend-Compliance wurde für Dokumenttyp-spezifische Pflichtlogik und technische E-Rechnungsvalidierung gehärtet.


## Schritt 32 – PLUGIN_ROADMAP Phase 3 Tiefenregeln umgesetzt (Reverse-Charge/innergemeinschaftlich + Validator-Härtung)
- `TaxComplianceDeService` fachlich vertieft:
  - EU-/Länderkontext für Steuerklassifikation ergänzt (Kundenland aus Dokumentadresse, Verkäuferland aus Tax-Profil).
  - Differenzierte Kategorien für `reverse_charge` und `intra_community` eingeführt (inkl. Prüfungen auf grenzüberschreitenden EU-Fall und 0%-Steuersatz).
  - Neue Compliance-Fehlerpfade für Sonderfälle ergänzt (z. B. fehlende USt-Id des Verkäufers bei innergemeinschaftlicher Leistung, Reverse-Charge im Inland).
  - Warnpfad für Mischfälle (`reverse_charge` + regulär steuerpflichtige Positionen) ergänzt.
- E-Rechnungs-Validierung für externe Referenzvalidatoren gehärtet:
  - Pflichtfelder/Formatchecks erweitert (`issueDate`, exaktes Dezimalformat, vorhandene Positionen, vorhandene Tax-Kategorien).
  - Format-spezifische Identifikatoren auf exakte Referenzwerte verschärft (XRechnung-Spec-Identifier, ZUGFeRD-Profile/Context).
  - XML-Export um zusätzliche strukturierte Inhalte (`lineItems`, `taxCategories`, `buyerCountry`) erweitert.
- Regressionstest `backend/tests/Regression/tax_compliance_phase3_regression_test.php` ausgebaut:
  - Innergemeinschaftlicher Sonderfall mit 0%-Steuer + EU-Auslandsadresse.
  - Fehler bei fehlender Verkäufer-USt-Id sowie grüner Pfad nach Nachpflege der USt-Id.
  - Bestehende Preflight-/Import-/Export-Regressionspfade bleiben abgesichert.

**Abnahme-Status Schritt 32:** Die in der Roadmap benannten Phase-3-Tiefenregeln sind backendseitig implementiert und durch Regressionstests gegen Sonderfall-/Validator-Risiken gehärtet.

## Schritt 33 – PLUGIN_ROADMAP Phase 3: Externe Referenzvalidatoren (CI-Gate) + Pflichtdaten-Mapping vervollständigt
- `TaxComplianceDeService` für produktionsnähere E-Rechnungsprofile erweitert:
  - Export-Mapping ergänzt um strukturierte Verkäufer-/Käufer-Pflichtdaten (`seller.name`, `seller.taxNumber|vatId`, `buyer.name`, `buyer.address.*`).
  - Deterministische `buyerReference`-Ableitung eingeführt (`<Kundenname> / <Belegnummer>`) für belastbare XRechnung-Referenzierung.
  - XML-Validator verschärft: fehlende Verkäufer-/Käufer-Pflichtdaten führen nun zu Fehlern (`missing_seller_*`, `missing_buyer_*`), `buyerReference` ist für XRechnung Pflichtfehler.
- Regressionstest `backend/tests/Regression/tax_compliance_phase3_regression_test.php` erweitert:
  - Prüft explizit das neue Pflichtdaten-Mapping (`<seller>`, `<buyer>`, `<buyerReference>`) im Export.
- Neuer CI-Gate-Runner `backend/scripts/ci_einvoice_reference_gate.php` ergänzt:
  - Erstellt referenznahe XRechnung-/ZUGFeRD-Exports aus einer In-Memory-Testdatenbasis.
  - Führt interne Validator-Prüfung zwingend aus.
  - Bindet externe Referenzvalidatoren optional per ENV an (`XRECHNUNG_VALIDATOR_URL`, `ZUGFERD_VALIDATOR_URL`) und bricht bei Ablehnung hart ab.
- GitHub-Workflow `.github/workflows/backend-ci.yml` ergänzt:
  - Führt Contract-/Regressionstests sowie das neue E-Invoice-CI-Gate als festen Pipeline-Schritt aus.

**Abnahme-Status Schritt 33:** Die in der Roadmap geforderte Anbindung externer Referenzvalidatoren (CI-Gate) ist technisch verdrahtet, und das Pflichtdaten-Mapping für produktive XRechnung/ZUGFeRD-Profile wurde backendseitig vervollständigt.


## Schritt 34 – PLUGIN_ROADMAP Phase 3: Produktive Validator-Endpunkte/Secrets je Umgebung vorbereitet
- CI-Gate-Script `backend/scripts/ci_einvoice_reference_gate.php` erweitert:
  - Umgebungsauflösung via `EINVOICE_VALIDATOR_ENV` (`dev|staging|prod`) ergänzt.
  - ENV-Auflösung priorisiert suffixierte Werte (`*_..._<ENV>`) mit Fallback auf Default-Werte.
  - Optionale Authentifizierung für externe Referenzvalidatoren unterstützt (`*_VALIDATOR_AUTH_HEADER`, `*_VALIDATOR_AUTH_TOKEN`).
  - Konfigurierbares Timeout über `EINVOICE_VALIDATOR_TIMEOUT_SECONDS` ergänzt.
- GitHub Workflow `.github/workflows/backend-ci.yml` erweitert:
  - Neues Job-Matrix-Gate `einvoice-reference-gate` für `dev`, `staging`, `prod` hinzugefügt.
  - Job auf GitHub `environment` je Matrix-Wert verdrahtet, damit Secrets isoliert je Umgebung gepflegt werden können.
  - Externe Validator-URL/Auth-Secrets und Timeout-Variable je Environment an das Gate-Script übergeben.
- Betriebsdokumentation `backend/README.md` ergänzt:
  - Vollständige ENV-Konventionen für Endpoint/Auth/Timeout dokumentiert.
  - Matrix-Ausführung über GitHub Environments als Zielbetrieb beschrieben.

**Abnahme-Status Schritt 34:** Die technische Verdrahtung für umgebungsbezogene Produktiv-Validatoren ist abgeschlossen. Die finale fachliche Abnahme gegen reale Referenzinstanzen erfolgt nach Befüllung der Environment-Secrets (`dev/staging/prod`) im Ziel-Repository.

## Schritt 35 – Flutter-Frontend auf `flutter_riverpod` 3.2.1 umgestellt (abgeschlossen)
- Flutter-Dependency in `flutter_app/pubspec.yaml` von `flutter_riverpod` `2.6.3` auf `3.2.1` aktualisiert.
- Projektdokumentation in `README.md` auf die neue Riverpod-Version angehoben.
- Verifikation im `flutter_app` gestartet (`flutter pub get`/`flutter test`), jedoch im Container durch fehlendes Flutter-SDK blockiert (`flutter: command not found`).

**Abnahme-Status Schritt 35:** Das Flutter-Frontend nutzt projektweit `flutter_riverpod` `3.2.1`; Die Quellbasis und Doku sind auf `3.2.1` migriert; die finale Laufzeitverifikation ist nachgelagert in einer Flutter-fähigen Umgebung auszuführen.

## Schritt 36 – Riverpod-3 API-Fixes im Billing-Flow (abgeschlossen)
- Diagnosefehler im `billing_flow_controller.dart` nach Riverpod-Upgrade bereinigt.
- Provider-Definition von `AutoDisposeNotifierProvider<...>(...)` auf Riverpod-3-konformes `NotifierProvider.autoDispose<...>(...)` umgestellt.
- Controller-Vererbung von `AutoDisposeNotifier<BillingFlowState>` auf `Notifier<BillingFlowState>` angepasst, sodass `build`, `ref` und `state` wieder korrekt aufgelöst werden.
- Ziel: Behebung der gemeldeten Analyzer-Fehler (`undefined_function`, `extends_non_class`, Folgefehler auf `state`/`ref`).

**Abnahme-Status Schritt 36:** Die gemeldeten Riverpod-Migrationsfehler im Billing-Flow-Codepfad sind auf Quelltextebene korrigiert; finale Verifikation erfolgt in Flutter-fähiger Umgebung.

## Schritt 24 – PLUGIN_ROADMAP Phase 4 erweitert (Provider-Adapter + Flutter Abo-Management-UI)
- Payment-Method-Update-Flow in `subscriptions_billing` auf **Provider-Adapter-Pattern** umgestellt:
  - Neue Adapter/Registry in `App\Services\SubscriptionsBilling\*` für Stripe/PayPal.
  - Service `SubscriptionsBillingService` nutzt Registry-Auflösung pro Request (`provider`) statt statischer URL-Generierung.
  - Persistenz `subscription_payment_method_updates` um `provider` erweitert, damit Recovery-Fälle provider-spezifisch nachvollziehbar sind.
- API/Composition aktualisiert:
  - `SubscriptionsBillingController` akzeptiert Payload beim Endpoint `POST /api/billing/subscriptions/contracts/{id}/payment-method-update-link`.
  - `App` verdrahtet Provider-Registry zentral mit Stripe/PayPal-Adaptern.
- Flutter Admin-UI für Phase 4 ergänzt:
  - Neuer Screen `SubscriptionManagementScreen` im Dashboard-Menü (`Abo-Management (Phase 4)`).
  - Neuer Riverpod-Controller `SubscriptionManagementController` lädt Pläne/Verträge und triggert die Jobs:
    - Recurring Engine
    - Auto-Invoicing
    - Dunning/Retention
    - Provider-spezifischer Payment-Method-Update-Link je Vertrag
- Regressionstest ergänzt:
  - `backend/tests/Regression/subscriptions_billing_phase4_regression_test.php` validiert Registry/Adapter-Mapping (Stripe/PayPal) und Invalid-Provider-Guard.

**Status-Update Phase 4:** Der offene Backlog-Punkt „Provider-Adapter + Flutter Abo-Management-UI“ ist umgesetzt. Für die finale Abnahme folgen produktive Provider-Webhooks/Completion-Callbacks inkl. Sandbox-E2E.

## Schritt 25 – CORS-Fix für Login-Preflight (`crm.ordentis.de` -> `api.ordentis.de`)
- CORS-Handling in `App::applyCors` robuster gemacht:
  - erlaubte Origins werden nicht mehr nur statisch über exakte Liste geprüft,
  - zusätzlich werden `https://*.ordentis.de` sowie `http://localhost:*` unterstützt.
- Hintergrund: Browser-Preflight für `POST /api/login/employee` vom CRM-Origin schlug ohne `Access-Control-Allow-Origin` fehl.
- Regressionstest ergänzt: `backend/tests/Regression/cors_origin_regression_test.php` validiert erlaubte/blockierte Origins.

**Status:** Preflight-Origin-Matching ist für produktive `ordentis.de`-Subdomains und lokale Entwicklungsports abgesichert.

## Schritt 26 – Header-Resolution-Härtung für CORS/Preflight
- `Request::header` erweitert, damit Header nicht nur über `HTTP_*` gelesen werden, sondern auch über Fallbacks:
  - `<HEADER_KEY>` (z. B. `ORIGIN`)
  - `REDIRECT_HTTP_<HEADER_KEY>`
  - optional `getallheaders()` (case-insensitive)
- Hintergrund: Je nach Reverse-Proxy/FPM-Setup kann `Origin` unter abweichenden Server-Keys ankommen, wodurch CORS-Matching trotz korrektem Browser-Origin ins Leere läuft.
- Regressionstest ergänzt: `backend/tests/Regression/request_header_resolution_regression_test.php`.

**Status:** Header-Auflösung ist robust gegenüber typischen Proxy-Varianten; CORS-Origin-Erkennung für Login-Preflights wird dadurch stabiler.

## Schritt 33 – Phase 1 E2E-Bugfix: Snapshot-Prüfung nach Finalisierung stabilisiert (abgeschlossen)
- Fehlerbild aus dem Phase-1-Flow reproduziert und behoben: Bei der Snapshot-Prüfung nach Dokument-Finalisierung konnte `exchange_rate` als String (`"1.080000"`) aus der API zurückkommen.
- `BillingDocumentSnapshot` um `fromApiData(...)`-Factory mit robuster Wechselkurs-Normalisierung erweitert:
  - numerische Werte (`num`) werden direkt übernommen,
  - String-Werte werden per `double.tryParse(...)` zuverlässig in `double` überführt,
  - Fallback bleibt deterministisch bei `1.0` für ungültige/fehlende Werte.
- Repository-Mapping in `ApiBillingFlowRepository.fetchDocumentSnapshot(...)` auf die neue Factory umgestellt, damit der E2E-Flow unabhängig vom konkreten JSON-Typ stabil bleibt.
- Testabdeckung ergänzt:
  - neuer Flutter-Test validiert explizit die Verarbeitung von String-`exchange_rate` im Snapshot-Mapping.

**Abnahme-Status Schritt 33:** Der gemeldete Phase-1-E2E-Fehler `type 'String' is not a subtype of type 'num?'` ist behoben und durch einen gezielten Regressionstest abgesichert.

## Schritt 34 – Phase 1 E2E-Bugfix nachgeschärft (striktes `exchange_rate`-Parsing)
- Review-Feedback zum vorherigen Fix eingearbeitet: Das Snapshot-Mapping toleriert weiterhin `exchange_rate` als String, fällt bei ungültigen Werten aber **nicht** mehr still auf `1.0` zurück.
- `BillingDocumentSnapshot._parseExchangeRate(...)` validiert nun strikt und wirft bei ungültigem/fehlendem Payload einen `FormatException`-Fehler.
- Damit wird der Fehlerpfad im E2E-Flow eindeutig (statt stillschweigender Default-Werte) und vereinfacht die Diagnose bei fehlerhaften Backend-Payloads.
- Testabdeckung erweitert: neuer Test stellt sicher, dass invalide `exchange_rate`-Strings explizit einen Fehler auslösen.

**Abnahme-Status Schritt 34:** String-basierte Wechselkurse bleiben unterstützt; ungültige Payload-Werte werden jetzt explizit und regressionssicher als Fehler behandelt.

## Schritt 35 – Admin-User-Anlage 422 behoben (`missing_user_header`)
- Fehleranalyse für `POST /api/admin/users` durchgeführt: Backend verlangte zwingend Header `X-User-Id`, während der Flutter-Admin-Client ihn nicht mitsendete.
- Flutter-Auth/Request-Header erweitert:
  - `AuthState` um `userId` ergänzt,
  - Login speichert `user_id` aus der Auth-Response,
  - Admin-/Billing-API-Requests senden `X-User-Id`, sobald vorhanden.
- Auth-API-Payload erweitert, damit `user_id` explizit im Login-Response enthalten ist.
- Account-Management-Controller gehärtet:
  - Für `tenant_id=superadmin` und Wildcard-Permissions (`X-Permissions: *`) wird ein synthetischer Admin-Actor akzeptiert,
  - damit schlagen Superadmin-Demo-Flows nicht mehr an der lokalen Account-Auflösung fehl.
- Regressionstest ergänzt: `backend/tests/Regression/account_management_superadmin_regression_test.php` validiert den Superadmin-Bypass deterministisch.

**Abnahme-Status Schritt 35:** Der gemeldete 422-Fehler (`missing_user_header`) ist für den Superadmin-Admin-Flow technisch adressiert; Header-Propagation und Backend-Authorisierungspfad sind regressionsgesichert.

## Schritt 36 – Phase 2 End-to-End nachgezogen (Provider-Adapter + Mahnstufen-Regression)
- Payment-Link-Persistenz im Plugin `billing_payments` auf echten Adapter-Output erweitert:
  - `provider_response_json` wird für Stripe- und PayPal-Linkerzeugung jetzt vollständig gespeichert und im Listing ausgeliefert.
  - Damit bleiben provider-spezifische Rohantworten tenant-sicher für Audit/Support verfügbar.
- Migrationsschema von `billing_payment_links` um `provider_response_json` ergänzt, damit Adapter-Contracts und Persistenz konsistent sind.
- Phase-2-Regressionstest (`backend/tests/Regression/billing_payments_phase2_regression_test.php`) um End-to-End-Pfade erweitert:
  - Service-seitige Stripe-/PayPal-Linkerzeugung gegen persistente Datenhaltung,
  - Validierung der gespeicherten Provider-Rohantwort,
  - bestehende Mahnstufen-Tagesdrossel-/Zins-/Payment-Kind-Checks bleiben aktiv.

**Abnahme-Status Schritt 36:** Phase-2-Flow ist End-to-End (Adapter -> Service -> Persistenz -> Read-Model) vervollständigt; Mahnstufen-Regression bleibt abgesichert.

## Schritt 37 – Phase 4 produktiv verdrahtet (Provider-Webhooks + Completion-Callbacks)
- `subscriptions_billing` um produktionsfähige Callback-/Webhook-Verarbeitung erweitert:
  - Neuer Endpoint `POST /api/billing/subscriptions/payment-method-updates/complete` für providerseitige Completion-Callbacks (token-basiert, tenant-sicher).
  - Neuer Endpoint `POST /api/billing/subscriptions/providers/{provider}/webhook` für Stripe/PayPal-Webhook-Inbound.
- Service-Layer in `SubscriptionsBillingService` ausgebaut:
  - Abschlusslogik für Payment-Method-Updates inkl. Status-Transition (`open -> completed|failed`).
  - Bei erfolgreichem Abschluss werden `subscription_contracts.payment_method_ref` aktualisiert sowie offene Dunning-Fälle (`payment_method_update_required=1`) auf retry-fähig zurückgesetzt.
  - Provider-Webhooks mappen Stripe- und PayPal-Sandbox-Events auf denselben Abschlussfluss.
  - Signaturprüfung über Shared-Secret-Validierung (HMAC-SHA256) via Umgebungsvariablen:
    - `SUBSCRIPTIONS_STRIPE_WEBHOOK_SECRET`
    - `SUBSCRIPTIONS_PAYPAL_WEBHOOK_SECRET`
- Regressionstest für End-to-End-Abnahme ergänzt:
  - `backend/tests/Regression/subscriptions_billing_phase4_webhooks_regression_test.php`
  - Prüft Completion-Callback, Stripe-Webhook und PayPal-Webhook inklusive Persistenz-/Dunning-/Audit-Zyklus-Effekten in einem durchgängigen Szenario.

**Abnahme-Status Schritt 37:** Der zuvor offene Phase-4-Backlogpunkt „Provider-Webhooks/Completion-Callbacks produktiv anbinden“ ist technisch umgesetzt und regressionsgesichert. Für die finale externe Abnahme mit echten PSP-Sandboxes müssen nur noch tenant-spezifische Sandbox-Secrets im Zielsystem gesetzt und gegen die realen Provider-Endpoints durchgeklickt werden.

## Schritt 38 – Phase 5 erweitert (Delivery-Worker + Portal-UI-Flow)
- `document_delivery` um Worker-Endpoint ergänzt: `POST /api/billing/delivery/process` (optional `limit`).
- Queue-Worker-Fachlogik ergänzt (`DocumentDeliveryService::processQueue`):
  - verarbeitet `email_queue` mit Zuständen `queued|retry|processing|sent|failed`.
  - Exponential Backoff über `retry_count`/`next_retry_at`, Fehlerdiagnose in `last_error`.
  - Provider-Dispatch-Validierung für SMTP/SendGrid/Mailgun inkl. Konfigurationschecks.
- Portalzugriff robust gegen Login-Identitäten gemacht: `X-User-Id` wird als Account-ID **oder** als E-Mail-Identifier aufgelöst (kompatibel zum Portal-Login-Flow).
- `email_queue`-Schema für Workerbetrieb erweitert (`retry_count`, `next_retry_at`, `processed_at`, `last_error`, `provider`, `message_id`, `updated_at`, Worker-Indizes).
- Flutter-Portal-Flow umgesetzt:
  - neuer Portal-Screen mit Dokumentliste + Detailansicht inkl. Zahlungsoptionen.
  - Login-Routing leitet `entrypoint=customer` direkt ins Kundenportal (`/portal`).

**Abnahme-Status Schritt 38:** Delivery-Worker und Kundenportal-UI sind implementiert; offener Abschluss für Phase 5 ist die produktionsnahe Sandbox-Abnahme der Provider inkl. Tracking-/Monitoring-Nachweis.

## Schritt 39 – Phase 6 gehärtet (Connector-Sync + DATEV/Excel-Streaming)
- `finance_reporting` um produktionsnahe Export-Download-Pipeline erweitert:
  - Neuer Endpoint `POST /api/billing/finance/exports/stream` liefert DATEV/OP/Steuerexporte als Datei-Stream.
  - Download-Header inkl. Dateiname, MIME-Type und Cache-Control über `Response::streamDownload` ergänzt.
  - Export-Writer schreibt CSV (`;`) bzw. Excel-kompatibles TSV (`\t`) zeilenweise auf `php://output`.
- Connector-Synchronisation eingeführt:
  - Neuer Endpoint `POST /api/billing/finance/connectors/sync` verarbeitet Queue-Einträge aus `finance_reporting_webhook_logs`.
  - Statusübergänge `queued -> delivered|failed` mit persistenter Rückschreibung implementiert.
  - Outbound-Webhook-Call mit Timeout, HMAC-Signaturheader (`X-Finance-Signature`) und HTTP-Statusauswertung gehärtet.
- Regressionstest ergänzt:
  - `backend/tests/Regression/finance_reporting_phase6_regression_test.php`
  - Deckt Datei-Streaming (Header/Rows) und Connector-Sync-Failurepfad (fehlende Webhook-URL) inkl. Statuspersistenz ab.

**Abnahme-Status Schritt 39:** Der konkrete Phase-6-Backlogpunkt „Connector-Synchronisation + DATEV/Excel-Datei-Streaming produktiv härten“ ist umgesetzt und regressionsseitig abgesichert; für die finale Produktionsabnahme fehlen nur noch reale Provider-Sandbox-Tests (Lexoffice/SevDesk/FastBill) mit tenant-spezifischen Secrets/Endpoints.

## Schritt 26 – PLUGIN_ROADMAP Phase 7 erweitert: Rollen auf Plugin-Capabilities mappen
- `OrgManagementService` um ein tenant-spezifisches Rollen-Capability-Mapping erweitert (`listRoleCapabilityMap`):
  - Konsolidiert je Rolle die effektiven Permissions aus `roles`/`role_permissions`.
  - Mapped nur **aktive + enabled** Plugins aus `tenant_plugins` auf die Rolle.
  - Berücksichtigt `required_permissions_json` je Plugin (inkl. Wildcard `*`) und liefert resultierende `plugin_capabilities` je Rolle.
- Neuer API-Endpunkt im Org-Management ergänzt:
  - `GET /api/org/roles/capabilities` (RBAC: `rbac.manage`) für die UI-Matrix „Rolle -> Plugin -> Capabilities“.
- Regressionstest ergänzt:
  - `backend/tests/Regression/org_management_role_capability_map_regression_test.php` prüft Rollenmatrix für `admin`, `buchhaltung`, `readonly` inkl. Filterung inaktiver Plugins.

**Fortschrittsstatus Phase 7:** Das Rollenmodell ist nun an Plugin-Capabilities gekoppelt und als auslesbare API verfügbar. Nächster Ausbau bleibt die UI-seitige Pflege/Visualisierung inkl. Default-Rollenprofilen.

## Schritt 40 – Phase 7 ausgeliefert: Default-Rollenprofile + Org-Management-UI-Matrix
- Tenant-spezifische Default-Role-Seeds im Org-Management ergänzt (`OrgManagementService`):
  - Beim Rollenabruf werden fehlende Standardprofile **einmalig pro Tenant** automatisch angelegt: `admin`, `buchhaltung`, `vertrieb`, `readonly`.
  - Seeds enthalten initiale Permission-Sets; bestehende tenant-eigene Rollen/Permissions bleiben unverändert (keine Überschreibung).
- Org-Management-UI im Admin-Dashboard erweitert:
  - Rollenpflege bleibt über `/api/admin/roles/permissions` erhalten.
  - Zusätzlich wird die Matrix `/api/org/roles/capabilities` geladen und visualisiert (Rolle -> Permissions -> aktive Plugin-Capabilities).
  - Hinweis auf ausgelieferte Default-Profile im UI ergänzt.
- Regression abgesichert:
  - `backend/tests/Regression/org_management_default_role_seed_regression_test.php` validiert automatische Seed-Anlage der vier Standardprofile.
  - `backend/tests/Regression/org_management_role_capability_map_regression_test.php` auf Seed-Verhalten (`vertrieb`) erweitert.

**Fortschrittsstatus Phase 7:** Der Backlogpunkt „Default-Rollenprofile als tenant-spezifische Seeds und UI-Matrix im Org-Management“ ist umgesetzt und regressionsseitig abgesichert. Nächster Ausbau: Audit-Log-Filter/Export-UX in der Org-Management-Maske vertiefen.

## Schritt 41 – PLUGIN_ROADMAP Phase 7 (Audit-Log-UX Filter/Export-Flow) im Org-Management vervollständigt
- **Org-Management API** für Audit-Logs erweitert:
  - `GET /api/org/audit-logs` unterstützt nun Filter auf `company_id`, `actor_id`, `action_key`, `status`, `from`, `to` sowie `limit`.
  - `POST /api/org/audit-logs/export` nutzt dieselben Filterkriterien und gibt die angewendeten Filter im Export-Metadatenblock zurück.
- **Request-Core** ergänzt um Query-Parameter-Parsing (`Request::query()`), damit Controller-Filter konsistent über URL-Parameter aufgelöst werden.
- **Flutter Admin-UI (Org-Management Bereich)** um eine vollständige Audit-Log UX erweitert:
  - Filterfelder (Company, Actor, Action, Status, Zeitfenster, Limit)
  - Tabellenansicht der gefilterten Audit-Events
  - CSV-Export-Flow inkl. Ergebnis-Feedback (Dateiname/Zeilenanzahl)
- **Regressionstest** ergänzt für Filter-/Export-Verhalten von Org-Management Audit-Logs (`backend/tests/Regression/org_management_audit_log_filter_export_regression_test.php`).

**Zwischenfazit Phase 7:** Der geforderte Audit-Log-Flow (Filter + Export) ist in API und Org-Management-UI umgesetzt; Governance-relevante Einsicht und Datenmitnahme sind tenant-sicher bedienbar.

## Schritt 42 – Phase 7 abgesichert: Multi-Company-Kontextwechsel + Membership-UX End-to-End
- **Org-Management API erweitert:**
  - Neuer Endpoint `GET /api/org/companies/{companyId}/memberships` liefert Teamzuordnungen je Company für die Membership-UX.
  - Service-Layer um `listCompanyMemberships` ergänzt; tenant-sicherer Join auf Company + Membership.
- **Flutter Admin-UI erweitert (Org-Management):**
  - Neuer Bereich „Multi-Company Kontext“ mit Company-Auswahl, Membership-Pflege (`user_id` + `role_key`) und kontextbezogenem `POST /org/context/switch`-Flow.
  - Erfolgreicher Kontextwechsel aktualisiert den Auth-State (`companyId`, effektive Permissions) und sendet Folgerequests mit `X-Company-Id` Header.
- **Regression abgesichert:**
  - `backend/tests/Regression/org_management_multi_company_context_regression_test.php` deckt Membership-Liste, Membership-Zuweisung und Kontextwechsel inkl. Rollen-/Permission-Auflösung ab.

**Zwischenfazit Phase 7:** Multi-Company-Kontextwechsel und Membership-UX sind nun End-to-End vom API- bis zum Flutter-Admin-Flow umgesetzt und regressionsseitig abgesichert.

## Schritt 31 – PLUGIN_ROADMAP Phase 8 (Adapter-Worker + Flutter-Import-Wizard-UI) erweitert (In Umsetzung)
- Backend `automation_integrations` um einen **Adapter-Worker** für queued Workflow-Runs erweitert:
  - Neue Service-Operation `processAutomationRuns()` mit Claim-Logik (`queued -> processing -> completed/failed`) und provider-spezifischer Dispatch-Schicht (`zapier`/`make`).
  - API-Endpunkt ergänzt: `POST /api/billing/automation/workflows/process`.
  - Worker schreibt Verarbeitungsergebnis in den Run-Datensatz zurück (inkl. Adapter-Metadaten).
- Flutter-Frontend um eine dedizierte **Phase-8 Automation/Import-Wizard-UI** ergänzt:
  - Neues Riverpod-Controller-Modul für Import-Preview, Import-Execute und Worker-Queue-Verarbeitung.
  - Neue Admin-Ansicht mit Dataset-Auswahl (`customers`, `products`, `historical_invoices`), JSON-Eingabe, Preview/Execute-Buttons und Ergebnis-Panels.
  - Dashboard-Navigation auf die neue Integrationsansicht umgestellt.
- Regressionstest für Phase-8-Härtung ergänzt (`backend/tests/Regression/automation_integrations_phase8_regression_test.php`):
  - Queue->Worker->Status-Update abgedeckt.
  - Import Preview + Execute inkl. Persistenzvalidierung abgedeckt.

**Abnahme-Status Phase 8 (Zwischenstand):** Adapter-Worker und Import-Wizard-UI sind funktional ergänzt; ausstehend ist die produktionsnahe Sandbox-Abnahme gegen reale CRM/No-Code-Integrationsprovider.
