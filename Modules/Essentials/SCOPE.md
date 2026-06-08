---
module: Essentials
will_extract_partial_to: Notas
will_extract_at_phase: 3.10
purpose: "UltimatePOS HRM herdado. Notas (NEW) extrai gradual em Fase 3.10."
contains:
  - "AttendanceController"
  - "DashboardController"
  - "DataController"
  - "DocumentController"
  - "DocumentShareController"
  - "EssentialsAllowanceAndDeductionController"
  - "EssentialsController"
  - "EssentialsHolidayController"
  - "EssentialsLeaveController"
  - "EssentialsLeaveTypeController"
  - "EssentialsMessageController"
  - "EssentialsSettingsController"
  - "InstallController"
  - "KnowledgeBaseController"
  - "PayrollController"
  - "ReminderController"
  - "SalesTargetController"
  - "ShiftController"
  - "ToDoController"
not_contains:
  - "Modules/Notas (NEW) — KB pessoal + tarefas + arquivo cliente"
trust_required: L3
owner: wagner
permission_prefix: essentials.*
charter_adr: 0080
related_adrs:
  - 0079-constituicao-oimpresso-7-camadas-governanca
  - 0080-trust-tiers-operacional-audit-findings
url_prefixes:
  - /essentials/*
drift_alerts: []
---

# Modules/Essentials

## Missão

UltimatePOS HRM herdado. Notas (NEW) extrai gradual em Fase 3.10.

## Trust level

**L3** — ver [TRUST-TIERS.md](../../memory/governance/TRUST-TIERS.md).

## Quando NÃO é tocado

Ver `not_contains[]` no frontmatter. Em dúvida, consulte
[ARCHITECTURE.md](../../memory/governance/ARCHITECTURE.md).

---

- **v1.0.0** (2026-05-05) — SCOPE.md inicial. Gerado em batch via Fase 3.4 do ADR 0079.
