<?php

namespace Modules\ADS\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Routing\Router;
use Modules\ADS\Services\RiskEngine;
use Modules\ADS\Services\PolicyEngine;
use Modules\ADS\Services\ConfidenceEngine;
use Modules\ADS\Services\DecisionRouter;
use Modules\ADS\Services\BrainBService;
use Modules\ADS\Services\ReviewerService;
use Modules\ADS\Services\PatternLearningService;
use Modules\ADS\Services\AutoTaskGeneratorService;
use Modules\ADS\Services\PlannerService;
use Modules\ADS\Services\ToolRegistry;
use Modules\ADS\Services\GovernanceRulesService;
use Modules\ADS\Services\ProjectDecomposerService;
use Modules\ADS\Services\DecisionLinksService;
use Modules\ADS\Services\UserScopeService;
use Modules\ADS\Services\ContextForTaskService;
use Modules\ADS\Http\Middleware\AdsApiAuth;
use Modules\ADS\Console\Commands\AdsHealthCommand;
use Modules\ADS\Console\Commands\ProcessBrainBCommand;
use Modules\ADS\Console\Commands\LearnPatternsCommand;
use Modules\ADS\Console\Commands\ReviewDecisionsCommand;
use Modules\ADS\Console\Commands\AutoGenerateTasksCommand;
use Modules\ADS\Console\Commands\PlanDecisionsCommand;
use Modules\ADS\Console\Commands\SkillScaffoldCommand;
use Modules\ADS\Services\ScaffoldSkillFromMissionService;

class AdsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerConfig();
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        $this->registerMiddleware();

        if ($this->app->runningInConsole()) {
            $this->commands([
                ProcessBrainBCommand::class,
                LearnPatternsCommand::class,
                ReviewDecisionsCommand::class,
                AutoGenerateTasksCommand::class,
                PlanDecisionsCommand::class,
                SkillScaffoldCommand::class,
                AdsHealthCommand::class,
            ]);
        }
    }

    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);

        $this->app->singleton(PolicyEngine::class);
        $this->app->singleton(RiskEngine::class);
        $this->app->singleton(ConfidenceEngine::class);
        $this->app->singleton(DecisionRouter::class);
        $this->app->singleton(BrainBService::class);
        $this->app->singleton(ReviewerService::class);
        $this->app->singleton(PatternLearningService::class);
        $this->app->singleton(AutoTaskGeneratorService::class);
        $this->app->singleton(PlannerService::class);
        $this->app->singleton(ToolRegistry::class);
        $this->app->singleton(GovernanceRulesService::class);
        $this->app->singleton(ProjectDecomposerService::class);
        $this->app->singleton(DecisionLinksService::class);
        $this->app->singleton(UserScopeService::class);
        $this->app->singleton(ContextForTaskService::class);
        $this->app->singleton(ScaffoldSkillFromMissionService::class);
    }

    protected function registerConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../Config/config.php' => config_path('ads.php'),
        ], 'config');

        $this->mergeConfigFrom(__DIR__ . '/../Config/config.php', 'ads');
    }

    protected function registerMiddleware(): void
    {
        /** @var Router $router */
        $router = $this->app['router'];
        $router->aliasMiddleware('ads.api', AdsApiAuth::class);
    }
}
