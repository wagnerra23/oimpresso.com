<?php

use Illuminate\Support\Facades\Route;
use Modules\ADS\Http\Controllers\Admin\DecisoesController;
use Modules\ADS\Http\Controllers\Admin\PolicyController;
use Modules\ADS\Http\Controllers\Admin\ConfidenceController;
use Modules\ADS\Http\Controllers\Admin\MetricasController;
use Modules\ADS\Http\Controllers\Admin\PatternsController;
use Modules\ADS\Http\Controllers\InstallController;

// Rotas de instalação 1-click (via /manage-modules → botão Install)
// Pattern: ADR 0024 / feedback_pattern_install_modulos
Route::middleware(['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'])
    ->prefix('ads')
    ->group(function () {
        Route::get('install',           [InstallController::class, 'index']);
        Route::get('install/uninstall', [InstallController::class, 'uninstall']);
        Route::get('install/update',    [InstallController::class, 'update']);
    });

Route::group([
    'middleware' => ['web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'],
    'prefix'     => 'ads',
], function () {
    // Inbox de decisions
    Route::get('/admin/decisoes',                  [DecisoesController::class, 'index'])
        ->name('ads.admin.decisoes.index');
    Route::get('/admin/decisoes/{id}',             [DecisoesController::class, 'show'])
        ->whereNumber('id')
        ->name('ads.admin.decisoes.show');
    Route::post('/admin/decisoes/{id}/approve',    [DecisoesController::class, 'approve'])
        ->whereNumber('id')
        ->name('ads.admin.decisoes.approve');
    Route::post('/admin/decisoes/{id}/reject',     [DecisoesController::class, 'reject'])
        ->whereNumber('id')
        ->name('ads.admin.decisoes.reject');
    Route::post('/admin/decisoes/{id}/dismiss',    [DecisoesController::class, 'dismiss'])
        ->whereNumber('id')
        ->name('ads.admin.decisoes.dismiss');

    // Páginas read-only de transparência
    Route::get('/admin/policy',     [PolicyController::class,     'index'])->name('ads.admin.policy.index');
    Route::get('/admin/confidence', [ConfidenceController::class, 'index'])->name('ads.admin.confidence.index');
    Route::get('/admin/metricas',   [MetricasController::class,   'index'])->name('ads.admin.metricas.index');
    Route::get('/admin/patterns',   [PatternsController::class,   'index'])->name('ads.admin.patterns.index');
});
