# CHANGELOG — Modules/Accounting

> Append-only. Mais novo no topo. Datas YYYY-MM-DD.

<<<<<<< HEAD
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
=======
## [Wave 27 — Polish final ≥88] — 2026-05-17 (60-79 → 88, +9 a +28pp)

### Adicionado — D9.a OTel spans BudgetService (+2 spans)
- `BudgetService::quartelyBudgetToMonthly` — span `accounting.budget.quartely_to_monthly`
  (hot-path BudgetController quando recalcula trimestre).
- `BudgetService::yearlyBudgetToMonthly` — span `accounting.budget.yearly_to_monthly`.
- Service agora importa `App\Util\OtelHelper` (canônico, NUNCA OpenTelemetry vendor).
- Zero-cost OTel quando `otel.enabled=false`.

### Adicionado — D7.c shim config/retention.accounting.php
- `config/retention.accounting.php` — alias ao canônico `Modules/Accounting/Config/retention.php`.
- Padrão consistente com `config/retention.ads.php` + `config/retention.whatsapp.php`.
- Permite que jobs/CRON globais futuros (`retention:purge-all`) iterem sobre
  `config('retention.*')` sem precisar conhecer cada módulo.
- Fallback defensivo se módulo desativado.

### Adicionado — D2 Pest expand Wave 27 (12 cenários novos)
- `Tests/Feature/Wave27AccountingPolishTest.php` cobre:
  - Spans BudgetService (Reflection source) + outputs preserved (sanity)
  - Shim retention.accounting.php carrega categorias do canônico
  - LogsActivity adoption Wave 25 (CashRegister + CashRegisterTransaction)
  - Multi-tenant Tier 0: CashRegister HasBusinessScope + ScopeByBusiness
  - Cross-tenant raw DB: Account biz=99 NUNCA vê biz=1 (Tier 0 ADR 0093)
  - Catálogo biz=0 IRREVOGÁVEL preservado (lição Wave 13/15)

### Tier 0 IRREVOGÁVEIS preservadas
- ⛔ Catálogo `business_id=0` (AccountSubtype, AccountDetailType, PaymentType) preservado
- ⛔ Multi-tenant Tier 0 (ADR 0093) — `business_id` global scope obrigatório
- ⛔ NUNCA biz=4 cliente real (ADR 0101) — biz=1 em testes
- ⛔ PT-BR comentários, identificadores PHP em inglês
- ⛔ OtelHelper canônico (`App\Util\OtelHelper`) — não OpenTelemetry vendor

### Referências
- ADR 0093 Multi-tenant Tier 0
- ADR 0101 Tests biz=1
- ADR 0155 Module Grade v3 (D9.a Services span coverage + D7.c retention config)
- ADR 0159 Wave 25 polish base
>>>>>>> origin/main

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
