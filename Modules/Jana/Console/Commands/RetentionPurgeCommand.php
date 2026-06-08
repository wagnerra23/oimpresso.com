<?php

declare(strict_types=1);

namespace Modules\Jana\Console\Commands;

use App\Business;
use Illuminate\Console\Command;
use Modules\Jana\Services\Privacy\RetentionPurgeService;

/**
 * jana:retention-purge — G1 P0 (AUDIT-SENIOR-2026-05-25 §6).
 *
 * Aplica `Config/retention.php` canon: itera business by business + entidades
 * configuradas + estratégia (default anonymize). 100% multi-tenant Tier 0 via
 * loop EXPLÍCITO `Business::each()` — NUNCA cross-tenant cleanup.
 *
 * Pré-requisito Wagner: `JANA_RETENTION_ENABLED=true` em prod biz=1 após canary 7d.
 * Default `enabled=false` em config — command em modo dry-run quando flag off.
 *
 * Uso:
 *   php artisan jana:retention-purge --dry-run                  # simula tudo
 *   php artisan jana:retention-purge --business=1 --dry-run     # 1 business
 *   php artisan jana:retention-purge --business=1               # executa biz=1
 *   php artisan jana:retention-purge --business=1 --entity=conversa
 *   php artisan jana:retention-purge --days-override=30 --dry-run
 *
 * Schedule canon: daily 03:00 BRT (app/Console/Kernel.php) atrás de `JANA_RETENTION_ENABLED=true`.
 *
 * @see Modules\Jana\Services\Privacy\RetentionPurgeService
 * @see Modules\Jana\Config\retention.php
 * @see memory/requisitos/Jana/AUDIT-SENIOR-2026-05-25.md §6 G1
 */
class RetentionPurgeCommand extends Command
{
    protected $signature = 'jana:retention-purge
                            {--business= : Limitar a business_id específico (default: itera todos)}
                            {--entity= : Limitar a entidade canon específica (default: itera todas)}
                            {--days-override= : Override TTL config (em dias)}
                            {--dry-run : Apenas conta o que seria purgado, não persiste}
                            {--force : Força execução mesmo com enabled=false em config}';

    protected $description = 'D7 LGPD retention purge — aplica retention.php canon (Art. 16 + Art. 18 §VI)';

    public function handle(RetentionPurgeService $service): int
    {
        $businessIdArg = $this->option('business');
        $entityArg = $this->option('entity');
        $daysOverride = $this->option('days-override') ? (int) $this->option('days-override') : null;
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $enabled = (bool) config('jana.retention.enabled', false);

        if (! $enabled && ! $force && ! $dryRun) {
            $this->warn('jana.retention.enabled=false em config.');
            $this->warn('Use --dry-run pra simular OU --force pra ignorar flag.');
            $this->warn('Wagner aprova JANA_RETENTION_ENABLED=true em prod só após canary 7d biz=1.');

            return self::FAILURE;
        }

        $strategy = (string) config('jana.retention.strategy', 'anonymize');

        $this->info('jana:retention-purge — ' . now()->toDateTimeString());
        $this->line("  estratégia    : {$strategy}");
        $this->line('  enabled       : ' . ($enabled ? 'true' : 'false (forçado via flag)'));
        if ($dryRun) {
            $this->warn('  [DRY RUN] nada será persistido');
        }
        if ($daysOverride !== null) {
            $this->warn("  [DAYS OVERRIDE] {$daysOverride}d sobrescreve config");
        }

        // Resolve businesses
        $businesses = $this->resolveBusinesses($businessIdArg);

        if ($businesses->isEmpty()) {
            $this->error('Nenhum business resolvido. Verifique --business ou tabela business.');

            return self::FAILURE;
        }

        // Resolve entidades
        $entities = $entityArg
            ? [$entityArg]
            : $service->listEntities();

        if ($entityArg && ! in_array($entityArg, $service->listEntities(), true)) {
            $this->error("Entidade '{$entityArg}' não está em retention.php. Válidas: " . implode(', ', $service->listEntities()));

            return self::FAILURE;
        }

        $this->line('  businesses    : ' . $businesses->count());
        $this->line('  entidades     : ' . count($entities));
        $this->newLine();

        $rows = [];
        $totalPurged = 0;
        $totalMatched = 0;
        $failures = 0;

        foreach ($businesses as $bizId) {
            foreach ($entities as $entity) {
                $result = $service->purgeEntity(
                    businessId: (int) $bizId,
                    entityKey: $entity,
                    retentionDaysOverride: $daysOverride,
                    dryRun: $dryRun,
                );

                if ($result['error']) {
                    $failures++;
                }

                $totalMatched += $result['rows_matched'];
                $totalPurged += $result['rows_purged'];

                // Skipa rows com retention_days=null (entidade indefinida)
                if ($result['retention_days'] === null) {
                    continue;
                }

                $rows[] = [
                    $bizId,
                    $entity,
                    $result['retention_days'],
                    $result['rows_matched'],
                    $result['rows_purged'],
                    $result['error'] ? 'ERR: ' . substr($result['error'], 0, 30) : 'OK',
                ];
            }
        }

        $this->table(
            ['business_id', 'entity', 'retention_days', 'matched', 'purged', 'status'],
            $rows,
        );

        $this->newLine();
        $this->info(sprintf(
            'Total: %d matched · %d purged · %d failures · %d businesses · %d entities',
            $totalMatched,
            $totalPurged,
            $failures,
            $businesses->count(),
            count($entities),
        ));

        if ($dryRun) {
            $this->warn('[DRY RUN] nada foi persistido.');
        }

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Multi-tenant Tier 0: itera business by business via loop EXPLÍCITO.
     * Quando --business é passado, restringe a 1 single business.
     *
     * @return \Illuminate\Support\Collection<int,int>
     */
    protected function resolveBusinesses(?string $businessIdArg): \Illuminate\Support\Collection
    {
        if ($businessIdArg !== null) {
            return collect([(int) $businessIdArg]);
        }

        // Sem --business: itera TODOS (cuidado em prod — confirmar via --dry-run primeiro).
        return Business::query()->pluck('id')->map(fn ($v) => (int) $v);
    }
}
