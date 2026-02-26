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
- `POST /api/pdf/render`
- `POST /api/email/send`
- Wenn `STRIPE_WEBHOOK_SECRET` gesetzt ist, wird die Signatur zwingend validiert.


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
