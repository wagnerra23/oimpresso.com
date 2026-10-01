<?php

// D8.a Security Wave 10 — throttle:30,1 em rotas Install (sensitivas: instalar/desinstalar
// modulo). 30 req/min/IP suficiente pra fluxo humano superadmin.
Route::middleware('throttle:30,1', 'web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin')->prefix('connector')->group(function () {
    // CONN-O2: o GET so mostra confirmacao (sem efeito); a acao roda no POST com CSRF.
    // Instalar/atualizar regeram as chaves OAuth (passport:install --force).
    Route::get('install', [Modules\Connector\Http\Controllers\InstallController::class, 'index']);
    Route::post('install', [Modules\Connector\Http\Controllers\InstallController::class, 'install']);
    Route::match(['get', 'post'], 'install/uninstall', [Modules\Connector\Http\Controllers\InstallController::class, 'uninstall']);
    Route::match(['get', 'post'], 'install/update', [Modules\Connector\Http\Controllers\InstallController::class, 'update']);
});

// D8.a Security Wave 10 — throttle:60,1 em UI Client management (OAuth clients).
Route::middleware('throttle:60,1', 'web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu')->prefix('connector')->group(function () {
    Route::get('/api', [Modules\Connector\Http\Controllers\ConnectorController::class, 'index']);
    // 'as'=>'connector' prefixa route names → connector.client.{index,create,...}
    // Evita colisão com Route::resource('client', Officeimpresso\ClientController) — ambos OAuth clients management
    // (route:cache falhava com "Another route has already been assigned name [client.index]").
    Route::resource('/client', 'Modules\Connector\Http\Controllers\ClientController', ['as' => 'connector']);
    // CONN-O4 · [W] D4 (2026-08-19): a rota GET de regenerar chaves saiu (rodava
    // passport:install --force e derrubava a integracao de todos os negocios). Regenerar
    // chave e operacao de servidor, nao de tela.
});