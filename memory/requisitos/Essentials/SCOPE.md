---
module: Essentials
will_extract_partial_to: Notas
will_extract_at_phase: 3.10
purpose: "Infraestrutura interna compartilhada herdada do UltimatePOS: HRM sob /hrm/* (folgas, presença, folha, turnos, feriados, metas) e produtividade sob /essentials/* (tarefas, lembretes, mensagens, documentos, base de conhecimento). Não é vertical vendável; é fundação consumida por todos os business."
migracao_ui: "pendente — tem Blade servido, sem duvida de escopo; fila em module-surface --migracao"
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
  - "modulo `Notas` (PROPOSTO — nao existe em Modules/) — KB pessoal + tarefas + arquivo cliente"
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

**L3** — ver [TRUST-TIERS.md](../../governance/TRUST-TIERS.md).

## Quando NÃO é tocado

Ver `not_contains[]` no frontmatter. Em dúvida, consulte
[ARCHITECTURE.md](../../governance/ARCHITECTURE.md).

---

- **v1.0.0** (2026-05-05) — SCOPE.md inicial. Gerado em batch via Fase 3.4 do ADR 0079.
