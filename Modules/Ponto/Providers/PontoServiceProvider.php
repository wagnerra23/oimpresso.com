<?php

namespace Modules\Ponto\Providers;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

/**
 * ServiceProvider do módulo Ponto (ex-PontoWr2, renomeado em Fase 3.7 PR-2).
 *
 * Modelado conforme Modules/Jana/Providers/JanaServiceProvider.php (padrão
 * UltimatePOS). Não há RouteServiceProvider separado — as rotas são
 * carregadas via start.php (ver module.json "files").
 *
 * Note: config keys + URLs + permissions + lang dir mantêm prefixo `pontowr2.*`
 * por compatibilidade (rename PHP-only — ver plano §4 erratum).
 */
class PontoServiceProvider extends ServiceProvider
{
    /**
     * Middlewares aliasados pelo módulo.
     *
     * @var array
     */
    protected $middleware = [
        'Ponto' => [
            'ponto.access' => 'CheckPontoAccess',
        ],
    ];

    /**
     * @var bool
     */
    protected $defer = false;

    /**
     * Boot do módulo.
     */
    public function boot(Router $router): void
    {
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');

        $this->registerMiddleware($this->app['router']);
        $this->registerBladeDirectives();
    }

    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->singleton(\Modules\Ponto\Services\ApuracaoService::class);
        $this->app->singleton(\Modules\Ponto\Services\BancoHorasService::class);
        $this->app->singleton(\Modules\Ponto\Services\AfdParserService::class);
        $this->app->singleton(\Modules\Ponto\Services\IntercorrenciaService::class);
        $this->app->singleton(\Modules\Ponto\Services\NsrService::class);
        $this->app->singleton(\Modules\Ponto\Services\MarcacaoService::class);
        $this->app->singleton(\Modules\Ponto\Services\ReportService::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Modules\Ponto\Console\Commands\ImportAfdCommand::class,
                \Modules\Ponto\Console\Commands\AfdInspecionarCommand::class,
                \Modules\Ponto\Console\Commands\PontoHealthCommand::class,
            ]);
        }
    }

    /**
     * Publica e merge do arquivo de config.
     */
    protected function registerConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../Config/config.php' => config_path('pontowr2.php'),
        ], 'config');

        $this->mergeConfigFrom(
            __DIR__ . '/../Config/config.php',
            'pontowr2'
        );
    }

    /**
     * Publica e registra as views.
     */
    public function registerViews(): void
    {
        $viewPath = resource_path('views/modules/pontowr2');
        $sourcePath = __DIR__ . '/../Resources/views';

        $this->publishes([
            $sourcePath => $viewPath,
        ], 'views');

        $this->loadViewsFrom(
            array_merge(array_map(function ($path) {
                return $path . '/modules/pontowr2';
            }, \Config::get('view.paths')), [$sourcePath]),
            'pontowr2'
        );
    }

    /**
     * Registra as traduções.
     */
    public function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/pontowr2');

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, 'pontowr2');
        } else {
            $this->loadTranslationsFrom(__DIR__ . '/../Resources/lang', 'pontowr2');
        }
    }

    /**
     * Registra os aliases de middleware do módulo.
     * Mesmo formato do Jana: array aninhado 'Modulo' => [alias => ClassName].
     */
    public function registerMiddleware(Router $router): void
    {
        foreach ($this->middleware as $module => $middlewares) {
            foreach ($middlewares as $alias => $middleware) {
                $class = "Modules\\{$module}\\Http\\Middleware\\{$middleware}";
                $router->aliasMiddleware($alias, $class);
            }
        }
    }

    /**
     * Diretivas Blade específicas do módulo.
     */
    protected function registerBladeDirectives(): void
    {
        Blade::directive('pontoCan', function ($expression) {
            return "<?php if (auth()->user()->can($expression, 'ponto')): ?>";
        });

        Blade::directive('endpontoCan', function () {
            return "<?php endif; ?>";
        });
    }

    /**
     * @return array
     */
    public function provides(): array
    {
        return [];
    }
}
