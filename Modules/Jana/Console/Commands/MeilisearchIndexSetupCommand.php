<?php

declare(strict_types=1);

namespace Modules\Jana\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * jana:meilisearch-setup — CODIFICA (config-as-code) os embedders + filterableAttributes
 * dos índices Meilisearch da Jana. Idempotente.
 *
 * Origem (2026-05-29): o embedder do `jana_memoria_facts` foi setado MANUAL via curl no
 * Sprint 9b e SE PERDEU (índice voltou a embedders `{}` → recall semântico do chat
 * degradou; memoria-search hybrid impossível). Sem este comando, qualquer reindex/recreate
 * do índice perde a config de novo. Agora vive no git (config copiloto.meilisearch_indexes).
 *
 * PATCH /indexes/{uid}/settings aplica embedders + filterableAttributes; o Meilisearch
 * re-embeda os docs existentes ao mudar o embedder (task async). Rodar `scout:import`
 * depois garante o reindex completo se necessário.
 *
 * Uso:
 *   php artisan jana:meilisearch-setup                 # todos os índices
 *   php artisan jana:meilisearch-setup --index=jana_memoria_facts
 *   php artisan jana:meilisearch-setup --dry-run       # mostra payload sem aplicar
 */
class MeilisearchIndexSetupCommand extends Command
{
    protected $signature = 'jana:meilisearch-setup {--index=all : uid do índice ou "all"} {--dry-run : mostra sem aplicar} {--check : SÓ compara settings vivos × config, exit 1 se drift (SettingsReconciler)}';

    protected $description = 'Codifica/aplica/reconcilia embedders + filterableAttributes dos índices Meilisearch da Jana (idempotente)';

    public function handle(): int
    {
        $host = rtrim((string) config('scout.meilisearch.host', 'http://localhost:7700'), '/');
        $key  = (string) config('scout.meilisearch.key', '');
        $alvo = (string) $this->option('index');
        $dry  = (bool) $this->option('dry-run');

        /** @var array<string, array<string, mixed>> $indexes */
        $indexes = (array) config('copiloto.meilisearch_indexes', []);

        if ($indexes === []) {
            $this->error('config copiloto.meilisearch_indexes vazia.');

            return self::FAILURE;
        }

        // SettingsReconciler (gap "embedder perdido 2×"): compara settings VIVOS × config
        // desejada e FALHA se driftou. CI-friendly (gate de PR) + cron (alerta). NÃO cura.
        if ($this->option('check')) {
            return $this->reconcileCheck($host, $key, $alvo, $indexes);
        }

        $this->info("Meilisearch index setup → {$host}".($dry ? ' [DRY-RUN]' : ''));

        foreach ($indexes as $uid => $cfg) {
            if ($alvo !== 'all' && $alvo !== $uid) {
                continue;
            }

            $payload   = $this->payloadPara($cfg);
            $embedders = implode(', ', array_keys($cfg['embedders'] ?? []));
            $filter    = implode(', ', $cfg['filterableAttributes'] ?? []);
            $this->line("→ {$uid}");
            $this->line("    embedders: {$embedders}");
            $this->line("    filterable: {$filter}");

            if ($dry) {
                continue;
            }

            $resp = Http::withToken($key)
                ->timeout(30)
                ->patch("{$host}/indexes/{$uid}/settings", $payload);

            if ($resp->failed()) {
                $this->error("    PATCH falhou ({$resp->status()}): ".mb_substr($resp->body(), 0, 300));

                return self::FAILURE;
            }

            $this->info('    ✓ aplicado (taskUid '.data_get($resp->json(), 'taskUid', '?').')');
        }

        if (! $dry) {
            $this->newLine();
            $this->line('Embedder mudou → Meilisearch re-embeda os docs (task async).');
            $this->line('Se precisar forçar reindex completo:');
            $this->line('  php artisan scout:import "Modules\\\\Jana\\\\Entities\\\\MemoriaFato"');
            $this->line('  php artisan scout:import "Modules\\\\Jana\\\\Entities\\\\Mcp\\\\McpMemoryDocument"');
        }

        return self::SUCCESS;
    }

    /**
     * Monta o payload PATCH /settings a partir da config de um índice.
     *
     * @param  array<string, mixed> $cfg
     * @return array<string, mixed>
     */
    public function payloadPara(array $cfg): array
    {
        $payload = [];

        if (! empty($cfg['embedders'])) {
            $payload['embedders'] = $cfg['embedders'];
        }
        if (! empty($cfg['filterableAttributes'])) {
            $payload['filterableAttributes'] = $cfg['filterableAttributes'];
        }

        return $payload;
    }

    /**
     * SettingsReconciler — GET settings vivos de cada índice, compara com a config
     * desejada, reporta drift. exit 1 se qualquer drift (gate). Alerta idempotente.
     *
     * @param  array<string, array<string, mixed>> $indexes
     */
    protected function reconcileCheck(string $host, string $key, string $alvo, array $indexes): int
    {
        $this->info("SettingsReconciler --check → {$host}");
        $totalDrift = [];

        foreach ($indexes as $uid => $cfg) {
            if ($alvo !== 'all' && $alvo !== $uid) {
                continue;
            }

            $resp = Http::withToken($key)->timeout(30)->get("{$host}/indexes/{$uid}/settings");
            if ($resp->failed()) {
                $this->error("  {$uid}: GET settings falhou ({$resp->status()})");

                return self::FAILURE;
            }

            $drift = $this->detectarDrift($uid, $cfg, (array) $resp->json());
            if ($drift === []) {
                $this->line("  ✓ {$uid}: em dia");
            } else {
                foreach ($drift as $d) {
                    $this->error("  ✗ {$d}");
                }
                $totalDrift = array_merge($totalDrift, $drift);
            }
        }

        if ($totalDrift !== []) {
            // Alerta idempotente por dia (mesmo padrão do freshness-check).
            try {
                \Illuminate\Support\Facades\DB::table('mcp_alertas_eventos')->updateOrInsert(
                    ['chave_idempotencia' => 'index_settings_drift:'.now()->toDateString()],
                    [
                        'tipo'       => 'index_settings_drift',
                        'severidade' => 'high',
                        'business_id' => null,
                        'metadata'   => json_encode(['drift' => $totalDrift]),
                        'updated_at' => now(),
                        'created_at' => now(),
                    ],
                );
            } catch (\Throwable $e) {
                $this->warn('  (alerta não persistido: '.$e->getMessage().')');
            }

            $this->newLine();
            $this->error('DRIFT detectado — rode `php artisan jana:meilisearch-setup` pra curar.');

            return self::FAILURE;
        }

        $this->info('Settings de todos os índices == config. Nenhum drift.');

        return self::SUCCESS;
    }

    /**
     * Compara a config desejada de UM índice com os settings vivos. Pura/testável.
     * Drift = embedder esperado ausente, source/model/dimensions divergente, ou
     * filterableAttributes diferente (como conjunto).
     *
     * @param  array<string, mixed> $cfg   config desejada (embedders + filterableAttributes)
     * @param  array<string, mixed> $vivo  settings vivos do Meilisearch
     * @return string[] lista de drifts (vazia = em dia)
     */
    public function detectarDrift(string $uid, array $cfg, array $vivo): array
    {
        $drift = [];

        /** @var array<string, array<string, mixed>> $expEmb */
        $expEmb = (array) ($cfg['embedders'] ?? []);
        /** @var array<string, array<string, mixed>> $vivoEmb */
        $vivoEmb = (array) ($vivo['embedders'] ?? []);

        foreach ($expEmb as $nome => $espec) {
            if (! isset($vivoEmb[$nome])) {
                $drift[] = "{$uid}: embedder '{$nome}' AUSENTE no índice (esperado na config)";

                continue;
            }
            foreach (['source', 'model', 'dimensions'] as $campo) {
                if (! array_key_exists($campo, (array) $espec)) {
                    continue;
                }
                $vivoVal = $vivoEmb[$nome][$campo] ?? null;
                if ($vivoVal != $espec[$campo]) {
                    $drift[] = "{$uid}: embedder '{$nome}'.{$campo} difere (config=".json_encode($espec[$campo]).' vivo='.json_encode($vivoVal).')';
                }
            }
        }

        $expFilt  = (array) ($cfg['filterableAttributes'] ?? []);
        $vivoFilt = (array) ($vivo['filterableAttributes'] ?? []);
        sort($expFilt);
        sort($vivoFilt);
        if ($expFilt !== $vivoFilt) {
            $drift[] = "{$uid}: filterableAttributes difere (config=[".implode(',', $expFilt).'] vivo=['.implode(',', $vivoFilt).'])';
        }

        return $drift;
    }
}
