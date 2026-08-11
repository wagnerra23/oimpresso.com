---
module: Connector
purpose: "Porta REST externa única do oimpresso (Passport auth:api, contrato congelado): expõe o núcleo do ERP a consumidores de fora — app POS móvel e integrações — e sustenta o handshake de licenciamento, registro e versão dos clientes Delphi WR Comercial em migração."
migracao_ui: "pendente — tem Blade servido, sem duvida de escopo; fila em module-surface --migracao"
contains:
  - "Api/ApiController"
  - "Api/AttendanceController"
  - "Api/BaseApiController"
  - "Api/BrandController"
  - "Api/BusinessController"
  - "Api/BusinessLocationController"
  - "Api/CashRegisterController"
  - "Api/CategoryController"
  - "Api/CheckUpdateController"
  - "Api/CommonResourceController"
  - "Api/ContactController"
  - "Api/Crm/CallLogsController"
  - "Api/Crm/FollowUpController"
  - "Api/ExpenseController"
  - "Api/FieldForce/FieldForceController"
  - "Api/LicencaComputadorController"
  - "Api/OImpressoRegistroController"
  - "Api/ProductController"
  - "Api/ProductSellController"
  - "Api/SellController"
  - "Api/SuperadminController"
  - "Api/TableController"
  - "Api/TaxController"
  - "Api/TypesOfServiceController"
  - "Api/UnitController"
  - "Api/UserController"
  - "ClientController"
  - "ConnectorController"
  - "DataController"
  - "InstallController"
not_contains:
  - "Conhecimento canônico (ADRs, sessions) → Modules/KB"
  - "Tasks Jira-style → Modules/Forja"
  - "MCP server admin → Modules/Forja"
trust_required: L0
owner: wagner
permission_prefix: connector.*
charter_adr: 0080
related_adrs:
  - 0079-constituicao-oimpresso-7-camadas-governanca
  - 0080-trust-tiers-operacional-audit-findings
url_prefixes:
  - /connector/*
drift_alerts: []
---

# Modules/Connector

## Missão

POS APIs UltimatePOS — coração dos POS clientes. Só Wagner toca.

## Trust level

**L0** — ver [TRUST-TIERS.md](../../memory/governance/TRUST-TIERS.md).

## Quando NÃO é tocado

Ver `not_contains[]` no frontmatter. Em dúvida, consulte
[ARCHITECTURE.md](../../memory/governance/ARCHITECTURE.md).

---

- **v1.0.0** (2026-05-05) — SCOPE.md inicial. Gerado em batch via Fase 3.4 do ADR 0079.
