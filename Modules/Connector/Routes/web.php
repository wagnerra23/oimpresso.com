<?php

Route::middleware('web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin')->prefix('connector')->group(function () {
    Route::get('install', [Modules\Connector\Http\Controllers\InstallController::class, 'index']);
    Route::post('install', [Modules\Connector\Http\Controllers\InstallController::class, 'install']);
    Route::get('install/uninstall', [Modules\Connector\Http\Controllers\InstallController::class, 'uninstall']);
    Route::get('install/update', [Modules\Connector\Http\Controllers\InstallController::class, 'update']);
});

Route::middleware('web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu')->prefix('connector')->group(function () {
    Route::get('/api', [Modules\Connector\Http\Controllers\ConnectorController::class, 'index']);
    // 'as'=>'connector' prefixa route names → connector.client.{index,create,...}
    // Evita colisão com Route::resource('client', Officeimpresso\ClientController) — ambos OAuth clients management
    // (route:cache falhava com "Another route has already been assigned name [client.index]").
    Route::resource('/client', 'Modules\Connector\Http\Controllers\ClientController', ['as' => 'connector']);
    Route::get('/regenerate', [Modules\Connector\Http\Controllers\ClientController::class, 'regenerate']);
});