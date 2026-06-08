<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// Wave 10 D8 — throttle 60 req/min/user em todas as rotas Spreadsheet.
// sheet_data pode ser payload grande (JSON) — upload/store/update sao vetor DDoS classico
// se nao limitados. Share/move-to-folder tambem expostos a brute enumeration.
Route::middleware(['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'throttle:60,1'])->prefix('spreadsheet')->group(function () {
    Route::get('get-sheet/{id}/share', [\Modules\Spreadsheet\Http\Controllers\SpreadsheetController::class, 'getShareSpreadsheet']);
    Route::post('post-share-sheet', [\Modules\Spreadsheet\Http\Controllers\SpreadsheetController::class, 'postShareSpreadsheet']);
    Route::resource('sheets', \Modules\Spreadsheet\Http\Controllers\SpreadsheetController::class)->except(['edit']);
    Route::get('install', [\Modules\Spreadsheet\Http\Controllers\InstallController::class, 'index']);
    Route::post('install', [\Modules\Spreadsheet\Http\Controllers\InstallController::class, 'install']);
    Route::get('install/uninstall', [\Modules\Spreadsheet\Http\Controllers\InstallController::class, 'uninstall']);
    Route::get('install/update', [\Modules\Spreadsheet\Http\Controllers\InstallController::class, 'update']);
    Route::post('add-folder', [\Modules\Spreadsheet\Http\Controllers\SpreadsheetController::class, 'addFolder']);
    Route::post('move-to-folder', [\Modules\Spreadsheet\Http\Controllers\SpreadsheetController::class, 'moveToFolder']);
});
