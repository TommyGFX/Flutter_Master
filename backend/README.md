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
- `POST /api/stripe/webhook`


## Auth Hinweise
- Access Tokens werden mit `firebase/php-jwt` (HS256) signiert.
- `JWT_SECRET` (Fallback `APP_KEY`) steuert die Signatur.
- Refresh Tokens sind One-Time-Tokens mit Rotation und DB-Persistenz (`refresh_tokens`).
- `REFRESH_TOKEN_TTL` steuert die Laufzeit in Sekunden (Default 30 Tage).
