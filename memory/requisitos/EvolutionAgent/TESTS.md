# EvolutionAgent — Plano de testes

> Cada user story do [SPEC.md](SPEC.md) tem ≥1 teste Pest que protege seu contrato. Testes seguem regra do Wagner: "Função/endpoint/migration nova sempre sai com teste Pest que proteja o contrato".

## Princípios

- **Pest > PHPUnit**: usar `it('...')` + expect-API.
- **Determinístico**: testes que envolvem LLM usam `Vizra::fake()` ou seed fixa + temperature=0.
- **Sem custo de API em CI**: tudo que chamaria LLM real vira fake; eval real só em job dedicado com tag `@costs-money`.
- **Fixture isolada**: testes não dependem de `memory/` real do projeto; usam `tests/Fixtures/memory-fake/`.

## Convenções

```
tests/Feature/Evolution/
  IndexCommandTest.php          # US-EVOL-001
  QueryCommandTest.php          # US-EVOL-002
  RankCommandTest.php           # US-EVOL-003
  EvalCommandTest.php           # US-EVOL-004
  PrCommentToolTest.php         # US-EVOL-006
  PrDraftToolTest.php           # US-EVOL-007

tests/Unit/Evolution/
  Tools/MemoryQueryToolTest.php
  Tools/ListAdrsToolTest.php
  Tools/RankByRoiToolTest.php
  Agents/EvolutionAgentTest.php
  Agents/FinanceiroAgentTest.php
  Embeddings/VoyageDriverTest.php
  Embeddings/CosineSimilarityTest.php

tests/Eval/
  golden.yml                    # 5 perguntas-ouro
  EvalRunnerTest.php            # roda com tag @costs-money

tests/Fixtures/
  memory-fake/                  # subset controlado pra testes
    requisitos/
      Financeiro/SPEC.md
      PontoWr2/SPEC.md
    decisions/
      0026-posicionamento.md
```

---

## Fase 1a — Skeleton mínimo (sem vetor ainda, sem LLM real)

**Objetivo desta fase**: provar que o pipeline `Artisan → Tool → leitura de memory/` funciona. **Sem custo de API**. Sem embeddings.

### T-001 · Comando `evolution:query` registrado

**Arquivo**: `tests/Feature/Evolution/QueryCommandTest.php`

```php
it('registra evolution:query no Artisan', function () {
    $this->artisan('list')
        ->expectsOutputToContain('evolution:query')
        ->assertExitCode(0);
});
```

**Por que importa**: regressão básica — comando some se Service Provider quebrar.

### T-002 · `evolution:query` retorna chunks de memory/

```php
it('retorna trechos relevantes para "Financeiro"', function () {
    config(['evolution.memory_path' => base_path('tests/Fixtures/memory-fake')]);

    $this->artisan('evolution:query', ['question' => 'Financeiro'])
        ->expectsOutputToContain('Financeiro/SPEC.md')
        ->assertExitCode(0);
});
```

**Por que**: contrato do comando — query "X" retorna chunk com "X".

### T-003 · Query irrelevante retorna vazio (sem falso positivo)

```php
it('retorna vazio para query sem match', function () {
    config(['evolution.memory_path' => base_path('tests/Fixtures/memory-fake')]);

    $this->artisan('evolution:query', ['question' => 'asdfqwerty12345'])
        ->expectsOutputToContain('Nenhum trecho encontrado')
        ->assertExitCode(0);
});
```

**Por que**: agente não pode inventar matches.

### T-004 · Tool `MemoryQuery` é unitária e pura

**Arquivo**: `tests/Unit/Evolution/Tools/MemoryQueryToolTest.php`

```php
it('retorna array com [file, heading, content, score]', function () {
    $tool = new MemoryQueryTool(memoryPath: base_path('tests/Fixtures/memory-fake'));

    $result = $tool->__invoke(query: 'Financeiro', topK: 5);

    expect($result)->toBeArray()
        ->and($result[0])->toHaveKeys(['file', 'heading', 'content', 'score'])
        ->and($result[0]['file'])->toContain('Financeiro');
});
```

**Por que**: tool isolada de framework — base pra Vizra Agent invocar.

### T-005 · Score determinístico (mesma query → mesma ordem)

```php
it('é determinístico em mesma query', function () {
    $tool = new MemoryQueryTool(memoryPath: base_path('tests/Fixtures/memory-fake'));

    $a = $tool->__invoke(query: 'Financeiro', topK: 3);
    $b = $tool->__invoke(query: 'Financeiro', topK: 3);

    expect(array_column($a, 'file'))->toBe(array_column($b, 'file'));
});
```

**Por que**: gate pro eval (LLM-as-Judge) ser confiável depois.

---

## Fase 1b — Vector embeddings (Voyage-3-lite)

### T-006 · `evolution:index` percorre memory/ e cria chunks

**Arquivo**: `tests/Feature/Evolution/IndexCommandTest.php`

```php
it('indexa arquivos .md em memory_chunks', function () {
    config(['evolution.memory_path' => base_path('tests/Fixtures/memory-fake')]);
    \Vizra\Embeddings\VoyageDriver::fake();

    $this->artisan('evolution:index')
        ->expectsOutputToContain('Indexed')
        ->assertExitCode(0);

    expect(\App\Models\Evolution\MemoryChunk::count())->toBeGreaterThan(0);
});
```

### T-007 · Re-index é idempotente

```php
it('não duplica chunks em re-index', function () {
    \Vizra\Embeddings\VoyageDriver::fake();

    $this->artisan('evolution:index')->assertExitCode(0);
    $count1 = \App\Models\Evolution\MemoryChunk::count();

    $this->artisan('evolution:index')->assertExitCode(0);
    $count2 = \App\Models\Evolution\MemoryChunk::count();

    expect($count2)->toBe($count1);
});
```

### T-008 · Re-index detecta arquivo modificado (mtime)

```php
it('re-indexa apenas arquivos com mtime mais recente', function () {
    \Vizra\Embeddings\VoyageDriver::fake();

    $this->artisan('evolution:index')->assertExitCode(0);

    touch(base_path('tests/Fixtures/memory-fake/requisitos/Financeiro/SPEC.md'));

    $output = $this->artisan('evolution:index')->expectsOutputToContain('1 reindexed');
    expect($output)->not->toContain('skipped');
});
```

### T-009 · Cosine similarity retorna top-K corretamente

**Arquivo**: `tests/Unit/Evolution/Embeddings/CosineSimilarityTest.php`

```php
it('cosine retorna 1.0 para vetores idênticos', function () {
    $v = [0.1, 0.2, 0.3];
    expect(CosineSimilarity::compute($v, $v))->toBe(1.0);
});

it('cosine retorna 0 para vetores ortogonais', function () {
    expect(CosineSimilarity::compute([1, 0, 0], [0, 1, 0]))->toBe(0.0);
});
```

---

## Fase 1c — EvolutionAgent (Vizra ADK)

### T-010 · Agente roda com Vizra fake

**Arquivo**: `tests/Unit/Evolution/Agents/EvolutionAgentTest.php`

```php
it('responde com mock LLM', function () {
    \Vizra\Vizra::fake([
        EvolutionAgent::class => 'Top 3: 1. Backfill purchases legacy 2. ... 3. ...',
    ]);

    $response = EvolutionAgent::run('próximo passo Financeiro?')->go();

    expect($response)->toContain('Top 3')->and($response)->toContain('Backfill');
});
```

### T-011 · Agente usa tool MemoryQuery quando relevante

```php
it('chama MemoryQuery tool em queries de contexto', function () {
    \Vizra\Vizra::fake();
    $spy = \Vizra\Vizra::spyTool(MemoryQueryTool::class);

    EvolutionAgent::run('o que diz a SPEC do Financeiro?')->go();

    expect($spy)->toHaveBeenCalled();
});
```

### T-012 · System prompt cita ADR 0026 e meta R$5mi

```php
it('system prompt inclui contexto hot tier', function () {
    $agent = new EvolutionAgent;

    $prompt = $agent->getSystemPrompt();

    expect($prompt)
        ->toContain('R$ 5mi')
        ->and($prompt)->toContain('ADR 0026');
});
```

---

## Fase 2 — Eval framework

### T-020 · Golden set YAML carrega corretamente

```php
it('parseia tests/Eval/golden.yml em 5 casos', function () {
    $cases = (new GoldenSetLoader)->load();
    expect($cases)->toHaveCount(5)
        ->and($cases->first())->toHaveKeys(['id', 'escopo', 'pergunta', 'esperado_contem']);
});
```

### T-021 · `evolution:eval` retorna score 0-100

```php
it('roda golden set e retorna JSON com score', function () {
    \Vizra\Vizra::fake([
        EvolutionAgent::class => fn ($prompt) => "Resposta mock para: $prompt",
    ]);

    $output = $this->artisan('evolution:eval')->run();
    $report = json_decode($output, true);

    expect($report)
        ->toHaveKey('score_accuracy_avg')
        ->and($report['score_accuracy_avg'])->toBeBetween(0, 100);
});
```

### T-022 · Falha CI se score regrediu >5%

```php
it('exit code 1 se score abaixo do baseline -5%', function () {
    file_put_contents(
        base_path('memory/evolution/baseline.json'),
        json_encode(['score_accuracy_avg' => 90])
    );
    \Vizra\Vizra::fake([EvolutionAgent::class => 'mock pobre']);

    $this->artisan('evolution:eval')->assertExitCode(1);
});
```

### T-023 (eval real, com custo) · LLM-as-Judge consistente entre runs

```php
it('judge score varia <10% em 3 runs', function () {
    $scores = collect(range(1, 3))->map(fn () =>
        (new GoldenSetRunner)->runOne('GOLD-001')
    );

    expect($scores->max() - $scores->min())->toBeLessThan(10);
})->group('costs-money')->skip(getenv('VIZRA_RUN_COST_TESTS') !== '1');
```

**Custo estimado**: ~$0.30 por run completo. Roda manualmente quando atualizar baseline.

---

## Fase 3 — Autonomia (tier-2 e tier-3)

### T-030 · Tier-2 PR comment respeita threshold de relevância

```php
it('não comenta se score relevância <0.7', function () {
    $tool = new OpenPrCommentTool;
    \Vizra\Vizra::fake();

    $tool->__invoke(prNumber: 1, body: 'sugestão fraca', relevanceScore: 0.5);

    Http::assertNotSent(); // gh api não foi chamada
});
```

### T-031 · Tier-3 PR-draft fica em rascunho (nunca direto em main)

```php
it('abre PR como draft', function () {
    Http::fake();

    (new OpenDraftPrTool)->__invoke(branch: 'cleanup/dead-code', diff: $smallDiff);

    Http::assertSent(fn ($req) =>
        $req->url() === 'https://api.github.com/repos/.../pulls'
        && $req['draft'] === true
    );
});
```

### T-032 · Tier-3 rejeita diff >50 linhas (vira issue)

```php
it('cria issue em vez de PR se diff >50 linhas', function () {
    Http::fake();
    $bigDiff = str_repeat("- old\n+ new\n", 30); // 60 linhas

    (new OpenDraftPrTool)->__invoke(branch: 'x', diff: $bigDiff);

    Http::assertSent(fn ($req) => str_contains($req->url(), '/issues'));
});
```

### T-033 · Tier-3 só roda com toggle ON

```php
it('skip se EVOLUTION_AUTO_PR_ENABLED=false', function () {
    config(['evolution.auto_pr_enabled' => false]);
    Http::fake();

    $this->artisan('evolution:propose --auto-pr')->assertExitCode(0);

    Http::assertNothingSent();
});
```

---

## Métricas de sucesso por fase (gates)

| Fase | Gate Pest | Gate manual |
|---|---|---|
| 1a | T-001..T-005 verde | Wagner roda `evolution:query` e vê output útil |
| 1b | T-006..T-009 verde | Re-index 50 arquivos <30s |
| 1c | T-010..T-012 verde | Wagner faz 5 perguntas, agente responde sem alucinar |
| 2 | T-020..T-022 verde | GH Actions roda eval em PR e comenta delta |
| 3 | T-030..T-033 verde | 1 PR-draft útil aceito por Wagner |

## Comandos pra rodar

```bash
# Tudo (sem custo de API)
php artisan test --filter=Evolution

# Só eval real (custo ~$1/run completo)
VIZRA_RUN_COST_TESTS=1 php artisan test --group=costs-money

# Só Pest unit
php artisan test tests/Unit/Evolution

# Watch mode (Pest)
./vendor/bin/pest --watch tests/Feature/Evolution
```

## Eval golden set inicial (`tests/Eval/golden.yml`)

```yaml
# Atualizar baseline com: php artisan evolution:eval --update-baseline
- id: GOLD-001
  escopo: Financeiro
  pergunta: "Qual próximo passo Financeiro depois da Onda 2?"
  esperado_contem:
    - "backfill"
    - "purchase"
    - "due"
  citacoes_esperadas:
    - "memory/requisitos/Financeiro/SPEC.md"

- id: GOLD-002
  escopo: PontoWr2
  pergunta: "Top 3 moves Tier A do PontoWr2?"
  esperado_contem:
    - "Dashboard vivo"
    - "Tier A"
  citacoes_esperadas:
    - "memory/requisitos/PontoWr2/"

- id: GOLD-003
  escopo: Cms
  pergunta: "Status atual do redesign Inertia/React?"
  esperado_contem:
    - "redesign"
    - "Inertia"
  citacoes_esperadas:
    - "memory/requisitos/Cms/"

- id: GOLD-004
  escopo: Copiloto
  pergunta: "Tenancy do Copiloto é multi-tenant?"
  esperado_contem:
    - "multi-tenant"
    - "business_id"
  citacoes_esperadas:
    - "memory/requisitos/Copiloto/"

- id: GOLD-005
  escopo: geral
  pergunta: "Qual feature de maior ROI pros próximos 6 meses?"
  esperado_contem:
    - "PricingFpv"
    - "Copiloto"
    - "CT-e"
  citacoes_esperadas:
    - "memory/decisions/" # ADR 0026
```
