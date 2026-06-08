# RUNBOOK — Implementar tool MCP `module-state <modulo>` (US-MCP-017)

> **Tipo:** runbook executável.
> **Pré-condição:** Wagner liberou implementação (gate definido em [SPEC §12](../SPEC-US-MCP-017-module-state-projection.md#12-lifecycle)).
> **NÃO COMEÇAR sem:** confirmação das 5 áreas cinzentas listadas em [SPEC §10](../SPEC-US-MCP-017-module-state-projection.md#10-áreas-cinzentas-parent-precisa-confirmar-com-wagner-antes-de-implementar).
> **PR target:** ≤300 linhas ([commit-discipline](../../../../.claude/skills/commit-discipline/SKILL.md) Tier A).

## Visão geral

Tool MCP read-side derivada do event stream (handoffs/sessions append-only) + tools MCP existentes (`tasks-list`, `decisions-search`) + git/gh + filesystem. CQRS projection per bounded context (DDD).

**Total estimado:** 14h IA-pair · 1.75 dev-day. Faseado em 7 fases sequenciais com smoke gate entre Fase 5 e Fase 6.

## Fase 1 — Skeleton ModuleStateTool class (0.5h)

### 1.1 Arquivo

Criar `Modules/Jana/Mcp/Tools/ModuleStateTool.php` herdando de `Laravel\Mcp\Server\Tool`.

### 1.2 Schema mínimo

```php
protected string $name = 'module-state';
protected string $title = 'Estado consolidado de um módulo (bounded context DDD)';
protected string $description = 'Projeção read-side do estado de um módulo: cycle ativo, tasks ativas, ADRs aplicáveis, handoffs recentes, PRs mergeados, charter, RUNBOOK, SPEC summary, CAPTERRA, drift. CQRS projection do event stream (handoffs append-only ADR 0130). Multi-tenant Tier 0. Cache 5min.';

public function schema(JsonSchema $schema): array
{
    return [
        'module' => $schema->string()
            ->required()
            ->description('Nome do módulo (ex: Sells, Whatsapp, Crm). Case-sensitive. Lista canônica via Glob memory/requisitos/*/SPEC.md.'),
    ];
}
```

### 1.3 Handle skeleton

```php
public function handle(Request $request): Response
{
    $module = trim((string) $request->get('module', ''));
    if ($module === '') {
        return Response::error('Parâmetro "module" obrigatório. Use tasks-list pra ver módulos disponíveis.');
    }

    // Validar módulo existe (Glob)
    if (! $this->moduloExiste($module)) {
        $disponiveis = $this->listarModulos();
        return Response::text("Módulo '{$module}' não encontrado.\n\nDisponíveis: " . implode(', ', $disponiveis));
    }

    // Multi-tenant scope herdado do Request
    $user = $request->user();
    $businessId = (int) data_get($user, 'business_id', 0);

    // Cache check
    $cacheKey = "module-state:{$module}:biz={$businessId}";
    if ($cached = $this->buscarCache($cacheKey)) {
        return Response::text($cached);
    }

    $t0 = microtime(true);
    $payload = $this->agregar($module, $businessId, $user);
    $payload['duration_ms'] = (int) ((microtime(true) - $t0) * 1000);
    $payload['cache_hit'] = false;

    $output = $this->renderMarkdown($payload);
    $this->salvarCache($cacheKey, $output, ttlSeconds: 300);

    return Response::text($output);
}
```

### Sinal de saída Fase 1

`php artisan mcp:tools | grep module-state` retorna a tool registrada (após Fase 4).

## Fase 2 — 9 coletores internos (4-5h)

> **Princípio guia:** cada coletor é `protected function coletarX($module, $businessId): array` — falha gracefully retornando `[]`, NUNCA throw. Pattern provado em [HandoffDiffTool.php §coletarPrs](../../../../Modules/Jana/Mcp/Tools/HandoffDiffTool.php).

### 2.1 `coletarCycle($module, $businessId)` (20min)

Query `mcp_cycles` join `mcp_cycle_goals` filtrando goals que mencionam o módulo (`goal LIKE %{$module}%` ou `module = {$module}`). Retorna cycle ativo + count goals tocando módulo.

```php
$cycle = DB::table('mcp_cycles')
    ->where('status', 'doing')
    ->orderByDesc('start_date')
    ->first(['key', 'status', 'goal']);

$goalsCount = DB::table('mcp_cycle_goals')
    ->where('cycle_key', $cycle->key ?? '')
    ->where(function ($q) use ($module) {
        $q->where('module', $module)->orWhere('description', 'LIKE', "%{$module}%");
    })
    ->count();
```

### 2.2 `coletarTasksAtivas($module, $businessId)` (30min)

Reusa lógica `TasksListTool`. Filtra status active (`whereNotIn('status', ['done', 'cancelled'])`) + `module = {$module}`. Top 8 por priority+status.

**Tier 0 multi-tenant:** se módulo tem coluna `business_id` em `mcp_tasks` → scope. Hoje `mcp_tasks` é repo-wide (governança projeto) — sem scope. Documentar gotcha.

### 2.3 `coletarAdrsAplicaveis($module)` (20min)

Reusa `DecisionsSearchTool` internamente:

```php
$searchTool = app(DecisionsSearchTool::class);
$response = $searchTool->handle(new Request(['query' => $module, 'limit' => 5]));
// Parser leve do response markdown → array de slugs+titles
```

Alternativa direta: `DB::table('mcp_memory_documents')->where('type','adr')->whereRaw('MATCH(title,content_md) AGAINST (? IN NATURAL LANGUAGE MODE)', [$module])->limit(5)`.

### 2.4 `coletarHandoffsRecentes($module)` (30min)

```php
$dir = base_path('memory/handoffs');
$matches = [];
foreach (scandir($dir) as $entry) {
    if (! str_ends_with($entry, '.md')) continue;
    $content = file_get_contents("{$dir}/{$entry}");
    if (stripos($content, $module) === false) continue;
    // Extract date from filename YYYY-MM-DD-HHMM-slug.md
    if (! preg_match('/^(\d{4}-\d{2}-\d{2})-(\d{4})-(.+)\.md$/', $entry, $m)) continue;
    $matches[] = [
        'date' => $m[1] . ' ' . substr($m[2],0,2) . ':' . substr($m[2],2,2),
        'slug' => $m[3],
        'sumario_1line' => $this->extrairPrimeiraLinhaNarrativa($content),
    ];
}
usort($matches, fn($a,$b) => strcmp($b['date'], $a['date']));
return array_slice($matches, 0, 3);
```

### 2.5 `coletarPrsRecentes($module)` (30min)

Process exec `gh pr list --search "merged:>=YYYY-MM-DD" --json number,title,author,mergedAt`. Pós-filtro: incluir só PRs que tocaram `Modules/{module}/` via `gh pr view <N> --json files` OU usar `gh pr list --search "merged:>=... path:Modules/{$module}"`.

**Timeout 10s** + fallback empty array (pattern HandoffDiffTool linha 188).

### 2.6 `coletarCharter($module)` (20min)

```php
$pagesGlob = glob(base_path("Modules/{$module}/Resources/views/**/*.charter.md"), GLOB_BRACE)
    ?: glob(base_path("resources/js/Pages/{$module}/**/*.charter.md"), GLOB_BRACE);

$live = $draft = 0; $semCharter = [];
foreach ($pagesGlob as $charterPath) {
    $content = file_get_contents($charterPath);
    if (preg_match('/^status:\s*(live|draft)/m', $content, $m)) {
        $m[1] === 'live' ? $live++ : $draft++;
    }
}
// Pages sem charter: glob *.tsx que NÃO tem .charter.md ao lado
```

### 2.7 `coletarRunbooks($module)` (15min)

```php
$pattern = base_path("memory/requisitos/{$module}/RUNBOOK*.md");
$files = glob($pattern);
return array_map(function ($f) {
    $mtime = filemtime($f);
    return [
        'path' => Str::after($f, base_path() . DIRECTORY_SEPARATOR),
        'last_edit' => date('Y-m-d', $mtime),
        'idade_dias' => (int) ((time() - $mtime) / 86400),
    ];
}, $files);
```

### 2.8 `coletarSpecSummary($module)` (30min)

Preferência: query `mcp_tasks` com `module = {$module}` group by status. Se vazio, fallback regex SPEC.md.

```php
$summary = DB::table('mcp_tasks')
    ->where('module', $module)
    ->groupBy('status')
    ->selectRaw('status, COUNT(*) as cnt')
    ->pluck('cnt', 'status')
    ->toArray();

return [
    'total' => array_sum($summary),
    'done' => $summary['done'] ?? 0,
    'review' => $summary['review'] ?? 0,
    'doing' => $summary['doing'] ?? 0,
    'blocked' => $summary['blocked'] ?? 0,
    'todo' => $summary['todo'] ?? 0,
    'ultima_us_adicionada' => DB::table('mcp_tasks')
        ->where('module', $module)
        ->orderByDesc('created_at')
        ->value('task_id'),
];
```

### 2.9 `coletarCapterra($module)` (20min)

```php
$path = base_path("memory/requisitos/{$module}/CAPTERRA-INVENTARIO.md");
if (! file_exists($path)) return null;
$content = file_get_contents($path);
return [
    'aprovado' => substr_count($content, '✅ APROVADO'),
    'parcial' => substr_count($content, '🟡 PARCIAL'),
    'ausente' => substr_count($content, '❌ AUSENTE'),
];
```

### Sinal de saída Fase 2

`Modules\Jana\Mcp\Tools\ModuleStateTool` instanciado em tinker retorna array preenchido pra `module=Whatsapp`.

## Fase 3 — Drift detection (1.5h)

### 3.1 Regras determinísticas

```php
protected function detectarDrift($module, array $signals): array
{
    $drift = [];

    // R1: US doing >7d sem commit
    foreach ($signals['tasks_ativas'] as $t) {
        if ($t['status'] === 'doing' && $this->diasSemCommit($t['task_id']) > 7) {
            $drift[] = ['tipo' => 'stale_doing', 'severidade' => 'warn',
                'mensagem' => "{$t['task_id']} doing há " . $this->diasSemCommit($t['task_id']) . "d sem commit"];
        }
    }

    // R2: Charter ausente em Page que está em prod
    foreach ($signals['charter']['sem_charter'] ?? [] as $page) {
        $drift[] = ['tipo' => 'charter_ausente', 'severidade' => 'info',
            'mensagem' => "Charter ausente em {$page} (página em prod)"];
    }

    // R3: RUNBOOK desatualizado vs último PR
    if (! empty($signals['runbooks_ativos'])) {
        $ultimoRb = max(array_column($signals['runbooks_ativos'], 'idade_dias'));
        $ultimoPr = ! empty($signals['prs_recentes'])
            ? (int) ((time() - strtotime($signals['prs_recentes'][0]['date'])) / 86400)
            : 999;
        if ($ultimoRb > 60 && $ultimoPr < $ultimoRb - 30) {
            $drift[] = ['tipo' => 'runbook_drift', 'severidade' => 'warn',
                'mensagem' => "RUNBOOK >60d enquanto PRs continuam mergeados — re-auditar."];
        }
    }

    // R4: US blocked >30d
    foreach ($signals['tasks_ativas'] as $t) {
        if ($t['status'] === 'blocked' && $this->diasNoStatus($t['task_id']) > 30) {
            $drift[] = ['tipo' => 'stale_blocked', 'severidade' => 'alert',
                'mensagem' => "{$t['task_id']} blocked há >30d — escalar Wagner."];
        }
    }

    return $drift;
}
```

### 3.2 Helpers

- `diasSemCommit($taskId)` — `git log --grep "{$taskId}" -1 --format=%ci` → diff hoje.
- `diasNoStatus($taskId)` — `mcp_task_status_history` se existe, senão `updated_at`.

### Sinal de saída Fase 3

Test unit `tests/Feature/Mcp/Tools/ModuleStateToolDriftTest.php` confirma 4 regras dispara corretamente.

## Fase 4 — Cache layer + register em OimpressoMcpServer (1h)

### 4.1 Migration

```bash
php artisan make:migration create_mcp_module_state_cache_table
```

```php
Schema::create('mcp_module_state_cache', function (Blueprint $table) {
    $table->id();
    $table->string('cache_key')->unique();  // module-state:Sells:biz=1
    $table->longText('output_md');
    $table->timestamp('expires_at');
    $table->timestamps();
    $table->index('expires_at');
});
```

### 4.2 Cache helpers

`buscarCache($key)` retorna `output_md` se `expires_at > now()`, senão null.
`salvarCache($key, $output, $ttl)` upsert com `expires_at = now()->addSeconds($ttl)`.

### 4.3 Register em OimpressoMcpServer

Adicionar no final do `$tools` array (Page 2 knowledge cluster), com comment block:

```php
// US-MCP-017 (SPEC memory/requisitos/Mcp/SPEC-US-MCP-017) — Module state CQRS
// projection per bounded context. Read-side derivada do event stream (handoffs
// append-only ADR 0130) + tools MCP existentes. Multi-tenant Tier 0 (ADR 0093).
// Cache 5min (paridade brief-fetch ADR 0091). Time MCP entrante usa pra
// onboarding por módulo em <1min. Princípio 2 tiered cost: ZERO LLM call default.
Tools\ModuleStateTool::class,
```

### Sinal de saída Fase 4

`mcp:tools` lista `module-state`. Cache funciona — segunda chamada <100ms.

## Fase 5 — Pest 5 tests (2h)

### 5.1 Estrutura

`tests/Feature/Mcp/Tools/ModuleStateToolTest.php`

Tests obrigatórios:

```php
it('retorna estado consolidado pra módulo Whatsapp biz=1 (smoke)', ...);
it('respeita Tier 0 multi-tenant — biz=4 não vê tasks scoped de biz=1', ...);
it('retorna erro útil quando módulo não existe', ...);
it('cache hit retorna em <100ms (segunda chamada)', ...);
it('drift detection flagga US doing >7d sem commit', ...);
```

### 5.2 Multi-tenant test

```php
$user1 = User::factory()->forBusiness(1)->create();
$user4 = User::factory()->forBusiness(4)->create();

// Create task scoped biz=1 em módulo "Sells" (assumindo Sells terá biz scope)
$task = McpTask::create(['task_id' => 'US-SELL-999', 'module' => 'Sells', 'business_id' => 1, 'status' => 'doing']);

$this->actingAs($user4);
$response = (new ModuleStateTool)->handle(new Request(['module' => 'Sells']));
expect($response->content())->not->toContain('US-SELL-999');
```

### Sinal de saída Fase 5

`vendor/bin/pest tests/Feature/Mcp/Tools/ModuleStateToolTest.php` — 5/5 green.

## Fase 6 — Smoke real biz=1 INTERATIVO (1h — Wagner valida)

> **GATE HUMANO:** Wagner roda smokes manuais e valida output. Se output for inútil/poluído, voltar Fase 2 ajustar coletores. **NÃO mergear sem este gate.**

### 6.1 Setup

Conectar via MCP client (Claude Code ou MCP Inspector) ao `mcp.oimpresso.com` produção com user biz=1.

### 6.2 Smokes obrigatórios

```
module-state Whatsapp   # 32 tasks ativas — denso
module-state Sells       # denso PRs últimos 30d
module-state Jana        # repo-wide / cross-tenant
module-state Crm         # bounded context simples
module-state ModuloInexistente   # erro útil + sugestões
```

### 6.3 Critérios Wagner aprovar

- [ ] Output cabe em 1 tela (<800 tokens)
- [ ] Cycle ativo correto
- [ ] Tasks ativas batem com `tasks-list module:<X>`
- [ ] ADRs top 5 relevantes (não ruído)
- [ ] Handoffs 3 últimos mencionam módulo verificável
- [ ] PRs 5 últimos batem com `gh pr list --search`
- [ ] Drift flagga corretamente itens stale conhecidos
- [ ] Multi-tenant: chamar biz=4 → não vê dados biz=1
- [ ] Performance <2s P95 (cache miss) · <100ms (cache hit)

### 6.4 Se falhar

Documentar em handoff `2026-MM-DD-HHMM-module-state-smoke-fail-<motivo>.md`. Voltar fase apropriada.

### Sinal de saída Fase 6

Handoff append-only criado em `memory/handoffs/` com seção "Smoke `module-state` aprovado por Wagner" + screenshots.

## Fase 7 — Docs README + SPEC.md anexar (0.5h)

### 7.1 README pacote MCP

Adicionar bullet em `Modules/Jana/Mcp/README.md` (criar se não existe):

```markdown
- **`module-state <modulo>`** — CQRS projection per bounded context. Estado consolidado de um módulo em <2s. Read-side derivada do event stream. Multi-tenant Tier 0. Cache 5min. Time MCP usa pra onboarding por módulo. SPEC: [US-MCP-017](../../memory/requisitos/Mcp/SPEC-US-MCP-017-module-state-projection.md).
```

### 7.2 Atualizar SPEC.md master Mcp

Em [memory/requisitos/Mcp/SPEC.md](../SPEC.md) marcar US-MCP-017 status `done` + commit-discipline format.

### 7.3 Anunciar ao time MCP

Skill `oimpresso-team-onboarding` ganha bullet:

```markdown
- Tool `module-state <Modulo>` — pega seu bounded context novo em <1min. Use ao receber atribuição de módulo desconhecido.
```

### Sinal de saída Fase 7

PR mergeado + tool live em produção + 1 dev do time MCP usou e reportou útil em handoff.

## Plano de implementação — sequenciamento de commits

> [commit-discipline](../../../../.claude/skills/commit-discipline/SKILL.md): 1 PR = 1 intent, ≤300 linhas, conventional commits, refs.

| Commit | Conteúdo | Linhas |
|---|---|---:|
| `feat(mcp): scaffold ModuleStateTool + schema + register` (Fase 1+4 register) | Skeleton + register OimpressoMcpServer + migration cache | ~60 |
| `feat(mcp): coletores estado-do-modulo (cycle/tasks/adrs/handoffs/prs)` (Fase 2 parte 1) | 5 primeiros coletores | ~120 |
| `feat(mcp): coletores estado-do-modulo (charter/runbook/spec/capterra)` (Fase 2 parte 2) | 4 coletores restantes + cache layer | ~100 |
| `feat(mcp): drift detection 4 regras` (Fase 3) | detectarDrift + helpers | ~80 |
| `test(mcp): Pest 5 tests ModuleStateTool incl. Tier 0` (Fase 5) | 5 testes | ~120 |
| `docs(mcp): README + SPEC US-MCP-017 done + skill onboarding bullet` (Fase 7) | Docs | ~30 |

**Total esperado:** ~510 linhas em 6 commits. Wagner pode requerer split adicional se algum commit ultrapassar 300.

## Rollback

Se em produção a tool degradar perf ou retornar lixo:

1. Remover do array `$tools` em `OimpressoMcpServer` → tool desaparece imediatamente (sem rota nova).
2. Cache em `mcp_module_state_cache` pode permanecer (não toca outras tools).
3. Migration pode ficar (tabela órfã não quebra nada).

Skill `mcp-first` continua válida — usuário usa tools alternativas (`brief-fetch` + `tasks-list module:` + `decisions-search`).

## Refs

- [SPEC US-MCP-017](../SPEC-US-MCP-017-module-state-projection.md) — definição funcional
- [Dossier 2026-05-15 §6+§8](../../../sessions/2026-05-15-arte-memoria-claude-code-oimpresso.md) — origem decisão CQRS
- [ADR 0130](../../../decisions/0130-handoff-append-only-mcp-first.md) — event stream canônico
- [ADR 0093](../../../decisions/0093-multi-tenant-isolation-tier-0.md) — Tier 0 isolation
- [ADR 0091](../../../decisions/0091-daily-brief.md) — pattern cache 5min
- [ADR 0094](../../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) — princípio 2 tiered cost
- [HandoffDiffTool.php](../../../../Modules/Jana/Mcp/Tools/HandoffDiffTool.php) — pattern multi-fonte best-effort
- [TasksListTool.php](../../../../Modules/Jana/Mcp/Tools/TasksListTool.php) — filtro module
- [DecisionsSearchTool.php](../../../../Modules/Jana/Mcp/Tools/DecisionsSearchTool.php) — semantic match top-N
