<?php

declare(strict_types=1);

namespace App\Console\Commands\FeatureFlag;

use App\Services\GrowthBookAdminService;
use Illuminate\Console\Command;
use Throwable;

/**
 * `php artisan flag:list [--project=]`
 *
 * Lista todas feature flags configuradas no GrowthBook.
 */
class FlagListCommand extends Command
{
    protected $signature = 'flag:list {--project= : Filtrar por projectId} {--json : Saída JSON crua}';

    protected $description = 'Lista feature flags do GrowthBook admin';

    public function handle(GrowthBookAdminService $service): int
    {
        if (! $service->isConfigured()) {
            $this->error('GrowthBookAdminService não configurado. Defina GROWTHBOOK_ADMIN_API_TOKEN no .env.');
            return self::FAILURE;
        }

        try {
            $features = $service->listFeatures($this->option('project') ?: null);
        } catch (Throwable $e) {
            $this->error('Falha: ' . $e->getMessage());
            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($features, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return self::SUCCESS;
        }

        if ($features === []) {
            $this->info('Nenhuma feature flag configurada.');
            return self::SUCCESS;
        }

        $rows = array_map(function (array $f) {
            $envs = $f['environments'] ?? [];
            $envSummary = collect($envs)->map(function ($e, $name) {
                $ruleCount = is_array($e['rules'] ?? null) ? count($e['rules']) : 0;
                $enabled = ($e['enabled'] ?? false) ? 'ON' : 'OFF';
                return "{$name}:{$enabled}({$ruleCount}r)";
            })->implode(' ');

            return [
                $f['id'] ?? '?',
                (string) ($f['valueType'] ?? 'boolean'),
                (string) ($f['defaultValue'] ?? '?'),
                $envSummary ?: '—',
            ];
        }, $features);

        $this->table(['Key', 'Type', 'Default', 'Environments'], $rows);
        return self::SUCCESS;
    }
}
