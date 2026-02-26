# Flutter_Master

Senior-Level Starter für eine **Flutter Web/Android/iOS App** mit **PHP 8.5 Backend** und **MySQL (PDO)**.

## Enthaltene Kernbausteine

### Backend (`backend/`)
- JWT Auth mit Multi-Tenant Claims + Superadmin-Entry.
- 3 Login-Einstiegspunkte + Superadmin:
  - `/api/login/company`
  - `/api/login/employee`
  - `/api/login/portal`
  - `/api/admin/login`
- RBAC-Service Grundstruktur.
- Plugin-System Grundstruktur (DB-basierte Plugin-Routen + Hooks).
- CRUD API (`/api/crud/{resource}`) mit Tenant-Isolation via Header.
- Upload-Endpunkte für Bild und Dateien inkl. MIME-Prüfung.
- Stripe Checkout Session Endpoint inkl. konfigurierbarer Success/Cancel-URLs.
- Stripe Customer Portal Session Endpoint für Self-Service Billing.
- Stripe Webhook Endpunkt mit optionaler Signaturprüfung (`STRIPE_WEBHOOK_SECRET`).
- Email Queue Basisklasse.
- SQL Migrationen für Multi-Tenant/Rollen/Plugins/Templates/Queue.

### Frontend (`flutter_app/`)
- Flutter 3 Struktur mit:
  - Material 3
  - Dark Mode
  - Riverpod 2.6.3
  - Dio 5.9.1
  - Intl 0.20.2
  - ARB (`lib/l10n`)
- Login UI mit 4 Modi (Company/Mitarbeiter/Portal/Superadmin).
- Modernes SaaS-Grundlayout mit Sidebar + Topbar.
- CRUD-Basis-UI gegen Backend API.

## Projekt starten

### Backend
```bash
cd backend
cp .env.example .env
php -S 0.0.0.0:8080 -t public
```

### Flutter
```bash
cd flutter_app
flutter pub get
flutter run -d chrome
```

## Schritt-für-Schritt Dokumentation
Siehe: `docs/IMPLEMENTATION_PROGRESS.md`

## Wichtiger Hinweis
Dies ist ein robuster **Starter** mit sauberer Architektur und Kern-Skeletten für alle geforderten Features.
Für produktiven Einsatz sollten als nächste Schritte u. a. implementiert werden:
- echtes JWT/Refresh Token Handling mit Rotationen,
- Domain-spezifische Stripe Webhook-Persistenz (Provisionierung, Rechnungslauf, Dunning),
- SMTP Versand & Queue Worker,
- PDF Rendering Engine,
- vollständiges RBAC + Plugin Lifecycle UI.
