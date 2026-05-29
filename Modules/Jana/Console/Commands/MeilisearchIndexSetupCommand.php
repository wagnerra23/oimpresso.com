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
    protected $signature = 'jana:meilisearch-setup {--index=all : uid do índice ou "all"} {--dry-run : mostra sem aplicar}';

    protected $description = 'Codifica/aplica embedders + filterableAttributes dos índices Meilisearch da Jana (idempotente)';

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
}
