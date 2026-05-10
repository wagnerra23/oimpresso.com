# Audit fixes sketch — 4 críticos pendentes pra Felipe segunda 2026-05-11

> **Pra Felipe:** sketch não-executado dos 4 fixes pendentes do audit doc `_AGENT_A_AUDIT_FINDINGS.md`. Cada seção mostra estado atual lido do arquivo, diagnóstico, fix exato (diff), side-effects a checar, e comando Pest pra validar.
>
> Recomendações alinhadas com `_FELIPE_DECISIONS_PRE_SPRINT1.md` (D1-D4). Felipe ainda decide — se discordar, registra rationale no PR US-INFRA-012.
>
> **Nada foi modificado em código real ainda.** Sketch só.
>
> Autor: sub-agent Opus 4.7 — 2026-05-10

---

## Critical 3 — Schema benchmark `period_start/end` (date range) vs `period` string YYYY-MM

### Estado atual

- **Arquivo migration:** `memory/decisions/proposals/drafts/migrations/2026_06_01_000004_create_benchmark_aggregates_table.php` linhas 40-41 e 53
- **Arquivo test:** `memory/decisions/proposals/drafts/tests/Feature/Insights/BenchmarkAggregatorKAnonymityTest.php` linhas 55, 69, 85, 151, 296

Snippet migration (lido):

```php
// linhas 40-41
$table->date('period_start');
$table->date('period_end');

// linha 53
$table->index(['vertical_id', 'metric_key', 'uf', 'period_start'], 'idx_bench_lookup');
```

Snippet test (lido — usa formato curto string):

```php
// linha 55, 69, 85
'period' => '2026-04',
// linha 151
->where('period', '2026-04')
// linha 296
expect($cols)->toContain('period');
```

Test linha 37 também usa `metric_key` na migration mas no test (linha 54) o campo é `metric` — secundário ao critical, mas alinhado se adotarmos string nome curto.

### Diagnóstico

Migration declara range de datas (`period_start` + `period_end` `date`) mas todos os 7+ tests usam `period` (string YYYY-MM). Test não roda — `Schema::hasColumn('benchmark_aggregates', 'period')` retorna false. Recomendação Felipe (`_FELIPE_DECISIONS_PRE_SPRINT1.md` D1): **Opção B** — alinhar migration ao test (string YYYY-MM).

Bonus alinhamento descoberto: campo `metric_key` (migration linha 37) vs `metric` (test linhas 54, 68, 84, 150, 295). Como o sketch já mexe na mesma migration, **vale unificar pra `metric` agora** (mais curto, alinhado com test).

### Fix proposto

**Arquivo:** `memory/decisions/proposals/drafts/migrations/2026_06_01_000004_create_benchmark_aggregates_table.php`

```diff
-            $table->string('metric_key', 100)
+            $table->string('metric', 100)
                 ->comment('Ex: receita_anual, ticket_medio, m2_produzidos_mes');
             $table->string('uf', 2)->nullable()->comment('NULL = agregado nacional; SP/RJ/etc = regional');
-            $table->date('period_start');
-            $table->date('period_end');
+            $table->string('period', 10)->comment('YYYY-MM (mensal); YYYY-WW reservado pra weekly futuro');
             $table->unsignedInteger('n_businesses')
                 ->comment('# de negócios na agregação. MUST be >= 5 (CHECK constraint).');
```

E atualizar índice (linha 53):

```diff
-            $table->index(['vertical_id', 'metric_key', 'uf', 'period_start'], 'idx_bench_lookup');
+            $table->index(['vertical_id', 'metric', 'uf', 'period'], 'idx_bench_lookup');
```

**Justificativa:** Benchmark de receita/ticket é mensal nativamente. String YYYY-MM permite query direta (`WHERE period = '2026-04'`), reduz cast e elimina join range. Se daqui 2 anos precisar weekly, migra coluna sem dor.

### Side-effects a checar

- [ ] Confirmar se algum outro test draft em `memory/decisions/proposals/drafts/tests/Unit/Insights/BenchmarkAggregatorTest.php` usa `period_start/end` ou `metric_key` — se sim, alinhar tb (audit doc menciona Mockery quirks no Unit test, separado)
- [ ] Service `Modules/Insights/Services/BenchmarkAggregator.php` (ainda não existe) deve nascer **já assinando** `compute($verticalId, string $metric, string $period)` em PT YYYY-MM, não usando date range
- [ ] Model `Modules/Insights/Models/BenchmarkAggregate.php` (ainda não existe) — `$casts` deve ter `period => 'string'`, NÃO `date`/`datetime`
- [ ] Nome do índice `idx_bench_lookup` ≤ 64 chars (CLAUDE.md regra) — atual 16 chars OK
- [ ] Comment do schema deixa claro que `period` é YYYY-MM hoje (não confundir com `period_id` FK em outras tabelas legacy do UltimatePOS)

### Validação Felipe

```bash
# 1) aplica migration draft em ambiente local fresh
vendor\bin\pest tests/Feature/Insights/BenchmarkAggregatorKAnonymityTest.php --filter="schema benchmark_aggregates"

# 2) suite completa do test
vendor\bin\pest tests/Feature/Insights/BenchmarkAggregatorKAnonymityTest.php
```

**Esperado:** 14 tests pass (ou cardinality similar conforme rodadas Pest local). Falha esperada se BenchmarkAggregator service ainda não implementado — OK pré-Sprint 1, smoke do schema basta.

---

## Critical 4 — Schema benchmark `value_p25/p50/p75` (quartiles) vs `value_p50/p90` (cauda)

### Estado atual

- **Arquivo migration:** `memory/decisions/proposals/drafts/migrations/2026_06_01_000004_create_benchmark_aggregates_table.php` linhas 45-47
- **Arquivo test:** `memory/decisions/proposals/drafts/tests/Feature/Insights/BenchmarkAggregatorKAnonymityTest.php` linhas 57, 71, 73, 179-181

Snippet migration (lido):

```php
// linhas 44-49
$table->decimal('value_min', 18, 2)->nullable();
$table->decimal('value_p25', 18, 2)->nullable()->comment('Percentil 25');
$table->decimal('value_p50', 18, 2)->nullable()->comment('Mediana');
$table->decimal('value_p75', 18, 2)->nullable()->comment('Percentil 75');
$table->decimal('value_max', 18, 2)->nullable();
$table->decimal('value_avg', 18, 2)->nullable();
```

Snippet test (lido):

```php
// linhas 57, 71-73
'value_avg' => 1500.00,
'value_p50' => 1400.00,
'value_p90' => 2500.00,

// linhas 179-181
expect($agg->value_p50)->not->toBeNull();
expect($agg->value_p90)->not->toBeNull();
expect((float) $agg->value_p90)->toBeGreaterThanOrEqual((float) $agg->value_p50);
```

### Diagnóstico

Migration tem percentis quartile (`p25`/`p50`/`p75`); tests assumem padrão indústria SaaS (`p50`/`p90` — mediana + cauda top 10%). Test que insere `value_p90` falha porque coluna não existe. Recomendação Felipe (`_FELIPE_DECISIONS_PRE_SPRINT1.md` D2): **Opção B** — `p50` + `p90` (alinhar à test).

### Fix proposto

**Arquivo:** mesmo da Critical 3.

```diff
             $table->decimal('value_min', 18, 2)->nullable();
-            $table->decimal('value_p25', 18, 2)->nullable()->comment('Percentil 25');
-            $table->decimal('value_p50', 18, 2)->nullable()->comment('Mediana');
-            $table->decimal('value_p75', 18, 2)->nullable()->comment('Percentil 75');
+            $table->decimal('value_p50', 18, 2)->nullable()->comment('Mediana (50%)');
+            $table->decimal('value_p90', 18, 2)->nullable()->comment('Top 10% — cauda alta');
             $table->decimal('value_max', 18, 2)->nullable();
             $table->decimal('value_avg', 18, 2)->nullable();
```

**Justificativa:** Padrão indústria SaaS pra benchmarking pequenas/médias é mediana + p90 ("estou acima da metade? quão longe estou do top 10%?"). Wagner como dono provavelmente prefere essa narrativa. p25 raramente entra em pitch real. Storage drop de 3 → 2 decimais (~10 bytes/linha — irrelevante mas direção certa).

### Side-effects a checar

- [ ] Service `BenchmarkAggregator->compute()` deve usar SQL `PERCENTILE_CONT(0.5)` e `PERCENTILE_CONT(0.9)` (MySQL 8) ou aproximação manual via `ROW_NUMBER() OVER (ORDER BY ...)` (MariaDB pré-10.7 não tem `PERCENTILE_CONT` nativo — Felipe checa versão Hostinger)
- [ ] Test `BenchmarkAggregatorTest.php` (Unit) — verificar se usa `p25/p75` em algum lugar (audit doc #10 menciona Mockery chain quebrado lá; se for refatorar, alinhar percentis tb)
- [ ] Model `BenchmarkAggregate.php` (futura) — `$fillable` lista deve ter `value_p50` + `value_p90` (NÃO `p25/p75`)
- [ ] Frontend Inertia (`Modules/Insights/Resources/views/...` futuro) — ao renderizar benchmark UI, label deve ser "Mediana" e "Top 10%" (não "Q1/Q2/Q3")

### Validação Felipe

```bash
# Roda mesma suite — assert sobre value_p90 já cobre
vendor\bin\pest tests/Feature/Insights/BenchmarkAggregatorKAnonymityTest.php
```

**Esperado:** os 2 tests que dão `expect($agg->value_p90)->not->toBeNull()` (linhas 179-181) passam após migration alinhada. Se BenchmarkAggregator service ainda nascendo, mockar/skip — schema basta pra Sprint 1.

---

## Critical 5 — `BackfillBusinessVerticalCommand` lê `tax_number` mas tabela `business` é `tax_number_1`

### Estado atual

- **Arquivo command:** `memory/decisions/proposals/drafts/migrations/BackfillBusinessVerticalCommand.php` linha 67
- **Arquivo test:** `memory/decisions/proposals/drafts/tests/Feature/Insights/BackfillBusinessVerticalCommandTest.php` linhas 47, 67, 92, 107, 121, 141, 166, 184, 203, 226, 245 (test usa `tax_number_1` em todos os factory `create()`)
- **Schema base UltimatePOS:** `database/migrations/2017_07_05_073658_create_business_table.php` linha 23 — define `$table->string('tax_number_1', 100);`

Snippet command (lido — linha 67):

```php
$cnpj = preg_replace('/\D/', '', (string) ($biz->tax_number ?? ''));
```

Snippet test (lido — linha 47):

```php
$biz = Business::factory()->create([
    'tax_number_1' => '12.345.678/0001-99',
    ...
]);
```

Comentário do header do command linha 21 diz textualmente `"tax_number pode estar vazio/inválido em alguns businesses legacy"` — bug se propagou no comentário tb.

### Diagnóstico

Schema UltimatePOS canônico tem `tax_number_1` (coluna principal CNPJ) e `tax_number_2` (coluna nullable secundária). Não existe coluna `tax_number` na tabela `business`. Command vai retornar NULL em todas as 56 linhas → `strlen($cnpj) !== 14` → conta `stats['sem_cnpj']++` em todos → command silenciosamente "nunca encontra CNPJ" sem erro óbvio. Bug grave: deploy seria no-op com aparência de sucesso.

Confirmado em código local (`database/migrations/2017_07_05_073658_create_business_table.php:23`). Não preciso de SSH no Hostinger — tabela é a base do UltimatePOS desde 2017, presente igual em qualquer cliente. Felipe ainda pode validar via `SHOW COLUMNS FROM business LIKE 'tax_%'` por garantia, mas é overkill.

### Fix proposto

**Arquivo:** `memory/decisions/proposals/drafts/migrations/BackfillBusinessVerticalCommand.php`

Linha 67:

```diff
-            $cnpj = preg_replace('/\D/', '', (string) ($biz->tax_number ?? ''));
+            $cnpj = preg_replace('/\D/', '', (string) ($biz->tax_number_1 ?? ''));
```

E ajustar o comentário do header (linha 21) pra ficar correto:

```diff
- *   5) tax_number pode estar vazio/inválido em alguns businesses legacy — command pula gracefully.
+ *   5) tax_number_1 pode estar vazio/inválido em alguns businesses legacy — command pula gracefully.
```

**Justificativa:** Coluna real do schema UltimatePOS é `tax_number_1`. Test já está correto (factory usa `tax_number_1`). Bug é unilateralmente do Command.

### Side-effects a checar

- [ ] Verificar se algum outro draft em `migrations/` ou `tests/` usa `$biz->tax_number` (sem `_1`) — Grep `tax_number[^_]` no draft tree
- [ ] Quando o Command for de fato registrado em `Modules/Insights/Console/Commands/`, garantir que docstring `@param` reflete `tax_number_1`
- [ ] PII: log linha 107-112 não deve incluir `tax_number_1` no contexto do `Log::info` — skill `multi-tenant-patterns` Tier A. Audit lib em `Modules/Jana/Utils/PiiRedactor.php` (se existir) deve mascarar antes de qualquer log
- [ ] Felipe validar (paranoia): `SHOW COLUMNS FROM business LIKE 'tax_%'` no Hostinger MySQL via `mysql -h ... -u u906587222_admin -p oimpresso` ou via heredoc PHP inline (`reference_hostinger_analise.md` na auto-mem). Esperado: `tax_number_1`, `tax_number_2`, `tax_label_1`, `tax_label_2`

### Validação Felipe

```bash
vendor\bin\pest tests/Feature/Insights/BackfillBusinessVerticalCommandTest.php --filter="backfill biz=1 atribui vertical"
```

**Esperado:** 1 test pass (linha 83 do test draft — confirma que o command lê `tax_number_1` corretamente, faz HTTP fake, mapeia CNAE 1813-0/01 pra vertical comunicacao_visual). Se passar, command + test alinhados.

Suite completa:

```bash
vendor\bin\pest tests/Feature/Insights/BackfillBusinessVerticalCommandTest.php
```

**Esperado:** 11 tests (contagem do draft) — todos pass se Critical 6 também resolvido.

---

## Critical 6 — Test usa `--force` flag, Command não tem signature `--force`

### Estado atual

- **Arquivo command:** `memory/decisions/proposals/drafts/migrations/BackfillBusinessVerticalCommand.php` linhas 42-44 + linha 50 (logica `whereNull`)
- **Arquivo test:** `memory/decisions/proposals/drafts/tests/Feature/Insights/BackfillBusinessVerticalCommandTest.php` linhas 137-155

Snippet command signature (lido — linhas 42-44):

```php
protected $signature = 'insights:backfill-vertical
                        {--business-id= : Specific business ID (default: all NULL vertical_id)}
                        {--dry-run : Não escreve nada, só mostra o que faria}';
```

Snippet command logic (lido — linha 50):

```php
$query = DB::table('business')->whereNull('vertical_id');
```

Snippet test que usa `--force` (lido — linhas 137-155):

```php
it('flag --force sobrescreve vertical_id existente', function () {
    $cv = Vertical::where('slug', 'comunicacao_visual')->first();
    $outra = Vertical::factory()->create(['slug' => 'outra', 'name' => 'Outra']);

    $biz = Business::factory()->create([
        'tax_number_1' => '12.345.678/0001-99',
        'vertical_id' => $outra->id,
    ]);

    Http::fake([
        'brasilapi.com.br/*' => Http::response([
            'cnae_fiscal' => '1813-0/01',
        ], 200),
    ]);

    Artisan::call('insights:backfill-vertical', ['--force' => true]);

    expect($biz->fresh()->vertical_id)->toBe($cv->id);
});
```

### Diagnóstico

Test invoca `Artisan::call('insights:backfill-vertical', ['--force' => true])` mas o command só declara `--business-id` e `--dry-run`. Em runtime Laravel lança `InvalidOptionException: The "--force" option does not exist`. Test atualmente falha. Recomendação Felipe (`_FELIPE_DECISIONS_PRE_SPRINT1.md` D4): **Opção A** — adicionar `--force` no Command (comportamento útil pra re-rodar quando Wagner descobrir que CNAE de algum cliente mudou).

### Fix proposto

**Arquivo:** `memory/decisions/proposals/drafts/migrations/BackfillBusinessVerticalCommand.php`

Mudança 1 — signature (linhas 42-44):

```diff
     protected $signature = 'insights:backfill-vertical
                             {--business-id= : Specific business ID (default: all NULL vertical_id)}
-                            {--dry-run : Não escreve nada, só mostra o que faria}';
+                            {--dry-run : Não escreve nada, só mostra o que faria}
+                            {--force : Re-roda em business que já tem vertical_id setado (sobrescreve)}';
```

Mudança 2 — logic (linha 50, dentro de `handle()`):

```diff
     public function handle(): int
     {
-        $query = DB::table('business')->whereNull('vertical_id');
+        $query = DB::table('business');
+        if (! $this->option('force')) {
+            $query->whereNull('vertical_id');
+        }
         if ($id = $this->option('business-id')) {
             $query->where('id', $id);
         }
         $businesses = $query->get();
```

**Justificativa:** Comportamento útil pra Wagner re-rodar backfill quando: (a) seeder vertical mudou (novo CNAE adicionado a uma vertical), (b) cliente reportou que CNAE legado estava errado, (c) BrasilAPI atualizou cnae_fiscal pra um CNPJ. Sem `--force`, Wagner teria que rodar SQL manual `UPDATE business SET vertical_id=NULL WHERE id=X` antes — fricção desnecessária. Test linha 137 já cobre o caso.

### Side-effects a checar

- [ ] Confirmar se há um teste de "default behavior NÃO sobrescreve" — sim, linha 117-135 do test draft (`backfill não sobrescreve vertical_id já preenchido (default behavior)`). Esse passa direto sem `--force` — bom, garante regressão se alguém mudar default
- [ ] Output do command (linha 61) — considerar adicionar `' (FORCE)'` ao info quando flag ativa, pra rastreabilidade em log de produção
- [ ] Log linha 107-112 — adicionar `'force' => $this->option('force')` no contexto pra audit trail
- [ ] Idempotência — comentário do header (linha 28-30) diz "Idempotente: re-rodar é seguro." Verdade tanto sem quanto com `--force`, mas com `--force` cada re-run paga round-trip BrasilAPI (rate limit 1s). Não é bug — só vale documentar
- [ ] Wagner aprovação: a flag `--force` é destrutiva (sobrescreve). Considerar prompt de confirmação CLI (`$this->confirm()`) se rodada SEM `--business-id` (pra evitar reset acidental dos 56). Felipe decide se vale; sketch não inclui pra manter mínimo

### Validação Felipe

```bash
# 1) test específico do --force
vendor\bin\pest tests/Feature/Insights/BackfillBusinessVerticalCommandTest.php --filter="flag --force sobrescreve"

# 2) test do default behavior (não-sobrescrita)
vendor\bin\pest tests/Feature/Insights/BackfillBusinessVerticalCommandTest.php --filter="backfill não sobrescreve vertical_id já preenchido"

# 3) suite completa
vendor\bin\pest tests/Feature/Insights/BackfillBusinessVerticalCommandTest.php
```

**Esperado:** ambos tests pass — `--force` reescreve `vertical_id` de `outra` pra `comunicacao_visual` (linha 154); default behavior preserva `outra` (linha 134). 11 tests total na suite.

---

## Sumário rápido pra Felipe (referenciado em `_FELIPE_DECISIONS_PRE_SPRINT1.md`)

| # | Onde | Mudança | Linhas afetadas | Esforço |
|---|---|---|---|---|
| 3 | `migrations/2026_06_01_000004_create_benchmark_aggregates_table.php` | `period_start/end` → `period` string + `metric_key` → `metric` + atualizar índice | 37, 40-41, 53 | ~5min |
| 4 | mesmo | `value_p25/p75` removidos, `value_p90` adicionado | 45-47 | ~3min |
| 5 | `migrations/BackfillBusinessVerticalCommand.php` | `tax_number` → `tax_number_1` (linha 67 + comentário 21) | 21, 67 | ~2min |
| 6 | mesmo | adicionar `--force` flag em signature + logic condicional `whereNull` | 42-44, 50 | ~5min |

**Total estimado:** ~15min de mudança em 2 arquivos draft + ~30-45min de smoke Pest local. Bate com estimativa de `_FELIPE_DECISIONS_PRE_SPRINT1.md` (~1.5h end-to-end incluindo PR).

**Pré-Pest:** Felipe confere se a env local tem MariaDB ≥10.2 ou MySQL ≥8 pro CHECK constraint funcionar (audit doc #9 médio). Se não, alinhamento da Critical 3+4 ainda é válido — só o teste do CHECK constraint (linhas 51, 65, 81 do test) que precisa de skip condicional.

---

## Itens ambíguos — Felipe valida

### Aliasing `metric` vs `metric_key`

**AMBÍGUO — Felipe valida.** Adicionei o rename `metric_key` → `metric` no fix da Critical 3 porque o test usa `metric` em 5+ lugares (54, 68, 84, 150, 168, 175, 295). Hipóteses:

1. **Adotar `metric` (proposto neste sketch):** alinha test, nome curto, semântica direta
2. **Manter `metric_key` na migration e atualizar test:** `metric_key` é nome mais "schema-y" (sugere FK ou enum-like), comum em tabelas de métrica multi-dimensional
3. **Ignorar — não é critical:** se Felipe não quiser tocar, deixar como ambiguidade pra resolver depois quando for criar o Service

Recomendo **(1)** porque o test foi escrito pensando no domínio (`metric` é "qual indicador estou agregando" — receita, ticket, etc.), não na schema. Mas se Felipe preferir **(2)**, custa só ajustar 7 linhas no test.

### `metric_key` longo (100 chars) vs `metric` curto

Se Felipe escolher hipótese (1), considerar reduzir `string('metric', 50)` — domínio só tem ~10 valores possíveis (`receita_anual`, `ticket_medio`, `m2_produzidos_mes`, etc.). 100 chars é overkill. Não bloqueante — só nota.
