<?php

use Illuminate\Support\Facades\Route;
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

// Placeholder rota download — implementar Sprint 1 dia 4 (US-ARQ-008 signed URL controller).
// Por ora só registra o nome pra Service::signedUrl() não quebrar:
Route::get('arquivos/download/{arquivo}', function ($arquivo) {
    abort(501, 'Sprint 1 dia 4 pendente — US-ARQ-008.');
})->name('arquivos.download');
