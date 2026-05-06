---
module: Project
will_delete_at_phase: 3.8
replaced_by: ProjectMgmt (a renomear pra Project em Fase 3.9)
purpose: "UltimatePOS Project legado — DELETE em Fase 3.8 (preservar dados úteis primeiro). ProjectMgmt vira Project em Fase 3.9."
contains:
  - "ActivityController"
  - "DataController"
  - "InstallController"
  - "InvoiceController"
  - "ProjectController"
  - "ProjectTimeLogController"
  - "ReportController"
  - "TaskCommentController"
  - "TaskController"
not_contains:
  - "Conhecimento canônico (ADRs, sessions) → Modules/KB"
  - "Tasks Jira-style → Modules/ProjectMgmt"
  - "MCP server admin → Modules/TeamMcp"
trust_required: L3
owner: wagner
permission_prefix: project.*
charter_adr: 0080
related_adrs:
  - 0079-constituicao-oimpresso-7-camadas-governanca
  - 0080-trust-tiers-operacional-audit-findings
url_prefixes:
  - /project/*
drift_alerts: []
---

# Modules/Project

## Missão

UltimatePOS Project legado — DELETE em Fase 3.8 (preservar dados úteis primeiro). ProjectMgmt vira Project em Fase 3.9.

## Trust level

**L3** — ver [TRUST-TIERS.md](../../memory/governance/TRUST-TIERS.md).

## Quando NÃO é tocado

Ver `not_contains[]` no frontmatter. Em dúvida, consulte
[ARCHITECTURE.md](../../memory/governance/ARCHITECTURE.md).

---

- **v1.0.0** (2026-05-05) — SCOPE.md inicial. Gerado em batch via Fase 3.4 do ADR 0079.
