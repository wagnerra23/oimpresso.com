<?php

namespace Modules\Forja\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Forja\Console\Commands\BriefHealthCommand;
use Modules\Forja\Console\Commands\RotateTokenCommand;
use Modules\Forja\Console\Commands\ForjaHealthCommand;
use Modules\Forja\Console\Commands\GenerateBriefCommand;
use Modules\Forja\Console\Commands\SkillTierReviewCommand;
use Modules\Forja\Console\Commands\SeedActorsCommand;

/**
 * ServiceProvider do módulo Forja.
 *
 * Modelado conforme Modules/TeamMcp/Providers/TeamMcpServiceProvider.php.
 * Rotas carregadas via start.php (ver module.json "files").
 */
class ForjaServiceProvider extends ServiceProvider
{
    /**
     * @var bool
     */
    protected $defer = false;

    public function boot(): void
    {
        $this->registerTranslations();
        $this->registerConfig();

        // Sem isto o comando existe no disco mas NUNCA chega ao Artisan — medido
        // 2026-07-28: `artisan project-mgmt:health` respondia "There are no commands
        // defined in the project-mgmt namespace" desde 2026-05-16 (Wave 17).
        // Padrão canônico: Modules/Vestuario/Providers/VestuarioServiceProvider.
        if ($this->app->runningInConsole()) {
            $this->commands([
                ForjaHealthCommand::class,
                // Ex-Modules/Brief (ADR 0091) — absorvido em 2026-07-30. Os dois
                // primeiros têm schedule em live (app/Console/Kernel.php):
                // brief:generate 6x/dia e skills:tier-review trimestral. A
                // signature não mudou, então o Kernel não precisou de patch.
                GenerateBriefCommand::class,
                SkillTierReviewCommand::class,
                BriefHealthCommand::class,
                // Identidade do MCP, recebida do TeamMcp em 2026-07-31
                // (mcp_actors + emissão de token — ADR 0081).
                SeedActorsCommand::class,
                RotateTokenCommand::class,
            ]);
        }

        // Ex-Modules/Brief — D7 LGPD (Wave 13): publica a config de retenção do
        // Daily Brief. Arquivo renomeado pra brief-retention.php porque a Forja
        // já tinha um Config/retention.php próprio; a CHAVE segue 'brief.*'.
        $this->publishes([
            __DIR__ . '/../Config/brief-retention.php' => config_path('brief.php'),
        ], 'brief-config');
    }

    public function register(): void
    {
        // Ex-Modules/Brief — merge sob o namespace 'brief.*' para que
        // config('brief.redact_pii_before_llm') e demais flags resolvam sem
        // publish. Lido por BriefGeneratorService; a chave NÃO pode virar
        // 'forja.*' sem tocar os consumidores.
        $this->mergeConfigFrom(
            __DIR__ . '/../Config/brief-retention.php',
            'brief'
        );
    }

    protected function registerConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../Config/config.php' => config_path('projectmgmt.php'),
        ], 'config');

        $this->mergeConfigFrom(
            __DIR__ . '/../Config/config.php',
            'projectmgmt'
        );
    }

    protected function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/projectmgmt');

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, 'projectmgmt');
        } else {
            $this->loadTranslationsFrom(__DIR__ . '/../Resources/lang', 'projectmgmt');
        }
    }

    public function provides(): array
    {
        return [];
    }
}
