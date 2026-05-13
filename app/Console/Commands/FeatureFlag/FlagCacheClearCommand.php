<?php

declare(strict_types=1);

namespace App\Console\Commands\FeatureFlag;

use App\Services\GrowthBookAdminService;
use Illuminate\Console\Command;

/**
 * `php artisan flag:cache-clear`
 *
 * Limpa cache local Laravel do FeatureFlagService (TTL 60s) — força re-fetch
 * imediato do GrowthBook na próxima chamada `isOn()`. Útil pós-mudança via UI
 * GrowthBook ou outro processo que não passou pelos commands desta API.
 */
class FlagCacheClearCommand extends Command
{
    protected $signature = 'flag:cache-clear';

    protected $description = 'Limpa cache local Laravel do FeatureFlagService (força refetch)';

    public function handle(GrowthBookAdminService $service): int
    {
        $service->clearLocalCache();
        $this->info('✓ Cache local Laravel (growthbook.features) limpo. Próxima isOn() vai refetch.');
        return self::SUCCESS;
    }
}
