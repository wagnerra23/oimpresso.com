<?php

// D8.a Security Wave 10 — throttle:30,1 em rotas Install (sensitivas: instalar/desinstalar
// modulo). 30 req/min/IP suficiente pra fluxo humano superadmin.
Route::middleware('throttle:30,1', 'web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin')->prefix('connector')->group(function () {
    Route::get('install', [Modules\Connector\Http\Controllers\InstallController::class, 'index']);
    Route::post('install', [Modules\Connector\Http\Controllers\InstallController::class, 'install']);
    Route::get('install/uninstall', [Modules\Connector\Http\Controllers\InstallController::class, 'uninstall']);
    Route::get('install/update', [Modules\Connector\Http\Controllers\InstallController::class, 'update']);
});

// D8.a Security Wave 10 — throttle:60,1 em UI Client management (OAuth clients).
Route::middleware('throttle:60,1', 'web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu')->prefix('connector')->group(function () {
    Route::get('/api', [Modules\Connector\Http\Controllers\ConnectorController::class, 'index']);
    // 'as'=>'connector' prefixa route names → connector.client.{index,create,...}
    // Evita colisão com Route::resource('client', Officeimpresso\ClientController) — ambos OAuth clients management
    // (route:cache falhava com "Another route has already been assigned name [client.index]").
    Route::resource('/client', 'Modules\Connector\Http\Controllers\ClientController', ['as' => 'connector']);
    Route::get('/regenerate', [Modules\Connector\Http\Controllers\ClientController::class, 'regenerate']);
});