# CHANGELOG — Modules/Essentials

> HRM + essenciais (Documents/Reminders/ToDo/KnowledgeBase/Holidays/Leaves/Payroll/Shifts) sobre UltimatePOS legacy.

## [Wave 27 — Polish final ≥90] — 2026-05-17

### Adicionado — D7 LogsActivity SATURATION em 4 Models RH sensíveis
- `EssentialsAttendance` — LogsActivity (marcação ponto CLT Art. 74 §3 = registro fiscal)
  - logOnly: user_id, business_id, essentials_shift_id, clock_in_time, clock_out_time
  - Skip: ip_address, clock_in_note (campos livres podem ter PII)
  - useLogName: `essentials.attendance`
- `EssentialsAllowanceAndDeduction` — LogsActivity (folha de pagamento PII forte)
  - logOnly: business_id, description, type, amount, amount_type, applicable_date
  - useLogName: `essentials.allowance_deduction`
- `PayrollGroup` — LogsActivity (grupo folha — referência fiscal CLT Art. 11)
  - logOnly: business_id, name, location_id, payroll_for, status
  - useLogName: `essentials.payroll_group`
- `Shift` — LogsActivity (escala de jornada CLT Art. 74)
  - logOnly: business_id, name, type, start_time, end_time, auto_clockout
  - Skip: holidays (array gigante polui audit_log)
  - useLogName: `essentials.shift`

### Pattern aplicado (canon LogsActivity Spatie)
- `logOnlyDirty` + `dontSubmitEmptyLogs` → anti-spam em activity_log
- `useLogName` per-entity → filtro fácil no UI activity_log + retention por entidade
- Skip de campos sensíveis livres (notes, message, holidays array) → minimização LGPD

### Saturação Pest
- `Modules/Essentials/Tests/Feature/Wave27PolishTest.php` (7 specs × dataset 5 = 17 assertions+):
  - 4 Models W27 + EssentialsMessage (W18 sentinel) com LogsActivity validado
  - getActivitylogOptions retorna LogOptions com useLogName correto per-entity
  - logOnlyDirty + dontSubmitEmptyLogs preservados (anti-spam)
  - D9 spans sentinel: TodoService/LeaveAuditService/ReminderAuditService preservados
  - D1 multi-tenant lock-in: 7+ HasBusinessScope + 6+ BelongsToBusinessViaParent (W18 SATURATION)

### Refs
- ADR 0093 Multi-tenant Tier 0 IRREVOGÁVEL
- ADR 0094 §4 Constituição v2 (Custo IA + audit trail)
- ADR 0155 Module Grade v3 + ADR 0159 Errata meta 97 realismo

## [Wave 25 — Polish D6.a saturation] — 2026-05-16

### Adicionado — D6.a Inertia::defer (+5 Controllers)
- `DocumentController::index` — `Inertia::defer` em `documents` + `memos` (2× `fetchByType` com JOIN + GROUP BY)
- `ToDoController::index` — `Inertia::defer` em `todos` (paginate + transform map) + `assignableUsers` (dropdownUsers DB)
- `EssentialsMessageController::index` — `Inertia::defer` em `messages` (toMessageShape map) + `locations` (BusinessLocation::forDropdown)
- `KnowledgeBaseController::index` — `Inertia::defer` em `books` (eager-load 2 níveis + ACL where + toBookShape recursivo)
- `EssentialsHolidayController::index` — `Inertia::defer` em `holidays` (with('location') + map) + `locations` (BusinessLocation::forDropdown)

### Pattern aplicado (Tier 0 — skill `inertia-defer-default`)
- Filtros + UI state (statuses/priorities/can_manage/initialTab/refreshInterval/me) ficam EAGER (props leves, evita waterfall em partial reloads).
- Queries pesadas (paginate + transform/JOIN + GROUP BY/eager-load/forDropdown) viram `Inertia::defer(fn () => ...)` — closure só executa quando frontend pede via `<Deferred>` wrapper.

### Referências
- ADR 0093 Multi-tenant Tier 0
- ADR 0101 Tests biz=1
- ADR 0155 Module Grade v3 (D6.a saturated 5/5 Controllers principais)
- ADR 0159 Wave 25 polish (level `biz_4_rota_livre_prod` mantido)
- Skill `inertia-defer-default` (Tier B) — `memory/requisitos/_DesignSystem/RUNBOOK-inertia-defer-pattern.md`

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
