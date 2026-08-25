<?php

use Illuminate\Support\Facades\Route;
use Modules\Arquivos\Http\Controllers\ArquivosAdminController;
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

// ── UI admin (US-ARQ-013 · Sprint 2) ─────────────────────────────────────────
// A tela nasce no PROPRIO modulo, em resources/js/Pages/Arquivos/ — decisao [W]
// 2026-07-29 registrada no SPEC (ADR 0360 deprecou o Admin Center, que era o destino
// anterior). Mesma stack de middleware do grupo Install + can() da permissao declarada.
//
// `arquivos.access` ja existia declarada em DataController::user_permissions (default
// false) e ate aqui NAO tinha nenhum consumidor no repo: esta rota e o primeiro.
//
// LEITURA PURA: so GET. Classificar/excluir entram na onda 2; retencao/purge dependem
// da proposta de ADR `arquivos-retencao-ui-aviso-titular`.
Route::middleware(['throttle:60,1', 'web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'])
    ->prefix('arquivos')
    ->group(function () {
        Route::get('/', [ArquivosAdminController::class, 'index'])
            ->middleware('can:arquivos.access')
            ->name('arquivos.index');
    });

// Wave 14 D8 Security — throttle:60,1 (60 req/min/IP) em rotas Arquivos.
// Arquivos é backbone DMS multi-tenant; throttle limita abuso (brute-force install,
// scraping de signed URLs expiradas, varredura sequencial de arquivo_id).
// Stack canonica UltimatePOS preservada apos throttle (web/auth/SetSessionData/etc).
Route::middleware(['throttle:60,1', 'web', 'authh', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu'])
    ->prefix('arquivos')
    ->group(function () {
        Route::get('install',           [InstallController::class, 'index']);
        Route::get('install/uninstall', [InstallController::class, 'uninstall']);
        Route::get('install/update',    [InstallController::class, 'update']);
    });

// Download via signed URL (Sprint 1 dia 4 — US-ARQ-008).
// Middleware `signed` valida expiração + assinatura HMAC (Laravel built-in).
// Auth obrigatório — multi-tenant Tier 0 aplica global scope no Arquivo::find.
// Wave 14 D8 — throttle:60,1 anti-brute-force em arquivo_id sequencial (signed URLs
// curtas têm TTL mas atacante pode varrer enquanto válidas).
Route::middleware(['throttle:60,1', 'web', 'auth', 'signed'])
    ->get('arquivos/download/{arquivo}', DownloadController::class)
    ->name('arquivos.download');
