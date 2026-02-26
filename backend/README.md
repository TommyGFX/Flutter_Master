# PHP 8.5 Backend (PDO/MySQL) – Starter

## Start
```bash
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
- `GET|POST|PUT|DELETE /api/crud/{resource}`
- `POST /api/upload/image`
- `POST /api/upload/file`
- `POST /api/stripe/webhook`
