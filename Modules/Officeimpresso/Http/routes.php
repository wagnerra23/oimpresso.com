<?php

Route::group([
    'middleware' => ['web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu'],
    'prefix' => 'officeimpresso',
    'namespace' => 'Modules\Officeimpresso\Http\Controllers'], function() {
    Route::get('/api', 'OfficeimpressoController@index');
    Route::resource('client', 'ClientController');
    Route::get('/regenerate', 'ClientController@regenerate');

    Route::resource('licenca_computador', 'LicencaComputadorController'); // Rota para todas as operações com licenças
    Route::get('businessall', 'LicencaComputadorController@businessall'); // Rota adicional para visualizar todas as licenças  
    Route::get('computadores', 'LicencaComputadorController@computadores');
    Route::get('/licenca_computador/{id}/toggle-block', 'LicencaComputadorController@toggleBlock')->name('licenca_computador.toggleBlock');

    Route::post('/licenca_computador/businessupdate/{id}', 'LicencaComputadorController@businessupdate')->name('business.update');
    Route::get('/licenca_computador/businessbloqueado/{id}', 'LicencaComputadorController@businessbloqueado')->name('business.bloqueado');
    Route::get('/licenca_computado/licencas/{id}', 'LicencaComputadorController@viewLicencas')->name('empresa.licencas');


    Route::resource('licenca_log', 'LicencaLogController');

    Route::get('/docs', function () {
        return view('superadmin.iframe', ['url' => 'https://docs.officeimpresso.com.br']);
    })->name('superadmin.docs');  
});

Route::group([
    'middleware' => ['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'],
    'prefix' => 'officeimpresso',
    'namespace' => 'Modules\Officeimpresso\Http\Controllers'
], function () {
    Route::get('install', 'InstallController@index');
    Route::post('install', 'InstallController@install');
    Route::get('install/uninstall', 'InstallController@uninstall');
    Route::get('install/update', 'InstallController@update');
});
