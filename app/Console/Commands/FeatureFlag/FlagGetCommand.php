<?php

declare(strict_types=1);

namespace App\Console\Commands\FeatureFlag;

use App\Services\GrowthBookAdminService;
use Illuminate\Console\Command;
use Throwable;

/**
 * `php artisan flag:get <key> [--env=production]`
 *
 * Mostra detalhe de 1 feature flag incluindo rules de targeting por environment.
 */
class FlagGetCommand extends Command
{
    protected $signature = 'flag:get {key : Feature key (ex: useV2SellsCreate)} {--env=production : Environment} {--json : Saída JSON crua}';

    protected $description = 'Mostra detalhe de 1 feature flag (rules, defaults, environment)';

    public function handle(GrowthBookAdminService $service): int
    {
        $key = (string) $this->argument('key');
        $env = (string) $this->option('env');

        if (! $service->isConfigured()) {
            $this->error('GrowthBookAdminService não configurado.');
            return self::FAILURE;
        }

        try {
            $feature = $service->getFeature($key);
        } catch (Throwable $e) {
            $this->error('Falha: ' . $e->getMessage());
            return self::FAILURE;
        }

        if ($feature === null) {
            $this->error("Feature '{$key}' não encontrada.");
            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($feature, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return self::SUCCESS;
        }

        $this->info("Feature: {$feature['id']}");
        $this->line('  defaultValue: ' . (string) ($feature['defaultValue'] ?? '?'));
        $this->line('  valueType:    ' . (string) ($feature['valueType'] ?? 'boolean'));
        $this->newLine();

        $envs = $feature['environments'] ?? [];
        if (! isset($envs[$env])) {
            $this->warn("Environment '{$env}' não definido. Envs disponíveis: " . implode(', ', array_keys($envs)));
            return self::SUCCESS;
        }

        $envData = $envs[$env];
        $enabled = ($envData['enabled'] ?? false) ? 'ON' : 'OFF';
        $this->info("Environment: {$env} [{$enabled}]");

        $rules = $envData['rules'] ?? [];
        if ($rules === []) {
            $this->line('  (sem rules — usa defaultValue)');
            return self::SUCCESS;
        }

        $rows = array_map(fn ($r) => [
            (string) ($r['id'] ?? '?'),
            (string) ($r['type'] ?? '?'),
            (string) ($r['value'] ?? '?'),
            (string) ($r['condition'] ?? '(sem condition)'),
            ($r['enabled'] ?? false) ? 'ON' : 'OFF',
        ], $rules);

        $this->table(['ID', 'Type', 'Value', 'Condition', 'Enabled'], $rows);
        return self::SUCCESS;
    }
}
