---
module: Auditoria
purpose: "Trilha de auditoria + governance review do oimpresso. Cataloga eventos críticos cross-módulo (LGPD Art. 37 accountability)."
contains:
  - "AuditoriaController"
  - "DataController"
  - "InstallController"
not_contains:
  - "Activity Log per-Model (Spatie\\Activitylog) → trait LogsActivity nos próprios Models"
  - "Conhecimento canônico (ADRs, sessions) → Modules/KB"
  - "Tasks Jira-style → Modules/ProjectMgmt"
  - "MCP server admin → Modules/TeamMcp"
trust_required: L3
owner: wagner
permission_prefix: auditoria.*
charter_adr: 0094
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0094-constituicao-v2-7-camadas-8-principios
url_prefixes:
  - /auditoria/*
drift_alerts: []
---

# Modules/Auditoria

## Missão

Trilha de auditoria centralizada — cataloga eventos críticos cross-módulo (mudanças de schema, ações superadmin, drifts detectados, escalações Tier 0) pra accountability LGPD Art. 37 + revisão governance.

## Trust level

**L3** — ver [TRUST-TIERS.md](../../memory/governance/TRUST-TIERS.md).

## Quando NÃO é tocado

Ver `not_contains[]` no frontmatter. Activity Log de mutações ordinárias vive nos próprios Models (trait `LogsActivity` Spatie); Auditoria consome **eventos consolidados de governance**, não cada UPDATE.

---

- **v1.0.0** (2026-05-20) — SCOPE.md inicial gerado durante PR #1183 (Fiscal cockpit) pra desbloquear `check-scope --strict` no CI.
