<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// log.desktop middleware captura IP, UA, endpoint, status, duration de
// cada request autenticada em /api/officeimpresso/*. Wrapped em try/catch
// — nunca afeta o processamento da request do Delphi.
Route::middleware(['auth:api', 'log.desktop'])->group(function () {
    // Ping + user — endpoint existente (nao mexer)
    Route::get('/officeimpresso', function (Request $request) {
        return $request->user();
    });

    // Audit opt-in (Delphi futuro) — aditivo, nunca obrigatorio
    Route::post('/officeimpresso/audit', [
        \Modules\Officeimpresso\Http\Controllers\AuditController::class, 'store'
    ])->name('api.officeimpresso.audit');
});
