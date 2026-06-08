<?php

namespace Modules\Arquivos\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * arquivos:dedupe-stats — Sprint 2 ADR 0123.
 *
 * Exibe estatísticas de deduplicação da tabela arquivos_dedupe.
 * Útil pra audit: detectar MD5s com muitas tentativas redundantes de upload
 * (ex: 200 uploads do mesmo PDF gerado automaticamente).
 *
 * A tabela arquivos_dedupe é cross-business (sem business_id — ADR 0123 §3
 * side-channel mitigation). O filtro --business faz cross-join com arquivos
 * pra garantir que o MD5 pertença ao business informado.
 *
 * Semântica de occurrences:
 * - occurrences = N → houve N uploads do mesmo conteúdo (mesmo MD5)
 * - Somente 1 arquivo fica em storage (o primeiro upload)
 * - (N-1) uploads foram dedup-skipped → economia = (N-1) × size_bytes
 *
 * Uso:
 *   php artisan arquivos:dedupe-stats
 *   php artisan arquivos:dedupe-stats --top=20
 *   php artisan arquivos:dedupe-stats --min-occurrences=5
 *   php artisan arquivos:dedupe-stats --business=4
 *
 * @see memory/decisions/0123-modules-arquivos-backbone.md Sprint 2
 */
class DedupeStatsCommand extends Command
{
    protected $signature = 'arquivos:dedupe-stats
        {--top=10 : Top N MD5s mais duplicados}
        {--min-occurrences=2 : Threshold mínimo (default 2 = só duplicatas reais)}
        {--business= : Filtrar por business_id (cross-join arquivos table) — opcional}';

    protected $description = 'Exibe estatísticas de deduplicação (arquivos_dedupe) — audit de uploads redundantes.';

    public function handle(): int
    {
        if (! Schema::hasTable('arquivos_dedupe')) {
            $this->error('arquivos_dedupe table missing — rode Modules/Arquivos migrate primeiro.');
            return 1;
        }

        $top            = max(1, (int) $this->option('top'));
        $minOccurrences = max(1, (int) $this->option('min-occurrences'));
        $businessId     = $this->option('business') !== null
            ? (int) $this->option('business')
            : null;

        // Query base com filtro opcional de business_id via EXISTS subquery.
        // arquivos_dedupe não tem business_id (ADR 0123 §3), então filtramos
        // via EXISTS em arquivos — garante que o MD5 pertença ao business.
        $query = DB::table('arquivos_dedupe as d')
            ->select([
                'd.md5',
                'd.occurrences',
                'd.first_seen_at',
                DB::raw('(SELECT a.size_bytes FROM arquivos a WHERE a.md5 = d.md5 LIMIT 1) as size_bytes'),
                DB::raw('(SELECT a.original_name FROM arquivos a WHERE a.md5 = d.md5 LIMIT 1) as original_name'),
            ])
            ->where('d.occurrences', '>=', $minOccurrences)
            ->orderByDesc('d.occurrences')
            ->limit($top);

        if ($businessId !== null) {
            $query->whereExists(function ($sub) use ($businessId) {
                $sub->select(DB::raw(1))
                    ->from('arquivos as a')
                    ->whereColumn('a.md5', 'd.md5')
                    ->where('a.business_id', $businessId);
            });
        }

        $rows = $query->get();

        if ($rows->isEmpty()) {
            $this->info('Nenhum duplicado encontrado com os critérios informados.');
            return 0;
        }

        // Monta tabela de saída.
        $headers = ['MD5 (8)', 'Occurrences', 'Size (KB)', 'Economia (KB)', 'First seen', 'Filename'];
        $tableRows = [];
        $totalEconomiaBytes = 0;

        foreach ($rows as $row) {
            $sizeBytes    = (int) ($row->size_bytes ?? 0);
            $economia     = $sizeBytes * max(0, (int) $row->occurrences - 1);
            $sizeKb       = $sizeBytes > 0 ? number_format($sizeBytes / 1024, 1) : '–';
            $economiaKb   = $economia  > 0 ? number_format($economia  / 1024, 1) : '0';
            $filename     = $row->original_name ?? '(sem registro em arquivos)';
            $firstSeen    = $row->first_seen_at ?? '–';

            $tableRows[] = [
                substr((string) $row->md5, 0, 8),
                (int) $row->occurrences,
                $sizeKb,
                $economiaKb,
                $firstSeen,
                mb_strimwidth((string) $filename, 0, 40, '…'),
            ];

            $totalEconomiaBytes += $economia;
        }

        $this->table($headers, $tableRows);

        $totalMb = number_format($totalEconomiaBytes / 1024 / 1024, 2);
        $this->newLine();
        $this->info("Total economia estimada: {$totalMb} MB" .
            ($businessId !== null ? " (business_id={$businessId})" : ' (todos businesses)'));

        return 0;
    }
}
