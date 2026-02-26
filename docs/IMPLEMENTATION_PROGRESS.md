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
- Stripe Webhook-Endpunkt (Signatur-Header, Event-Dispatch-Skelett).
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
1. Reales JWT via `firebase/php-jwt` + Refresh Tokens.
2. Persistente Queue Worker (Redis/MySQL polling).
3. Komplette Stripe Checkout Session Erstellung + Customer Portal.
4. PDF Rendering (z. B. Dompdf) und SMTP Versand (z. B. Symfony Mailer).
5. Plugin-Lifecycle UI + Rechteverwaltung im Admin-Bereich.
