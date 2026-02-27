# Production Release Notes

## Endpoint-Inventar (aus `backend/src/Core/App.php` generiert)

Alle Endpunkte sind im Frontend über **`/api-workbench`** erreichbar (Admin-Modul `ApiWorkbenchScreen`).

| Methode | Endpoint | Frontend-UI |
|---|---|---|
| `POST` | `/api/login/company` | `/api-workbench` |
| `POST` | `/api/login/employee` | `/api-workbench` |
| `POST` | `/api/login/portal` | `/api-workbench` |
| `POST` | `/api/admin/login` | `/api-workbench` |
| `POST` | `/api/token/refresh` | `/api-workbench` |
| `POST` | `/api/logout` | `/api-workbench` |
| `GET` | `/api/crud/{resource}` | `/api-workbench` |
| `POST` | `/api/crud/{resource}` | `/api-workbench` |
| `PUT` | `/api/crud/{resource}/{id}` | `/api-workbench` |
| `DELETE` | `/api/crud/{resource}/{id}` | `/api-workbench` |
| `POST` | `/api/upload/image` | `/api-workbench` |
| `POST` | `/api/upload/file` | `/api-workbench` |
| `POST` | `/api/stripe/checkout-session` | `/api-workbench` |
| `POST` | `/api/stripe/customer-portal` | `/api-workbench` |
| `POST` | `/api/stripe/webhook` | `/api-workbench` |
| `POST` | `/api/pdf/render` | `/api-workbench` |
| `POST` | `/api/email/send` | `/api-workbench` |
| `GET` | `/api/admin/plugins` | `/api-workbench` |
| `POST` | `/api/admin/plugins/{plugin}/status` | `/api-workbench` |
| `PUT` | `/api/admin/plugins/{plugin}/lifecycle` | `/api-workbench` |
| `GET` | `/api/admin/roles/permissions` | `/api-workbench` |
| `PUT` | `/api/admin/roles/{roleKey}/permissions` | `/api-workbench` |
| `GET` | `/api/admin/approvals` | `/api-workbench` |
| `POST` | `/api/admin/approvals/{approvalId}/approve` | `/api-workbench` |
| `POST` | `/api/admin/approvals/{approvalId}/reject` | `/api-workbench` |
| `GET` | `/api/admin/plugin-shell` | `/api-workbench` |
| `GET` | `/api/admin/feature-flags` | `/api-workbench` |
| `PUT` | `/api/admin/feature-flags/{flagKey}` | `/api-workbench` |
| `POST` | `/api/admin/domain-events` | `/api-workbench` |
| `POST` | `/api/admin/outbox/process` | `/api-workbench` |
| `GET` | `/api/admin/outbox/metrics` | `/api-workbench` |
| `POST` | `/api/platform/impersonate/company` | `/api-workbench` |
| `GET` | `/api/platform/admin-stats` | `/api-workbench` |
| `GET` | `/api/platform/audit-logs` | `/api-workbench` |
| `GET` | `/api/platform/reports` | `/api-workbench` |
| `GET` | `/api/admin/users` | `/api-workbench` |
| `POST` | `/api/admin/users` | `/api-workbench` |
| `PUT` | `/api/admin/users/{id}` | `/api-workbench` |
| `DELETE` | `/api/admin/users/{id}` | `/api-workbench` |
| `GET` | `/api/customers` | `/api-workbench` |
| `POST` | `/api/customers` | `/api-workbench` |
| `PUT` | `/api/customers/{id}` | `/api-workbench` |
| `DELETE` | `/api/customers/{id}` | `/api-workbench` |
| `GET` | `/api/billing/documents` | `/api-workbench` |
| `POST` | `/api/billing/documents` | `/api-workbench` |
| `GET` | `/api/billing/documents/{id}` | `/api-workbench` |
| `PUT` | `/api/billing/documents/{id}` | `/api-workbench` |
| `POST` | `/api/billing/documents/{id}/finalize` | `/api-workbench` |
| `POST` | `/api/billing/documents/{id}/convert-to-invoice` | `/api-workbench` |
| `POST` | `/api/billing/documents/{id}/credit-note` | `/api-workbench` |
| `POST` | `/api/billing/documents/{id}/status` | `/api-workbench` |
| `GET` | `/api/billing/documents/{id}/history` | `/api-workbench` |
| `GET` | `/api/billing/documents/{id}/pdf` | `/api-workbench` |
| `GET` | `/api/billing/documents/{id}/payment-links` | `/api-workbench` |
| `POST` | `/api/billing/documents/{id}/payment-links` | `/api-workbench` |
| `GET` | `/api/billing/documents/{id}/payments` | `/api-workbench` |
| `POST` | `/api/billing/documents/{id}/payments` | `/api-workbench` |
| `GET` | `/api/billing/dunning/config` | `/api-workbench` |
| `PUT` | `/api/billing/dunning/config` | `/api-workbench` |
| `POST` | `/api/billing/dunning/run` | `/api-workbench` |
| `GET` | `/api/billing/dunning/cases` | `/api-workbench` |
| `GET` | `/api/billing/bank-account` | `/api-workbench` |
| `PUT` | `/api/billing/bank-account` | `/api-workbench` |
| `GET` | `/api/billing/tax-compliance/config` | `/api-workbench` |
| `PUT` | `/api/billing/tax-compliance/config` | `/api-workbench` |
| `POST` | `/api/billing/tax-compliance/documents/{id}/preflight` | `/api-workbench` |
| `POST` | `/api/billing/tax-compliance/documents/{id}/seal` | `/api-workbench` |
| `POST` | `/api/billing/tax-compliance/documents/{id}/correction` | `/api-workbench` |
| `GET` | `/api/billing/tax-compliance/documents/{id}/e-invoice/export` | `/api-workbench` |
| `POST` | `/api/billing/tax-compliance/e-invoice/import` | `/api-workbench` |
| `GET` | `/api/billing/subscriptions/plans` | `/api-workbench` |
| `POST` | `/api/billing/subscriptions/plans` | `/api-workbench` |
| `GET` | `/api/billing/subscriptions/contracts` | `/api-workbench` |
| `POST` | `/api/billing/subscriptions/contracts` | `/api-workbench` |
| `PUT` | `/api/billing/subscriptions/contracts/{id}` | `/api-workbench` |
| `POST` | `/api/billing/subscriptions/contracts/{id}/change-plan` | `/api-workbench` |
| `POST` | `/api/billing/subscriptions/run-recurring` | `/api-workbench` |
| `POST` | `/api/billing/subscriptions/auto-invoicing/run` | `/api-workbench` |
| `POST` | `/api/billing/subscriptions/dunning/run` | `/api-workbench` |
| `POST` | `/api/billing/subscriptions/contracts/{id}/payment-method-update-link` | `/api-workbench` |
| `POST` | `/api/billing/subscriptions/payment-method-updates/complete` | `/api-workbench` |
| `POST` | `/api/billing/subscriptions/providers/{provider}/webhook` | `/api-workbench` |
| `GET` | `/api/billing/delivery/templates` | `/api-workbench` |
| `PUT` | `/api/billing/delivery/templates/{templateKey}` | `/api-workbench` |
| `GET` | `/api/billing/delivery/provider` | `/api-workbench` |
| `PUT` | `/api/billing/delivery/provider` | `/api-workbench` |
| `POST` | `/api/billing/delivery/process` | `/api-workbench` |
| `GET` | `/api/portal/documents` | `/api-workbench` |
| `GET` | `/api/portal/documents/{id}` | `/api-workbench` |
| `POST` | `/api/billing/delivery/tracking/events` | `/api-workbench` |
| `GET` | `/api/billing/finance/kpis` | `/api-workbench` |
| `GET` | `/api/billing/finance/op-list` | `/api-workbench` |
| `GET` | `/api/billing/finance/tax-report` | `/api-workbench` |
| `POST` | `/api/billing/finance/exports` | `/api-workbench` |
| `POST` | `/api/billing/finance/exports/stream` | `/api-workbench` |
| `GET` | `/api/billing/finance/connectors` | `/api-workbench` |
| `PUT` | `/api/billing/finance/connectors` | `/api-workbench` |
| `POST` | `/api/billing/finance/connectors/{provider}/webhook` | `/api-workbench` |
| `POST` | `/api/billing/finance/connectors/sync` | `/api-workbench` |
| `GET` | `/api/org/companies` | `/api-workbench` |
| `POST` | `/api/org/companies` | `/api-workbench` |
| `GET` | `/api/org/companies/{companyId}/memberships` | `/api-workbench` |
| `PUT` | `/api/org/companies/{companyId}/memberships` | `/api-workbench` |
| `POST` | `/api/org/context/switch` | `/api-workbench` |
| `GET` | `/api/org/roles` | `/api-workbench` |
| `PUT` | `/api/org/roles/{roleKey}` | `/api-workbench` |
| `GET` | `/api/org/roles/capabilities` | `/api-workbench` |
| `GET` | `/api/org/audit-logs` | `/api-workbench` |
| `POST` | `/api/org/audit-logs/export` | `/api-workbench` |
| `GET` | `/api/billing/automation/api-versions` | `/api-workbench` |
| `POST` | `/api/billing/automation/api-versions` | `/api-workbench` |
| `POST` | `/api/billing/automation/idempotency/claim` | `/api-workbench` |
| `GET` | `/api/billing/automation/crm/connectors` | `/api-workbench` |
| `PUT` | `/api/billing/automation/crm/connectors` | `/api-workbench` |
| `POST` | `/api/billing/automation/crm/{provider}/sync` | `/api-workbench` |
| `GET` | `/api/billing/automation/time-entries` | `/api-workbench` |
| `POST` | `/api/billing/automation/time-entries` | `/api-workbench` |
| `POST` | `/api/billing/automation/time-entries/invoice` | `/api-workbench` |
| `GET` | `/api/billing/automation/workflows/catalog` | `/api-workbench` |
| `POST` | `/api/billing/automation/workflows/runs` | `/api-workbench` |
| `POST` | `/api/billing/automation/workflows/process` | `/api-workbench` |
| `POST` | `/api/billing/automation/import/preview` | `/api-workbench` |
| `POST` | `/api/billing/automation/import/execute` | `/api-workbench` |
| `GET` | `/api/billing/catalog/products` | `/api-workbench` |
| `POST` | `/api/billing/catalog/products` | `/api-workbench` |
| `PUT` | `/api/billing/catalog/products/{id}` | `/api-workbench` |
| `GET` | `/api/billing/catalog/price-lists` | `/api-workbench` |
| `POST` | `/api/billing/catalog/price-lists` | `/api-workbench` |
| `PUT` | `/api/billing/catalog/price-lists/{id}` | `/api-workbench` |
| `GET` | `/api/billing/catalog/price-lists/{id}/items` | `/api-workbench` |
| `POST` | `/api/billing/catalog/price-lists/{id}/items` | `/api-workbench` |
| `GET` | `/api/billing/catalog/bundles` | `/api-workbench` |
| `POST` | `/api/billing/catalog/bundles` | `/api-workbench` |
| `PUT` | `/api/billing/catalog/bundles/{id}` | `/api-workbench` |
| `GET` | `/api/billing/catalog/discount-codes` | `/api-workbench` |
| `POST` | `/api/billing/catalog/discount-codes` | `/api-workbench` |
| `POST` | `/api/billing/catalog/quotes/calculate` | `/api-workbench` |
| `GET` | `/api/platform/security/gdpr` | `/api-workbench` |
| `PUT` | `/api/platform/security/gdpr/retention-rules` | `/api-workbench` |
| `POST` | `/api/platform/security/gdpr/exports` | `/api-workbench` |
| `POST` | `/api/platform/security/gdpr/deletions` | `/api-workbench` |
| `GET` | `/api/platform/security/auth-policies` | `/api-workbench` |
| `PUT` | `/api/platform/security/auth-policies` | `/api-workbench` |
| `GET` | `/api/platform/security/backups` | `/api-workbench` |
| `POST` | `/api/platform/security/backups` | `/api-workbench` |
| `POST` | `/api/platform/security/backups/restore` | `/api-workbench` |
| `GET` | `/api/platform/security/archive-records` | `/api-workbench` |
| `POST` | `/api/platform/security/archive-records` | `/api-workbench` |
| `GET` | `/api/platform/security/reliability/policies` | `/api-workbench` |
| `PUT` | `/api/platform/security/reliability/policies` | `/api-workbench` |
| `GET` | `/api/billing/customers` | `/api-workbench` |
| `POST` | `/api/billing/customers` | `/api-workbench` |
| `PUT` | `/api/billing/customers/{id}` | `/api-workbench` |
| `GET` | `/api/self/profile` | `/api-workbench` |
| `PUT` | `/api/self/profile` | `/api-workbench` |

## Smoke-Test (manuell)

1. Backend mit produktiver `.env` starten.
2. Flutter Web im Release mit `--dart-define=API_BASE_URL=https://<api-host>/api` starten.
3. Als Admin einloggen (`/login`).
4. In Dashboard auf **Endpoint Workbench** gehen.
5. Für jeden Endpoint: Path-Parameter ersetzen, Query/Body JSON eintragen, Request absenden.
6. Prüfen: Loading, Fehlerkarte, Empty-Hinweis und Response-Anzeige.

## Deploy-Checkliste

- `backend/.env` aus `backend/.env.example` erstellen und alle Secrets setzen.
- DB-Migration `backend/src/migrations/001_init.sql` in produktiver DB ausführen.
- PHP-FPM/Nginx oder Apache auf `backend/public` deployen.
- Flutter Web Release bauen: `flutter build web --release --dart-define=API_BASE_URL=...`.
- Build-Artefakt `flutter_app/build/web` ausliefern.