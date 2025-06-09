<?php

namespace App\Providers;

use Inertia\Inertia;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use App\System;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->registerInertia();
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Schema::defaultStringLength(191);
        View::composer(
            ['layouts.app', 'app', 'layouts.partials.doc_search'],
            function ($view) {
                $__gcse_html = System::getProperty('gcse_html');
                $__gcse_js = System::getProperty('gcse_js');
                $view->with(compact('__gcse_html', '__gcse_js'));
            }
        );
    }

    public function registerInertia()
    {
        Inertia::version(function () {
            return md5_file(public_path('mix-manifest.json'));
        });

        Inertia::share([
            'auth' => function () {
                return [
                    'user' => Auth::user() ? [
                        'id' => Auth::user()->id,
                        'name' => Auth::user()->name,                        
                        'email' => Auth::user()->email,
                        'role' => Auth::user()->role
                    ] : null,
                ];
            },
            'flash' => function () {
                return [
                    'success' => Session::get('success'),
                    'error' => Session::get('error'),
                ];
            },
            'status' => function () {
                return Session()->get('status') ? Session()->get('status') : (object) [];
            },
            'errors' => function () {
                return Session::get('errors')
                    ? Session::get('errors')->getBag('default')->getMessages()
                    : (object) [];
            },
            'is_public_ticket_enabled' => function() {

                $is_public_ticket_enabled = System::getProperty('is_public_ticket_enabled');
                
                return $is_public_ticket_enabled;
            },
            'support_current_datetime' => function() {
                return Carbon::now()->format('d/m/Y h:i A');
            },
            'gcse_html' => function() {

                $gcse_html = System::getProperty('gcse_html');
                
                return $gcse_html;
            },
            'support_timing' => function() {
                $support_timing = System::getProperty('support_timing');
                if(!empty($support_timing)) {
                    return json_decode($support_timing, true);
                } else {
                    return [];
                }
            },
            'is_enabled_support_timing' => function() {
                $is_enabled_support_timing = System::getProperty('enable_support_timing');
                if(!empty($is_enabled_support_timing)) {
                    return (bool)$is_enabled_support_timing;
                } else {
                    return false;
                }
            },
            'custom_fields' => function() {
                $custom_fields = System::getProperty('custom_fields');
                if(!empty($custom_fields)) {
                    $custom_fields = json_decode($custom_fields, true);
                    foreach ($custom_fields as $key => $custom_field) {
                        if(!isset($custom_field['filled_by']) && empty($custom_field['filled_by'])) {
                            $custom_fields[$key]['filled_by'] = 'customer';
                        }
                    }
                    return $custom_fields;
                } else {
                    return [];
                }
            }
        ]);
    }
}
