<?php

Route::get('/pricing', 'Modules\Superadmin\Http\Controllers\PricingController@index')->name('pricing');

Route::group(['middleware' => ['web', 'auth', 'language', 'AdminSidebarMenu', 'superadmin'], 'prefix' => 'superadmin', 'namespace' => 'Modules\Superadmin\Http\Controllers'], function () {
    Route::get('/install', 'InstallController@index');
    Route::get('/install/update', 'InstallController@update');
    Route::get('/install/uninstall', 'InstallController@uninstall');

    Route::get('/', 'SuperadminController@index');
    Route::get('/stats', 'SuperadminController@stats');
    
    Route::get('/{business_id}/toggle-active/{is_active}', 'BusinessController@toggleActive');

    Route::get('/users/{business_id}', 'BusinessController@usersList');
    Route::post('/update-password', 'BusinessController@updatePassword');


    Route::resource('/business', 'BusinessController');
    Route::get('/business/{id}/destroy', 'BusinessController@destroy');

    Route::resource('/packages', 'PackagesController');
    Route::get('/packages/{id}/destroy', 'PackagesController@destroy');

    Route::get('/settings', 'SuperadminSettingsController@edit');
    Route::put('/settings', 'SuperadminSettingsController@update');
    Route::get('/edit-subscription/{id}', 'SuperadminSubscriptionsController@editSubscription');
    Route::post('/update-subscription', 'SuperadminSubscriptionsController@updateSubscription');
    Route::resource('/superadmin-subscription', 'SuperadminSubscriptionsController');

    Route::get('/communicator', 'CommunicatorController@index');
    Route::post('/communicator/send', 'CommunicatorController@send');
    Route::get('/communicator/get-history', 'CommunicatorController@getHistory');

    Route::resource('/frontend-pages', 'PageController');

    // Abrir em pagina nova, Fica no Superadmin 
    Route::get('/portainer', function () {
        return redirect()->away('http://jana.wr2.com.br:9000/#!/home');
    })->name('superadmin.portainer');
    
    Route::get('/painel', function () {
        return redirect()->away('https://painel.wr2.com.br:7800/1f7980e1');
    })->name('superadmin.painel');
    
});

Route::group(['middleware' => ['web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu'],
    'namespace' => 'Modules\Superadmin\Http\Controllers'], function () {
        //Routes related to paypal checkout
        Route::get(
            '/subscription/{package_id}/paypal-express-checkout',
            'SubscriptionController@paypalExpressCheckout'
    );

        //Routes related to pesapal checkout
        Route::get('/subscription/{package_id}/pesapal-callback', ['as' => 'pesapalCallback', 'uses'=>'SubscriptionController@pesapalCallback']);

        Route::get('/subscription/{package_id}/pay', 'SubscriptionController@pay');
        Route::any('/subscription/{package_id}/confirm', 'SubscriptionController@confirm')->name('subscription-confirm');
        Route::get('/all-subscriptions', 'SubscriptionController@allSubscriptions');

        Route::get('/subscription/{package_id}/register-pay', 'SubscriptionController@registerPay')->name('register-pay');

        Route::resource('/subscription', 'SubscriptionController');
    });

Route::get('/page/{slug}', 'Modules\Superadmin\Http\Controllers\PageController@showPage')->name('frontend-pages');
