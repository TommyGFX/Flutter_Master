# Plugin-SDK Contract (Phase 0, verbindlich)

## Ziel und Geltungsbereich
Dieser Vertrag fixiert die technische Schnittstelle für das Plugin-System in Phase 0 der Billing/Finance-SaaS-Roadmap. Er gilt als **verbindliche Basis** für Backend-, API- und UI-Integrationen sowie für alle nachfolgenden Plugin-Phasen (1-10).

Referenzquellen der bestehenden Implementierung:
- Persistenzmodell in `backend/src/migrations/001_init.sql`
- Runtime-/Lifecycle-Handling in `backend/src/Services/PluginManager.php`
- API-/Shell-Verhalten in `backend/src/Controllers/PluginFoundationController.php`

---

## 1) Plugin-Metadaten (Contract-first)

### 1.1 Kanonische Felder
Jedes Plugin muss die folgenden Metadaten führen:
- `plugin_key` (string, eindeutig)
- `version` (string, semver-kompatibel)
- `display_name` (string)
- `capabilities` (string[])
- `required_permissions` (string[])
- `lifecycle_status` (`installed | enabled | suspended | retired`)

### 1.2 Persistenzabbildung
- **Globale Definitionen**: `plugin_definitions`
  - `plugin_key`, `version`, `display_name`, `capabilities_json`, `required_permissions_json`
- **Tenant-spezifische Aktivierung/Lifecycle**: `tenant_plugins`
  - `tenant_id`, `plugin_key`, `display_name`, `version`, `lifecycle_status`, `is_active`

### 1.3 Normative Regeln
- `plugin_key` ist stabil und wird nie tenant-spezifisch umbenannt.
- `version` wird bei inkompatiblen API-/Payload-Änderungen erhöht.
- `capabilities` beschreiben Features, **nicht** UI-Komponenten.
- `required_permissions` definieren die minimale RBAC-Menge zur Sichtbarkeit/Bedienung.

---

## 2) Lifecycle-Contract

### 2.1 Zustandsmodell
Verpflichtendes Lifecycle-Modell pro Tenant:
`installed -> enabled -> suspended -> retired`

### 2.2 Zustandssemantik
- `installed`: Plugin ist registriert, aber nicht aktiv nutzbar.
- `enabled`: Plugin ist aktiv und darf fachliche Hooks/API-Flows ausführen.
- `suspended`: Temporär deaktiviert (Daten bleiben erhalten).
- `retired`: Endzustand für aktive Nutzung; nur read-/migrationsnahe Operationen zulässig.

### 2.3 Aktivitätsregel
`is_active = true` gilt ausschließlich im Zustand `enabled`.

---

## 3) Hook-Contract (erlaubte Hook-Typen)

### 3.1 Zulässige Hooks
Die Plattform unterstützt in Phase 0 ausschließlich:
- `before_validate`
- `before_finalize`
- `after_finalize`
- `before_send`
- `after_payment`

### 3.2 Verhalten
- Nicht-whitelistete Hook-Namen sind ungültig und werden ignoriert/abgelehnt.
- Hook-Konfiguration ist tenant-spezifisch in `plugin_hooks` ablegbar.
- Hook-Ausführung erfolgt ausschließlich innerhalb tenant-sicherer Kontexte.

---

## 4) Tenant/Company Feature-Flags

### 4.1 Scope
Feature-Flags werden in `tenant_feature_flags` pro Kombination aus `tenant_id + company_id + flag_key` gespeichert.

### 4.2 API-Contract
- `GET /api/admin/plugin-foundation/feature-flags`
- `PUT /api/admin/plugin-foundation/feature-flags/{flagKey}`

### 4.3 Regeln
- Standard-`company_id` ist `default`, wenn kein `X-Company-Id` Header gesetzt ist.
- Flags steuern inkrementelle Rollouts und dürfen harte RBAC-Regeln nicht aushebeln.

---

## 5) Domain-Event + Outbox Contract

### 5.1 Pflicht-Events (Phase 0)
- `invoice.created`
- `invoice.finalized`
- `payment.received`

### 5.2 Persistenz
- Domain-Events: `domain_events`
- Auslieferwarteschlange: `outbox_messages`

### 5.3 Verarbeitungsregeln
- Event-Publishing ist tenant-spezifisch.
- Outbox-Nachrichten sind idempotent über `message_key` eindeutig.
- Retries erfolgen über `retry_count`, `next_retry_at`, `delivery_status`.

### 5.4 API-Contract
- `POST /api/admin/plugin-foundation/domain-events`
- `POST /api/admin/plugin-foundation/outbox/process`

---

## 6) UI-Shell & Sichtbarkeitsregeln

### 6.1 Shell-Endpoint
- `GET /api/admin/plugin-shell`

### 6.2 Sichtbarkeit
Ein Plugin wird im Shell-Response nur gezeigt, wenn:
1. Tenant-Kontext gültig ist (`X-Tenant-Id`),
2. Aufrufer `plugins.manage` besitzt,
3. alle `required_permissions` gegen `X-Permissions` erfüllt sind.

### 6.3 Response-Minimum
Shell liefert je Plugin mindestens:
- `plugin_key`
- `display_name`
- `version`
- `lifecycle_status`
- `capabilities`
- `required_permissions`
- `hooks` (gemäß Whitelist)

---

## 7) Sicherheits- und Governance-Regeln
- Jede Plugin-Operation ist tenant-isoliert.
- RBAC-Prüfungen sind verpflichtend (`plugins.manage` für Foundation-Schnittstellen).
- Vertragsänderungen am Plugin-SDK erfolgen nur versioniert und mit Migrations-/Kompatibilitätsplan.

---

## 8) Definition of Done für „Phase 0 – Plugin-SDK Contract schriftlich fixieren“
Dieser Schritt gilt als erledigt, wenn:
1. der vorliegende Contract dokumentiert und im Repo versioniert ist,
2. die Contract-Inhalte mit bestehender Implementierung konsistent sind,
3. die Fortschrittsdokumentation den Status auf „schriftlich fixiert“ aktualisiert.

