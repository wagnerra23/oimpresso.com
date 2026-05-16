<?php

namespace Modules\Admin\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Admin\Http\Middleware\IsWagner;
use Modules\Admin\Http\Middleware\TailscaleOnly;

/**
 * ServiceProvider do módulo Admin (Centro de Operações Wagner-only).
 *
 * Sprint 1 — ADR 0122 (Admin Center @ CT 100, Tailscale-only).
 *
 * Boot:
 * - registra middlewares `tailscale-only` e `is-wagner`
 * - carrega rotas web (3 rotas Install + futuro /admin)
 * - carrega migrations (mcp_admin_audit_log)
 *
 * @see memory/decisions/0122-admin-center-ct100.md
 */
class AdminServiceProvider extends ServiceProvider
{
    /**
     * @var bool
     */
    protected $defer = false;

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Modules\Admin\Console\Commands\AdminHealthCommand::class,
            ]);
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../Config/config.php', 'admin'
        );

        $router = $this->app['router'];
        $router->aliasMiddleware('tailscale-only', TailscaleOnly::class);
        $router->aliasMiddleware('is-wagner', IsWagner::class);
    }
}
