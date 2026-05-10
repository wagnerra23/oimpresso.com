<?php

namespace Modules\ComunicacaoVisual\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

/**
 * RouteServiceProvider — Modules/ComunicacaoVisual.
 *
 * Mapeia Routes/web.php com middleware 'web'.
 * Sprint 1: apenas rotas Install. Rotas admin entram Sprint 2+.
 *
 * @see memory/requisitos/ComunicacaoVisual/SPEC.md
 */
class RouteServiceProvider extends ServiceProvider
{
    protected $namespace = 'Modules\\ComunicacaoVisual\\Http\\Controllers';

    public function boot(): void
    {
        parent::boot();
    }

    public function map(): void
    {
        $this->mapWebRoutes();
    }

    protected function mapWebRoutes(): void
    {
        Route::middleware('web')
            ->namespace($this->namespace)
            ->group(__DIR__ . '/../Routes/web.php');
    }
}
