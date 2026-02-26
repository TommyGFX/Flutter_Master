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
