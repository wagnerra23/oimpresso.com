# CHANGELOG — Modules/Essentials

> HRM + essenciais (Documents/Reminders/ToDo/KnowledgeBase/Holidays/Leaves/Payroll/Shifts) sobre UltimatePOS legacy.

## [Unreleased] — Wave 18 SATURATION (2026-05-16)

### Adicionado — D1 Multi-tenant Tier 0 SATURATION (+14 entries)
- 7 Entities com `business_id` DIRETO agora usam `HasBusinessScope`:
  - `EssentialsAttendance`, `EssentialsHoliday`, `EssentialsLeaveType`,
    `EssentialsMessage`, `EssentialsAllowanceAndDeduction`, `PayrollGroup`, `Shift`
- 6 Entities filhas (FK chain) agora usam `BelongsToBusinessViaParent`:
  - `EssentialsTodoComment` → `essentials_todos`
  - `EssentialsUserShift` → `essentials_shifts`
  - `EssentialsUserAllowancesAndDeduction` → `essentials_allowances_and_deductions`
  - `EssentialsUserSalesTarget` → `users`
  - `DocumentShare` → `essentials_documents`
  - `PayrollGroupTransaction` → `essentials_payroll_groups`

### Adicionado — D7 LGPD retention SATURATION (+9 entries)
- `Modules/Essentials/Config/retention.php` expandido com 9 entries:
  payroll (3), shifts (2), targets (1), allowances (2), comments (1).
- Folha/RH = 1825 dias (CLT Art. 11 + RFB fiscal 5 anos).
- `EssentialsMessage` recebeu `LogsActivity` (subject/to_email podem citar PII).

### Adicionado — D9 Observability
- N/A — Essentials majoritariamente CRUD UltimatePOS herdado; Services próprios
  futuros (folha automatizada) receberão `OtelHelper::span` quando criados.

### Saturação Pest
- `Modules/Essentials/Tests/Feature/Wave18SaturationTest.php` (10+ datasets):
  - 7 entities × HasBusinessScope assert (14 datasets via 2 specs)
  - 6 entities filhas × BelongsToBusinessViaParent + `businessParentRelation` (18 datasets)
  - 9 retention keys + LogsActivity + escape valve withoutGlobalScope

### Marcado
- `module.json` agora declara `fsm_n_a:true` com razão documentada
  (HRM/ToDo/Documents = CRUD com workflow administrativo, não pipeline FSM canônico).

## Referências
- [ADR 0093](../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) Multi-tenant Tier 0 IRREVOGÁVEL
- [ADR 0143](../../memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) FSM Canon (N/A pra Essentials)
- [ADR 0155](../../memory/decisions/0155-module-grade-v3.md) Module Grade v3
- [ADR 0159](../../memory/decisions/0159-module-grade-v3-errata-meta-97-realismo.md) Errata meta 97
