<?php

// D8.a Security Wave 10 — throttle:60,1 (60 req/min/IP) em rotas Blade legacy
// AssetManagement. Auth web ja garante user logado; throttle limita abuso (ex:
// brute force destroy ou scraping DataTables ajax). Stack canonica UltimatePOS
// preservada apos throttle.
Route::middleware('throttle:60,1', 'web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu')->prefix('asset')->group(function () {
    Route::get('install', [Modules\AssetManagement\Http\Controllers\InstallController::class, 'index']);
    Route::post('install', [Modules\AssetManagement\Http\Controllers\InstallController::class, 'install']);
    Route::match(['get', 'post'], 'install/uninstall', [Modules\AssetManagement\Http\Controllers\InstallController::class, 'uninstall']);
    Route::match(['get', 'post'], 'install/update', [Modules\AssetManagement\Http\Controllers\InstallController::class, 'update']);

    // Sem `show`: os 4 metodos devolviam `view('assetmanagement::show')`, que nao existe, e
    // estouravam `View [show] not found` para qualquer usuario logado que digitasse a URL.
    // Nenhum link, botao ou action() da UI apontava para eles (medido: _saida-15.md do
    // playbook do Patrimonio). `except(['show'])` so tira o GET: o DELETE na mesma URI,
    // que Bens.tsx e Manutencoes.tsx usam, continua registrado.
    Route::resource('assets', Modules\AssetManagement\Http\Controllers\AssetController::class)->except(['show']);
    Route::resource('allocation', Modules\AssetManagement\Http\Controllers\AssetAllocationController::class)->except(['show']);
    Route::resource('revocation', Modules\AssetManagement\Http\Controllers\RevokeAllocatedAssetController::class)->except(['show']);
    // 'as'=>'asset' prefixa route names → asset.settings.{index,create,...}
    // Evita colisão com Route::resource('/settings', Manufacturing\SettingsController)
    // (route:cache falhava com "Another route has already been assigned name [settings.index]").
    //
    // So `index` e `store` (2026-09-30, thread 20 do playbook do Patrimonio). Configuracao e
    // UM registro por empresa (`business.asset_settings`): a tela React do indice ja e o
    // formulario e grava por POST /asset/settings. `create`/`show`/`edit` devolviam views que
    // nao existem (`View [x] not found`, medido na _saida-15.md) e `update`/`destroy` tinham
    // corpo vazio. Nenhum link, action() ou request da UI chegava neles. A ADR 0414 (D-FORMS)
    // manda a edicao para a Page do indice, e aqui ela ja e essa Page.
    Route::resource('settings', Modules\AssetManagement\Http\Controllers\AssetSettingsController::class, ['as' => 'asset'])->only(['index', 'store']);
    Route::get('dashboard', [Modules\AssetManagement\Http\Controllers\AssetController::class, 'dashboard']);

    Route::resource('asset-maintenance', 'Modules\AssetManagement\Http\Controllers\AssetMaitenanceController')->except(['show']);
});
