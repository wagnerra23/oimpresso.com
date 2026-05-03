<?php

use Illuminate\Support\Facades\Route;
use Modules\ADS\Http\Controllers\Api\DecisionController;

// Health check público (não exige auth)
Route::get('ads/health', [DecisionController::class, 'health']);

// Endpoint principal: roteia evento → grava em mcp_dual_brain_decisions
Route::middleware(['ads.api'])->group(function () {
    Route::post('ads/route', [DecisionController::class, 'route']);
});
