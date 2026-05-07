<?php

declare(strict_types=1);

namespace Modules\Whatsapp\Console\Commands;

use Illuminate\Console\Command;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\WhatsappBusinessConfig;
use Modules\Whatsapp\Jobs\WhatsappDriverHealthCheckJob;

/**
 * Dispara WhatsappDriverHealthCheckJob pra TODOS businesses com driver
 * não-oficial cadastrado (zapi/baileys).
 *
 * Schedule canônico: every 6h via app/Console/Kernel.php.
 *
 * Uso:
 *   php artisan whatsapp:health-check-all
 *   php artisan whatsapp:health-check-all --business=4   (apenas 1 business)
 *
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-014
 * @see memory/requisitos/Whatsapp/ARCHITECTURE.md §4
 */
class DriverHealthCheckAllCommand extends Command
{
    protected $signature = 'whatsapp:health-check-all
                            {--business= : Apenas 1 business_id (default: todos)}';

    protected $description = 'Pinga driver primário Whatsapp de todos businesses (ou 1 específico) — schedule 6h em 6h';

    public function handle(): int
    {
        $businessId = $this->option('business');

        $query = WhatsappBusinessConfig::query()
            ->withoutGlobalScope(ScopeByBusiness::class)
            ->whereIn('driver', ['zapi', 'baileys']); // Meta Cloud não precisa health check

        if ($businessId !== null) {
            $query->where('business_id', (int) $businessId);
        }

        $configs = $query->get();
        $count = $configs->count();

        if ($count === 0) {
            $this->info('Nenhum business com driver não-oficial (zapi/baileys) ativo. Nada a fazer.');
            return self::SUCCESS;
        }

        $this->info("Disparando health check pra {$count} business(es)...");

        $configs->each(function (WhatsappBusinessConfig $config) {
            WhatsappDriverHealthCheckJob::dispatch($config->business_id);
            $this->line("  → business={$config->business_id} driver={$config->driver}");
        });

        $this->info("✓ {$count} jobs enfileirados na queue 'whatsapp'.");

        return self::SUCCESS;
    }
}
