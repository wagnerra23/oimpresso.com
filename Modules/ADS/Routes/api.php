<?php

use Illuminate\Support\Facades\Route;
use Modules\ADS\Http\Controllers\Api\DecisionController;
use Modules\ADS\Http\Controllers\Api\RecentEventsController;
use Modules\ADS\Http\Controllers\Api\ScopeController;
use Modules\ADS\Http\Controllers\Api\ContextController;

// Health check público (não exige auth)
Route::get('ads/health', [DecisionController::class, 'health']);

// Endpoints autenticados — Brain A (CT 100) consome via HTTP poll
Route::middleware(['ads.api'])->group(function () {
    Route::post('ads/route',           [DecisionController::class,      'route']);
    Route::get('ads/recent-commits',   [RecentEventsController::class,  'commits']);
    Route::get('ads/recent-errors',    [RecentEventsController::class,  'errors']);
    Route::get('ads/scope/check',      [ScopeController::class,         'check']);
    Route::get('ads/scope/user/{user_id}', [ScopeController::class,    'listUserModules'])
        ->whereNumber('user_id');
    Route::post('ads/context-for-task',    [ContextController::class,  'forTask']);
});
