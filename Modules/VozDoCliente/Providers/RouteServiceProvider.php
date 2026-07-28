<?php

namespace Modules\VozDoCliente\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    // NÃO declarar `protected $namespace`: o boot() do RouteServiceProvider do
    // Laravel chama setRootControllerNamespace($this->namespace), que polui o
    // root controller namespace GLOBAL do UrlGenerator e quebra toda
    // `action('App\Http\Controllers\...@metodo')` legada → HTTP 500.
    // Rotas usam FQCN [Controller::class, 'metodo'].

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
