<?php

use Illuminate\Support\Facades\Route;
use Modules\ADS\Http\Controllers\Admin\DecisoesController;

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
});
