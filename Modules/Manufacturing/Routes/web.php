<?php

// D8.a Security Wave 14 — throttle:60,1 (60 req/min/IP) em rotas Blade legacy
// Manufacturing. Auth web ja garante user logado; throttle limita abuso (brute
// force destroy, scraping de DataTables ajax /get-ingredient-row, etc).
// Stack canonica UltimatePOS preservada (web/CSRF/SetSessionData/auth/AdminSidebarMenu).
// NUNCA desligar CSRF do grupo `web` — token enforced pelo VerifyCsrfToken middleware.
Route::middleware('throttle:60,1', 'web', 'authh', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu')->prefix('manufacturing')->group(function () {
    Route::get('/install', [Modules\Manufacturing\Http\Controllers\InstallController::class, 'index']);
    Route::post('/install', [Modules\Manufacturing\Http\Controllers\InstallController::class, 'install']);
    Route::get('/install/update', [Modules\Manufacturing\Http\Controllers\InstallController::class, 'update']);
    Route::get('/install/uninstall', [Modules\Manufacturing\Http\Controllers\InstallController::class, 'uninstall']);

    Route::get('/is-recipe-exist/{variation_id}', [Modules\Manufacturing\Http\Controllers\RecipeController::class, 'isRecipeExist']);
    Route::get('/ingredient-group-form', [Modules\Manufacturing\Http\Controllers\RecipeController::class, 'getIngredientGroupForm']);
    Route::get('/get-recipe-details', [Modules\Manufacturing\Http\Controllers\RecipeController::class, 'getRecipeDetails']);
    Route::get('/get-ingredient-row/{variation_id}', [Modules\Manufacturing\Http\Controllers\RecipeController::class, 'getIngredientRow']);
    Route::get('/add-ingredient', [Modules\Manufacturing\Http\Controllers\RecipeController::class, 'addIngredients']);
    Route::resource('/recipe', 'Modules\Manufacturing\Http\Controllers\RecipeController')->except('edit', 'update');
    Route::resource('/production', 'Modules\Manufacturing\Http\Controllers\ProductionController');

    Route::resource('/settings', 'Modules\Manufacturing\Http\Controllers\SettingsController')->only('index', 'store');

    // US-MANU-005 — Insumos (impacto reverso + simulador). 100% leitura.
    // Nasceu em `/v2/insumos` e passou ao endereço canônico no cutover de 2026-09-04.
    Route::get('/insumos', [Modules\Manufacturing\Http\Controllers\RecipeController::class, 'insumos'])
        ->name('manufacturing.insumos.index');

    /*
     * CUTOVER 2026-09-04 — pedido [F]: "módulo inteiro em produção, com os links e vínculos
     * reais, sem rotas alternativas".
     *
     * As telas React passaram a ser servidas nos ENDEREÇOS CANÔNICOS (`/production`,
     * `/report`, `/settings`, `/insumos`, e `/recipe` desde a US-MANU-001). Os `/v2/*` eram
     * o andaime da migração: viram REDIRECT permanente pro canônico em vez de sumir, porque
     * link salvo, favorito e histórico do time apontam pra eles — 301 preserva isso sem
     * manter dois destinos vivos.
     *
     * Os métodos `indexV2`/`reportV2`/`indexV2` (Settings) continuam sendo a implementação
     * única: o método canônico DELEGA pra eles. O que sumiu foi o segundo ENDEREÇO, não o
     * segundo código.
     */
    Route::permanentRedirect('/v2/production', '/manufacturing/production');
    Route::permanentRedirect('/v2/report', '/manufacturing/report');
    Route::permanentRedirect('/v2/settings', '/manufacturing/settings');
    Route::permanentRedirect('/v2/insumos', '/manufacturing/insumos');

    // Nome adicionado no cutover 2026-09-04: a rota existia SEM nome (nada a referenciava
    // por nome), e o charter da tela precisa apontar pra um `rota_nome` que exista.
    Route::get('/report', [Modules\Manufacturing\Http\Controllers\ProductionController::class, 'getManufacturingReport'])
        ->name('manufacturing.report.index');

    Route::post('/update-product-prices', [Modules\Manufacturing\Http\Controllers\RecipeController::class, 'updateRecipeProductPrices']);
});
