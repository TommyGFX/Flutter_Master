# PHP 8.5 Backend (PDO/MySQL) – Starter

## Start
```bash
composer install
php -S 0.0.0.0:8080 -t public
```

## Konfiguration
Kopiere `.env.example` nach `.env` und passe DB/Stripe/SMTP Werte an.

## Architektur
- `src/Core`: Kernel, Router, Config, Response
- `src/Controllers`: API Controller
- `src/Services`: JWT, RBAC, Plugin, Stripe, Queue
- `src/Repositories`: DB-Zugriff
- `src/Templates`: Email/PDF Template Hooks

## Domains & CORS
- Produktions-API Domain: `https://api.ordentis.de`
- Erlaubte Frontend-Origin: `https://crm.ordentis.de`
- Für lokale Entwicklung sind zusätzlich `http://localhost:3000` und `http://localhost:5173` freigegeben.

## API Einstiegspunkte
- `POST /api/login/company`
- `POST /api/login/employee`
- `POST /api/login/portal`
- `POST /api/admin/login`
- `POST /api/token/refresh`
- `POST /api/logout`
- `GET|POST|PUT|DELETE /api/crud/{resource}`
- `POST /api/upload/image`
- `POST /api/upload/file`
- `POST /api/stripe/checkout-session`
- `POST /api/stripe/customer-portal`
- `POST /api/stripe/webhook`
- `POST /api/pdf/render`
- `POST /api/email/send`
- `POST /api/platform/impersonate/company`
- `GET /api/platform/admin-stats`
- `GET /api/platform/audit-logs`
- `GET /api/platform/reports`
- `GET|POST|PUT|DELETE /api/admin/users`
- `GET|POST|PUT|DELETE /api/customers`
- `GET|PUT /api/self/profile`
- `GET /api/billing/delivery/templates`
- `PUT /api/billing/delivery/templates/{templateKey}`
- `GET|PUT /api/billing/delivery/provider`
- `GET /api/portal/documents`
- `GET /api/portal/documents/{id}`
- `POST /api/billing/delivery/tracking/events`


## Auth Hinweise
- Access Tokens werden mit `firebase/php-jwt` (HS256) signiert.
- `JWT_SECRET` (Fallback `APP_KEY`) steuert die Signatur.
- Refresh Tokens sind One-Time-Tokens mit Rotation und DB-Persistenz (`refresh_tokens`).
- `REFRESH_TOKEN_TTL` steuert die Laufzeit in Sekunden (Default 30 Tage).


## Stripe Checkout + Customer Portal
### Benötigte ENV-Variablen
- `STRIPE_SECRET_KEY`
- `STRIPE_CHECKOUT_SUCCESS_URL`
- `STRIPE_CHECKOUT_CANCEL_URL`
- `STRIPE_PORTAL_RETURN_URL`
- Optional: `STRIPE_WEBHOOK_SECRET` (für Signaturprüfung)

### Checkout Session erstellen
`POST /api/stripe/checkout-session`

Beispiel-Body:
```json
{
  "mode": "subscription",
  "line_items": [
    {"price": "price_123", "quantity": 1}
  ],
  "customer_email": "kunde@example.com",
  "client_reference_id": "tenant_42",
  "metadata": {"tenant_id": "42"}
}
```

### Customer Portal Session erstellen
`POST /api/stripe/customer-portal`

Beispiel-Body:
```json
{
  "customer_id": "cus_123"
}
```

### Webhook
- `POST /api/stripe/webhook`
- Wenn `STRIPE_WEBHOOK_SECRET` gesetzt ist, wird die Signatur zwingend validiert.
- Domain-Persistenz wird in folgenden Tabellen abgelegt:
  - `stripe_webhook_events` (Roh-Event + Verarbeitungsergebnis, idempotent über `stripe_event_id`)
  - `tenant_provisioning_events` (`checkout.session.completed`)
  - `tenant_subscription_entitlements` (`customer.subscription.updated|deleted`)
  - `stripe_dunning_cases` (`invoice.payment_failed|invoice.paid`)



## PDF Rendering (Dompdf)
- Endpoint: `POST /api/pdf/render`
- Nutzt entweder `html` aus dem Request-Body oder `template_key` aus `pdf_templates`.
- Rückgabe enthält Base64-kodiertes PDF (`content_base64`) für flexible Auslieferung im Frontend.

Beispiel-Body:
```json
{
  "template_key": "invoice",
  "context": {"customer.name": "Max Mustermann"},
  "filename": "rechnung.pdf"
}
```

## Multi-Tenant SMTP Versand (Symfony Mailer)
- Endpoint: `POST /api/email/send`
- SMTP Konfiguration wird tenant-spezifisch aus `tenant_smtp_settings` geladen.
- Fallback auf `.env` (`SMTP_*`), falls kein Tenant-Record existiert.
- Optional kann `template_key` (aus `email_templates`) genutzt werden.

Beispiel-Body:
```json
{
  "to": "kunde@example.com",
  "template_key": "welcome",
  "context": {"customer": {"name": "Max"}}
}
```

## RBAC/Plugin Approval-Flow + Audit-Logs (Multi-Tenant)
Kritische Änderungen werden nicht direkt angewendet, sondern als Approval-Request pro Tenant gespeichert.

### Relevante Header
- `X-Tenant-Id`: Tenant-Kontext (Pflicht)
- `X-User-Id`: ausführender User (Pflicht für Approval/Audit)
- `X-Permissions`: kommaseparierte Permissions (`plugins.manage`, `rbac.manage`, `approvals.manage`)
- `X-Approval-Status`: optionaler Filter für `GET /api/admin/approvals`

### Endpunkte
- `GET /api/admin/plugins`
- `POST /api/admin/plugins/{plugin}/status` → erstellt `pending_approval`
- `PUT /api/admin/plugins/{plugin}/lifecycle` → setzt Plugin-Lifecycle auf `installed|enabled|suspended|retired`
- `GET /api/admin/roles/permissions`
- `PUT /api/admin/roles/{roleKey}/permissions` → erstellt `pending_approval`
- `GET /api/admin/approvals`
- `POST /api/admin/approvals/{approvalId}/approve`
- `POST /api/admin/approvals/{approvalId}/reject`

## Plugin Foundation (Roadmap Phase 0)
Neue Fundament-Endpunkte für standardisierte Plugin-Metadaten, Feature-Flags und Eventing:

- `GET /api/admin/plugin-shell`
  - Liefert sichtbare Plugins inklusive `version`, `lifecycle_status`, `capabilities`, `required_permissions` und Standard-Hooks.
- `GET /api/admin/feature-flags`
  - Liest Feature-Flags pro Tenant/Company (`X-Company-Id`, optional; default `default`).
- `PUT /api/admin/feature-flags/{flagKey}`
  - Setzt Feature-Flag (`{"enabled": true|false}`) tenant-/company-spezifisch.
- `POST /api/admin/domain-events`
  - Persistiert Domain-Events (`invoice.created`, `invoice.finalized`, `payment.received`) und erzeugt Outbox-Message.
- `POST /api/admin/outbox/process`
  - Verarbeitet ausstehende Outbox-Messages in Batches (`{"limit": 50}` optional).
  - Worker nutzt `processing`-Locking, exponentielles Retry-Backoff (bis max. 5 Versuche) und markiert danach als `failed`.
- `GET /api/admin/outbox/metrics`
  - Liefert Monitoring-Metriken (`status`-Zähler, `oldest_pending_at`, Retry-/Timeout-Konfiguration).

### Persistenz
- `approval_requests`: Anfrage, Payload, Requester/Approver, Status (`pending|approved|rejected`)
- `audit_logs`: unveränderbare Audit-Spur je Tenant inkl. Actor, Action, Zielobjekt, IP und User-Agent

Hinweise:
- Self-Approval ist blockiert (`requested_by !== approved_by`).
- Die fachliche Änderung (Plugin-Status / Role-Permissions) wird erst bei Approval im selben Tenant ausgeführt.


## Plattform-Admin (Superadmin)
Diese Endpunkte sind **plattformweit** und nur mit gültigem Superadmin-JWT nutzbar.

### Auth
- Header: `Authorization: Bearer <access_token>`
- Token muss `is_superadmin=true` enthalten.
- Erforderliche Permission: entweder `*` oder die spezifische Action (`platform.*`).

### Endpunkte
- `POST /api/platform/impersonate/company`
  - Erzeugt Access/Refresh-Token für einen Ziel-Tenant (Company-Impersonation).
  - Body: `{ "tenant_id": "tenant_123", "user_id": "optional", "permissions": ["*"] }`
- `GET /api/platform/admin-stats`
  - Liefert globale Kennzahlen (`tenants_total`, `users_total`, `pending_approvals_total`, etc.).
- `GET /api/platform/audit-logs`
  - Globale Audit-Logs mit Pagination (Header `X-Page`, `X-Per-Page`) und Filtern
    (`X-Audit-Tenant-Id`, `X-Audit-Action`, `X-Audit-Status`).
- `GET /api/platform/reports`
  - Erweiterte Analytics je Tenant inkl. Summaries (User, aktive Plugins, offene Approvals, Sessions, Audit-Volumen).

## Tenant-fähige Admin/User/Customer-Verwaltung
- Persistenz über `tenant_accounts` mit Soft-Delete (`deleted_at`) und Lifecycle-Attributen (`is_active`, `email_confirmed`, `created_at`, `updated_at`).
- Enthaltene Felder:
  - `id`, `first_name`, `last_name`, `company`, `street`, `house_number`, `postal_code`, `city`, `country`, `phone`, `email`, `password_hash`, `vat_number`, `tenant_id`, `role_id`, `email_confirmed`, `is_active`, `created_at`, `updated_at`, `deleted_at`, `account_type`.
- API-Rollenverhalten:
  - **Admin**: Vollzugriff auf User + Customer CRUD.
  - **User (Mitarbeiter)**: Vollzugriff auf Customer CRUD.
  - **Customer**: nur eigene Daten lesen/ändern (`/api/customers` liefert nur den eigenen Datensatz, Updates über `/api/self/profile`).

### Relevante Header
- `X-Tenant-Id`: Tenant-Kontext (Pflicht)
- `X-User-Id`: Aktor-ID aus `tenant_accounts` (Pflicht)


## Document Delivery (Roadmap Phase 5)
- Mehrsprachige Templates pro Kanal (`email`, `portal`) über `document_delivery_templates`.
- Tenant-Providerkonfiguration für `smtp`, `sendgrid`, `mailgun` über `document_delivery_provider_configs`.
- Kundenportal liest tenant-sichere Dokumente aus Billing und liefert vorhandene Zahlungslinks mit aus.
- Optionales Tracking erfasst `mail_open`/`link_click` in `document_delivery_tracking_events`.


## Platform Security/Ops (Roadmap Phase 10)
- `GET /api/platform/security/gdpr`
- `PUT /api/platform/security/gdpr/retention-rules`
- `POST /api/platform/security/gdpr/exports`
- `POST /api/platform/security/gdpr/deletions`
- `GET|PUT /api/platform/security/auth-policies`
- `GET|POST /api/platform/security/backups`
- `POST /api/platform/security/backups/restore`
- `GET|POST /api/platform/security/archive-records`
- `GET|PUT /api/platform/security/reliability/policies`

Hinweise:
- Alle Endpunkte sind tenant-spezifisch und erwarten `X-Tenant-Id`.
- DSGVO-Workflows sind als auditable Requests angelegt (`platform_security_data_exports`, `platform_security_deletion_requests`).
- Backup/Restore und Archivierung sind als MVP synchron als `completed` hinterlegt und können auf Worker-Betrieb erweitert werden.
