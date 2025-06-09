<?php

Route::group(['middleware' => ['web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu'], 'prefix' => 'chat', 'namespace' => 'Modules\Chat\Http\Controllers'], function()
{
    Route::get('/api', 'ChatController@index');
    Route::resource('/client', 'ClientController');
    Route::get('/regenerate', 'ClientController@regenerate');

	Route::get('/evolution-api', function () {
		return view('superadmin.iframe', ['url' => 'https://evolutionapi.wr2.com.br/manager']);
	})->name('superadmin.evolution-api');
	
    Route::get('/typebot', function () {
        return view('superadmin.iframe', ['url' => 'https://typebot.wr2.com.br']);
    })->name('superadmin.typebot');

    Route::get('/minio', function () {
        return view('superadmin.iframe', ['url' => 'https://minio.wr2.com.br/login']);
    })->name('superadmin.minio');

    Route::get('/conversas', function () {
        return view('superadmin.iframe', ['url' => 'https://chat.wr2.com.br/app/accounts/1/dashboard']);
    })->name('superadmin.conversas');

    Route::get('/contatos', function () {
        return view('superadmin.iframe', ['url' => 'https://chat.wr2.com.br/app/accounts/1/contacts?page=1']);
    })->name('superadmin.contatos');

    Route::get('/relatorios', function () {
        return view('superadmin.iframe', ['url' => 'https://chat.wr2.com.br/app/accounts/1/reports/overview']);
    })->name('superadmin.relatorios');

    Route::get('/campanhas', function () {
        return view('superadmin.iframe', ['url' => 'https://chat.wr2.com.br/app/accounts/1/campaigns/ongoing']);
    })->name('superadmin.campanhas');

    Route::get('/central-de-ajuda', function () {
        return view('superadmin.iframe', ['url' => 'https://chat.wr2.com.br/app/accounts/1/portals/all']);
    })->name('superadmin.central-de-ajuda');

    Route::get('/configuracoes', function () {
        return view('superadmin.iframe', ['url' => 'https://chat.wr2.com.br/app/accounts/1/settings/general']);
    })->name('superadmin.configuracoes');

    Route::get('/perfil', function () {
        return view('superadmin.iframe', ['url' => 'https://chat.wr2.com.br/aapp/accounts/1/profile/settings']);
    })->name('superadmin.perfil');	
});


Route::group(['middleware' => ['auth:api', 'timezone'], 'prefix' => 'chat/api', 'namespace' => 'Modules\Chat\Http\Controllers\Api'], function()
{
	Route::resource('business-location', 'BusinessLocationController', ['only' => ['index', 'show']]);

	Route::resource('contactapi', 'ContactController', ['only' => ['index', 'show', 'store', 'update']]);

	Route::post('contactapi-payment', 'ContactController@contactPay');

	Route::resource('unit', 'UnitController', ['only' => ['index', 'show']]);

	Route::resource('taxonomy', 'CategoryController', ['only' => ['index', 'show']]);

	Route::resource('brand', 'BrandController', ['only' => ['index', 'show']]);

	Route::resource('product', 'ProductController', ['only' => ['index', 'show']]);

	Route::get('selling-price-group', 'ProductController@getSellingPriceGroup');

	Route::get('variation/{id?}', 'ProductController@listVariations');

	Route::resource('tax', 'TaxController', ['only' => ['index', 'show']]);

	Route::resource('table', 'TableController', ['only' => ['index', 'show']]);

    Route::get('user/loggedin', 'UserController@loggedin');
	Route::resource('user', 'UserController', ['only' => ['index', 'show']]);

	Route::resource('types-of-service', 'TypesOfServiceController', ['only' => ['index', 'show']]);

	Route::get('payment-accounts', 'CommonResourceController@getPaymentAccounts');

	Route::get('payment-methods', 'CommonResourceController@getPaymentMethods');

	Route::resource('sell', 'SellController', ['only' => ['index', 'store', 'show', 'update', 'destroy']]);

	Route::post('sell-return', 'SellController@addSellReturn');

	Route::get('list-sell-return', 'SellController@listSellReturn');
	
	Route::post('update-shipping-status', 'SellController@updateSellShippingStatus');

	Route::resource('expense', 'ExpenseController', ['only' => ['index', 'store', 'show', 'update']]);
	Route::get('expense-refund', 'ExpenseController@listExpenseRefund');

	Route::resource('cash-register', 'CashRegisterController', ['only' => ['index', 'store', 'show', 'update']]);

	Route::get('business-details', 'CommonResourceController@getBusinessDetails');

	Route::get('profit-loss-report', 'CommonResourceController@getProfitLoss');

	Route::get('product-stock-report', 'CommonResourceController@getProductStock');
	Route::get('notifications', 'CommonResourceController@getNotifications');

	Route::get('active-subscription', 'SuperadminController@getActiveSubscription');
	Route::get('packages', 'SuperadminController@getPackages');

	Route::get('get-attendance/{user_id}', 'AttendanceController@getAttendance');
	Route::post('clock-in', 'AttendanceController@clockin');
	Route::post('clock-out', 'AttendanceController@clockout');
	Route::get('holidays', 'AttendanceController@getHolidays');
	Route::post('update-password', 'UserController@updatePassword');
});

Route::group(['middleware' => ['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'], 'namespace' => 'Modules\Chat\Http\Controllers', 'prefix' => 'chat'], function () {
	Route::get('install', 'InstallController@index');
    Route::post('install', 'InstallController@install');
    Route::get('install/uninstall', 'InstallController@uninstall');
    Route::get('install/update', 'InstallController@update');
});