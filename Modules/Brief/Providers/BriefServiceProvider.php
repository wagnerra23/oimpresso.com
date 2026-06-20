<?php

namespace Modules\Brief\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Brief\Console\Commands\GenerateBriefCommand;
use Modules\Brief\Console\Commands\SkillTierReviewCommand;

/**
 * ServiceProvider do módulo Brief.
 *
 * Sprint 1 — Daily Brief (camada L7 da Constituição V2). Ver ADR 0091.
 *
 * Boot: rotas API (brief-fetch HTTP) + rotas web (3 rotas Install ADR 0024)
 * + comandos artisan. UI admin futura virá em US-COPI-090/091.
 */
class BriefServiceProvider extends ServiceProvider
{
    /**
     * @var bool
     */
    protected $defer = false;

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../Routes/api.php');
        $this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');

        // D7 LGPD (Wave 13): publica config de retenção pra que
        // config('brief.redact_pii_before_llm') e demais flags fiquem
        // disponíveis em tempo de execução (BriefGeneratorService).
        $this->publishes([
            __DIR__.'/../Config/retention.php' => config_path('brief.php'),
        ], 'brief-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateBriefCommand::class,
                SkillTierReviewCommand::class,
            ]);
        }
    }

    public function register(): void
    {
        // D7 LGPD (Wave 13): merge config retention.php sob namespace 'brief.*'
        // — flags como 'brief.redact_pii_before_llm' funcionam sem publish.
        $this->mergeConfigFrom(
            __DIR__.'/../Config/retention.php',
            'brief'
        );

        // Service container: autoresolve PiiRedactor (Modules\Jana\Services\Privacy\)
        // — registrado no Jana ServiceProvider.
    }
}
