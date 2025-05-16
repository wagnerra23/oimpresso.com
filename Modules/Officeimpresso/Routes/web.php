<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/



Route::middleware('web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu')->prefix('officeimpresso')->group(function () {


    // Route::get('catalogue-qr', [\Modules\Officeimpresso\Http\Controllers\OfficeimpressoController::class, 'generateQr']);
    // Route::get('/catalogue/{business_id}/{location_id}', [\Modules\Officeimpresso\Http\Controllers\OfficeimpressoController::class, 'index']);
    // Route::get('/show-catalogue/{business_id}/{product_id}', [\Modules\Officeimpresso\Http\Controllers\OfficeimpressoController::class, 'show']);


    Route::get('install', [\Modules\Officeimpresso\Http\Controllers\InstallController::class, 'index']);
    Route::post('install', [\Modules\Officeimpresso\Http\Controllers\InstallController::class, 'install']);
    Route::get('install/uninstall', [\Modules\Officeimpresso\Http\Controllers\InstallController::class, 'uninstall']);
    Route::get('install/update', [\Modules\Officeimpresso\Http\Controllers\InstallController::class, 'update']);
});
