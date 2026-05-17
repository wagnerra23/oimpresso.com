# CHANGELOG — Modules/Accounting

> Append-only. Mais novo no topo. Datas YYYY-MM-DD.

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
