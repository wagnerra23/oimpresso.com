<?php

declare(strict_types=1);

namespace App\Console\Commands\FeatureFlag;

use App\Services\GrowthBookAdminService;
use Illuminate\Console\Command;
use Throwable;

/**
 * `php artisan flag:create <key> --descricao="..." [--environment=production]`
 *
 * Cria no GrowthBook uma feature booleana DESLIGADA (defaultValue false, sem rule).
 * Sem ela o `flag:set` recusa ("Feature não existe") e a tela fica no Blade pra todo mundo.
 * Não liga nada: ligar pra uma empresa é `flag:set <key> --biz=N --enabled=true` depois.
 * Rodar de novo numa feature que já existe não muda nada.
 */
class FlagCreateCommand extends Command
{
    protected $signature = 'flag:create
        {key : Feature key (ex: useV2ComprasPedidos)}
        {--descricao= : O que a flag liga (obrigatório)}
        {--environment=production : Environment do GrowthBook (o --env é opção global do Laravel)}';

    protected $description = 'Cria feature flag booleana desligada no GrowthBook';

    public function handle(GrowthBookAdminService $service): int
    {
        $key = (string) $this->argument('key');
        $descricao = trim((string) $this->option('descricao'));
        $env = (string) $this->option('environment');

        if ($descricao === '') {
            $this->error('--descricao é obrigatório.');
            return self::FAILURE;
        }

        if (! $service->isConfigured()) {
            $this->error('GrowthBookAdminService não configurado.');
            return self::FAILURE;
        }

        try {
            $r = $service->createBooleanFeature($key, $descricao, $env);
        } catch (Throwable $e) {
            $this->error('Falha: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info($r['criada']
            ? "✓ Feature '{$key}' criada desligada ({$env})."
            : "· Feature '{$key}' já existia; nada mudou.");

        return self::SUCCESS;
    }
}
