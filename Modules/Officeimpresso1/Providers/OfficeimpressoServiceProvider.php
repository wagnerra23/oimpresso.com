<?php

namespace Modules\Officeimpresso\Providers;

use Illuminate\Database\Eloquent\Factory;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use App\Utils\ModuleUtil;
use App\Utils\Util;

class OfficeimpressoServiceProvider extends ServiceProvider
{
    /**
     * Boot the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->registerFactories();
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->registerScheduleCommands();
        
        View::composer(
            ['officeimpresso::layouts.nav'],
            function ($view) {
                $commonUtil = new Util();
                $is_admin = $commonUtil->is_admin(auth()->user(), auth()->user()->business_id);
                $view->with('__is_admin', $is_admin);
            }
        );

        // view::composer(['officeimpresso::layouts.partials.header_part',
        //     'report.profit_loss', ], function ($view) {
        //         $module_util = new ModuleUtil();

        //         if (auth()->user()->can('superadmin')) {
        //             $__is_officeimpresso_enabled = $module_util->isModuleInstalled('Officeimpresso');
        //         } else {
        //             $business_id = session()->get('user.business_id');
        //             $__is_officeimpresso_enabled = (bool) $module_util->hasThePermissionInSubscription($business_id, 'officeimpresso_module');
        //         }

        //         $view->with(compact('__is_officeimpresso_enabled'));
        //     });

            // view::composer(['officeimpresso::layouts.partials.header_part'], function ($view) {
            //     $is_employee_allowed = false;
            //     $clock_in = null;
    
            //     $module_util = new ModuleUtil();
            //     if ($module_util->isModuleInstalled('Officeimpresso')) {
            //         $business_id = session()->get('user.business_id');
    
            //         //Check if employee are allowed or not to enter own attendance.
            //         $is_employee_allowed = auth()->user()->can('officeimpresso.allow_users_for_attendance_from_web');
    
            //         //Check if clocked in or not.
            //         $clock_in = EssentialsAttendance::where('officeimpresso_attendances.business_id', $business_id)
            //                         ->leftjoin('officeimpresso_shifts as es', 'es.id', '=', 'officeimpresso_attendances.officeimpresso_shift_id')
            //                         ->where('user_id', auth()->user()->id)
            //                         ->whereNull('clock_out_time')
            //                         ->select([
            //                             'clock_in_time', 'es.name as shift_name', 'es.start_time', 'es.end_time',
            //                         ])
            //                         ->first();
            //     }
    
            //     $view->with(compact('is_employee_allowed', 'clock_in'));
            // });
        
        //     view::composer(['officeimpresso::attendance.clock_in_clock_out_modal',
        //     'officeimpresso::attendance.create', ], function ($view) {
        //         $util = new \App\Utils\Util();
        //         $ip_address = $util->getUserIpAddr();

        //         $settings = session()->get('business.officeimpresso_settings');
        //         $settings = ! empty($settings) ? json_decode($settings, true) : [];
        //         $is_location_required = ! empty($settings['is_location_required']) ? true : false;

        //         $view->with(compact('ip_address', 'is_location_required'));
        //     });

        // $this->registerScheduleCommands();
    
    }


    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->register(RouteServiceProvider::class);       
        $this->registerCommands();
    }

    /**
     * Register config.
     *
     * @return void
     */
    protected function registerConfig()
    {
        $this->publishes([
            __DIR__.'/../Config/config.php' => config_path('officeimpresso.php'),
        ], 'config');
        $this->mergeConfigFrom(
            __DIR__.'/../Config/config.php', 'officeimpresso'
        );
    }

    /**
     * Register views.
     *
     * @return void
     */
    public function registerViews()
    {
        $viewPath = resource_path('views/modules/officeimpresso');

        $sourcePath = __DIR__.'/../Resources/views';

        $this->publishes([
            $sourcePath => $viewPath,
        ], 'views');

        $this->loadViewsFrom(array_merge(array_map(function ($path) {
            return $path.'/modules/officeimpresso';
        }, config('view.paths')), [$sourcePath]), 'officeimpresso');
    }

    /**
     * Register translations.
     *
     * @return void
     */
    public function registerTranslations()
    {
        $langPath = resource_path('lang/modules/officeimpresso');

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, 'officeimpresso');
        } else {
            $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'officeimpresso');
        }
    }

    /**
     * Register an additional directory of factories.
     *
     * @return void
     */
    public function registerFactories()
    {
        if (! app()->environment('production') && $this->app->runningInConsole()) {
            app(Factory::class)->load(__DIR__.'/../Database/factories');
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [];
    }

    /**
     * Register commands.
     *
     * @return void
     */
    protected function registerCommands()
    {

    }

    public function registerScheduleCommands()
    {

    }
}
