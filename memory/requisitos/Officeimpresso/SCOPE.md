---
module: Officeimpresso
purpose: "Licenciamento, bloqueio e auditoria das máquinas do Office Impresso desktop (Delphi) — CRUD de licenças e logs, credenciais OAuth Passport e API de acesso do desktop, delegáveis ao suporte por permissão. Inclui o importador Firebird do WR Comercial legado."
migracao_ui: "pendente — tem Blade servido, sem duvida de escopo; fila em module-surface --migracao"
contains:
  - "AuditController"
  - "ClientController"
  - "DataController"
  - "InstallController"
  - "LicencaComputadorController"
  - "LicencaLogController"
  - "OfficeimpressoController"
not_contains:
  - "Conhecimento canônico (ADRs, sessions) → Modules/KB"
  - "Tasks Jira-style → Modules/Forja"
  - "MCP server admin → Modules/Forja"
trust_required: L3
owner: wagner
permission_prefix: officeimpresso.*
charter_adr: 0080
related_adrs:
  - 0079-constituicao-oimpresso-7-camadas-governanca
  - 0080-trust-tiers-operacional-audit-findings
url_prefixes:
  - /officeimpresso/*
drift_alerts: []
---

# Modules/Officeimpresso

## Missão

Sistema Office Impresso desktop licenciamento (Superadmin-only).

## Trust level

**L3** — ver [TRUST-TIERS.md](../../memory/governance/TRUST-TIERS.md).

## Quando NÃO é tocado

Ver `not_contains[]` no frontmatter. Em dúvida, consulte
[ARCHITECTURE.md](../../memory/governance/ARCHITECTURE.md).

---

- **v1.0.0** (2026-05-05) — SCOPE.md inicial. Gerado em batch via Fase 3.4 do ADR 0079.
