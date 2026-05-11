<?php

namespace Modules\OficinaAuto\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

/**
 * RouteServiceProvider — Modules/OficinaAuto.
 *
 * Mapeia Routes/web.php com middleware 'web'.
 * V0: rotas Install + CRUD Vehicle + CRUD ServiceOrder.
 *
 * @see memory/requisitos/OficinaAuto/SPEC.md
 * @see memory/decisions/0137-modules-oficinaauto-qualificada.md
 */
class RouteServiceProvider extends ServiceProvider
{
    protected $namespace = 'Modules\\OficinaAuto\\Http\\Controllers';

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
