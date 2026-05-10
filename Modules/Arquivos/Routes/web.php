<?php

use Illuminate\Support\Facades\Route;
use Modules\Arquivos\Http\Controllers\DownloadController;
use Modules\Arquivos\Http\Controllers\InstallController;

/*
|--------------------------------------------------------------------------
| Arquivos — rotas web
|--------------------------------------------------------------------------
|
| Sprint 1 — ADR 0123 (Modules/Arquivos DMS backbone).
|
| Arquivos é backbone consumido via trait HasArquivos. Não tem UI própria.
| UI admin entra em Sprint 2 (Pages/Arquivos no Modules/Admin).
|
| Rotas:
| - 3 Install obrigatórias (ADR 0024)
| - download signed-URL (Sprint 1 dia 4 — placeholder via name 'arquivos.download')
*/

Route::middleware(['web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'])
    ->prefix('arquivos')
    ->group(function () {
        Route::get('install',           [InstallController::class, 'index']);
        Route::get('install/uninstall', [InstallController::class, 'uninstall']);
        Route::get('install/update',    [InstallController::class, 'update']);
    });

// Download via signed URL (Sprint 1 dia 4 — US-ARQ-008).
// Middleware `signed` valida expiração + assinatura HMAC (Laravel built-in).
// Auth obrigatório — multi-tenant Tier 0 aplica global scope no Arquivo::find.
Route::middleware(['web', 'auth', 'signed'])
    ->get('arquivos/download/{arquivo}', DownloadController::class)
    ->name('arquivos.download');
