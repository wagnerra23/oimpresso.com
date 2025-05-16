<?php

namespace Modules\Grow\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The module namespace to assume when generating URLs to actions.
     *
     * @var string
     */
    protected $moduleNamespace = 'Modules\Grow\Http\Controllers';

    /**
     * Called before routes are registered.
     *
     * Register any model bindings or pattern based filters.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();
    }

    /**
     * Define the routes for the application.
     *
     * @return void
     */
    public function map()
    {
        $this->mapApiRoutes();

        $this->mapWebRoutes();
    }

    /**
     * Define the "web" routes for the application.
     *
     * These routes all receive session state, CSRF protection, etc.
     *
     * @return void
     */
    protected function mapWebRoutes()
    {
        Route::middleware('web')
            ->namespace($this->moduleNamespace)
            ->group(module_path('Grow', '/Routes/web.php'));
    }

    // protected function mapWebRoutes() {
    //     Route::middleware('web')
    //         ->namespace($this->namespace)
    //         ->group(
    //             function ($router) {
    //                 require base_path('routes/web.php');
    //                 require base_path('routes/custom/web.php');
    //             }
    //         );
    // }


    /**
     * Define the "api" routes for the application.
     *
     * These routes are typically stateless.
     *
     * @return void
     */
    protected function mapApiRoutes()
    {
        Route::prefix('api')
            ->middleware('api')
            ->namespace($this->moduleNamespace)
            ->group(module_path('Grow', '/Routes/api.php'));
    }

    // protected function mapApiRoutes() {
    //     Route::prefix('api')
    //         ->middleware('api')
    //         ->namespace($this->namespace)
    //         ->group(
    //             function ($router) {
    //                 require base_path('routes/custom/api.php');
    //                 require base_path('routes/api.php');
    //             }
    //         );
    // }





    
}
