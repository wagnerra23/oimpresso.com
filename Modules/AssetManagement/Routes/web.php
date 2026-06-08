<?php

// D8.a Security Wave 10 — throttle:60,1 (60 req/min/IP) em rotas Blade legacy
// AssetManagement. Auth web ja garante user logado; throttle limita abuso (ex:
// brute force destroy ou scraping DataTables ajax). Stack canonica UltimatePOS
// preservada apos throttle.
Route::middleware('throttle:60,1', 'web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu')->prefix('asset')->group(function () {
    Route::get('install', [Modules\AssetManagement\Http\Controllers\InstallController::class, 'index']);
    Route::post('install', [Modules\AssetManagement\Http\Controllers\InstallController::class, 'install']);
    Route::get('install/uninstall', [Modules\AssetManagement\Http\Controllers\InstallController::class, 'uninstall']);
    Route::get('install/update', [Modules\AssetManagement\Http\Controllers\InstallController::class, 'update']);

    Route::resource('assets', Modules\AssetManagement\Http\Controllers\AssetController::class);
    Route::resource('allocation', Modules\AssetManagement\Http\Controllers\AssetAllocationController::class);
    Route::resource('revocation', Modules\AssetManagement\Http\Controllers\RevokeAllocatedAssetController::class);
    // 'as'=>'asset' prefixa route names → asset.settings.{index,create,...}
    // Evita colisão com Route::resource('/settings', Manufacturing\SettingsController)
    // (route:cache falhava com "Another route has already been assigned name [settings.index]").
    Route::resource('settings', Modules\AssetManagement\Http\Controllers\AssetSettingsController::class, ['as' => 'asset']);
    Route::get('dashboard', [Modules\AssetManagement\Http\Controllers\AssetController::class, 'dashboard']);

    Route::resource('asset-maintenance', 'Modules\AssetManagement\Http\Controllers\AssetMaitenanceController');
});
