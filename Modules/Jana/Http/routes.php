<?php

Route::group(['middleware' => ['web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu'], 'prefix' => 'jana', 'namespace' => 'Modules\Jana\Http\Controllers'], function()
{
    Route::get('/api', 'JanaController@index');
    Route::resource('/client', 'ClientController');
    Route::get('/regenerate', 'ClientController@regenerate');

    Route::get('/jana', function () { return view('superadmin.iframe', ['url' => 'https://jana.wr2.com.br']);})->name('superadmin.jana');
    Route::get('/n8n', function () {  return view('superadmin.iframe', ['url' => 'https://n8n.wr2.com.br']); })->name('superadmin.n8n');
    Route::get('/flowise', function () { return view('superadmin.iframe', ['url' => 'https://flowise.wr2.com.br']);})->name('superadmin.flowise');
    Route::get('/docs', function () { return view('superadmin.iframe', ['url' => 'https://docs.officeimpresso.com.br']); })->name('superadmin.docs');	
});


Route::group(['middleware' => ['auth:api', 'timezone'], 'prefix' => 'jana/api', 'namespace' => 'Modules\Jana\Http\Controllers\Api'], function()
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

Route::group(['middleware' => ['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'], 'namespace' => 'Modules\Jana\Http\Controllers', 'prefix' => 'jana'], function () {
	Route::get('install', 'InstallController@index');
    Route::post('install', 'InstallController@install');
    Route::get('install/uninstall', 'InstallController@uninstall');
    Route::get('install/update', 'InstallController@update');
});