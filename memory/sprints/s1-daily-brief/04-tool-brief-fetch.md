# Tool MCP `brief-fetch` — spec + handler PHP

> **Posição na arquitetura:** L1 (MCP Core). Primeira tool que toda
> sessão de Claude chama, forçada pela skill `brief-first` (Tier A).

---

## Schema da tool (registro no MCP server)

```json
{
  "name": "brief-fetch",
  "description": "Devolve o Daily Brief mais recente — markdown ≤3.5k tokens com estado consolidado do projeto. CHAME ANTES DE QUALQUER OUTRA TOOL no início de toda sessão. Cache de 5min, custo trivial. Substitui exploração inicial via cycles-active + sessions-recent + tasks-active + decisions-search.",
  "input_schema": {
    "type": "object",
    "properties": {
      "force_refresh": {
        "type": "boolean",
        "default": false,
        "description": "Se true, dispara regeneração antes de retornar (uso restrito a Wagner; respeita cap diário). Default false retorna do cache."
      }
    },
    "required": []
  }
}
```

---

## Handler PHP (no module ADS ou novo BriefModule)

`Modules/Brief/Http/Controllers/BriefFetchController.php`:

```php
<?php

namespace Modules\Brief\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Brief\Services\BriefGeneratorService;
use Modules\McpAudit\Services\AuditLogger;

final class BriefFetchController
{
    public function __construct(
        private BriefGeneratorService $generator,
        private AuditLogger $audit,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $agentId = $request->header('X-MCP-Agent-Id', 'unknown');
        $forceRefresh = (bool) $request->input('force_refresh', false);

        // Force refresh só pra Wagner + respeitando cap diário
        if ($forceRefresh) {
            $this->guardForceRefresh($agentId);
            $this->generator->generateNow();
        }

        // Cache 5min — 10 agentes na mesma janela hits cache
        $brief = Cache::remember(
            'brief.current',
            now()->addMinutes(5),
            fn () => $this->fetchCurrent()
        );

        if (!$brief) {
            return response()->json([
                'error' => 'no_brief_available',
                'hint' => 'Brief ainda não foi gerado. Aguarde próximo cron ou force_refresh=true (Wagner).',
            ], 503);
        }

        // Audit log
        $this->audit->log([
            'tool' => 'brief-fetch',
            'agent_id' => $agentId,
            'tokens_out' => $brief['token_count'],
            'cache_hit' => !$forceRefresh,
            'staleness_min' => $brief['staleness_minutes'],
        ]);

        // Skill telemetry — quem chamou brief-fetch ativou skill brief-first
        DB::table('mcp_skill_telemetry')->insert([
            'skill_name' => 'brief-first',
            'agent_id' => $agentId,
            'triggered_at' => now(),
            'success' => true,
            'tokens_saved_estimate' => 15000, // estimativa conservadora
        ]);

        return response()->json([
            'content' => $brief['content'],
            'meta' => [
                'generated_at' => $brief['generated_at'],
                'token_count' => $brief['token_count'],
                'staleness_minutes' => $brief['staleness_minutes'],
                'next_refresh_in_min' => $this->minutesToNextCron(),
            ],
        ]);
    }

    private function fetchCurrent(): ?array
    {
        $row = DB::selectOne('SELECT * FROM get_current_brief()');
        if (!$row) return null;

        return [
            'content' => $row->content,
            'token_count' => $row->token_count,
            'generated_at' => $row->generated_at,
            'staleness_minutes' => $row->staleness_minutes,
        ];
    }

    private function guardForceRefresh(string $agentId): void
    {
        if (!str_contains($agentId, 'wagner')) {
            abort(403, 'force_refresh restrito a Wagner');
        }
        $todayCount = DB::table('mcp_briefs')
            ->whereDate('generated_at', today())
            ->count();
        if ($todayCount >= 8) {
            abort(429, 'Cap diário de 8 gerações atingido');
        }
    }

    private function minutesToNextCron(): int
    {
        $hours = [7, 11, 14, 17, 20, 23];
        $now = now();
        foreach ($hours as $h) {
            $candidate = $now->copy()->setTime($h, 0);
            if ($candidate->gt($now)) {
                return (int) $now->diffInMinutes($candidate);
            }
        }
        return (int) $now->diffInMinutes($now->copy()->addDay()->setTime(7, 0));
    }
}
```

---

## Cron — Laravel scheduler

`app/Console/Kernel.php` (ou `routes/console.php` no Laravel 11+):

```php
$schedule->command('brief:generate')
    ->cron('0 7,11,14,17,20,23 * * *')
    ->timezone('America/Sao_Paulo')
    ->withoutOverlapping()
    ->onFailure(function () {
        // Mantém brief anterior, alerta no MCP inbox
        DB::table('mcp_inbox')->insert([
            'channel'    => 'ops',
            'severity'   => 'critical',
            'message'    => '🚨 Brief gerador falhou — usando snapshot anterior',
            'created_at' => now(),
        ]);
    });
```

`app/Console/Commands/GenerateBriefCommand.php`:

```php
final class GenerateBriefCommand extends Command
{
    protected $signature = 'brief:generate {--dry-run}';

    public function handle(BriefGeneratorService $svc): int
    {
        DB::statement('CALL refresh_brief_inputs_cache()');

        $aggregated = DB::selectOne('SELECT * FROM mcp_brief_inputs_cache WHERE singleton_id = 1');
        $content = $svc->generateFromAggregated($aggregated);

        $validator = new BriefValidator();
        $result = $validator->validate($content);

        if (!$result->isOk()) {
            $this->error("Brief inválido: {$result->reason}");
            DB::table('mcp_briefs')->insert([
                'content' => $content,
                'token_count' => mb_strlen($content) / 4,
                'source_hash' => hash('sha256', json_encode($aggregated)),
                'valid' => false,
                'error_msg' => $result->reason,
            ]);
            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->info($content);
            return self::SUCCESS;
        }

        DB::table('mcp_briefs')->insert([
            'content' => $content,
            'token_count' => $result->tokenCount,
            'source_hash' => hash('sha256', json_encode($aggregated)),
            'cost_usd' => $svc->lastCallCost(),
            'valid' => true,
        ]);

        Cache::forget('brief.current');
        $this->info("Brief gerado: {$result->tokenCount} tokens · \${$svc->lastCallCost()}");
        return self::SUCCESS;
    }
}
```

---

## Rota MCP

`routes/api.php`:

```php
Route::middleware(['mcp.auth', 'throttle:60,1'])
    ->prefix('mcp')
    ->group(function () {
        Route::post('/tools/brief-fetch', BriefFetchController::class);
    });
```

---

## Teste manual (curl)

```bash
# Como agent regular
curl -X POST https://mcp.oimpresso.com/api/mcp/tools/brief-fetch \
  -H "Authorization: Bearer $MCP_TOKEN" \
  -H "X-MCP-Agent-Id: claude-felipe-laptop" \
  -H "Content-Type: application/json" \
  -d '{}'

# Wagner forçando refresh
curl -X POST https://mcp.oimpresso.com/api/mcp/tools/brief-fetch \
  -H "Authorization: Bearer $WAGNER_MCP_TOKEN" \
  -H "X-MCP-Agent-Id: wagner-claude-desktop" \
  -d '{"force_refresh": true}'
```

---

## Critério de aceite (definição de pronto)

- [ ] Tool registrada e visível em `mcp__oimpresso__brief-fetch`
- [ ] Retorna 200 com `content` + `meta` em <300ms (cache hit)
- [ ] Cache de 5min funciona (10 chamadas seguidas → 1 query SQL)
- [ ] `force_refresh=true` funciona pra Wagner, 403 pra outros agents
- [ ] Audit log grava cada call em `mcp_audit_log`
- [ ] Skill telemetry grava `brief-first` trigger em cada call
- [ ] Cron 7h/11h/14h/17h/20h/23h roda sem overlap
- [ ] Brief inválido → mantém anterior + alerta no MCP inbox channel `ops`
- [ ] Dry-run funciona pra Wagner debugar prompt
