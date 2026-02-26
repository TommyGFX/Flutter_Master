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
