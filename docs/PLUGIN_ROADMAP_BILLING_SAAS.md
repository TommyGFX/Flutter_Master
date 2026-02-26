# Plugin-Roadmap: Billing/Finance SaaS (Flutter 3.41 Standard)

## Zielbild
Diese Roadmap priorisiert die angefragten Features in kleinen, aufeinander aufbauenden Schritten. Alle Geschäftsdomänen werden als **Plugin** umgesetzt, damit Mandanten Funktionen gezielt aktivieren/deaktivieren können.

## Architekturprinzipien (verbindlich)
- **Plugin-first:** Jede Funktion liegt als aktivierbares Plugin mit Lifecycle `installed -> enabled -> suspended -> retired` vor.
- **Contract-first:** Pro Plugin ein klarer Domain-Contract (DTOs, Events, API-Schnittstellen).
- **Tenant-Isolation:** Jede Query und jeder Write ist tenant-sicher.
- **Finalisierung/Unveränderbarkeit:** Dokumente sind bis zur Finalisierung editierbar, danach nur über Korrekturfluss.
- **Flutter 3.41 UI-Standard:** Material 3, Theme-gesteuerte Komponenten, Lokalisierung, responsive Layout, strikte Trennung von Presentation/State/Domain.

---

## Delivery-Phasen (kleine Schritte)

## Phase 0 – Fundament für alle Plugins
### Schritt 0.1 – Plugin-SDK stabilisieren
- Standardisiere Plugin-Metadaten (`key`, `version`, `capabilities`, `required_permissions`).
- Definiere Hook-Typen: `before_validate`, `before_finalize`, `after_finalize`, `before_send`, `after_payment`.
- Ergänze Feature-Flags pro Tenant/Company.

### Schritt 0.2 – Domain-Event-Bus + Outbox
- Persistente Domain-Events einführen (`invoice.created`, `invoice.finalized`, `payment.received`).
- Outbox + Retry-Worker aufsetzen (Basis für Webhooks, E-Mail, Integrationen).

### Schritt 0.3 – Shared UI Shell (Flutter)
- Einheitliche Plugin-Navigation im Admin-Dashboard.
- Capability-basierte Sichtbarkeit (RBAC + Plugin aktiviert).

**Abnahme Phase 0:** Plugins können sauber registriert, aktiviert und in UI/API sichtbar gemacht werden. ✅ **Abgeschlossen**



## Phase 1 – Core: Rechnungen & Dokumente (MVP)
### Plugin: `billing_core`
### Schritt 1.1 – Dokumentenmodell
- Entitäten: `Document`, `LineItem`, `TaxBreakdown`, `DocumentTotals`, `DocumentAddress`.
- Dokumenttypen: Angebot, Auftragsbestätigung, Rechnung, Gutschrift/Storno.

### Schritt 1.2 – Nummernkreise
- Nummernkreise pro Jahr/Serie, transaktionssicher und lückenlos.
- Reservierung beim Finalisieren, niemals bei Entwurf.

### Schritt 1.3 – Rechenlogik
- Positionszeilen, Mengen, Rabatte (pro Zeile + gesamt), Versand/Fees.
- Brutto/Netto/USt-Berechnung deterministisch und testbar.

### Schritt 1.4 – Kunden-/Adressbuch
- Firmen- und Privatkunden, mehrere Adressen, Ansprechpartner.
- Standard-Rechnungs- und Lieferadresse.

### Schritt 1.5 – Angebots- und Auftragsbestätigungsfluss
- Konvertierung Angebot -> Rechnung mit Snapshot der Konditionen.

### Schritt 1.6 – Gutschriften/Storno
- Voll- und Teilgutschriften mit Referenz auf Ursprungsdokument.

### Schritt 1.7 – Mehrwährung
- EUR + beliebige Zielwährungen, Wechselkurs pro Dokument fixieren.

### Schritt 1.8 – PDF Export
- Corporate Template (Logo, Farben, Footer), fallback-sicher.

### Schritt 1.9 – Status & Historie
- Status: Entwurf, gesendet, fällig, bezahlt, überfällig.
- Änderungsprotokoll je Dokument.

**Abnahme Phase 1:** End-to-End Flow von Angebot bis bezahlter Rechnung inkl. PDF und Historie.


## Phase 2 – Payments & Mahnwesen (MVP+)
### Plugin: `billing_payments`
### Schritt 2.2 – Zahlungseingänge
- Voll/Teilzahlungen, Gebühren, Skonto und Restforderung.

### Schritt 2.4 – Gebühren & Verzugszinsen
- Konfigurierbare Regeln pro Tenant (Mahngebühr, Zinssatz, Karenzzeit).

### Schritt 2.5 – Bank-/SEPA-Infos
- IBAN/BIC am Mandanten hinterlegen, optional QR-Code in PDF.

**Abnahme Phase 2:** Automatisierter Forderungsprozess von Fälligkeit bis Mahnstufe.

_Status-Update (Implementierung):_ Zahlungslink-Provider-Abstraktion via Adapter-Pattern (Stripe/PayPal) sowie Mahnlogik inkl. Tagesdrossel je Fall und automatisiertem Regressionstest sind umgesetzt. Die MVP+-Punkte Zahlungseingänge (inkl. Teilzahlungen/Skonto), tenant-spezifische Gebühren-/Verzugszinsregeln und Bank-/SEPA-Infos sind backendseitig umgesetzt und regressionsseitig abgesichert.

---

## Phase 3 – Steuern & Compliance (DACH/DE)
### Plugin: `tax_compliance_de`
### Schritt 3.1 – USt-Regelwerk
- Standard/ermäßigt/0%, Reverse Charge, innergemeinschaftliche Lieferung/Leistung.

### Schritt 3.2 – Kleinunternehmerregelung (§19)
- Automatische Steuerdarstellung ohne USt-Ausweis je Konfiguration.

### Schritt 3.3 – Pflichtangaben-Checks
- Preflight-Validierung vor Finalisierung (Steuernummer, USt-IdNr., Leistungsdatum etc.).

### Schritt 3.4 – GoBD-nahe Nachvollziehbarkeit
- Finalisierte Dokumente versiegeln; Änderungen nur per Korrekturbeleg.

### Schritt 3.5 – E-Rechnung
- Export/Import: XRechnung, ZUGFeRD (B2G-fähig).

**Abnahme Phase 3:** DE-konforme Dokumenterstellung mit Validierung und E-Rechnungsfähigkeit.

---

## Phase 4 – Wiederkehrende Umsätze & Abos
### Plugin: `subscriptions_billing`
### Schritt 4.1 – Plan- und Vertragsmodell
- Laufzeit, Verlängerung, Kündigungsfrist, Upgrade/Downgrade.

### Schritt 4.2 – Recurring Engine
- Monatlich/Jährlich, Proration bei Planwechsel.

### Schritt 4.3 – Auto-Invoicing + Versand
- Periodische Rechnungserstellung + E-Mail-Versand.

### Schritt 4.4 – Dunning/Retention
- Retry-Plan bei Zahlungsfehlern, Payment-Method-Update-Flow.

**Abnahme Phase 4:** Vollautomatischer Abo-Cashflow inkl. Recovery-Prozess.

---

## Phase 5 – E-Mail & Versand
### Plugin: `document_delivery`
### Schritt 5.1 – Mehrsprachige Templates
- Platzhalter/Variablen, Anhänge, kanalabhängige Vorlagen.

### Schritt 5.2 – SMTP/Provider
- Eigene Domain/SMTP plus Provideradapter (SendGrid/Mailgun).

### Schritt 5.3 – Kundenportal
- Dokumente ansehen, laden, bezahlen, Stammdaten pflegen.

### Schritt 5.4 – Tracking (optional)
- Mail-Open/Link-Click-Ereignisse datenschutzkonform.

**Abnahme Phase 5:** Vollständiger digitaler Zustellprozess inkl. Self-Service.

---

## Phase 6 – Reporting & Buchhaltung
### Plugin: `finance_reporting`
### Schritt 6.1 – KPI-Dashboard
- Umsatz, offene Posten, MRR/ARR, DSO.

### Schritt 6.2 – OP-Liste
- Fälligkeiten, Alterung, Mahnstufen.

### Schritt 6.3 – Steuerberichte
- USt-Voranmeldung-Übersicht, optional OSS.

### Schritt 6.4 – Exporte
- DATEV, CSV/Excel.

### Schritt 6.5 – APIs/Webhooks
- Connectoren zu Lexoffice/SevDesk/FastBill.

**Abnahme Phase 6:** Finanzdaten revisionsnah exportier- und auswertbar.

---

## Phase 7 – Team, Rechte, Multi-Company
### Plugin: `org_management`
### Schritt 7.1 – Multi-Company Scope
- Mehrere Firmen/Mandanten pro Account mit sauberer Kontextumschaltung.

### Schritt 7.2 – Rollenmodell
- Admin, Buchhaltung, Vertrieb, Read-only (plus Custom Roles).

### Schritt 7.3 – Audit-Log
- Wer hat was wann gemacht (UI + Export).

**Abnahme Phase 7:** Skalierbare Organisationsverwaltung mit Governance.

---

## Phase 8 – Automatisierung & Integrationen
### Plugin: `automation_integrations`
### Schritt 8.1 – API-first Ausbau
- REST/GraphQL konsistent versionieren, Idempotenz-Schlüssel.

### Schritt 8.2 – CRM-Integrationen
- HubSpot/Pipedrive als Adapter.

### Schritt 8.3 – Time Tracking -> Rechnung
- Stundenzettel und Projekte fakturierbar machen.

### Schritt 8.4 – Zapier/Make
- Trigger + Actions für No-Code Automationen.

### Schritt 8.5 – Import-Wizard
- Kunden, Produkte, historische Rechnungen.

**Abnahme Phase 8:** Hohe Integrationsfähigkeit und kurze Time-to-Value.

---

## Phase 9 – Produktkatalog & Preislogik
### Plugin: `catalog_pricing`
### Schritt 9.1 – Produkt-/Servicekatalog
- SKU, Standardpreise, Standardsteuersatz.

### Schritt 9.2 – Preislisten/Bundles
- B2B-Sonderpreise, Staffelpreise.

### Schritt 9.3 – Rabattcodes
- Für Subscription- und Einmalumsätze.

**Abnahme Phase 9:** Wiederverwendbare Preislogik für Sales und Billing.

---

## Phase 10 – Sicherheit, Betrieb, Qualität
### Plugin: `platform_security_ops`
### Schritt 10.1 – DSGVO
- AVV-Bausteine, Datenexport, Löschkonzept mit Fristen.

### Schritt 10.2 – 2FA/SSO
- Business-Tier Features (TOTP/SAML/OIDC optional).

### Schritt 10.3 – Backup/Restore
- Mandantenfähige Sicherung und Wiederherstellung.

### Schritt 10.4 – Revisionssichere Ablage
- Versionierung, Nachvollziehbarkeit, Aufbewahrungsregeln.

### Schritt 10.5 – Reliability
- Rate Limits, Monitoring, Alerting, Status Page.

**Abnahme Phase 10:** Betriebsreife SaaS-Plattform mit Sicherheitsbaseline.

---

## Querschnitt: Qualitätssicherung je Phase
- Unit-Tests für Domainlogik (Steuern, Totals, Dunning, Proration).
- Contract-Tests je Plugin-API.
- Migrations-/Rollback-Tests.
- Flutter Golden-/Widget-Tests für kritische Screens (Rechnungseditor, Dashboard, Mahnübersicht).
- E2E-Szenarien: Angebot -> Rechnung -> Zahlung -> Mahnung -> Export.

## Fortschrittsdokumentation (aktuell)
| Phase | Fokus | Status | Nächster konkreter Schritt |
|---|---|---|---|
| 0 | Plugin-Fundament | **Abgeschlossen** | Kein offener Backlog-Eintrag (erledigte Punkte aus der Aktionsliste entfernt) |
| 1 | Rechnungen & Dokumente | **Abgeschlossen** (Abnahme via E2E-Flow inkl. Nummernkreis/Mehrwährung/PDF-Export dokumentiert) | Phase 2 End-to-End mit Payment-Provider-Adapter (Stripe/PayPal) + Mahnstufen-Regression absichern |
| 2 | Payments & Mahnwesen | **Abgeschlossen** (Zahlungseingänge inkl. Teilzahlungen/Skonto, tenant-spezifische Gebühren-/Verzugszinsregeln und Bank-/SEPA-Infos umgesetzt) | Kein offener Backlog-Eintrag (Phase-2-Umfang abgeschlossen) |
| 3 | Tax & Compliance | In Umsetzung (CI-Gate um umgebungsbezogene Produktiv-Validator-Endpoints/Secrets inkl. Auth erweitert; GitHub-Environments dev/staging/prod verdrahtet) | Nächster Schritt: Secrets je Umgebung in GitHub Environments befüllen und gegen reale Referenzinstanzen abnehmen (Abnahmeprotokoll erfassen) |
| 4 | Abos | In Umsetzung (Provider-Adapter + Flutter Abo-Management-UI ergänzt; Provider-Webhooks + Completion-Callbacks produktiv verdrahtet und regressionsgesichert) | Nächster Schritt: Reale PSP-Sandbox-Abnahme mit tenant-spezifischen Secrets/Endpoints durchführen und Abnahmeprotokoll dokumentieren |
| 5 | E-Mail & Versand | In Umsetzung (Delivery-Worker inkl. Retry/Provider-Dispatch und Flutter-Portal-UI-Flow ergänzt) | Nächster Schritt: Reale SMTP/SendGrid/Mailgun-Sandbox-Abnahme inkl. Tracking-Ereignis-Validierung dokumentieren |
| 6 | Reporting | In Umsetzung (Backend-MVP + Härtung Connector-Sync/Export-Streaming) | Nächster Schritt: Connector-Delivery gegen reale Lexoffice/SevDesk/FastBill-Sandboxes mit tenant-spezifischen Secrets und Monitoring abnehmen |
| 7 | Team & Rechte | In Umsetzung (Rollen-Capability-Mapping-API inkl. tenant-spezifischer Default-Rollenprofile und Org-Management-UI-Matrix ausgeliefert; Audit-Log-UX mit Filter-/Export-Flow im Org-Management ergänzt) | Nächster Schritt: Multi-Company-Kontextwechsel inkl. Membership-UX End-to-End absichern |
| 8 | Integrationen | In Umsetzung (Backend-MVP implementiert) | Adapter-Worker + Flutter-Import-Wizard-UI vervollständigen |
| 9 | Katalog/Preise | In Umsetzung (Backend-MVP implementiert) | Flutter-Katalog-UI + Angebotseditor-Integration der Preislogik |
| 10 | Security/Ops | In Umsetzung (Backend-MVP implementiert) | Worker-gestützte Lösch-/Restore-Jobs + operatives Monitoring verdrahten |


### Phase 6 – Implementierungsstand (Backend-MVP)
- `finance_reporting` Plugin-Endpunkte für KPI-Dashboard, OP-Liste und Steuerreport umgesetzt.
- Export-Endpunkt für DATEV-, OP- und Steuerdaten (CSV/Excel-Formatkennung) ergänzt.
- Connector-Verwaltung für Lexoffice/SevDesk/FastBill inkl. Webhook-Queue-Logging implementiert.
- **Neu (Härtung):** Datei-Streaming-Endpunkt für DATEV/OP/Steuer-Exporte (`csv`/`excel` als TSV) mit Download-Headern und speicherschonender Writer-Pipeline ergänzt.
- **Neu (Härtung):** Connector-Synchronisationslauf (`queued` -> `delivered|failed`) inkl. Signatur-Headern, Timeout und Statuspersistenz eingeführt.


### Phase 8 – Implementierungsstand (Backend-MVP)
- `automation_integrations` Plugin-Endpunkte für API-Versionierung, Idempotenz-Claims und Integrationskatalog ergänzt.
- CRM-Adapter-Backbone (HubSpot/Pipedrive) inkl. Connector-Konfiguration und Sync-Queue-Logging implementiert.
- Time-Tracking nach Rechnung umgesetzt (`time_entries` -> `billing_documents`/`billing_line_items`) mit tenant-sicherem Invoicing-Flow.
- Zapier/Make-Workflow-Runs sowie Import-Wizard (Preview + Execute für Kunden/Produkte/Historien) als Backend-MVP verfügbar.



### Phase 9 – Implementierungsstand (Backend-MVP)
- `catalog_pricing` Plugin-Endpunkte für Produktstamm, Preislisten, Staffelregeln, Bundles und Rabattcodes ergänzt.
- Preislogik-Service für tenant-sichere Quote-Berechnung (Netto/Rabatt/Steuer/Gesamt) inkl. Preislisten- und Rabattcode-Auflösung implementiert.
- Datenmodell für SKU-basierten Produktkatalog sowie wiederverwendbare Preisregeln in Sales-/Billing-Flows erweitert.


### Phase 10 – Implementierungsstand (Backend-MVP)
- `platform_security_ops` Plugin-Endpunkte für DSGVO-Übersicht, Datenexporte und Löschanfragen (inkl. Fristen-/Retention-Regeln) ergänzt.
- Sicherheitsfeatures für Business-Tiers umgesetzt: tenant-spezifische Auth-Policies mit MFA-Modi (`off|optional|required`) und optionalen SSO-Providern (`saml|oidc`).
- Backup/Restore-Basis implementiert (Sicherungs- und Restore-Jobs mit Status, Prüfsumme und Storage-Key-Metadaten).
- Revisionssichere Ablage als versionierte Archiv-Records mit Integritätshash und Aufbewahrungsdatum bereitgestellt.
- Reliability-Baseline ergänzt: konfigurierbare Richtlinien für Rate-Limits, Monitoring, Alerting und Status-Page pro Tenant.


---

## Fortschrittsprotokoll (aktueller Stand)
### Backlog-Bereinigung (aktuelle Revision)
- Erledigte Aktionslisten für **Phase 0** (Abnahmeabsicherung) und **Phase 1** (Billing-Flow-Härtung) wurden aus dem offenen Roadmap-Backlog entfernt.
- Fokus der offenen Liste liegt damit auf den nächsten fachlichen Abnahmeschritten statt auf bereits abgeschlossenen Qualitätsmaßnahmen.

### Phase 0 – Plugin-Fundament
- **Status:** ✅ abgeschlossen
- **Dokumentationsstand:** Detailpunkte sind erledigt und wurden aus der offenen Aktionsliste entfernt.

### Phase 1 – Flutter Billing UI + E2E-Flow (Angebot -> bezahlt)
- **Status:** 🔄 vervollständigt (UI + automatisierter Ablauf implementiert)
- **Dokumentationsstand:** Die abgeschlossenen Härtungsmaßnahmen wurden aus der offenen Liste entfernt; als nächster Schritt bleibt die fachliche Phase-1-Abnahme.
