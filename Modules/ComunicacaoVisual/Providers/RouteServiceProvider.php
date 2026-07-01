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
    // NÃO declarar `protected $namespace`: o boot() do RouteServiceProvider do
    // Laravel chama setRootControllerNamespace($this->namespace), que polui o
    // root controller namespace GLOBAL do UrlGenerator e quebra toda
    // `action('App\Http\Controllers\...@metodo')` legada → HTTP 500.
    // Rotas usam FQCN [Controller::class, 'metodo'] — não precisam de namespace
    // de grupo. Ver memory/sessions — fix sell-return 500.

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
