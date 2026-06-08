# Audit Agent A — drafts pre-Sprint 1 (2026-05-10)

> **Para Felipe ler ANTES de aplicar drafts segunda-feira.**
>
> Sub-agent Opus 4.7 auditou os drafts em `migrations/`, `tests/`, `repair-shared-refactor/` e `modules-comunicacao-visual-scaffold/` em 2026-05-10. Achou 6 críticos + 8 médios + 3 cosméticos.
>
> **Todos os 6 críticos pre-fixados nos drafts** (2 triviais 2026-05-10 manhã + 2 schema benchmark 2026-05-10 tarde + 2 BackfillCommand 2026-05-10 tarde tarde). Felipe segunda **só valida** que concorda com as escolhas, roda Pest local, e abre PR US-INFRA-012.

---

## Pre-fixados nesta auditoria (6 críticos — TODOS)

### 1. ✅ Typo middleware `'authh'` em scaffold ComunicacaoVisual

**Arquivo:** `modules-comunicacao-visual-scaffold/Routes/web.php:27`

**Problema:** Middleware list tinha `'authh'` (4 letras h) duplicado em paralelo ao `'auth'` real. Em runtime daria `Target class [authh] does not exist`.

**Fix aplicado:** removido `'authh'`, mantido só `'auth'`.

### 2. ✅ `DataController.php` em `Http/` (deveria estar em `Http/Controllers/`)

**Arquivo:** `modules-comunicacao-visual-scaffold/Http/DataController.php`

**Problema:** UltimatePOS espera `Modules\<X>\Http\Controllers\DataController` (middleware `AdminSidebarMenu` chama esse path explícito). Comentário no header avisava mas era armadilha esquecível.

**Fix aplicado:** movido pra `Http/Controllers/DataController.php`.

### 3. ✅ Schema benchmark `period` (D1) — pre-fixado 2026-05-10 tarde

**Arquivo:** `migrations/2026_06_01_000004_create_benchmark_aggregates_table.php`

**Decisão aplicada:** Opção B do `_FELIPE_DECISIONS_PRE_SPRINT1.md` — `period` string YYYY-MM em vez de range `period_start/period_end`.

**Razão:** benchmark de receita/ticket é mensal nativamente. Tests já usavam `period` string (linhas 55,69,85,151,296 do `BenchmarkAggregatorKAnonymityTest.php`).

**Felipe segunda:** se discordar (querendo weekly/quinzenal) reverte e abre ADR justificando custo de range vs ganho de granularidade.

### 4. ✅ Schema benchmark `p50/p90` (D2) — pre-fixado 2026-05-10 tarde

**Arquivo:** mesmo `migrations/2026_06_01_000004_create_benchmark_aggregates_table.php`.

**Decisão aplicada:** Opção B — `value_p50` (mediana) + `value_p90` (cauda) em vez de quartiles `p25/p50/p75`.

**Razão:** padrão SaaS benchmarking (Stripe, ProfitWell, ChartMogul) — "estou acima da mediana? quão longe estou do top 10%?". p25 raramente entra em pitch real. Tests já alinhados.

**Felipe segunda:** se quiser quartiles pra exibir boxplot, reverte e justifica use case.

---

## Pendentes — Felipe decide segunda (2 críticos de design)

> ⚠️ **#3 e #4 já pre-fixados** acima (schema benchmark via D1+D2). Restam só #5 (`tax_number` vs `tax_number_1`) e #6 (`--force` flag). Subseções abaixo mantidas pra histórico.

### 3. ⚠️ Schema benchmark — `period_start/period_end` (migration) vs `period` string (testes) — RESOLVIDO

**Arquivos:**
- `migrations/2026_06_01_000004_create_benchmark_aggregates_table.php:40-41`
- `tests/Feature/Insights/BenchmarkAggregatorKAnonymityTest.php:55,69,85,151,296`

**Conflito:**
- Migration usa `period_start` (date) + `period_end` (date) — range de datas formal
- Tests assumem `period` (string YYYY-MM, ex: `'2026-04'`) — formato curto

**Tradeoff:**
- **Opção A (manter migration):** range de datas é mais flexível, suporta bench semanal/quinzenal. Custos: queries via prefix LIKE precisam virar BETWEEN, schema mais "pesado", string→date casts em todo lugar
- **Opção B (alinhar à test):** `period` string YYYY-MM é mais simples, queries diretas. Custo: trava granularidade mensal pra sempre

**Recomendação Felipe:** Opção B (string `period`). Benchmark de receita/ticket é mensal nativamente; granularidade < mês é overhead sem ganho. Se daqui 2 anos precisar weekly, migra coluna sem dor.

**Fix necessário (Opção B):**
```php
// migration: substituir
$table->date('period_start');
$table->date('period_end');
// por
$table->string('period', 10)->comment('YYYY-MM (mensal) ou YYYY-WW (semanal futuro)');
```

### 4. ⚠️ Schema benchmark — `value_p25/p50/p75` (migration) vs `value_p50/p90` (testes) — RESOLVIDO

**Arquivos:** mesmos da #3.

**Conflito:**
- Migration: percentis quartile (p25, p50, p75)
- Tests: mediana + cauda (p50, p90)

**Tradeoff:**
- p25/p75 = quartiles padrão estatística (ex: boxplot)
- p50/p90 = mediana + p90 cauda (padrão indústria pra benchmarking SaaS — "como vc se compara à mediana e ao top 10%?")

**Recomendação Felipe:** p50 + p90 (alinhar à test). Benchmarking pra "minha receita está acima da mediana? como me comparo aos top 10%?" — Wagner como dono de SaaS provavelmente prefere essa narrativa. p25 é raramente útil em pitch.

**Fix necessário:**
```php
// migration: substituir as 3 colunas value_p* por
$table->decimal('value_p50', 18, 2)->nullable()->comment('Mediana');
$table->decimal('value_p90', 18, 2)->nullable()->comment('Top 10% cauda');
```

### 5. ⚠️ `BackfillBusinessVerticalCommand` lê `tax_number` mas tabela `business` tem `tax_number_1` — RESOLVIDO

**Arquivos:**
- `migrations/BackfillBusinessVerticalCommand.php:67`
- `tests/Feature/Insights/BackfillBusinessVerticalCommandTest.php:47-51`

**Problema:** O Command faz `$biz->tax_number ?? ''` mas a coluna no UltimatePOS é `tax_number_1` (legacy multi-tax). Vai retornar NULL silenciosamente — **command nunca vai resolver CNPJ → CNAE → vertical**.

**Recomendação Felipe:** confirmar coluna exata via `SHOW CREATE TABLE business` no Hostinger antes do PR. Se confirmar `tax_number_1`:
```php
// BackfillBusinessVerticalCommand.php:67
- (string) ($biz->tax_number ?? '')
+ (string) ($biz->tax_number_1 ?? '')
```

E no test, factory já está correto (usa `tax_number_1`).

### 6. ⚠️ Test usa `--force` flag, Command não tem signature `--force` — RESOLVIDO

**Arquivos:**
- `tests/Feature/Insights/BackfillBusinessVerticalCommandTest.php:137-155`
- `migrations/BackfillBusinessVerticalCommand.php:42-44`

**Problema:** Command signature só tem `--business-id` e `--dry-run`. Test invoca com `--force` (pra re-rodar em business já com vertical setado). Vai dar `option not defined`.

**Decisão Felipe:** ou adiciona `--force` no Command (recomendado, comportamento útil), ou remove o teste:
```php
// BackfillBusinessVerticalCommand.php signature
- {--business-id=}
- {--dry-run}
+ {--business-id=}
+ {--dry-run}
+ {--force : Re-roda em business que já tem vertical_id setado (sobrescreve)}
```

E na lógica do `handle()`:
```php
$query = DB::table('business');
if (! $this->option('force')) {
    $query->whereNull('vertical_id');
}
```

---

## Médios (8) — Felipe pode pegar incrementalmente em PR-2

### 7. `add_vertical_cnae_to_business.php:42` — `->after('id')` na tabela `business`

Coloca `vertical_id` logo após `id`. Pode quebrar SELECTs com `*` em código legacy posição-baseado. **Fix:** `->after('owner_id')` ou final da tabela.

### 8. `BackfillBusinessVerticalCommand.php:107-111` — Mockery `shouldNotHaveReceived` com closure

Sintaxe Mockery ambígua. **Fix:** trocar por `shouldHaveReceived('info')->withArgs(fn($msg, $ctx) => !str_contains(json_encode($ctx), '12.345.678'))`.

### 9. `BenchmarkAggregatorKAnonymityTest.php:51-62` — CHECK constraint silenciosamente passa em MariaDB <10.2.1

**Fix:** adicionar pre-check `DB::select("SELECT VERSION()")` + skip + warning se versão não suporta CHECK.

### 10. `BenchmarkAggregatorTest.php:46-50` — Mockery chained string `DB::shouldReceive('table->where->where->groupBy->get')`

Não funciona em Mockery — precisa cada método separado com `andReturnSelf()`. **Fix:** refatorar pra `DB::shouldReceive('table')->andReturnSelf()` etc. OU usar `RefreshDatabase` real.

### 11. `VerticalsSeeder.php:88` — `'outros'` com `sort_order: 99` (gap proposital?)

Resto vai até 51. Gap intencional pra futuras inserções? **Fix:** comentário documentando intenção.

### 12. `ProducaoOficinaController.php.proposed:160` — `\App\Business::find($businessId)`

Se `Business` model tem `BelongsToBusiness` aplicado (raro), busca pode retornar NULL. **Fix:** comentário `// SUPERADMIN: Business é root tenancy` OU usar `DB::table('business')->find()`.

### 13. `MIGRATION_NOTES.md:146-154` — test "move endpoint preserves business_id scope"

Cria `JobSheet::factory()->create(['business_id' => 99])` mas não chama `actingAsBusinessUser(businessId: 1)` explícito. Confirmar se herda do `beforeEach` linha 84.

### 14. `cnae_codigos_table.php:30` — index sem nome explícito (CLAUDE.md exige)

Indexes auto-gerados ficam ~28 chars (OK em isolado), mas convenção interna é nome explícito. **Fix:** `->index('codigo_X', 'cnae_codigos_X_idx')`.

---

## Cosméticos (3)

### 15. `composer.json:18-19` — autoload `psr-4` com string vazia

UltimatePOS usa `"./"` ou caminho explícito. Conferir `Modules/ADS/composer.json` antes.

### 16. `VerticalsSeeder.php:30` — `name_plural` igual `name` em invariante PT (Comunicação Visual)

OK passar null pra economizar.

### 17. UTF-8 em seeder (`Comunicação`, `Saúde`)

Confirmar `config/database.php` charset = `utf8mb4` no Hostinger.

---

## Auditado limpo — passou no audit

- Migration `add_vertical_cnae_to_business.php` down() usa `dropIndex` antes de `dropConstrainedForeignId` (correto pra MySQL 8)
- `BackfillCommand` usa `DB::table('business')` — bypassa scope intencionalmente em CLI cross-tenant (bem comentado)
- `cnae_codigos` é catalogo global sem `business_id` — intencional, teste cobre (`CnaeCodigosTest.php:169`)
- `verticals` é global sem `business_id` — teste cobre (`VerticalsTest.php:181-189`)
- `benchmark_aggregates` sem `business_id` — intencional cross-tenant agregado, teste cobre (`BenchmarkAggregatorKAnonymityTest.php:235-237,290-304`)
- Repair refactor preserva `where('business_id', $businessId)` em todos paths de query (Controller `:68,80,124,129`)
- README scaffold avisa sobre LegacyMenuAdapter, Ziggy, e regra Pest local

---

## Bug ambiental Pest local (achado durante audit)

Ao tentar rodar Pest local pra validar o PR #387, descobri:

**Arquivo:** `Modules/Jana/Tests/Feature/Admin/GovernancaControllerTest.php:12` + `Modules/Jana/Tests/Feature/Admin/CustosControllerTest.php:12`

**Erro:**
```
ERROR  Test case [Tests\TestCase] can not be used. The folder
[D:\oimpresso.com\Modules\Jana\Tests\Feature\Admin\GovernancaControllerTest.php]
already uses the test case [Tests\TestCase].
```

**Causa raiz:** ambos arquivos declaram `uses(Tests\TestCase::class)->in(__DIR__)`. Pest tenta registrar TestCase pra mesma pasta `Modules/Jana/Tests/Feature/Admin` 2x — conflito.

**Fix sugerido:** mover `uses(Tests\TestCase::class)->in(__DIR__)` pra um único `Modules/Jana/Tests/Pest.php` central. Os arquivos individuais usam só `uses(Tests\TestCase::class);` sem `->in()`.

**Impacto CI:** se CI executa Pest com `--filter` ou path específico que cruza esses 2 arquivos, quebra. Se CI roda phpunit puro (não Pest), não quebra. Verificar `.github/workflows/ci.yml`.

**Severidade:** médio. Bloqueia debugging local em Modules/Jana.

---

## Ranking pra Felipe atacar segunda

1. ~~**Decidir #3+#4 (schema benchmark)**~~ — ✅ pre-fixado 2026-05-10 tarde (Opção B). Felipe valida que concorda.
2. **#5 + #6 (BackfillCommand: tax_number_1 + --force)** — 30min update Command (1 SSH `SHOW COLUMNS` + edit signature + edit handle)
3. **Bug Pest Modules/Jana** — 15min mover `uses(...)->in()`
4. **Médios (#7-#14)** — incremental, PR-2 separado

Esforço total restante: **~45min críticos** + ~2-3h médios. (Antes da pre-fix tarde: 1.5h).

---

**Auditoria por:** sub-agent Claude Opus 4.7 (background `agentId: a999564cac1129866`)
**Validação:** Felipe roda Pest local segunda com fixes aplicados antes de PR.
