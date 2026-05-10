<?php

use Modules\Auditoria\Http\Controllers\AuditoriaController;
use Modules\Auditoria\Http\Controllers\InstallController;

/**
 * Rotas /auditoria — UI Inertia rica de governanca + undo (per ADR 0127).
 *
 * Stack canonica UltimatePOS: ['web','SetSessionData','auth','language',
 * 'timezone','AdminSidebarMenu','CheckUserLogin'] (ver memory/proibicoes.md).
 *
 * Permissoes Spatie:
 *   - auditoria.view             : ler /auditoria
 *   - auditoria.revert.own       : reverter ação propria <=24h
 *   - auditoria.revert.any       : reverter qualquer acao <=30d (admin)
 *   - auditoria.revert.unlimited : reverter sem limite (superadmin)
 *
 * Per ADR 0127 §princípio 5.
 */
Route::middleware([
    'web',
    'SetSessionData',
    'auth',
    'language',
    'timezone',
    'AdminSidebarMenu',
    'CheckUserLogin',
])->prefix('auditoria')->name('auditoria.')->group(function () {

    Route::get('/', [AuditoriaController::class, 'index'])->name('index');
    Route::get('/{activityId}', [AuditoriaController::class, 'show'])
        ->where('activityId', '[0-9]+')
        ->name('show');

    // POST de revert exige razao >= 10 chars (validation no Controller)
    Route::post('/{activityId}/revert', [AuditoriaController::class, 'revert'])
        ->where('activityId', '[0-9]+')
        ->name('revert');
});

/*
 * Redirect 301 da rota legacy /reports/activity-log -> /auditoria
 * Mantem querystring (filtros). Per ADR 0127 §F3.
 */
Route::get('/reports/activity-log', function () {
    $qs = request()->getQueryString();
    return redirect('/auditoria'.($qs ? '?'.$qs : ''), 301);
});

// Rotas Install 1-click (ADR 0024) — sem elas o botao "Install" da tela
// /manage-modules fica sem action.
Route::middleware(['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'])
    ->prefix('auditoria')
    ->group(function () {
        Route::get('install',           [InstallController::class, 'index']);
        Route::get('install/uninstall', [InstallController::class, 'uninstall']);
        Route::get('install/update',    [InstallController::class, 'update']);
    });
