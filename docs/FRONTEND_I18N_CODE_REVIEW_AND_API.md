# Frontend i18n Refactoring – Code Review & API Documentation

## 1) Scope
Dieses Dokument beschreibt den abgeschlossenen Frontend-Refactor auf Internationalisierung (i18n), das begleitende Senior-Code-Review nach Flutter-3.41-Standards sowie die API-Dokumentation der im Frontend verwendeten Endpunkte.

---

## 2) Refactoring-Überblick (Flutter UI → i18n)

### Ziele
- Hardcoded UI-Texte aus den Screens entfernen.
- Zentrale Lokalisierung für Deutsch + Englisch bereitstellen.
- `MaterialApp` auf lokalisierte Titel und Delegates umstellen.

### Umgesetzte Punkte
- Neue ARB-Quellen für `en` und `de` angelegt.
- App-weite Lokalisierungsklasse und BuildContext-Extension ergänzt.
- Login-, Dashboard- und CRUD-UI vollständig auf lokalisierte Strings umgestellt.
- Lokalisierungs-Setup in `main.dart` aktualisiert.

---

## 3) Senior Code Review (vollständig)

### 3.1 Architektur & Wartbarkeit
- **Positiv:** Saubere Feature-Trennung (`features/auth`, `features/admin`, `features/crud`) und zentralisierte Netzwerkschicht via Dio-Provider.
- **Verbesserung umgesetzt:** UI-Strings zentral in Lokalisierung ausgelagert; dadurch bessere Wartbarkeit, einfachere Übersetzbarkeit und weniger Duplikate.

### 3.2 Flutter 3.41 Standards
- Material-3-fähige Widgets und Theme-Nutzung bleiben erhalten.
- `context.mounted` wird nach async Login-Navigation korrekt genutzt.
- State-Handling über Riverpod bleibt konsistent.
- Controller-Disposal ergänzt (Login/CRUD), um Memory-Leaks zu vermeiden.

### 3.3 UX-Review
- Alle Haupt-Navigationslabels und Card-Titel sind jetzt lokalisierbar.
- API-Aktionslabels wurden konsistent in das i18n-System übernommen.
- Fallbacks für dynamische Textinhalte sind weiterhin vorhanden (`not set`, `none`).

### 3.4 Risiken / offene Punkte
- In dieser Umgebung war `flutter gen-l10n` nicht verfügbar. Daher wurde eine lokale `AppLocalizations`-Implementierung ergänzt, damit das Projekt weiterhin konsistent bleibt.
- Für produktive Pipelines wird empfohlen, die Generierung via Flutter SDK in CI verpflichtend auszuführen.

### 3.5 Ergebnis
✅ i18n-Refactoring abgeschlossen.  
✅ Review-relevante Qualitätsverbesserungen integriert.  
✅ API-Dokumentation aktualisiert.

---

## 4) Frontend API-Dokumentation

## 4.1 Authentifizierung

### POST `/api/login/company`
- Zweck: Login für Company-Accounts.
- Request: `{ email, password, tenant_id }`
- Response (typisch): Token, Tenant-Kontext, Berechtigungen.

### POST `/api/login/employee`
- Zweck: Login für Mitarbeiter.
- Request: `{ email, password, tenant_id }`

### POST `/api/login/portal`
- Zweck: Login für Portal-/Customer-Zugänge.
- Request: `{ email, password, tenant_id }`

### POST `/api/admin/login`
- Zweck: Superadmin-Login.
- Request: `{ email, password }`

---

## 4.2 Admin Dashboard APIs

### GET `/api/admin/plugins`
- Zweck: Plugin-Status pro Tenant lesen.
- Header: `X-Tenant-Id`, optional `Authorization`, `X-Permissions`.

### POST `/api/admin/plugins/{pluginKey}/status`
- Zweck: Plugin aktivieren/deaktivieren (Approval-basiert).
- Request: `{ is_active: bool }`

### GET `/api/admin/roles/permissions`
- Zweck: Rollen und Berechtigungen abrufen.

### PUT `/api/admin/roles/{roleKey}/permissions`
- Zweck: Berechtigungen einer Rolle aktualisieren (Approval-basiert).
- Request: `{ permissions: string[] }`

### GET `/api/admin/approvals`
- Zweck: Approval Requests laden.

### POST `/api/admin/approvals/{id}/approve`
- Zweck: Approval freigeben.

### POST `/api/admin/approvals/{id}/reject`
- Zweck: Approval ablehnen.

---

## 4.3 Platform APIs (Superadmin)

### GET `/api/platform/admin-stats`
- Zweck: Plattform-KPIs abrufen.

### GET `/api/platform/audit-logs`
- Zweck: Globale Audit-Logs abrufen.

### GET `/api/platform/reports`
- Zweck: Plattform-Reports abrufen.

### POST `/api/platform/impersonate/company`
- Zweck: Impersonation eines Company-Tenants.
- Request: `{ tenant_id: string }`

---

## 4.4 Account APIs

### GET `/api/admin/users`
### POST `/api/admin/users`
- Zweck: Admin-User lesen/anlegen.

### GET `/api/customers`
### POST `/api/customers`
- Zweck: Customer lesen/anlegen.

### GET `/api/self/profile`
- Zweck: Eigenes Profil lesen.

---

## 4.5 Integrations-APIs

### POST `/api/stripe/checkout-session`
- Zweck: Stripe Checkout Session erzeugen.

### POST `/api/stripe/customer-portal`
- Zweck: Stripe Customer Portal Session erzeugen.

### POST `/api/pdf/render`
- Zweck: HTML als PDF rendern.

### POST `/api/email/send`
- Zweck: E-Mail versenden.

---

## 4.6 CRUD API

### GET `/api/crud/crm_items`
- Zweck: CRM-Datensätze listen.

### POST `/api/crud/crm_items`
- Zweck: CRM-Datensatz anlegen.
- Request: `{ name: string }`

---

## 5) Fortschrittsstatus
- i18n-Refactor: **abgeschlossen**
- Senior Code Review: **abgeschlossen**
- API Dokumentation: **abgeschlossen**
