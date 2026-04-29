<?php

namespace Modules\Copiloto\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Copiloto\Services\Cache\SemanticCacheService;

/**
 * MEM-CACHE-1 — Stats do cache semântico.
 *
 *   php artisan copiloto:cache:stats                    # global
 *   php artisan copiloto:cache:stats --business=4       # só ROTA LIVRE
 *   php artisan copiloto:cache:stats --top=10           # top entradas mais reutilizadas
 *   php artisan copiloto:cache:stats --invalidar=4      # CUIDADO: zera cache do biz=4
 */
class CacheStatsCommand extends Command
{
    protected $signature = 'copiloto:cache:stats
                            {--business= : ID do business (ou omite p/ global)}
                            {--top=10 : Top N entradas mais reutilizadas}
                            {--invalidar= : ID do business pra invalidar cache (CUIDADO)}';

    protected $description = 'Stats do cache semântico — hits, tokens economizados, R$ economizados';

    public function handle(SemanticCacheService $cache): int
    {
        if ($invalBiz = $this->option('invalidar')) {
            if (! $this->confirm("Invalidar TODO cache de biz={$invalBiz}?", false)) {
                $this->warn('Abortado.');
                return self::SUCCESS;
            }
            $count = $cache->invalidarPorBusiness((int) $invalBiz);
            $this->info("✓ {$count} entradas invalidadas em biz={$invalBiz}");
            return self::SUCCESS;
        }

        $bizId = $this->option('business') !== null ? (int) $this->option('business') : null;

        // Stats agregadas
        $stats = $cache->stats($bizId);
        $this->info('=== Stats globais ' . ($bizId ? "biz={$bizId}" : 'plataforma') . ' ===');
        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Entradas em cache', number_format($stats['entradas_cache'])],
                ['Total hits acumulados', number_format($stats['total_hits'])],
                ['Hit rate (hits/entradas)', $stats['hit_rate']],
                ['R$ economizados (acumulado)', 'R$ ' . number_format($stats['r$_economizado'], 4, ',', '.')],
            ]
        );

        // Top reutilizadas
        $topN = (int) $this->option('top');
        $q = DB::table('copiloto_cache_semantico')->where('hits', '>', 0);
        if ($bizId !== null) $q->where('business_id', $bizId);
        $top = $q->orderByDesc('hits')
            ->limit($topN)
            ->get(['id', 'business_id', 'query_original', 'hits', 'tokens_in', 'tokens_out', 'custo_brl_original']);

        if ($top->isEmpty()) {
            $this->warn('Nenhuma entrada com hits ainda. Cache começa a render valor após 2ª query similar.');
            return self::SUCCESS;
        }

        $this->info("=== Top {$topN} queries reutilizadas ===");
        $rows = [];
        foreach ($top as $e) {
            $tokens = ($e->tokens_in ?? 0) + ($e->tokens_out ?? 0);
            $totalEconomizado = $e->hits * (float) ($e->custo_brl_original ?? 0);
            $rows[] = [
                $e->id,
                $e->business_id ?? '—',
                mb_substr($e->query_original ?? '', 0, 50),
                $e->hits,
                number_format($tokens),
                'R$ ' . number_format($totalEconomizado, 4, ',', '.'),
            ];
        }
        $this->table(['ID', 'Biz', 'Query', 'Hits', 'Tokens orig.', 'R$ economizado'], $rows);

        // Distribuição por TTL
        $expiraEm1h = (int) DB::table('copiloto_cache_semantico')
            ->where('expira_em', '<', now()->addHour())->count();
        $valid = (int) DB::table('copiloto_cache_semantico')
            ->where('expira_em', '>=', now())->count();
        $expirado = (int) DB::table('copiloto_cache_semantico')
            ->where('expira_em', '<', now())->count();

        $this->info('=== Estado do TTL ===');
        $this->table(
            ['Estado', 'Entradas'],
            [
                ['Válidas', number_format($valid)],
                ['Expira na próxima 1h', number_format($expiraEm1h)],
                ['Já expiradas (lixo)', number_format($expirado)],
            ]
        );

        return self::SUCCESS;
    }
}
