<?php

namespace Modules\Marketplaces\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    // NÃO declarar `protected $namespace`: polui o root controller namespace GLOBAL do
    // UrlGenerator. Rotas usam FQCN [Controller::class, 'metodo'].

    public function map(): void
    {
        Route::middleware('web')->group(__DIR__ . '/../Routes/web.php');
    }
}
