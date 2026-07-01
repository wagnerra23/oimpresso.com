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
    // NÃO declarar `protected $namespace`: o boot() do RouteServiceProvider do
    // Laravel chama setRootControllerNamespace($this->namespace), que polui o
    // root controller namespace GLOBAL do UrlGenerator. O último módulo a bootar
    // "vencia" e quebrava toda `action('App\Http\Controllers\...@metodo')` legada
    // (string sem `\` inicial) → HTTP 500 (ex: DataTable de /sell-return).
    // Rotas deste módulo usam FQCN [Controller::class, 'metodo'], então não
    // precisam de namespace de grupo. Ver memory/sessions — fix sell-return 500.

    public function map(): void
    {
        $this->mapWebRoutes();
    }

    protected function mapWebRoutes(): void
    {
        Route::middleware('web')
            ->group(__DIR__ . '/../Routes/web.php');
    }
}
