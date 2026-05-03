<?php

use Illuminate\Support\Facades\Route;
use Modules\ADS\Http\Controllers\Api\DecisionController;
use Modules\ADS\Http\Controllers\Api\RecentEventsController;

// Health check público (não exige auth)
Route::get('ads/health', [DecisionController::class, 'health']);

// Endpoints autenticados — Brain A (CT 100) consome via HTTP poll
Route::middleware(['ads.api'])->group(function () {
    Route::post('ads/route',           [DecisionController::class,      'route']);
    Route::get('ads/recent-commits',   [RecentEventsController::class,  'commits']);
    Route::get('ads/recent-errors',    [RecentEventsController::class,  'errors']);
});
