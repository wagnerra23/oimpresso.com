# CHANGELOG — Modules/Accounting

> Append-only. Mais novo no topo. Datas YYYY-MM-DD.

## [Wave 28 — Polish saturation FINAL 60-79-88 → ≥92 (+4pp)] — 2026-05-17

### Adicionado — D9 +1 span `accounting.budget.yearly_to_monthly` + companion `quarterly_to_monthly`
- `BudgetService` instrumentado com `OtelHelper::spanBiz`:
  - `accounting.budget.yearly_to_monthly` — split anual em 12 buckets `month_N` com proteção `eliminate_decimals` (soma 12 meses == yearly_budget exato)
  - `accounting.budget.quarterly_to_monthly` (companion span) — split trimestral em 3 buckets named keys
- Service stateless após Wave 28; mantém retro-compat com construtor de argumentos legacy (Controllers UltimatePOS).
- Span attributes sem PII: apenas valor numérico orçamento (não é dado pessoal LGPD) + `eliminate_decimals` flag + `module`.

### Adicionado — D2 +3 Pest cross-tenant Wave 28
- `Tests/Feature/Wave28AccountingSaturationTest.php` (~9 cenários):
  - D9 W28 span yearly + quarterly + retorno preservado (12 keys + soma == budget)
  - D2 W28 cross-tenant Budget (biz=1 NÃO aparece em raw query biz=99 — LGPD Art. 6 ADR 0093) + ScopeByBusiness filtra Budget biz=1 quando session=biz=99 + quartelyBudgetToMonthly contract preservado
  - Tier 0 W28 preservação: catálogo `chart_of_accounts` biz=0 NÃO alterado (regression guard explícito — lesson W13/W15) + OtelHelper fail-loud em `accounting.budget.*`
  - D3 W28 CHANGELOG entry (este)
- Tests MySQL-aware (skip SQLite quando schema `budgets`/`chart_of_accounts` ausente — ADR 0101).

### D3 W28 doc
- CHANGELOG (este entry).

### Preservado (Tier 0 IRREVOGÁVEIS)
- ⛔ Catálogo `chart_of_accounts` biz=0 NÃO alterado (lesson W13/W15)
- D7.b W25 LogsActivity em 8/8 Entities principais (Account, AccountTransaction, Budget, ChartOfAccount, DocumentAndNote, JournalEntry, CashRegister, CashRegisterTransaction)
- D7.c retention.php 5 categorias LGPD canônicas (CTN Art. 195 / CC Art. 206 / Lei 8.846/94)

### Referências
- ADR 0093 Multi-tenant Tier 0 IRREVOGÁVEL · ADR 0101 Tests biz=1 · ADR 0155 Module Grade v3 D9 saturated +1

## [Wave 25 — Polish D7.b LogsActivity] — 2026-05-16

### Adicionado — D7.b audit trail append-only (+2 Entities)
- `CashRegister` — agora usa `LogsActivity` (Spatie ActivityLog) — `logOnly(['status', 'closing_amount', 'initial_amount', 'closed_at', 'location_id'])` (sensível: caixa físico = vínculo PII operador). Mudanças registradas em `activity_log` table (subject_type=`Modules\Accounting\Entities\CashRegister`).
- `CashRegisterTransaction` — agora usa `LogsActivity` (`logAll()` — pivot caixa × transação livre de PII direta, importante pra reconciliação contábil + rastreabilidade fiscal CTN Art. 195 5 anos).

### Pattern aplicado
- Trait `LogsActivity` + `getActivitylogOptions()` retornando `LogOptions::defaults()->logOnlyDirty()->dontSubmitEmptyLogs()` (padrão Spreadsheet/Account/JournalEntry W17).
- Catálogo biz=0 preservado (lesson W13/W15) — nenhum default seed dependente alterado.

### Não alterado (intencional — já saturado)
- `Account`, `AccountTransaction`, `Budget`, `ChartOfAccount`, `DocumentAndNote`, `JournalEntry` já tinham `LogsActivity` desde W17/W18.
- `Config/retention.php` (Wave 11 D7.c) declara 5 categorias canônicas (lancamentos / balancetes / notas_fiscais / logs_audit_contabil / clientes_fornecedores) com bases legais CTN Art. 195 / CC Art. 206 / Lei 8.846/94.

### Referências
- ADR 0093 Multi-tenant Tier 0
- ADR 0101 Tests biz=1
- ADR 0155 Module Grade v3 (D7.b audit trail saturated em 8/8 Entities principais)
- ADR 0159 Wave 25 polish (level `biz_4_rota_livre_prod` mantido)
