---
title: "RUNBOOK — Pattern artisan command oimpresso (multi-tenant + LGPD + DRY-RUN)"
owner: W
status: ativo
last_validated: "2026-06-08"
---

# RUNBOOK — Pattern artisan command oimpresso (multi-tenant + LGPD + DRY-RUN)

> **Use sempre que criar command artisan novo em qualquer `Modules/<X>/Console/Commands/`.**
>
> Validado em 9 commands entregues na sessão 2026-05-10 (7 em Modules/Arquivos + 1 ComunicacaoVisual + 1 Vestuario).

## Origem

Sessão 2026-05-10 entregou 9 commands seguindo o mesmo pattern. Esta consolidação previne drift e padroniza criação de novos commands.

## Princípios duros (todo command oimpresso respeita)

1. **Multi-tenant Tier 0** ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)) — CLI sem session web, então `--business=N` é flag explícito (default: admin global view com warning visível)
2. **DRY-RUN obrigatório pra ações destructivas** — qualquer command que muda estado (write/delete) precisa `--dry-run` flag
3. **Idempotência** — rodar 2x não corrompe (filter pra excluir já-processados)
4. **Audit log preservation** — ações que tocam arquivos/dados de negócio inserem em audit_log com payload `business_id` original
5. **PT-BR mensagens** — tudo em PT-BR (Wagner+Eliana usuários reais), código em inglês ok
6. **Cap interno** — todas queries têm cap (ex: 1000 rows) pra proteger DB em prod
7. **Schema check graceful** — `Schema::hasTable` no início; se ausente, exit 1 com msg clara em vez de crash

## Template canônico

```php
<?php

namespace Modules\<X>\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * <modulo>:<ação> — <descrição curta> ADR <NNNN>.
 *
 * <Contexto: quando rodar, por que existe, qual gap fecha>
 *
 * Uso:
 *   php artisan <modulo>:<ação>
 *     --business=N          (filtro multi-tenant; ausente = admin global)
 *     --limit=500           (cap rows)
 *     --dry-run             (não escreve, só simula)
 *
 * @see memory/decisions/<NNNN>-<slug>.md
 */
class <NomeAcao>Command extends Command
{
    protected $signature = '<modulo>:<acao>
        {--business= : Filtra por business_id (default: todos — admin operação)}
        {--limit=500 : Cap rows processadas}
        {--dry-run : Não escreve no DB, só loga o que faria}';

    protected $description = '<Descrição clara em PT-BR — o que command faz e quando rodar>.';

    public function handle(): int
    {
        // 1. Validar tabelas existem
        if (! Schema::hasTable('<tabela_alvo>')) {
            $this->error('<tabela_alvo> table missing — rode Modules/<X> migrate primeiro.');
            return 1;
        }

        // 2. Resolver opções com defaults
        $businessId = $this->option('business') ? (int) $this->option('business') : null;
        $limit = (int) $this->option('limit');
        $dryRun = (bool) $this->option('dry-run');

        // 3. Header com contexto + warning admin global se aplicável
        $this->info("🔍 <Header descritivo> — " . now()->toDateTimeString());
        if ($businessId === null) {
            $this->warn('   MODO ADMIN — todos businesses (sem --business filter)');
        } else {
            $this->line("   Filtro: business_id={$businessId}");
        }

        // 4. Query base com filtro multi-tenant + cap
        $query = DB::table('<tabela_alvo>')
            ->whereNull('deleted_at')  // ou outro filter primário
            ->limit(min($limit, 1000)); // cap interno duro

        if ($businessId !== null) {
            $query->where('business_id', $businessId);
        }

        $total = (clone $query)->count();
        $this->info("   Encontradas {$total} rows" . ($dryRun ? ' [DRY-RUN]' : ''));

        if ($total === 0) {
            $this->info('   Nada pra processar.');
            return 0;
        }

        // 5. Warning visível antes de ação destructiva (sem --dry-run)
        if (! $dryRun) {
            $this->warn("⚠️  <DESCRIÇÃO DESTRUCTIVA — N rows serão modificadas/deletadas>");
        }

        // 6. Stats accumulator
        $stats = ['processed' => 0, 'skipped' => 0, 'errored' => 0];

        // 7. Chunk processing pra streaming-friendly + log periódico
        $query->orderBy('id')
            ->chunk(100, function ($rows) use ($dryRun, &$stats) {
                foreach ($rows as $row) {
                    try {
                        if ($dryRun) {
                            $this->line("  [dry] arquivo:{$row->id} biz:{$row->business_id} → would <ação>");
                            $stats['processed']++;
                            continue;
                        }

                        // Ação real: <write/delete/update>
                        // ...

                        // Audit log obrigatório se toca dados de negócio
                        DB::table('<modulo>_audit_log')->insert([
                            'business_id' => $row->business_id,
                            'action' => '<acao>',
                            'payload' => json_encode([/* metadata */]),
                            'created_at' => now(),
                        ]);

                        $stats['processed']++;
                    } catch (\Throwable $e) {
                        $stats['errored']++;
                        Log::warning('<modulo>.<acao>.error', [
                            'row_id' => $row->id ?? null,
                            'business_id' => $row->business_id ?? null,
                            'error' => substr($e->getMessage(), 0, 200),
                        ]);
                    }
                }

                // Log batch
                Log::info('<modulo>.<acao>.batch', [
                    'processed' => $stats['processed'],
                    'errored' => $stats['errored'],
                ]);
            });

        // 8. Footer com summary + return code
        $this->newLine();
        $this->info("   Processados: {$stats['processed']}");
        if ($stats['skipped'] > 0) $this->warn("   Skipados:    {$stats['skipped']}");
        if ($stats['errored'] > 0) $this->error("   Errored:     {$stats['errored']}");

        // Exit code: 0=OK, 2=majority errored, 1=other failure
        return $stats['errored'] > $total / 2 ? 2 : 0;
    }
}
```

## Convenção de nomes

| Pattern | Exemplo |
|---------|---------|
| `<modulo>:<acao-substantivada>` | `arquivos:health-check`, `comvis:demo-seed`, `vestuario:settings` |
| Action subcommand opcional | `vestuario:settings list/get/set` |
| Sempre kebab-case | `arquivos:export-zip`, NÃO `arquivos:exportZip` |

## Flags padrão (todo command tem)

| Flag | Tipo | Default | Quando usar |
|------|------|---------|-------------|
| `--business=` | int? | null (admin global) | Multi-tenant filter — sempre presente exceto commands global-only (ex: schedule check) |
| `--limit=` | int | 500-1000 | Cap rows processadas — proteger DB em prod |
| `--dry-run` | bool | false | OBRIGATÓRIO se command muda estado (write/delete/update) |

Flags adicionais conforme necessidade:
- `--tag=` — filtro por classified_by (commands que processam backfilled rows)
- `--days=` — janela temporal (commands com retention/cleanup)
- `--alert` — exit code 2 se health-check falhar (cron + monitoring integration)
- `--json` — output estruturado em vez de tabela (dashboard integration)
- `--clean` — limpa dados anteriores antes de criar (commands de seed/demo)

## Output canônico

### Modo padrão (table)
- Header `🔍 <Descrição> — <timestamp>`
- Sub-header com filtro aplicado ou warning admin global
- `$this->table` ou `$this->line` per-row
- Footer summary com counters

### Modo `--json`
JSON estruturado com schema estável:
```json
{
  "command": "arquivos:health-check",
  "timestamp": "2026-05-10T12:50:00Z",
  "business_filter": 1,
  "summary": {"ok": 4, "warn": 1, "fail": 0, "total": 5},
  "details": [...]
}
```

### Modo `--dry-run`
Cada row print prefix `[dry]` + descrição da ação que seria feita. Sem audit log + sem write.

## Exit codes

| Code | Significado |
|------|-------------|
| 0 | Sucesso (zero rows OK também) |
| 1 | Validação pré-execução falhou (tabela ausente, flag ausente, etc) |
| 2 | Maioria dos rows processados deu erro (`errored > total/2`) — ou `--alert` flag e algum FAIL detectado |

## Registro no ServiceProvider

```php
// Modules/<X>/Providers/<X>ServiceProvider.php

public function boot(): void
{
    $this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');
    $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');

    if ($this->app->runningInConsole()) {
        $this->commands([
            \Modules\<X>\Console\Commands\<NomeAcao>Command::class,
            // ... mais commands
        ]);
    }
}
```

## Pest tests obrigatórios

`Modules/<X>/Tests/Feature/<NomeAcao>CommandTest.php` (mín 5 cenários):

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatible: requer schema MySQL UltimatePOS');
    }
    if (! Schema::hasTable('<tabela_alvo>')) {
        $this->markTestSkipped('<tabela_alvo> missing — rode migrate primeiro');
    }
});

it('command está registrado em artisan list', function () {
    $commands = Artisan::all();
    expect($commands)->toHaveKey('<modulo>:<acao>');
});

it('--business ausente → exit 1 + msg PT-BR clara', function () {
    $exitCode = Artisan::call('<modulo>:<acao>'); // sem flags
    expect($exitCode)->toBe(1);
    expect(Artisan::output())->toContain('--business é obrigatório');
});

it('--dry-run não modifica DB', function () {
    // Setup row de teste
    $id = DB::table('<tabela>')->insertGetId([/* ... */]);

    $exitCode = Artisan::call('<modulo>:<acao>', ['--business' => 1, '--dry-run' => true]);

    $row = DB::table('<tabela>')->where('id', $id)->first();
    // Assert row não foi modificada
    expect($row-><campo>)->toBe(/* valor original */);

    DB::table('<tabela>')->where('id', $id)->delete();
});

it('idempotente — rodar 2x não muda já-processados', function () {
    // Setup + run 1x
    Artisan::call('<modulo>:<acao>', ['--business' => 1]);
    $afterFirst = DB::table('<tabela>')->where('id', $id)->first();

    // Run 2x
    Artisan::call('<modulo>:<acao>', ['--business' => 1]);
    $afterSecond = DB::table('<tabela>')->where('id', $id)->first();

    expect($afterSecond-><campo>)->toBe($afterFirst-><campo>);
});

it('multi-tenant Tier 0 — --business=1 não toca biz=99', function () {
    $idBiz1 = DB::table('<tabela>')->insertGetId(['business_id' => 1, ...]);
    $idBiz99 = DB::table('<tabela>')->insertGetId(['business_id' => 99, ...]);

    Artisan::call('<modulo>:<acao>', ['--business' => 1]);

    $rowBiz99 = DB::table('<tabela>')->where('id', $idBiz99)->first();
    // Assert biz=99 row preservada
    expect($rowBiz99-><campo>)->toBe(/* valor original */);
});
```

Padrão Pest:
- `uses(Tests\TestCase::class);` SEM `->in(__DIR__)` (PR #393 fix)
- biz=1 (Wagner WR2) — nunca biz=4 (ROTA LIVRE — ADR 0101)
- Cleanup em `afterEach` ou no fim de cada test

## Anti-padrões catalogados

| ❌ Anti-padrão | ✅ Correto |
|---------------|-----------|
| Command sem `--dry-run` mexe direto em prod | DRY-RUN flag obrigatório pra ações destructivas |
| `session()` em command CLI | `--business=N` flag explícito |
| Sem cap interno → query 1M rows derruba DB | `LIMIT min($flag, 1000)` |
| Sem audit log → ação invisível pra LGPD | Insert em `<modulo>_audit_log` por row processado |
| Throw exception generic → output truncado em prod | try/catch + `Log::warning` + accumulator stats |
| Mensagens em inglês ou hardcoded UTF-8 quebrado | PT-BR consistente, encoding UTF-8 limpo |
| Sem registro no ServiceProvider | `$this->commands([...])` no `boot()` |
| Sem Pest test (matrix CI passa green sem cobertura real) | mín 5 cenários (registered, validação, dry-run, idempotência, multi-tenant) |

## Histórico de uso (sessão 2026-05-10)

| Command | PR | Linhas | Pest tests |
|---------|-----|--------|-----------|
| `arquivos:recalcular-metadata` | [#407](https://github.com/wagnerra23/oimpresso.com/pull/407) | ~135 | 6 |
| `arquivos:dedupe-stats` | [#413](https://github.com/wagnerra23/oimpresso.com/pull/413) | ~120 | 4 |
| `arquivos:reencrypt-vault` | [#415](https://github.com/wagnerra23/oimpresso.com/pull/415) | ~180 | 8 |
| `arquivos:audit-log` | [#420](https://github.com/wagnerra23/oimpresso.com/pull/420) | ~370 | 7 |
| `arquivos:retention-cleanup` | [#429](https://github.com/wagnerra23/oimpresso.com/pull/429) | ~240 | 8 |
| `arquivos:health-check` | [#450](https://github.com/wagnerra23/oimpresso.com/pull/450) | ~265 | 7 |
| `arquivos:export-zip` | [#481](https://github.com/wagnerra23/oimpresso.com/pull/481) | ~280 | 7 |
| `comvis:demo-seed` | [#458](https://github.com/wagnerra23/oimpresso.com/pull/458) | ~440 | 6 |
| `vestuario:settings` | [#419](https://github.com/wagnerra23/oimpresso.com/pull/419) | ~228 | 11 |

Total: 9 commands, ~64 Pest tests, 0 reverts.

---

**Owner:** Felipe (sprint dev), Wagner (governança)
**Última atualização:** 2026-05-10 — origem sessão massiva 30+ PRs
**Refs:** ADR 0093 (multi-tenant Tier 0), ADR 0061 (zero auto-mem privada), [RUNBOOK-validacao-pos-deploy.md](RUNBOOK-validacao-pos-deploy.md), [RUNBOOK-ingestao-documentos.md](../Arquivos/RUNBOOK-ingestao-documentos.md)
