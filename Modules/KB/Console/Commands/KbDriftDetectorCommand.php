<?php

declare(strict_types=1);

namespace Modules\KB\Console\Commands;

use App\Util\OtelHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * kb:drift-detector — Wave 23 KB §G4 — drift artigos KB vs git log.
 *
 * Cron compara `kb_articles.updated_at` (kb_nodes onde type='article' OR is_editable=true)
 * vs `git log --since=30d` paths citados em body_blocks/sourceDoc.
 *
 * Alert se artigo cita arquivo deletado/movido no git nos últimos 30 dias.
 *
 * Tier 0 multi-tenant: roda por business (--business-id obrigatório) —
 * commands CLI fora de HTTP, session() null.
 *
 * Uso:
 *   php artisan kb:drift-detector --business-id=1
 *   php artisan kb:drift-detector --business-id=1 --since=7d
 *   php artisan kb:drift-detector --business-id=1 --detail
 *   php artisan kb:drift-detector --business-id=1 --mock  # CI safe (sem git real)
 *
 * Exit code:
 *   0 = nenhum drift detectado
 *   1 = drift detectado (artigos referem paths deletados/movidos)
 *
 * Tier 0 multi-tenant: --business-id obrigatório (ADR 0093 §"Commands & Jobs sem HTTP context").
 *
 * @see Modules/KB/Services/KbRagService.php
 * @see Wave 22 FICHA KB §G4 Drift detector artigo KB vs git log
 */
class KbDriftDetectorCommand extends Command
{
    protected $signature = 'kb:drift-detector
                            {--business-id= : Business ID (obrigatório — Tier 0 ADR 0093)}
                            {--since=30d : Janela git log (ex: 7d, 14d, 30d)}
                            {--detail : Log detalhado por artigo}
                            {--mock : Skipa git real, usa paths injetados (CI safe)}';

    protected $description = 'Drift detector KB — alerta se artigos citam arquivos deletados/movidos no git recentemente.';

    public function handle(): int
    {
        $bizId = (int) $this->option('business-id');
        if ($bizId <= 0) {
            $this->error('--business-id obrigatório (multi-tenant Tier 0 ADR 0093).');

            return self::FAILURE;
        }
        if ($bizId === 4) {
            $this->error('biz=4 (ROTA LIVRE prod) NUNCA em scripts (ADR 0101). Use biz=1.');

            return self::FAILURE;
        }

        return OtelHelper::span('kb.drift_detector', [
            'module' => 'KB',
            'business_id' => $bizId,
        ], function () use ($bizId) {
            return $this->doHandle($bizId);
        });
    }

    private function doHandle(int $bizId): int
    {
        $since = (string) $this->option('since');
        $mock = (bool) $this->option('mock');
        $detail = (bool) $this->option('detail');

        // 1) Lista paths deletados/movidos no git (last $since)
        $deletedPaths = $mock
            ? $this->mockDeletedPaths()
            : $this->gitDeletedPaths($since);

        if (empty($deletedPaths)) {
            $this->info("Nenhum path deletado/movido no git nos últimos {$since}.");

            return self::SUCCESS;
        }

        // 2) Busca artigos KB do business que referenciam esses paths
        $drifts = $this->findArticleReferences($bizId, $deletedPaths);

        if ($detail) {
            $rows = [];
            foreach ($drifts as $d) {
                $rows[] = [
                    'node_id' => $d['node_id'],
                    'slug' => $d['slug'],
                    'path_deletado' => mb_substr($d['path'], 0, 60),
                    'tipo_drift' => $d['drift_type'],
                ];
            }
            $this->table(['Node', 'Slug', 'Path', 'Type'], $rows);
        }

        $report = [
            'ran_at' => now()->toIso8601String(),
            'business_id' => $bizId,
            'since' => $since,
            'deleted_paths_count' => count($deletedPaths),
            'drifts_detected' => count($drifts),
            'mock_mode' => $mock,
        ];

        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        if (! empty($drifts)) {
            Log::channel('copiloto-ai')->warning('[KB drift-detector] ALERT artigos citam paths deletados', $report + [
                'drifts' => $drifts,
            ]);
            $this->error('DRIFT: '.count($drifts).' artigos referenciam paths deletados no git');

            return self::FAILURE;
        }

        Log::channel('copiloto-ai')->info('[KB drift-detector] OK', $report);
        $this->info('OK: nenhum drift detectado');

        return self::SUCCESS;
    }

    /**
     * Lista paths deletados/movidos via `git log --diff-filter=DR --since=<since> --name-only`.
     *
     * @return array<int,string>
     */
    private function gitDeletedPaths(string $since): array
    {
        $process = new Process(
            ['git', 'log', '--diff-filter=DR', "--since={$since}", '--name-only', '--pretty=format:'],
            base_path()
        );
        $process->setTimeout(30);

        try {
            $process->run();
        } catch (\Throwable $e) {
            Log::channel('copiloto-ai')->warning('[KB drift-detector] git log falhou', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        if (! $process->isSuccessful()) {
            return [];
        }

        $lines = array_filter(array_map('trim', explode("\n", $process->getOutput())));

        return array_values(array_unique($lines));
    }

    /**
     * @return array<int,string>
     */
    private function mockDeletedPaths(): array
    {
        // Pra testes — paths conhecidos que NÃO existem mais.
        return [
            'memory/decisions/0000-deleted-test.md',
            'Modules/Removed/Service.php',
        ];
    }

    /**
     * Procura kb_nodes (article ou is_editable) cujo body_blocks/snippet menciona
     * qualquer path da lista deletada. Match simples por substring (defense in depth).
     *
     * Efeito colateral (Fase A1): persiste o veredito por nó em
     * `kb_nodes.code_drift_state` pra surfacar na KB (HealthPanel/NodeReader):
     *   - com match  → {checked_at, refs:[{path, drift_type}]}
     *   - sem match  → NULL (limpa flag anterior — self-healing quando o doc é corrigido)
     * A escrita é raw (DB::table), só quando o CONJUNTO de paths muda, e fica fora
     * do KbNodeObserver + activity-log (sem ruído de audit por cron).
     *
     * @param  array<int,string>  $deletedPaths
     * @return array<int,array<string,mixed>>
     */
    private function findArticleReferences(int $bizId, array $deletedPaths): array
    {
        // Schema KB pode não estar bootstrapped em CI puro — graceful skip
        if (! \Illuminate\Support\Facades\Schema::hasTable('kb_nodes')) {
            return [];
        }

        $canPersist = \Illuminate\Support\Facades\Schema::hasColumn('kb_nodes', 'code_drift_state');

        $candidates = DB::table('kb_nodes')
            ->where('business_id', $bizId)
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->where('type', 'article')
                  ->orWhere('is_editable', 1)
                  ->orWhere('is_editable', true);
            })
            ->select('id', 'slug', 'title', 'body_blocks', 'code_drift_state')
            ->limit(5000)
            ->get();

        $now = now()->toIso8601String();
        $drifts = [];

        foreach ($candidates as $node) {
            $body = (string) ($node->body_blocks ?? '');

            // Todos os paths deletados que este nó referencia (não só o 1º).
            $matched = [];
            foreach ($deletedPaths as $path) {
                if ($path === '') {
                    continue;
                }
                // JSON escape converte '/' em '\/'. Cobrimos ambas variantes.
                $pathEscaped = str_replace('/', '\/', $path);
                if (str_contains($body, $path) || str_contains($body, $pathEscaped)) {
                    $matched[] = ['path' => $path, 'drift_type' => 'reference_deleted_path'];
                }
            }

            if ($canPersist) {
                $this->persistDriftState($node, $matched, $now);
            }

            if (! empty($matched)) {
                // Report/exit-code preservam a forma legada: 1 linha por nó.
                $drifts[] = [
                    'node_id' => (int) $node->id,
                    'slug' => (string) $node->slug,
                    'title' => (string) $node->title,
                    'path' => $matched[0]['path'],
                    'drift_type' => 'reference_deleted_path',
                ];
            }
        }

        return $drifts;
    }

    /**
     * Grava o veredito de drift do nó só quando o CONJUNTO de paths mudou
     * (evita churn de UPDATE a cada cron). checked_at só refresca na mudança.
     *
     * @param  object  $node  row com id + code_drift_state (json string|null)
     * @param  array<int,array{path:string,drift_type:string}>  $matched
     */
    private function persistDriftState(object $node, array $matched, string $now): void
    {
        // Paths atualmente persistidos.
        $currentPaths = [];
        if (! empty($node->code_drift_state)) {
            $decoded = json_decode((string) $node->code_drift_state, true);
            foreach ((array) ($decoded['refs'] ?? []) as $ref) {
                if (isset($ref['path'])) {
                    $currentPaths[] = (string) $ref['path'];
                }
            }
        }

        $newPaths = array_map(static fn ($m) => $m['path'], $matched);

        $a = $currentPaths;
        $b = $newPaths;
        sort($a);
        sort($b);
        if ($a === $b) {
            return; // conjunto idêntico — nada a fazer
        }

        DB::table('kb_nodes')
            ->where('id', $node->id)
            ->update([
                'code_drift_state' => empty($matched)
                    ? null
                    : json_encode(['checked_at' => $now, 'refs' => $matched], JSON_UNESCAPED_UNICODE),
            ]);
    }
}
