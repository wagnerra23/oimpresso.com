<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * jobs:purge-represados — purga backlog de filas SEM worker (audit 2026-07-02).
 *
 * Contexto: QUEUE_CONNECTION=database no Hostinger, mas o Kernel só agenda
 * `queue:work` pras filas `whatsapp` e `whatsapp-history`. Todas as outras
 * filas acumularam ~48k jobs órfãos desde 2026-05-14 (DownloadMediaJob legado,
 * Rebuild* diários empilhados, listeners NFC-e, links WhatsApp stale, etc).
 *
 * Rodar o backlog antigo é PIOR que descartar: syncs bancários ×1057 (rate
 * limit), links de aprovação WhatsApp de semanas atrás (outreach stale pra
 * cliente real), NFC-e retroativa. Jobs recorrentes (Rebuild/Sync/Reindex)
 * são idempotentes "latest-wins" — o próximo cron re-dispatcha fresco.
 *
 * REGRA MESTRE (memory/proibicoes.md): dry-run é o DEFAULT. DELETE só com
 * --execute explícito, após Wagner aprovar a tabela de impacto.
 *
 * Uso:
 *   php artisan jobs:purge-represados                          # dry-run, todas as filas órfãs
 *   php artisan jobs:purge-represados --queue=nfe              # dry-run, só nfe
 *   php artisan jobs:purge-represados --before=2026-07-01      # cutoff custom
 *   php artisan jobs:purge-represados --execute                # DELETE real (Wagner aprovou)
 *
 * Filas `whatsapp` e `whatsapp-history` são SEMPRE recusadas — têm worker
 * ativo e cleanup próprio (`whatsapp:jobs-cleanup-stale`).
 *
 * @see app/Console/Kernel.php (worker backlog gated por QUEUE_BACKLOG_WORKER_ENABLED)
 * @see Modules/Whatsapp/Console/Commands/CleanupStaleJobsCommand.php (pattern)
 */
class PurgeJobsRepresadosCommand extends Command
{
    /** Filas órfãs catalogadas no audit 2026-07-02 (sem worker no Kernel). */
    private const FILAS_ORFAS_DEFAULT = [
        'default',
        'customer-memory',
        'employee-performance',
        'jana-index',
        'copiloto-memoria',
        'nfe',
    ];

    /** Filas com worker vivo — purga aqui é proibida (use whatsapp:jobs-cleanup-stale). */
    private const FILAS_PROTEGIDAS = ['whatsapp', 'whatsapp-history'];

    protected $signature = 'jobs:purge-represados
                            {--queue=* : Fila(s) alvo (default: todas as filas órfãs catalogadas)}
                            {--before= : Só jobs criados antes desta data Y-m-d (default: agora, ou seja, todo o backlog)}
                            {--execute : Executa o DELETE. Sem esta flag é dry-run (REGRA MESTRE)}';

    protected $description = 'Purga (dry-run por default) jobs represados de filas sem worker. DELETE exige --execute.';

    public function handle(): int
    {
        $queues = (array) $this->option('queue');
        if ($queues === []) {
            $queues = self::FILAS_ORFAS_DEFAULT;
        }

        $protegidas = array_intersect($queues, self::FILAS_PROTEGIDAS);
        if ($protegidas !== []) {
            $this->error(sprintf(
                'Filas protegidas (worker ativo): %s. Use whatsapp:jobs-cleanup-stale.',
                implode(', ', $protegidas),
            ));

            return self::FAILURE;
        }

        $before = $this->option('before');
        $cutoffUnix = $before !== null
            ? \Illuminate\Support\Carbon::parse((string) $before)->timestamp
            : now()->timestamp;

        $execute = (bool) $this->option('execute');

        // Tabela de impacto antes→depois por fila + classe (REGRA MESTRE #2).
        // Agrupamento em PHP (não JSON_EXTRACT SQL) pra ser DB-agnóstico.
        $grupos = [];
        DB::table('jobs')
            ->whereIn('queue', $queues)
            ->where('created_at', '<', $cutoffUnix)
            ->select('id', 'queue', 'payload')
            ->orderBy('id')
            ->chunk(500, function ($chunk) use (&$grupos) {
                foreach ($chunk as $j) {
                    $classe = json_decode($j->payload, true)['displayName'] ?? '(payload ilegível)';
                    $chave = $j->queue.'|'.$classe;
                    $grupos[$chave] = ($grupos[$chave] ?? 0) + 1;
                }
            });
        arsort($grupos);

        $total = array_sum($grupos);

        $this->table(
            ['Fila', 'Job', 'Jobs a deletar'],
            array_map(
                fn (string $chave, int $n) => [...explode('|', $chave, 2), $n],
                array_keys($grupos),
                array_values($grupos),
            ),
        );
        $this->info(sprintf(
            '%s — %d jobs em %d fila(s), cutoff created_at < %s',
            $execute ? 'EXECUTE' : 'DRY-RUN (nada deletado)',
            $total,
            count($queues),
            date('Y-m-d H:i:s', $cutoffUnix),
        ));

        if (! $execute) {
            $this->comment('Pra deletar de verdade: repita com --execute (após aprovação Wagner — REGRA MESTRE).');

            return self::SUCCESS;
        }

        $deleted = DB::table('jobs')
            ->whereIn('queue', $queues)
            ->where('created_at', '<', $cutoffUnix)
            ->delete();

        Log::warning('[jobs.purge-represados] backlog purgado', [
            'queues' => $queues,
            'cutoff_unix' => $cutoffUnix,
            'deleted' => $deleted,
        ]);

        $this->info(sprintf('Deletados %d jobs.', $deleted));

        return self::SUCCESS;
    }
}
