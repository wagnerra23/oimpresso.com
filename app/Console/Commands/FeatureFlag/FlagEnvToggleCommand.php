<?php

declare(strict_types=1);

namespace App\Console\Commands\FeatureFlag;

use App\Services\GrowthBookAdminService;
use Illuminate\Console\Command;
use Throwable;

/**
 * `php artisan flag:env-toggle <key> --enabled=true|false [--env=production]`
 *
 * Mata-switch: liga/desliga uma feature inteira em um environment. Diferente de
 * `flag:set` (que mexe em rule específica de biz_id), este toca o `enabled` do
 * environment todo — quando OFF, ninguém recebe a feature, independentemente
 * das rules cadastradas.
 *
 * Útil pra: emergência geral, killswitch, pausar canary global.
 */
class FlagEnvToggleCommand extends Command
{
    protected $signature = 'flag:env-toggle
        {key : Feature key (ex: useV2SellsCreate)}
        {--enabled= : true|false — liga/desliga o environment inteiro}
        {--env=production : Environment}
        {--clear-cache : Limpa cache local Laravel pós-mudança}';

    protected $description = 'Mata-switch: liga/desliga feature inteira em um environment';

    public function handle(GrowthBookAdminService $service): int
    {
        $key = (string) $this->argument('key');
        $env = (string) $this->option('env');
        $enabledRaw = $this->option('enabled');

        if ($enabledRaw === null) {
            $this->error('--enabled=true|false é obrigatório.');
            return self::FAILURE;
        }

        $enabled = in_array(strtolower((string) $enabledRaw), ['true', '1', 'on', 'yes'], true);

        if (! $service->isConfigured()) {
            $this->error('GrowthBookAdminService não configurado.');
            return self::FAILURE;
        }

        try {
            $service->setEnvEnabled($key, $enabled, $env);
            $action = $enabled ? 'LIGADA' : 'DESLIGADA';
            $this->info("✓ Feature '{$key}' {$action} no environment '{$env}'.");
        } catch (Throwable $e) {
            $this->error('Falha: ' . $e->getMessage());
            return self::FAILURE;
        }

        if ($this->option('clear-cache')) {
            $service->clearLocalCache();
            $this->info('✓ Cache local Laravel limpo.');
        }

        return self::SUCCESS;
    }
}
