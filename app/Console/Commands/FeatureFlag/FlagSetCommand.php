<?php

declare(strict_types=1);

namespace App\Console\Commands\FeatureFlag;

use App\Services\GrowthBookAdminService;
use Illuminate\Console\Command;
use Throwable;

/**
 * `php artisan flag:set <key> --biz=N --enabled=true|false [--env=production]`
 *
 * Liga/desliga feature pra um business_id específico (rule id `biz-{N}`).
 *
 * Exemplos:
 *   php artisan flag:set useV2SellsCreate --biz=4 --enabled=false  # desliga pra Larissa
 *   php artisan flag:set useV2SellsCreate --biz=1 --enabled=true   # canary Wagner
 *   php artisan flag:set useV2SellsCreate --biz=4 --remove          # remove rule (volta pro default)
 */
class FlagSetCommand extends Command
{
    protected $signature = 'flag:set
        {key : Feature key (ex: useV2SellsCreate)}
        {--biz= : business_id alvo (obrigatório)}
        {--enabled= : true|false — valor que será forçado pro biz alvo}
        {--remove : Em vez de toggle, remove a rule biz-{N} (volta pro defaultValue)}
        {--env=production : Environment}
        {--clear-cache : Limpa cache local Laravel pós-mudança}';

    protected $description = 'Liga/desliga feature flag pra um business_id específico';

    public function handle(GrowthBookAdminService $service): int
    {
        $key = (string) $this->argument('key');
        $bizRaw = $this->option('biz');
        $env = (string) $this->option('env');
        $remove = (bool) $this->option('remove');

        if ($bizRaw === null || $bizRaw === '') {
            $this->error('--biz=N é obrigatório.');
            return self::FAILURE;
        }

        $bizId = (int) $bizRaw;
        if ($bizId <= 0) {
            $this->error('--biz deve ser inteiro positivo.');
            return self::FAILURE;
        }

        if (! $service->isConfigured()) {
            $this->error('GrowthBookAdminService não configurado.');
            return self::FAILURE;
        }

        try {
            if ($remove) {
                $service->removeBizRule($key, $bizId, $env);
                $this->info("✓ Rule biz-{$bizId} removida de '{$key}' ({$env}). Biz volta pro defaultValue.");
            } else {
                $enabledRaw = $this->option('enabled');
                if ($enabledRaw === null) {
                    $this->error('--enabled=true|false é obrigatório (a menos que use --remove).');
                    return self::FAILURE;
                }
                $forceValue = in_array(strtolower((string) $enabledRaw), ['true', '1', 'on', 'yes'], true);
                $service->setBizRule($key, $bizId, $forceValue, $env);
                $valueStr = $forceValue ? 'true' : 'false';
                $this->info("✓ Rule biz-{$bizId} em '{$key}' ({$env}) → value={$valueStr}.");
            }
        } catch (Throwable $e) {
            $this->error('Falha: ' . $e->getMessage());
            return self::FAILURE;
        }

        if ($this->option('clear-cache')) {
            $service->clearLocalCache();
            $this->info('✓ Cache local Laravel limpo (FeatureFlagService).');
        } else {
            $this->line('Dica: cache local Laravel tem TTL 60s. Use --clear-cache pra invalidar imediato.');
        }

        return self::SUCCESS;
    }
}
