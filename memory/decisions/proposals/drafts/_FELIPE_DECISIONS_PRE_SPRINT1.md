# Felipe — 4 decisões antes do PR US-INFRA-012 (segunda 2026-05-11)

> **Companion doc** de [`_AGENT_A_AUDIT_FINDINGS.md`](_AGENT_A_AUDIT_FINDINGS.md). Aqui está cada crítico pendente em formato decisão (A vs B, recomendação, esforço).
>
> 🔄 **STATUS 2026-05-10 tarde tarde:** Wagner autorizou pre-aplicar **TODAS** as 4 decisões nos drafts. D1, D2, D3, D4 todas em ✅ APLICADO.
>
> **Felipe segunda só precisa:** ler este doc (~5min) → validar que concorda com as 4 escolhas → rodar `vendor\bin\pest tests/Feature/Insights` local → abrir PR US-INFRA-012 movendo drafts pra `database/migrations/` real.
>
> Se discordar de alguma decisão, reverte naquele arquivo + abre ADR justificando.

---

## D1 — Schema benchmark: granularidade de período ✅ PRE-APLICADO

> Opção B aplicada em commit `d1-d2-benchmark-prefix` (2026-05-10 tarde). Migration `2026_06_01_000004_create_benchmark_aggregates_table.php` agora usa `$table->string('period', 10)` em vez de `period_start/period_end`. Index `idx_bench_lookup` ajustado pra usar `period`.
>
> **Felipe valida:** se concorda mantém. Se discorda (querendo weekly/quinzenal nativo), reverte e abre ADR justificando.



**Onde:** `migrations/2026_06_01_000004_create_benchmark_aggregates_table.php:40-41`

| | Opção A — `period_start` + `period_end` (date range) | Opção B — `period` string YYYY-MM |
|---|---|---|
| **Forma** | 2 colunas `date` | 1 coluna `string(10)` |
| **Granularidade** | Qualquer (semanal, mensal, quinzenal, custom) | Só mensal (extensível pra YYYY-WW depois) |
| **Query típica** | `WHERE period_start BETWEEN ... AND period_end = ...` | `WHERE period = '2026-04'` |
| **Custo de virar Opção A no futuro** | — | Migration `string` → 2 colunas `date` + backfill (`<1h`, sem dor) |
| **Casts/joins** | string→date em todo lugar | Direto |
| **Test atual** | desalinhado | alinhado |

**Recomendação:** **B** (string `period`).
**Razão:** Benchmark de receita/ticket é mensal nativamente. Granularidade <mês é overhead sem ganho hoje. Se em 2 anos quiser weekly, troca coluna sem dor.

**Implementação (B):**
```php
// migrations/2026_06_01_000004_create_benchmark_aggregates_table.php:40-41
- $table->date('period_start');
- $table->date('period_end');
+ $table->string('period', 10)->comment('YYYY-MM (mensal); YYYY-WW reservado pra weekly futuro');
```

---

## D2 — Schema benchmark: que percentis exibir ✅ PRE-APLICADO

> Opção B aplicada no mesmo commit. Migration agora tem `value_p50` (mediana) + `value_p90` (cauda) — `value_p25` e `value_p75` removidos.
>
> **Felipe valida:** se quiser quartiles pra exibir boxplot, reverte e justifica use case.



**Onde:** mesma migration, colunas `value_p*`.

| | Opção A — `value_p25` + `value_p50` + `value_p75` | Opção B — `value_p50` + `value_p90` |
|---|---|---|
| **Estatística** | Quartiles (boxplot clássico) | Mediana + cauda top 10% |
| **Pitch ao Wagner-cliente** | "Você está no Q1, Q2 ou Q3?" | "Está acima da mediana? Quão longe está do top 10%?" |
| **Padrão indústria SaaS** | Raro em benchmarking | Padrão (Stripe, ProfitWell, ChartMogul) |
| **Storage** | 3 colunas decimal | 2 colunas decimal |
| **Test atual** | desalinhado | alinhado |

**Recomendação:** **B** (`p50` + `p90`).
**Razão:** Benchmarking SaaS pra dono de empresa quer 2 perguntas: "estou acima da mediana?" e "quão longe estou do top?". p25 raramente entra em pitch real.

**Implementação (B):**
```php
// migration: substituir 3 colunas value_p25/p50/p75 por
- $table->decimal('value_p25', 18, 2)->nullable();
- $table->decimal('value_p50', 18, 2)->nullable();
- $table->decimal('value_p75', 18, 2)->nullable();
+ $table->decimal('value_p50', 18, 2)->nullable()->comment('Mediana');
+ $table->decimal('value_p90', 18, 2)->nullable()->comment('Top 10% (cauda)');
```

---

## D3 — Coluna CNPJ na tabela `business`: `tax_number` ou `tax_number_1`? ✅ CONFIRMADO + APLICADO

> **Confirmado via SSH 2026-05-10 tarde** (`SHOW COLUMNS FROM business LIKE 'tax_number%'`):
> - `tax_number_1` ← Wagner WR2 usa este (CNPJ principal)
> - `tax_number_2` (segundo CNPJ legado, raramente populado)
>
> **Aplicado:** `BackfillBusinessVerticalCommand.php:67` agora lê `$biz->tax_number_1 ?? ''`. Comentário inline cita confirmação 2026-05-10.
>
> **Felipe valida:** se concordar, segue. Se discordar (ex: quer fallback `tax_number_1 ?? tax_number_2`), edita inline.



**Onde:** `migrations/BackfillBusinessVerticalCommand.php:67`.

**Sintoma:** Command lê `$biz->tax_number ?? ''`. Test factory escreve `tax_number_1`. Se coluna real for `tax_number_1`, command **resolve sempre NULL silenciosamente** — backfill nunca preenche `vertical_id`.

**Como decidir (1 comando SSH):**
```bash
# Hostinger SSH (warm-up + comando)
for i in 1 2 3 4 5; do curl -s -o /dev/null --max-time 15 https://oimpresso.com/login; done
ssh -4 -o ConnectTimeout=900 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 \
  'cd ~/domains/oimpresso.com/public_html && php -r "echo json_encode(DB::connection()->getSchemaBuilder()->getColumnListing(\"business\"));"' \
  | python3 -m json.tool
```

Ou direto MySQL:
```bash
mysql -h... -e 'SHOW COLUMNS FROM business LIKE "tax_number%"'
```

**Recomendação:** alinhar Command à coluna real. Test e factory já estão em `tax_number_1` (UltimatePOS legacy multi-tax). 90% de chance de ser `tax_number_1`.

**Implementação (assumindo `tax_number_1`):**
```php
// BackfillBusinessVerticalCommand.php:67
- (string) ($biz->tax_number ?? '')
+ (string) ($biz->tax_number_1 ?? '')
```

---

## D4 — `BackfillBusinessVerticalCommand` precisa de `--force`? ✅ APLICADO (Opção A)

> **Aplicado:** Command signature ganhou `--force` (default false = safe whereNull idempotente). `handle()` ramifica entre filtrar por `whereNull('vertical_id')` (default) ou processar todos (com `--force`).
>
> **Felipe valida:** se concordar, segue. Se preferir Opção B (remove test), reverte ambos.



**Onde:** `migrations/BackfillBusinessVerticalCommand.php:42-44` + `tests/.../BackfillBusinessVerticalCommandTest.php:137-155`.

**Sintoma:** Test invoca com `--force` mas signature do Command só tem `--business-id` e `--dry-run`. Test quebra com `option not defined`.

| | Opção A — Adicionar `--force` ao Command | Opção B — Remover o test |
|---|---|---|
| **Comportamento útil?** | Sim — re-rodar em business já com vertical (correção, mudança de CNAE) | Perde caso de teste de re-execução |
| **Esforço** | 5 linhas no signature + handle | 1 linha apaga |
| **Risco** | Nenhum (default safe = whereNull) | Backfill de correção fica não-coberto |

**Recomendação:** **A** (adicionar `--force`).
**Razão:** Comportamento útil pra hot-fix de CNAE errado em business já classificado. Custo trivial.

**Implementação (A):**
```php
// BackfillBusinessVerticalCommand.php
protected $signature = 'oimpresso:backfill-business-vertical
                       {--business-id=}
                       {--dry-run}
+                       {--force : Re-roda em business com vertical_id já setado (sobrescreve)}';

public function handle()
{
    $query = DB::table('business');
+   if (! $this->option('force')) {
        $query->whereNull('vertical_id');
+   }
    // ... resto igual
}
```

---

## Checklist Felipe segunda (5min validar + Pest + PR)

- [x] **D1** — Opção B pre-aplicada 2026-05-10 tarde (Wagner autorizou)
- [x] **D2** — Opção B pre-aplicada 2026-05-10 tarde (Wagner autorizou)
- [x] **D3** — coluna confirmada `tax_number_1` via SSH 2026-05-10 tarde, Command ajustado
- [x] **D4** — Opção A aplicada (--force flag adicionado, default safe)
- [ ] Validar todas as 4 decisões — concordo? (se não, reverter aquela específica + ADR)
- [ ] Bug Pest Modules/Jana (`uses(...)->in(__DIR__)` duplicado em Admin/) — corrigir junto OU PR separado
- [ ] `vendor\bin\pest tests/Feature/Insights` local — verde
- [ ] PR US-INFRA-012 movendo drafts pra `database/migrations/` real + Module/Insights/Console/Commands/

**Se discordar de qualquer recomendação acima, registra rationale no PR description** — cria precedente pra próximas decisões similares.

---

**Sub-agent A** (Opus 4.7) auditou em 2026-05-10. Decisões D1-D4 escolhidas pelo Wagner+Felipe são **autoritativas** sobre as recomendações deste doc.
