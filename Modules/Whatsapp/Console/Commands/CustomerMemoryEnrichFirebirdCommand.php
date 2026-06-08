<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Console\Commands;

use Illuminate\Console\Command;
use Modules\Whatsapp\Services\CustomerMemory\OfficeimpressoEnrichService;
use Modules\Whatsapp\Services\CustomerMemory\Sources\JsonFileFirebirdSource;

/**
 * US-WA-VOZ-002 — Comando enrich customer_memory com Firebird OfficeImpresso.
 *
 * Uso:
 *   # 1) Wagner roda Python local pra exportar Firebird → JSON
 *   python scripts/firebird/export-customers.py --output storage/app/firebird/customers-2026-05-15.json
 *
 *   # 2) Upload pro Hostinger (scp/git ou via outro mecanismo)
 *
 *   # 3) Rodar enrich
 *   php artisan customer-memory:enrich-firebird --business=1 \
 *       --json=storage/app/firebird/customers-2026-05-15.json
 *
 *   php artisan customer-memory:enrich-firebird --business=1 --json=... --limit=500 --detail
 *
 * Tier 0: --business obrigatório. Cross-tenant via JSON sem proteção
 * (admin é quem decide quem entra no JSON exportado).
 *
 * @see Modules/Whatsapp/Services/CustomerMemory/OfficeimpressoEnrichService.php
 * @see scripts/firebird/export-customers.py
 */
class CustomerMemoryEnrichFirebirdCommand extends Command
{
    protected $signature = 'customer-memory:enrich-firebird
        {--business= : business_id obrigatório (Tier 0 ADR 0093)}
        {--json= : path do JSON exportado Firebird (default: storage/app/firebird/customers-latest.json)}
        {--limit=1000 : máximo customer_memory processados}
        {--stale-days=30 : threshold external_sources_enriched_at (default 30d)}
        {--detail : log breakdown por customer}';

    protected $description = 'Enriquece customer_memory com lookup cliente Firebird OfficeImpresso (US-WA-VOZ-002).';

    public function handle(): int
    {
        $businessId = (int) $this->option('business');
        if ($businessId <= 0) {
            $this->error('--business=N obrigatório (Tier 0 multi-tenant).');
            return Command::INVALID;
        }

        $jsonPath = (string) ($this->option('json')
            ?: storage_path('app/firebird/customers-latest.json'));

        if (! file_exists($jsonPath)) {
            $this->error("JSON não encontrado: {$jsonPath}");
            $this->warn('Rode primeiro o export Python:');
            $this->line('  python scripts/firebird/export-customers.py --output ' . $jsonPath);
            return Command::FAILURE;
        }

        $limit = max(1, (int) $this->option('limit'));
        $staleDays = max(1, (int) $this->option('stale-days'));
        $detail = (bool) $this->option('detail');

        $source = new JsonFileFirebirdSource($jsonPath);

        if (! $source->isHealthy()) {
            $this->error("JSON source unhealthy (arquivo >30d ou inválido): {$jsonPath}");
            return Command::FAILURE;
        }

        $this->info("Source: {$source->sourceLabel()}");
        $this->info("Path: {$jsonPath}");

        $service = new OfficeimpressoEnrichService($source);

        $startedAt = microtime(true);
        $stats = $service->enrichBusiness($businessId, $limit, $staleDays);
        $durationMs = (int) ((microtime(true) - $startedAt) * 1000);

        $this->table(
            ['biz', 'processed', 'matched', 'skipped', 'duration_ms'],
            [[
                $businessId,
                $stats['processed'],
                $stats['matched'],
                $stats['skipped'],
                $durationMs,
            ]]
        );

        if ($stats['processed'] === 0) {
            $this->warn('Nenhum customer_memory elegível pra enrich (todos enriched < stale-days OU biz vazio).');
            return Command::SUCCESS;
        }

        $matchRate = round($stats['matched'] * 100 / max(1, $stats['processed']), 1);
        $this->info("Match rate: {$matchRate}% ({$stats['matched']}/{$stats['processed']})");

        return Command::SUCCESS;
    }
}
